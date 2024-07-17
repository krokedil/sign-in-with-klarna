<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIWK_VERSION', '0.0.2' );

/**
 * Sign_In_With_Klarna class.
 */
class SignInWithKlarna {

	public const REST_API_NAMESPACE     = 'siwk/v1';
	public const REST_API_CALLBACK_PATH = '/callback';

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
	public static $placement_hook = 'siwk_placement';

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * The interface used for reading from a JWT token.
	 *
	 * @var JWT
	 */
	public $jwt;

	/**
	 * Handles AJAX requests.
	 *
	 * @var AJAX
	 */
	public $ajax;

	/**
	 * Handles metadata associated with a WordPress user.
	 *
	 * @var User
	 */
	public $user;

	/**
	 * Class constructor.
	 *
	 * @param array $settings The plugin settings to extract from.
	 */
	public function __construct( $settings ) {
		$this->settings = new Settings( $settings );
		$this->jwt      = new JWT( wc_string_to_bool( $this->settings->test_mode ), $this->settings );
		$this->user     = new User( $this->jwt );
		$this->ajax     = new AJAX( $this->jwt, $this->user );

		add_action( 'woocommerce_proceed_to_checkout', array( $this, self::$placement_hook ), intval( $this->settings->cart_placement ) );
		add_action( 'woocommerce_login_form_end', array( $this, self::$placement_hook ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'rest_api_init', array( $this, 'register_callback_endpoint' ) );
	}

	/**
	 * Register endpoint for the sign-in callback.
	 *
	 * @return void
	 */
	public function register_callback_endpoint() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_CALLBACK_PATH,
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'handle_redirect_callback' ),
			)
		);
	}

	/**
	 * Callback for the sign-in endpoint.
	 *
	 * @return void
	 */
	public function handle_redirect_callback() {
		$response = wp_remote_get( plugin_dir_url( __FILE__ ) . 'templates/callback.html' );
		$body     = wp_remote_retrieve_body( $response );

		$account_page = get_permalink( wc_get_page_id( 'shop' ) );
		if ( empty( $body ) ) {
			wp_safe_redirect( $account_page );
		} else {
			header( 'Content-Type: text/html' );
			$client_id = $this->settings->get( 'client_id' );
			$locale    = $this->settings->locale;

			// The Klarna SDK will not run any event if the redirect_uri differs from the pre-registered URL. Therefore, we cannot redirect the user to the callback.html page. Instead, we must echo the contents of the file. And since we cannot add any query parameters, we must use template strings to add the client ID and the locale.
			$body = str_replace( '%client_id%', $client_id, $body );
			$body = str_replace( '%locale%', $locale, $body );

			// Show a link back to the account page in case the sign in fails.
			$body = str_replace( '%store_url%', $account_page, $body );

			// The AJAX URL.
			$body = str_replace( '%sign_in_url%', \WC_AJAX::get_endpoint( 'siwk_sign_in_from_redirect' ), $body );

			// phpcs:ignore -- body does not contain user input.
			echo $body;
		}
		exit;
	}


	/**
	 * Enqueue scripts.
	 *
	 * Determines whether the SIWK button should be rendered.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		/**
		 * Check if we need to display the SIWK button:
		 * 1. if logged in or guest but has not signed in with klarna.
		 * 2. signed in, but need to renew the refresh token.
		 */
		$show_button = empty( get_user_meta( get_current_user_id(), User::REFRESH_TOKEN_KEY, true ) );
		if ( ! $show_button ) {
			return;
		}

		// 'siwk_script' MUST BE registered before Klarna's lib.js
		$script_path = plugin_dir_url( __FILE__ ) . 'assets/siwk.js';
		wp_register_script( 'siwk_script', $script_path, array(), SIWK_VERSION, false );
		$siwk_params = array(
			'sign_in_from_popup_url'   => \WC_AJAX::get_endpoint( 'siwk_sign_in_from_popup' ),
			'sign_in_from_popup_nonce' => wp_create_nonce( 'siwk_sign_in_from_popup' ),

		);
		wp_localize_script( 'siwk_script', 'siwk_params', $siwk_params );
		wp_enqueue_script( 'siwk_script' );
		wp_enqueue_script( self::$library_handle, 'https://js.klarna.com/web-sdk/v1/klarna.js', array( 'siwk_script' ), SIWK_VERSION, true );

		// Add data- attributes to the script tag.
		add_action( 'script_loader_tag', array( $this, 'siwk_script_tag' ), 10, 2 );
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

		$locale      = esc_attr( $this->settings->locale );
		$client_id   = esc_attr( apply_filters( 'siwk_client_id', $this->settings->get( 'client_id' ) ) );
		$market      = esc_attr( apply_filters( 'siwk_market', $this->settings->get( 'market' ) ) );
		$environment = esc_attr( apply_filters( 'siwk_environment', wc_string_to_bool( $this->settings->get( 'test_mode' ) ) ? 'playground' : 'production' ) );

		return str_replace( ' src', " defer data-locale='{$locale}' data-market='{$market}' data-environment='{$environment}' data-client-id='{$client_id}' src", $tag );
	}

	/**
	 * Output the "Sign in with Klarna" button HTML.
	 *
	 * @return void
	 */
	public function siwk_placement() {
		// Only run this function ONCE PER ACTION to prevent duplicate buttons. First time it is run, did_action will return 0. A non-zero value means it has already been run.
		if ( did_action( self::$placement_hook ) ) {
			return;
		}

		$theme     = esc_attr( apply_filters( 'siwk_button_theme', $this->settings->get( 'button_theme' ) ) ); // default (dark), light, outlined.
		$shape     = esc_attr( apply_filters( 'siwk_button_shape', $this->settings->get( 'button_shape' ) ) ); // default (rounded), rectangle, pill.
		$alignment = esc_attr( apply_filters( 'siwk_logo_alignment', $this->settings->get( 'badge_alignment' ) ) ); // badge, right, center.

		// Woo requires pretty permalinks, therefore, we can don't have to fallback to the rest_route parameter.
		$endpoint     = self::REST_API_NAMESPACE . self::REST_API_CALLBACK_PATH;
		$callback_url = home_url( "wp-json/{$endpoint}" );

		$redirect_to = esc_attr( apply_filters( 'siwk_redirect_uri', $callback_url ) );
		$scope       = esc_attr( apply_filters( 'siwk_scope', 'openid offline_access payment:request:create profile:name profile:email profile:phone profile:billing_address' ) );

		$attributes = "id='klarna-identity-button' data-scope='{$scope}' data-theme='{$theme}' data-shape='{$shape}' data-logo-alignment='{$alignment}' data-redirect-uri='{$redirect_to}'";

		// phpcs:ignore -- must be echoed as html; attributes already escaped.
		echo "<klarna-identity-button $attributes></klarna-identity-button>";
	}
}
