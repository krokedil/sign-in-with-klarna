<?php
namespace Krokedil\SignInWithKlarna;

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
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Class constructor.
	 *
	 * @param array $settings The plugin settings to extract from.
	 */
	public function __construct( $settings ) {
		$this->settings = new Settings( $settings );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		Ajax::init();
	}


	/**
	 * Enqueue scripts.
	 *
	 * Determines whether the SIWK button should be rendered.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		/*
		 * Check if we need to display the SIWK button:
		 * 1. if logged in or guest but has not signed in with klarna.
		 * 2. signed in, but need to renew the refresh token.
		 */
		if ( ! empty( get_user_meta( get_current_user_id(), self::$refresh_token_key, true ) ) ) {
			return;
		}

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

		// Add data- attributes to the script tag.
		add_action( 'script_loader_tag', array( $this, 'siwk_script_tag' ), 10, 2 );
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
			$has_expired = 403;
			$code        = $response->get_error_code();

			// Delete all instances of SIWK refresh token in the user's metadata. This should ensure the SIWK button should appear again on the frontend.
			if ( $has_expired === $code ) {
				delete_user_meta( $user_id, self::$refresh_token_key );
			}

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
			if ( in_array( $gateway->id, array( 'kco', 'klarna_payments' ), true ) ) {

				// Set the highest ordered Klarna payment gateway.
				WC()->session->set( 'chosen_payment_method', $gateway->id );
				WC()->payment_gateways->set_current_gateway( $gateway->id );

				break;
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

		$locale      = esc_attr( apply_filters( 'siwk_locale', get_locale() ) );
		$client_id   = esc_attr( apply_filters( 'siwk_client_id', $this->settings->get( 'client_id' ) ) );
		$market      = esc_attr( apply_filters( 'siwk_market', $this->settings->get( 'market' ) ) );
		$environment = esc_attr( apply_filters( 'siwk_environment', 'playground' === $this->settings->get( 'environment' ) ? 'playground' : 'production' ) );
		$scope       = esc_attr( apply_filters( 'siwk_scope', 'offline_access profile phone email billing_address' ) );

		return str_replace( ' src', " data-locale='{$locale}' data-market='{$market}' data-environment='{$environment}' data-client-id='{$client_id}' data-scope='{$scope}' data-on-sign-in='onSignIn' data-on-error='onSignInError' src", $tag );
	}

	/**
	 * Output the "Sign in with Klarna" button HTML.
	 *
	 * @return void
	 */
	public function siwk_placement() {
		$theme     = esc_attr( apply_filters( 'siwk_button_theme', $this->settings->get( 'button_theme' ) ) ); // default, dark, light.
		$shape     = esc_attr( apply_filters( 'siwk_button_shape', $this->settings->get( 'button_shape' ) ) ); // default, rectangle, pill.
		$alignment = esc_attr( apply_filters( 'siwk_logo_alignment', $this->settings->get( 'logo_alignment' ) ) ); // left, right, center.

		// phpcs:ignore -- must be echoed as html; attributes escaped.
		echo "<klarna-sign-in data-theme='{$theme}' data-shape='{$shape}' data-logo-alignment='{$alignment}'></klarna-sign-in>";
	}
}
