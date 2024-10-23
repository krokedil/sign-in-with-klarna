<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SIWK_VERSION' ) ) {
	define( 'SIWK_VERSION', '1.0.0' );
}

/**
 * Sign_In_With_Klarna class.
 */
class SignInWithKlarna {
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
	public static $placement_hook = 'siwk_output_button';

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
		$this->jwt      = new JWT( wc_string_to_bool( $this->settings->get( 'test_mode' ) ), $this->settings );
		$this->user     = new User( $this->jwt );
		$this->ajax     = new AJAX( $this->jwt, $this->user );

		// Initialize the callback endpoint for handling the redirect flow.
		new Redirect( $this->settings );

		if ( $this->should_display() ) {
			// Frontend hooks.
			add_action( 'woocommerce_proceed_to_checkout', array( $this, self::$placement_hook ), intval( $this->settings->get( 'cart_placement' ) ) );
			add_action( 'woocommerce_login_form_start', array( $this, self::$placement_hook ) );
			add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'output_button' ), 5 );

			// Enqueue library and SDK.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		// Enqueue settings page styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * Determines whether the SIWK button should be rendered.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// 'siwk_script' MUST BE registered before Klarna's lib.js
		$script_path = plugin_dir_url( __FILE__ ) . 'assets/js/siwk.js';
		wp_register_script( 'siwk_script', $script_path, array( 'jquery' ), SIWK_VERSION, false );
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
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( empty( $section ) || 'klarna_payments' !== $section ) {
			return;
		}

		$path = plugin_dir_url( __FILE__ ) . 'assets/css/admin-siwk.css';
		wp_enqueue_style( 'siwk_admin_css', $path, array(), SIWK_VERSION );

		$script_path = plugin_dir_url( __FILE__ ) . 'assets/js/admin-siwk.js';
		wp_enqueue_script( 'siwk_admin_script', $script_path, array( 'jquery' ), SIWK_VERSION, false );
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

		$attributes = $this->get_attributes();
		return str_replace( ' src', " defer {$attributes}' src", $tag );
	}

	/**
	 * Output the "Sign in with Klarna" button HTML.
	 *
	 * @param string $style The CSS style to apply to the button.
	 * @return void
	 */
	public function output_button( $style = '' ) {
		// Only run this function ONCE PER ACTION to prevent duplicate buttons. First time it is run, did_action will return 0. A non-zero value means it has already been run.
		if ( did_action( self::$placement_hook ) ) {
			return;
		}

		$style      = 'width: 100%; max-width: 100%;' . ( ! empty( $style ) ? " {$style}" : '' );
		$attributes = $this->get_attributes() . " style='" . esc_attr( $style ) . "'";

		$attributes = apply_filters( 'siwk_button_attributes', $attributes );

		// phpcs:ignore -- must be echoed as html; attributes already escaped.
		echo "<klarna-identity-button id='klarna-identity-button' class='siwk-button' $attributes></klarna-identity-button>";
	}

	/**
	 * Get the attributes for the Sign in with Klarna button.
	 *
	 * @return string
	 */
	private function get_attributes() {
		$locale      = esc_attr( $this->settings->get( 'locale' ) );
		$scope       = esc_attr( $this->settings->get( 'scope' ) );
		$market      = esc_attr( apply_filters( 'siwk_market', $this->settings->get( 'market' ) ) );
		$environment = esc_attr( apply_filters( 'siwk_environment', wc_string_to_bool( $this->settings->get( 'test_mode' ) ) ? 'playground' : 'production' ) );
		$client_id   = esc_attr( apply_filters( 'siwk_client_id', $this->settings->get( 'client_id' ) ) );
		$redirect_to = esc_attr( Redirect::get_callback_url() );

		return "data-locale='{$locale}' data-scope='{$scope}' data-market='{$market}' data-environment='{$environment}' data-client-id='{$client_id}' data-redirect-uri='{$redirect_to}'";
	}

	/**
	 * Determine whether the button should be displayed.
	 *
	 * @return bool
	 */
	private function should_display() {
		/**
		 * Check if we need to display the SIWK button:
		 * 1. if logged in or guest but has not signed in with klarna.
		 * 2. signed in, but need to renew the refresh token.
		 */
		return empty( get_user_meta( get_current_user_id(), User::REFRESH_TOKEN_KEY, true ) );
	}
}
