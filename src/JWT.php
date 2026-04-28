<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

use KP_Form_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Used for working with JWT tokens and their data.
 */
class JWT {
	/**
	 * The Klarna request's base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The regional endpoint (EU v. NA).
	 *
	 * @var string `eu` or `na`.
	 */
	public $region;

	/**
	 * Class constructor.
	 *
	 * @param bool     $test_mode Whether to use the test or production endpoint.
	 * @param Settings $settings Settings.
	 */
	public function __construct( $test_mode, $settings ) {
		$environment    = $test_mode ? 'playground.' : '';
		$this->base_url = "https://login.{$environment}klarna.com";
		$this->settings = $settings;

		if ( \class_exists( 'KP_Form_Fields' ) ) {
			$country      = strtolower( kp_get_klarna_country() );
			$country_data = KP_Form_Fields::$kp_form_auto_countries[ $country ] ?? null;
			$endpoint     = empty( $country_data['endpoint'] ) ? 'eu' : 'na';

			$this->region = strtolower( apply_filters( 'klarna_base_region', $endpoint ) );
		}
	}

	/**
	 * The Klarna request's base URL.
	 *
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * Validate and extract the payload only if the JWT token is valid.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|\WP_Error A validated JSON decoded JWT token array or WP_Error if invalid.
	 */
	public function get_payload( $jwt_token ) {
		$parts = explode( '.', $jwt_token );

		if ( \count( $parts ) !== 3 ) {
			return new \WP_Error( 'JWT token invalid.' );
		}

		$payload = json_decode( base64_decode( $parts[1] ), true );

		return empty( $payload ) ? new \WP_Error( 'JWT token invalid.' ) : $payload;
	}
}
