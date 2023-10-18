<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIWK settings class.
 */
class Settings {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Class constructor.
	 *
	 * @param  array $args The settings array where SIWK settings are to be appended.
	 * @return void
	 */
	public function __construct( $settings ) {
		$this->settings = self::defaults();
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array The default settings values.
	 */
	public static function defaults() {
		return array(
			'siwk_client_id'      => '',
			'siwk_market'         => '',
			'siwk_mode'           => 'playground',
			'siwk_button_theme'   => 'default',
			'siwk_button_shape'   => 'default',
			'siwk_logo_alignment' => 'left',
			'siwk_cart_placement' => 10,
		);
	}

	/**
	 * Get the value for a SIWK setting.
	 *
	 * The 'siwk_' prefix is optional.
	 *
	 * @param string $setting The setting name.
	 * @return string|false FALSE if incorrect setting name, otherwise stored or default value.
	 */
	public function get( $setting ) {
		$setting = 'siwk_' . str_replace( $setting, 'siwk_', '' );
		if ( isset( $this->settings[ $setting ] ) ) {
			return $setting;
		}

		return false;
	}

	/**
	 * Set the value of a SIWK setting.
	 *
	 * The 'siwk_' prefix is optional.
	 *
	 * @param string     $setting The setting name.
	 * @param string|int $value The new value.
	 * @return void
	 */
	public function set( $setting, $value ) {
		$setting = 'siwk_' . str_replace( $setting, 'siwk_', '' );
		if ( isset( self::defaults()[ $setting ] ) ) {
			$this->settings[ $setting ] = $value;
		}
	}

	/**
	 * Extend your plugin with the required SIWK settings.
	 *
	 * @param array $settings Your settings array to append to.
	 * @return array
	 */
	public function add_siwk_settings( $settings ) {
		$settings['siwk_title'] = array(
			'title' => __( 'Sign in with Klarna', 'siwk' ),
			'type'  => 'title',
		);

		$settings['siwk_client_id'] = array(
			'name'        => 'siwk_client_id',
			'title'       => __( 'Client ID', 'siwk' ),
			'description' => __( 'The UUID you received after the Sign in with Klarna onboarding.', 'siwk' ),
			'type'        => 'text',
		);

		$settings['siwk_environment'] = array(
			'name'        => 'siwk_environment',
			'title'       => __( 'Environment', 'siwk' ),
			'type'        => 'select',
			'description' => __( 'The environment to which the integration is pointing.', 'siwk' ),
			'default'     => 'playground',
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
			'default'     => wc_get_base_location()['country'] ?? '',
		);

		$settings['siwk_button_theme'] = array(
			'name'        => 'siwk_button_theme',
			'title'       => __( 'Button theme' ),
			'type'        => 'select',
			'description' => __( 'The button\'s color theme.', 'siwk' ),
			'default'     => 'default',
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
			'default'     => 'default',
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
			'default'     => 'left',
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
			'default'     => '10',
			'options'     => array(
				'10'  => __( 'Before "Proceed to checkout" button', 'siwk' ),
				'100' => __( 'After "Proceed to checkout" button', 'siwk' ),
			),
		);

		return $settings;
	}

}
