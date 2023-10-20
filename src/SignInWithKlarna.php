<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		/**
		 * Check if we need to display the SIWK button:
		 * 1. if logged in or guest but has not signed in with klarna.
		 * 2. signed in, but need to renew the refresh token.
		 */
		$show_button = empty( get_user_meta( get_current_user_id(), User::$refresh_token_key, true ) );
		if ( ! $show_button ) {
			return;
		}

		$path        = dirname( __FILE__ ) . '/assets/siwk.js';
		$script_path = substr( $path, strpos( $path, '/wp-content' ) );

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
