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
	 * @var string 'playground' or 'production'.
	 */

	public $environment;

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
	 * Change alignment of the Klarna logo on the call to action button based on the provided configuration.
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
	 * The regional endpoint (EU v. US).
	 *
	 * @var string
	 */
	public $region;

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
	}

	/**
	 * Retrieve the value of a SIWK setting.
	 *
	 * @param string $setting The name of the setting.
	 * @return string|int
	 */
	public function get( $setting ) {
		$setting = str_replace( 'siwk_', '', $setting );
		return $this->$setting;
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
		$settings['siwk_title'] = array(
			'title' => $this->default()['siwk_title'],
			'type'  => 'title',
		);

		$settings['siwk_client_id'] = array(
			'name'        => 'siwk_client_id',
			'title'       => __( 'Client ID', 'siwk' ),
			'description' => __( 'The UUID you received after the Sign in with Klarna onboarding.', 'siwk' ),
			'type'        => 'text',
			'default'     => $this->default()['siwk_client_id'],
		);

		$settings['siwk_environment'] = array(
			'name'        => 'siwk_environment',
			'title'       => __( 'Environment', 'siwk' ),
			'type'        => 'select',
			'description' => __( 'The environment to which the integration is pointing.', 'siwk' ),
			'default'     => $this->default()['siwk_environment'],
			'options'     => array(
				'playground' => __( 'Playground', 'siwk' ),
				'production' => __( 'Production', 'siwk' ),
			),
		);

		$settings['siwk_market'] = array(
			'name'        => 'siwk_market',
			'title'       => __( 'Market', 'siwk' ),
			'type'        => 'text',
			'description' => __( 'The market or the country where this integration is available.', 'siwk' ),
			'default'     => $this->default()['siwk_market'],
		);

		$settings['siwk_region'] = array(
			'name'        => 'siwk_region',
			'title'       => __( 'Region', 'siwk' ),
			'type'        => 'select',
			'description' => __( 'The regional endpoint.', 'siwk' ),
			'default'     => $this->default()['siwk_region'],
			'options'     => array(
				'eu' => __( 'EU', 'siwk' ),
			),
		);

		$settings['siwk_button_theme'] = array(
			'name'        => 'siwk_button_theme',
			'title'       => __( 'Button theme' ),
			'type'        => 'select',
			'description' => __( 'The button\'s color theme.', 'siwk' ),
			'default'     => $this->default()['siwk_button_theme'],
			'options'     => array(
				'default' => __( 'Default', 'siwk' ),
				'dark'    => __( 'Dark', 'siwk' ),
				'light'   => __( 'Light', 'siwk' ),
			),
		);

		$settings['siwk_button_shape'] = array(
			'name'        => 'siwk_button_shape',
			'title'       => __( 'Button shape' ),
			'type'        => 'select',
			'description' => __( 'The button\'s shape.', 'siwk' ),
			'default'     => $this->default()['siwk_button_shape'],
			'options'     => array(
				'default'   => __( 'Default', 'siwk' ),
				'rectangle' => __( 'Rectangle', 'siwk' ),
				'pill'      => __( 'Pill', 'siwk' ),
			),
		);

		$settings['siwk_logo_alignment'] = array(
			'name'        => 'siwk_logo_alignment',
			'title'       => __( 'Logo alignment' ),
			'type'        => 'select',
			'description' => __( 'Change alignment of the Klarna logo on the call to action button based on the provided configuration.', 'siwk' ),
			'default'     => $this->default()['siwk_logo_alignment'],
			'options'     => array(
				'left'   => __( 'Left', 'siwk' ),
				'center' => __( 'Center', 'siwk' ),
				'right'  => __( 'Right', 'siwk' ),
			),
		);

		$settings['siwk_cart_placement'] = array(
			'name'        => 'siwk_cart_placemeent',
			'title'       => __( 'Cart page placement', 'siwk' ),
			'type'        => 'select',
			'description' => __( 'Change the placement of the "Sign in with Klarna" button on the cart page.', 'siwk' ),
			'default'     => $this->default()['siwk_cart_placement'],
			'options'     => array(
				'10'  => __( 'Before "Proceed to checkout" button', 'siwk' ),
				'100' => __( 'After "Proceed to checkout" button', 'siwk' ),
			),
		);

		return $settings;
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
		$this->environment    = $settings['siwk_environment'];
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
			'siwk_title'          => __( 'Sign in with Klarna', 'siwk' ) ?? 'Sign in with Klarna',
			'siwk_client_id'      => '',
			'siwk_market'         => wc_get_base_location()['country'] ?? '',
			'siwk_region'         => 'eu',
			'siwk_environment'    => 'playground',
			'siwk_button_theme'   => 'default',
			'siwk_button_shape'   => 'default',
			'siwk_logo_alignment' => 'left',
			'siwk_cart_placement' => 10,
		);

	}
}
