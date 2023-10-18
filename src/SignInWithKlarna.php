<?php
namespace Krokedil\SignInWithKlarna;

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
		 * The UUID you received after the Sign in with Klarna onboarding.
		 *
		 * @var string
		 */
		private $client_id;
		/**
		 * The market or the country where this integration is available.
		 *
		 * @var string
		 */
		private $market;
		/**
		 * The environment to which the integration is pointing: playground or production.
		 *
		 * @var string 'playground' or 'production'.
		 */
		private $mode;

		/**
		 * The button's color theme.
		 *
		 * @var string
		 */
		private $button_theme;

		/**
		 * The button's shape.
		 *
		 * @var string
		 */
		private $button_shape;

		/**
		 * Change alignment of the Klarna logo on the call to action button based on the provided configuration.
		 *
		 * @var string
		 */
		private $logo_alignment;

		public $settings;
		public $assets;

		/**
		 * Class constructor.
		 *
		 * @param mixed $settings
		 */
		public function __construct( $settings ) {
			$this->settings = new Settings( $settings );
			$this->assets   = new Assets( $this->settings );

			Ajax::init();
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

	}
}

