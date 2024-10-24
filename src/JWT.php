<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\JWK as FirebaseJWK;
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
	 * Klarna JWKS URL.
	 *
	 * @var string
	 */
	private $jwks_url;

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
	 * JWKS
	 *
	 * @var array
	 */
	private $jwks;

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

		$this->jwks_url = "{$this->base_url}/{$this->region}/lp/idp/.well-known/jwks.json";
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
	 * Validate a JWT token.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|false The validated JWT token as an array or FALSE if invalid.
	 */
	private function is_valid_jwt( $jwt_token ) {
		if ( empty( $this->jwks ) ) {
			$response = wp_remote_get(
				$this->jwks_url,
				array(
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$this->jwks = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		try {

			// An exception is thrown if the token is invalid. Convert the stdClass to associative array.
			return json_decode( wp_json_encode( FirebaseJWT::decode( $jwt_token, FirebaseJWK::parseKeySet( $this->jwks ) ) ), true );
		} catch ( \Exception $e ) {

			// Set to false to ensure new keys are retrieved next time this function is called.
			$this->jwks = false;
			return false;
		}
	}

	/**
	 * Validate and extract the payload only if the JWT token is valid.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|\WP_Error A validated JSON decoded JWT token array or WP_Error if invalid.
	 */
	public function get_payload( $jwt_token ) {
		$jwt_token = $this->is_valid_jwt( $jwt_token );
		return empty( $jwt_token ) ? new \WP_Error( 'JWT token invalid.' ) : $jwt_token;
	}

	/**
	 * Retrieve tokens from Klarna.
	 *
	 * @param string $refresh_token The Klarna refresh token.
	 * @return array|\WP_Error A validated array of tokens or WP_Error if the no new tokens could be retrieved.
	 */
	public function get_fresh_tokens( $refresh_token ) {
		// We need an existing refresh token to issue a new one.
		if ( empty( $refresh_token ) ) {
			return new \WP_Error( 'missing_refresh_token', 'No refresh token provided.' );
		}

		// We retrieve the client ID from the settings. This should ensure that a refresh token is only used for the correct client.
		$response = wp_remote_post(
			"{$this->base_url}/{$this->region}/lp/idp/oauth2/token",
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $this->settings->get( 'client_id' ),
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( $code >= 400 ) {
			return new \WP_Error( 'refresh_token', wp_remote_retrieve_body( $response ) );
		}

		// Validate and extract the JWT tokens.
		$tokens = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! $this->is_valid_jwt( $tokens['id_token'] ) ) {
			return new \WP_Error( 'invalid_jwt', 'The response from Klarna was not a valid JWT.' );
		}

		// convert to milliseconds from seconds.
		$tokens['expires_in'] = $tokens['expires_in'] * 1000 + time();
		return $tokens;
	}
}
