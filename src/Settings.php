<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages settings for SIWK, per plugin.
 */
class Settings {

	/**
	 * The UUID you received after the Sign in with Klarna onboarding.
	 *
	 * @var string
	 */
	public $client_id;

	/**
	 * The market or the country where this integration is available.
	 *
	 * @var string
	 */
	public $market;
	/**
	 * The environment to which the integration is pointing: playground or production.
	 *
	 * @var string 'yes' or 'no'.
	 */

	public $test_mode;

	/**
	 * The button's color theme.
	 *
	 * @var string
	 */
	public $button_theme;

	/**
	 * The button's shape.
	 *
	 * @var string
	 */
	public $button_shape;

	/**
	 * Change alignment of the Klarna logo on the call to action button based on the provided configuration.
	 *
	 * @var string
	 */

	/**
	 * Change alignment of the Klarna badge on the call to action button based on the provided configuration.
	 *
	 * @var string
	 */
	public $logo_alignment;

	/**
	 * Change the position of the button on the cart page.
	 *
	 * @var int
	 */
	public $cart_placement;

	/**
	 * The regional endpoint (EU v. NA).
	 *
	 * @var string
	 */
	public $region;

	/**
	 * Internal settings.
	 *
	 * @var array
	 */
	private $internal;

	/**
	 * Class constructor
	 *
	 * @param array $settings The settings to extract from.
	 */
	public function __construct( $settings ) {
		$settings = wp_parse_args(
			$settings,
			$this->default(),
		);

		$this->update( $settings );

		// These are settings that are not accessible through the settings page.
		$this->internal = array(
			'locale' => apply_filters( 'siwk_locale', str_replace( '_', '-', get_locale() ) ),

			// These three scopes are required for full functionality and shouldn't be modified by the merchant.
			'scope'  => 'openid offline_access payment:request:create ' . apply_filters( 'siwk_scope', 'profile:name profile:email profile:phone profile:billing_address' ),
		);

		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'extend_settings' ) );
	}

	/**
	 * Retrieve the value of a SIWK setting.
	 *
	 * @param string $setting The name of the setting.
	 * @return string|int
	 */
	public function get( $setting ) {
		$setting = str_replace( 'siwk_', '', $setting );
		return apply_filters( "siwk_{$setting}", $this->$setting );
	}

	/**
	 * Intended for retrieving internal settings.
	 *
	 * @param string $name The name of the setting.
	 * @return mixed|null The setting value or NULL if not found.
	 */
	public function __get( $name ) {
		return $this->internal[ $name ] ?? null;
	}

	/**
	 * Update all the internal settings.
	 *
	 * @param array $settings The settings to extract from, or pass an array to use the option (if it was set).
	 * @return void
	 */
	public function update( $settings ) {
		if ( ! empty( $settings ) ) {
			$this->store( $settings );
		}
	}


	/**
	 * Extend your plugin with the required SIWK settings.
	 *
	 * @param array $settings Your plugin settings as an array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		return array_merge(
			$settings,
			array(
				'siwk_title'          => array(
					'title'       => __( 'Sign in with Klarna', 'siwk' ),
					'description' => __( 'An improved way to drive shoppers straight to the checkout, with all their preferences already set.', 'siwk' ),
					'links'       => array(
						array(
							'url'   => 'https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/before-you-start/',
							'title' => __( 'Documentation', 'klarna-onsite-messaging-for-woocommerce' ),
						),
					),
					'type'        => 'kp_section_start',
				),
				'siwk_enabled'        => array(
					'name'    => 'siwk_enabled',
					'title'   => __( 'Enable/Disable', 'siwk' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sign in with Klarna', 'siwk' ),
					'default' => $this->default()['siwk_enabled'],
				),
				'siwk_client_id'      => array(
					'name'        => 'siwk_client_id',
					'title'       => __( 'Client ID', 'siwk' ),
					'description' => __( 'The client ID you received after the Sign in with Klarna onboarding.', 'siwk' ),
					'type'        => 'text',
					'default'     => $this->default()['siwk_client_id'],
					'desc_tip'    => true,
				),
				'siwk_test_mode'      => array(
					'name'        => 'siwk_test_mode',
					'title'       => __( 'Test mode', 'siwk' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable test mode', 'siwk' ),
					'description' => __( 'In test mode, customer data for a test user will be used. If the user does not already exist, a new user will be created using the test data.', 'siwk' ),
					'default'     => $this->default()['siwk_test_mode'],
				),

				'siwk_market'         => array(
					'name'        => 'siwk_market',
					'title'       => __( 'Market', 'siwk' ),
					'type'        => 'text',
					'description' => __( 'The market or the country where this integration is available.', 'siwk' ),
					'default'     => $this->default()['siwk_market'],
				),
				'siwk_region'         => array(
					'name'        => 'siwk_region',
					'title'       => __( 'Region', 'siwk' ),
					'type'        => 'select',
					'description' => __( 'The regional endpoint.', 'siwk' ),
					'default'     => $this->default()['siwk_region'],
					'options'     => array(
						'eu' => __( 'EU', 'siwk' ),
						'na' => __( 'NA', 'siwk' ),
					),
				),
				'siwk_button_theme'   => array(
					'name'        => 'siwk_button_theme',
					'title'       => __( 'Button theme' ),
					'type'        => 'select',
					'description' => __( 'The button\'s color theme.', 'siwk' ),
					'default'     => $this->default()['siwk_button_theme'],
					'options'     => array(
						'default'  => __( 'Dark', 'siwk' ),
						'light'    => __( 'Light', 'siwk' ),
						'outlined' => __( 'Outlined', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_button_shape'   => array(
					'name'        => 'siwk_button_shape',
					'title'       => __( 'Button shape' ),
					'type'        => 'select',
					'description' => __( 'The button\'s shape.', 'siwk' ),
					'default'     => $this->default()['siwk_button_shape'],
					'options'     => array(
						'rounded'   => __( 'Rounded', 'siwk' ),
						'rectangle' => __( 'Rectangular', 'siwk' ),
						'pill'      => __( 'Pill', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_logo_alignment' => array(
					'name'        => 'siwk_logo_alignment',
					'title'       => __( 'Badge alignment' ),
					'type'        => 'select',
					'description' => __( 'Change alignment of the Klarna logo on the call to action button.', 'siwk' ),
					'default'     => $this->default()['siwk_logo_alignment'],
					'options'     => array(
						'default' => __( 'Badge', 'siwk' ),
						'left'    => __( 'Left', 'siwk' ),
						'center'  => __( 'Centered', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_cart_placement' => array(
					'name'        => 'siwk_cart_placement',
					'title'       => __( 'Cart page placement', 'siwk' ),
					'type'        => 'select',
					'description' => __( 'Change the placement of the "Sign in with Klarna" button on the cart page.', 'siwk' ),
					'default'     => $this->default()['siwk_cart_placement'],
					'options'     => array(
						'10'  => __( 'Before "Proceed to checkout" button', 'siwk' ),
						'100' => __( 'After "Proceed to checkout" button', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_callback_url'   => array(
					'name'        => 'siwk_callback_url',
					'title'       => __( 'Redirect URL', 'siwk' ),
					'type'        => 'text',
					'description' => __( 'Please add this URL to your list of allowed redirect URLs in the "Sign in with Klarna" settings on the <a href="https://portal.klarna.com/">Klarna merchant portal</a>.', 'siwk' ),
					'default'     => Redirect::get_callback_url(),
					'disabled'    => true,
					'css'         => 'width: ' . strlen( Redirect::get_callback_url() ) . 'ch; color: #2c3338',
				),
				'siwk_scope'          => array(
					'name'        => 'siwk_scopes',
					'title'       => __( 'Scopes', 'siwk' ),
					'type'        => 'textarea',
					'description' => __( 'These scopes are included by default, as necessary for creating a WooCommerce customer account in your shop.  More about available scopes with Sign in with Klarna <a href="https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/integrate-sign-in-with-klarna/web-sdk-integration/#scopes-and-claims">here</a>. Additional scopes can be customized if applicable, more info <a href="https://gist.github.com/mntzrr/4bf23ca394109d40575f2abc05811ddc">here</a>.', 'siwk' ),
					'default'     => $this->scope,
					'disabled'    => true,
					'css'         => 'background: #fff !important; color: #2c3338; resize: none;',
				),
			)
		);
	}

	/**
	 * Update the internal settings state.
	 *
	 * @param array $settings The settings to extract from.
	 * @return void
	 */
	private function store( $settings ) {
		$this->client_id      = $settings['siwk_client_id'];
		$this->market         = $settings['siwk_market'];
		$this->region         = $settings['siwk_region'];
		$this->test_mode      = $settings['siwk_test_mode'];
		$this->button_theme   = $settings['siwk_button_theme'];
		$this->button_shape   = $settings['siwk_button_shape'];
		$this->logo_alignment = $settings['siwk_logo_alignment'];
		$this->cart_placement = $settings['siwk_cart_placement'];
	}

	/**
	 * Retrieve the default settings values.
	 *
	 * @return array
	 */
	private function default() {
		return array(
			'siwk_client_id'      => '',
			'siwk_enabled'        => 'no',
			'siwk_market'         => wc_get_base_location()['country'] ?? '',
			'siwk_region'         => 'eu',
			'siwk_test_mode'      => 'no',
			'siwk_title_theme'    => __( 'Theme, button shape & placements', 'siwk' ),
			'siwk_button_theme'   => 'default',
			'siwk_button_shape'   => 'rounded',
			'siwk_logo_alignment' => 'default',
			'siwk_cart_placement' => 10,
		);
	}
}
