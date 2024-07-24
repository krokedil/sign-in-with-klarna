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
	public static $placement_hook = 'output_button';

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

		// Initialize the callback endpoint for handling the redirect flow.
		new Redirect( $this->settings );

		// Frontend hooks.
		add_action( 'woocommerce_proceed_to_checkout', array( $this, self::$placement_hook ), intval( $this->settings->cart_placement ) );
		add_action( 'woocommerce_login_form_start', array( $this, self::$placement_hook ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'width_constrained_button' ), 5 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Outputs the button with a width and max-width 100% constrain.
	 *
	 * Used in the mini-cart.
	 *
	 * @return void
	 */
	public function width_constrained_button() {
		$this->output_button( 'width: 100%; max-width: 100%;' );
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
	 * @param string $style The CSS style to apply to the button.
	 * @return void
	 */
	public function output_button( $style = '' ) {
		// Only run this function ONCE PER ACTION to prevent duplicate buttons. First time it is run, did_action will return 0. A non-zero value means it has already been run.
		if ( did_action( self::$placement_hook ) ) {
			return;
		}

		$theme     = esc_attr( $this->settings->get( 'button_theme' ) ); // default (dark), light, outlined.
		$shape     = esc_attr( $this->settings->get( 'button_shape' ) ); // default (rounded), rectangle, pill.
		$alignment = esc_attr( $this->settings->get( 'logo_alignment' ) ); // badge, right, center.

		$redirect_to = esc_attr( Redirect::get_callback_url() );
		$scope       = esc_attr( $this->settings->scope );
		$attributes  = "id='klarna-identity-button' data-scope='{$scope}' data-theme='{$theme}' data-shape='{$shape}' data-logo-alignment='{$alignment}' data-redirect-uri='{$redirect_to}'";

		if ( ! empty( $style ) ) {
			$attributes .= " style='" . esc_attr( $style ) . "'";
		}

		$attributes = apply_filters( 'siwk_button_attributes', $attributes );

		// phpcs:ignore -- must be echoed as html; attributes already escaped.
		echo "<klarna-identity-button $attributes></klarna-identity-button>";
	}
}
