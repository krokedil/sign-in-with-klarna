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
	 * The locale.
	 *
	 * @var string
	 */
	public $locale;


	/**
	 * OAuth scopes.
	 *
	 * @var array
	 */
	private $scope;

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

		if ( isset( $settings['siwk_callback_url'] ) && empty( $settings['siwk_callback_url'] ) ) {
			$settings['siwk_callback_url'] = Redirect::get_callback_url();
			update_option( 'woocommerce_klarna_payments_settings', $settings );
		}

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
		if ( 'scope' === $setting ) {
			// These scopes are required for full functionality and shouldn't be modified by the merchant, and must be excluded from the filter.
			$required = 'openid offline_access customer:login profile:name profile:email profile:phone profile:billing_address';
			return $required . apply_filters( "siwk_{$setting}", implode( ' ', array_keys( array_filter( $this->scope ) ) ) );
		}

		return apply_filters( "siwk_{$setting}", $this->{$setting} );
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
				'siwk'                                 => array(
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
				'siwk_enabled'                         => array(
					'name'    => 'siwk_enabled',
					'title'   => __( 'Enable/Disable', 'siwk' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sign in with Klarna', 'siwk' ),
					'default' => $this->default()['siwk_enabled'],
				),
				'siwk_callback_url'                    => array(
					'name'              => 'siwk_callback_url',
					'title'             => __( 'Redirect URL', 'siwk' ),
					'type'              => 'text',
					'description'       => __( 'Add this URL to your list of allowed redirect URLs in the "Sign in with Klarna" settings on the <a href="https://portal.klarna.com/">Klarna merchant portal</a>.', 'siwk' ),
					'default'           => Redirect::get_callback_url(),
					'css'               => 'min-width: 100%',
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
				),
				'siwk_button_theme'                    => array(
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
				'siwk_button_shape'                    => array(
					'name'        => 'siwk_button_shape',
					'title'       => __( 'Button shape' ),
					'type'        => 'select',
					'description' => __( 'The button\'s shape.', 'siwk' ),
					'default'     => $this->default()['siwk_button_shape'],
					'options'     => array(
						'default'     => __( 'Rounded', 'siwk' ),
						'rectangular' => __( 'Rectangular', 'siwk' ),
						'pill'        => __( 'Pill', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_logo_alignment'                  => array(
					'name'        => 'siwk_logo_alignment',
					'title'       => __( 'Badge alignment' ),
					'type'        => 'select',
					'description' => __( 'Change alignment of the Klarna logo on the call to action button.', 'siwk' ),
					'default'     => $this->default()['siwk_logo_alignment'],
					'options'     => array(
						'default'  => __( 'Badge', 'siwk' ),
						'left'     => __( 'Left', 'siwk' ),
						'centered' => __( 'Centered', 'siwk' ),
					),
					'desc_tip'    => true,
				),
				'siwk_cart_placement'                  => array(
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
				'siwk_required_scopes_email'           => array(
					'name'              => 'siwk_required_scopes',
					'title'             => __( 'Required Customer Data', 'siwk' ),
					'type'              => 'checkbox',
					'label'             => __( 'Email Address', 'siwk' ),
					'default'           => 'yes',
					'disabled'          => true,
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_name'            => array(
					'name'              => 'siwk_required_scopes_name',
					'type'              => 'checkbox',
					'label'             => __( 'Full name', 'siwk' ),
					'default'           => 'yes',
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_phone'           => array(
					'name'              => 'siwk_required_scopes_phone',
					'type'              => 'checkbox',
					'label'             => __( 'Phone number', 'siwk' ),
					'default'           => 'yes',
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_billing_address' => array(
					'name'              => 'siwk_required_scopes_billing_address',
					'type'              => 'checkbox',
					'label'             => __( 'Billing address', 'siwk' ),
					'description'       => __( 'These scopes are included by default, as necessary for creating a WooCommerce customer account in your shop.  More about available scopes with Sign in with Klarna <a href="https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/integrate-sign-in-with-klarna/web-sdk-integration/#scopes-and-claims">here</a>. Additional scopes can be customized if applicable, more info <a href="https://gist.github.com/mntzrr/4bf23ca394109d40575f2abc05811ddc">here</a>.', 'siwk' ),
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_optional_scopes_date_of_birth'   => array(
					'name'    => 'siwk_optional_scopes_date_of_birth',
					'title'   => 'Request Additional Customer Data',
					'type'    => 'checkbox',
					'label'   => __( 'Date of birth', 'siwk' ),
					'default' => 'no',
				),
				'siwk_optional_scopes_country'         => array(
					'name'    => 'siwk_optional_scopes_country',
					'type'    => 'checkbox',
					'label'   => __( 'Country', 'siwk' ),
					'default' => 'no',
					'class'   => 'siwk',
				),
				'siwk_optional_scopes_language'        => array(
					'name'    => 'siwk_optional_scopes_language',
					'type'    => 'checkbox',
					'label'   => __( 'Language Preference', 'siwk' ),
					'default' => 'no',
					'class'   => 'siwk',
				),
				'siwk_previews'                        => array(
					'type'     => 'kp_section_end',
					'previews' => array(
						array(
							'title' => __( 'Preview', 'siwk' ),
							'image' => $this->get_preview_image(),
						),
					),
				),
			)
		);
	}

	/**
	 * Retrieve the client ID from the Klarna plugin settings.
	 *
	 * @param array $settings The settings to extract from.
	 * @return string The client ID or empty string.
	 */
	private function get_client_id( $settings ) {
		$country     = strtolower( kp_get_klarna_country() );
		$test_mode   = wc_string_to_bool( $this->test_mode );
		$combined_eu = wc_string_to_bool( $settings['combine_eu_credentials'] ?? 'no' );

		$prefix = $test_mode ? 'test_' : '';
		if ( $combined_eu ) {
		if ( $combined_eu && key_exists( $country, \KP_Form_Fields::available_countries( 'eu' ) ) ) {
			return $settings[ "{$prefix}client_id_eu" ] ?? '';
		}

		return $settings[ "{$prefix}client_id_{$country}" ] ?? '';
	}

	/**
	 * Get the preview image URL for the "Sign in with Klarna" button.
	 *
	 * @return string The preview image url.
	 */
	private function get_preview_image() {
		$theme     = $this->button_theme;
		$shape     = $this->button_shape;
		$alignment = $this->logo_alignment;

		return plugin_dir_url( __FILE__ ) . "assets/img/preview-{$shape}_shape-{$theme}_theme-{$alignment}_alignment.png";
	}

	/**
	 * Update the internal settings state.
	 *
	 * @param array $settings The settings to extract from.
	 * @return void
	 */
	private function update( $settings ) {
		$default = $this->default();

		$this->test_mode      = $settings['testmode'] ?? $default['siwk_test_mode'];
		$this->button_theme   = $settings['siwk_button_theme'];
		$this->button_shape   = $settings['siwk_button_shape'];
		$this->logo_alignment = $settings['siwk_logo_alignment'];
		$this->cart_placement = $settings['siwk_cart_placement'];
		$this->client_id      = $this->get_client_id( $settings );
		$this->market         = kp_get_klarna_country();

		$this->locale = apply_filters( 'siwk_locale', str_replace( '_', '-', get_locale() ) );

		// The array keys match the name of the scopes they define.
		$this->scope = array(
			'profile:name'          => wc_string_to_bool( $settings['siwk_required_scopes_name'] ),
			'profile:email'         => wc_string_to_bool( $settings['siwk_required_scopes_email'] ),
			'profile:phone'         => wc_string_to_bool( $settings['siwk_required_scopes_phone'] ),
			'profile:language'      => wc_string_to_bool( $settings['siwk_optional_scopes_language'] ),
			'profile:country'       => wc_string_to_bool( $settings['siwk_optional_scopes_country'] ),
			'profile:date_of_birth' => wc_string_to_bool( $settings['siwk_optional_scopes_date_of_birth'] ),
		);
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
			'siwk_test_mode'      => 'no',
			'siwk_title_theme'    => __( 'Theme, button shape & placements', 'siwk' ),
			'siwk_button_theme'   => 'default',
			'siwk_button_shape'   => 'default',
			'siwk_logo_alignment' => 'default',
			'siwk_cart_placement' => 10,
			'siwk_callback_url'   => Redirect::get_callback_url(),
		);
	}
}
