<?php
namespace Krokedil\SignInWithKlarna;

define( 'SIWK_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'SignInWithKlarna' ) ) {
	/**
	 * Sign_In_With_Klarna class.
	 */
	class SignInWithKlarna {

		/**
		 * The meta key for refresh token.
		 *
		 * @var string
		 */
		public static $refresh_token_key = 'siwk_refresh_token';
		/**
		 * The meta key for access token.
		 *
		 * @var string
		 */
		public static $access_token_key = '_siwk_access_token';

		/**
		 * The handle name for the JavaScript library.
		 *
		 * @var string
		 */
		public static $library_handle = 'siwk_library';

		/**
		 * The action hook name for outputting the placement HTML.
		 *
		 * @var string
		 */
		public static $siwk_placement_hook = 'siwk_placement';

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * *Singleton* clone.
		 *
		 * @return void
		 */
		public function __clone() {         }

		/**
		 * *Singleton* wakeup.
		 *
		 * @return void
		 */
		public function __wakeup() {        }

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 */
		public function init() {
			if ( is_user_logged_in() ) {
				return;
			}
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'script_loader_tag', array( $this, 'siwk_script_tag' ), 10, 2 );

			include_once '/var/www/html/wp-content/plugins/sign-in-with-klarna/src/class-siwk-ajax.php';
		}

		/**
		 * Enqueue scripts.
		 *
		 * @return void
		 */
		public function enqueue_scripts() {
			$script_path = get_home_url( null, '/wp-content/plugins/klarna-payments-for-woocommerce/vendor/krokedil/sign-in-with-klarna/src/assets/siwk.js' );

			// 'siwk_script' MUST BE registered before Klarna's lib.js
			wp_register_script( 'siwk_script', $script_path, array(), '0.0.1', false );
			$siwk_params = array(
				'sign_in_url'   => \WC_AJAX::get_endpoint( 'siwk_sign_in' ),
				'sign_in_nonce' => wp_create_nonce( 'siwk_sign_in' ),

			);
			wp_localize_script( 'siwk_script', 'siwk_params', $siwk_params );
			wp_enqueue_script( 'siwk_script' );

			wp_register_script( self::$library_handle, 'https://x.klarnacdn.net/sign-in-with-klarna/v1/lib.js', array( 'siwk_script' ), '0.0.1', true );
			wp_enqueue_script( self::$library_handle );
		}

		/**
		 * Update the refresh token, and generate a new access token.
		 *
		 * Assumes refresh token is saved to user's metadata.
		 *
		 * @return bool TRUE if the access token was set, otherwise FALSE.
		 */
		public static function update_refresh_token() {
			$user_id       = get_current_user_id();
			$refresh_token = get_user_meta( $user_id, self::$refresh_token_key, true );
			if ( empty( $refresh_token ) ) {
				return false;
			}

			$payload  = array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'refresh_token' => $refresh_token['refresh_token'],
					'client_id'     => $refresh_token['client_id'],
					'grant_type'    => 'refresh_token',
				),
			);
			$region   = 'eu';
			$iss      = $refresh_token['iss'];
			$response = wp_remote_post( "{$iss}/{$region}/lp/idp/oauth2/token", $payload );
			if ( is_wp_error( $response ) ) {
				return false;
			} else {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				$refresh_token = self::get_refresh_token( $body['access_token'], $body['id_token'], $body['refresh_token'] );
				update_user_meta( $user_id, self::$refresh_token_key, $refresh_token );
				return self::set_access_token( $user_id, $body['access_token'], intval( $body['expires_in'] ) ?? 299 );
			}
		}

		/**
		 * Get the access token or generate a new one if it has already expired.
		 *
		 * @param int $user_id The user ID.
		 * @return string|false The access token or FALSE.
		 */
		public static function get_access_token( $user_id ) {
			// Guest user.
			if ( 0 === $user_id ) {
				return false;
			}

			$access_token_key = $user_id . self::$access_token_key;

			// Check for existing transient.
			$access_token = get_transient( $access_token_key );
			if ( ! empty( $access_token ) ) {
				return $access_token;
			}
			// Check if the user has refresh token.
			$refresh_token = get_user_meta( $user_id, self::$refresh_token_key, true );
			if ( empty( $refresh_token ) ) {
				return false;
			}

			// Update refresh token, and fetch new access token.
			self::update_refresh_token();
			return get_transient( $access_token_key );
		}

		/**
		 * Get the refresh token metadata required for issuing new access token.
		 *
		 * @param string $jwt_access_token JWT access token.
		 * @param string $jwt_id_token JWT id token.
		 * @param string $refresh_token Opaque refresh token.
		 * @return array
		 */
		public static function get_refresh_token( $jwt_access_token, $jwt_id_token, $refresh_token ) {
			$id_token     = self::get_jwt_payload( $jwt_id_token );
			$access_token = self::get_jwt_payload( $jwt_access_token );

			return array(
				'client_id'     => $access_token['client_id'],
				'jti'           => $id_token['jti'],
				'auth_time'     => intval( $id_token['auth_time'] ),
				'iss'           => $id_token['iss'],
				'refresh_token' => $refresh_token,
			);
		}


		/**
		 * Extract the JWT payload.
		 *
		 * @param string $jwt_token The JWT token.
		 * @return array The payload.
		 */
		public static function get_jwt_payload( $jwt_token ) {
			$segments = explode( '.', $jwt_token );
			return json_decode( base64_decode( $segments[1] ), true );
		}

		/**
		 * Store the access token as a transient associated with user ID.
		 *
		 * @param int    $user_id The user to associate the access token with.
		 * @param string $jwt_access_token JWT access token.
		 * @param int    $expires_in How long the transient is valid (seconds).
		 * @return bool TRUE if the access token was set, otherwise FALSE.
		 */
		public static function set_access_token( $user_id, $jwt_access_token, $expires_in ) {
			return set_transient( $user_id . self::$access_token_key, $jwt_access_token, $expires_in );
		}

		/**
		 * Simulate logging in as user ID.
		 *
		 * Also sets the selected payment method to Klarna if possible.
		 *
		 * @param int $user_id The user to login as.
		 * @return void
		 */
		public static function set_current_user( $user_id ) {
			wc_set_customer_auth_cookie( $user_id );
			wp_set_current_user( $user_id );

			// Set Klarna as the selected payment method.
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $gateway ) {
				if ( in_array( $gateway->id, array( 'kco', 'klarna_payments' ) ) ) {
					WC()->session->set( 'chosen_payment_method', $gateway->id );
					WC()->payment_gateways->set_current_gateway( $gateways->id );
				}
			}
		}

		/**
		 * Add extra attributes to the Klarna script tag.
		 *
		 * @param string $tag The <script> tag attributes for the enqueued script.
		 * @param string $handle The script's registered handle.
		 * @return string
		 */
		public function siwk_script_tag( $tag, $handle ) {
			if ( self::$library_handle !== $handle ) {
				return $tag;
			}

			$locale      = apply_filters( 'siwk_locale', get_locale() );
			$market      = 'SE';
			$environment = 'playground';
			$client_id   = 'd0c2a58e-7d61-4ffb-a444-3411fb344d40';
			$scope       = 'offline_access profile phone email billing_address';

			return str_replace( ' src', " data-locale='{$locale}' data-market='{$market}' data-environment='{$environment}' data-client-id='{$client_id}' data-scope='{$scope}' data-on-sign-in='onSignIn' data-on-error='onSignInError' src", $tag );
		}

		/**
		 * Output the "Sign in with Klarna" button HTML.
		 *
		 * @return void
		 */
		public function siwk_placement() {
			$theme     = 'default'; // default, dark, light.
			$shape     = 'default'; // default, rectangle, pill.
			$alignment = 'left';

			// phpcs:ignore -- must be output as HTML.
			echo "<klarna-sign-in data-theme='{$theme}' data-shape='{$shape}' data-logo-alignment='{$alignment}'></klarna-sign-in>";
		}
	}
}

