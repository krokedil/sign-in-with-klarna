<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIWK_VERSION', '0.0.2' );


/**
 * Handles the callback from the redirect flow.
 */
class Redirect {

	public const REST_API_NAMESPACE     = 'siwk/v1';
	public const REST_API_CALLBACK_PATH = '/callback';

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Class constructor.
	 *
	 * @param Settings $settings The plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
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

		$redirect_url = apply_filters( 'siwk_redirect_url', get_permalink( wc_get_page_id( 'shop' ) ) );
		if ( empty( $body ) ) {
			wp_safe_redirect( $redirect_url );
		} else {
			header( 'Content-Type: text/html' );
			$client_id = $this->settings->get( 'client_id' );
			$locale    = $this->settings->locale;

			// The Klarna SDK will not run any event if the redirect_uri differs from the pre-registered URL. Therefore, we cannot redirect the user to the callback.html page. Instead, we must echo the contents of the file. And since we cannot add any query parameters, we must use template strings to add the client ID and the locale.
			$body = str_replace( '%client_id%', $client_id, $body );
			$body = str_replace( '%locale%', $locale, $body );

			// Show a link back to the account page in case the sign in fails.
			$body = str_replace( '%store_url%', $redirect_url, $body );

			// The AJAX URL.
			$body = str_replace( '%sign_in_url%', \WC_AJAX::get_endpoint( 'siwk_sign_in_from_redirect' ), $body );

			// phpcs:ignore -- body does not contain user input.
			echo $body;
		}
		exit;
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @return string
	 */
	public static function get_callback_url() {
		// Since Woo requires pretty permalinks, we can assume it is always set, therefore, don't have to fallback to the "rest_route" parameter.
		$endpoint = self::REST_API_NAMESPACE . self::REST_API_CALLBACK_PATH;
		return home_url( "wp-json/{$endpoint}" );
	}
}
