<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\SignInWithKlarna;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\JWK as FirebaseJWK;

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
	 * JWKS
	 *
	 * @var array
	 */
	private $jwks;

	/**
	 * Class constructor.
	 *
	 * @param bool $test_mode Whether to use the test or production endpoint.
	 */
	public function __construct( $test_mode ) {
		$this->base_url = 'https://' . ( $test_mode ? 'login.playground.klarna.com' : 'login.klarna.com' );
		$this->jwks_url = $this->base_url . '/eu/lp/idp/.well-known/jwks.json';
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
	 * Check if a given JWT is valid.
	 *
	 * This includes signature verification.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|bool The JWT decoded if is valid, otherwise, FALSE.
	 */
	public function is_valid_jwt( $jwt_token ) {
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
			// Convert the stdClass to associative array.
			return json_decode( wp_json_encode( FirebaseJWT::decode( $jwt_token, FirebaseJWK::parseKeySet( $this->jwks ) ) ), true );
		} catch ( \Exception $e ) {

			// Set to false to ensure new keys are retrieved next time this function is called.
			$this->jwks = false;
			return false;
		}
	}

	/**
	 * Extract the JWT payload.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|\WP_Error The payload.
	 */
	public function get_payload( $jwt_token ) {
		$payload = $this->is_valid_jwt( $jwt_token );
		return empty( $payload ) ? new \WP_Error( 'JWT token invalid.' ) : $payload;
	}

	/**
	 * Get the refresh token metadata required for issuing new access token.
	 *
	 * @param string $jwt_access_token JWT access token.
	 * @param string $jwt_id_token JWT id token.
	 * @param string $refresh_token Opaque refresh token.
	 * @return \WP_Error|array Return WP_Error if a refresh token could not be retrieved.
	 */
	public function get_refresh_token( $jwt_access_token, $jwt_id_token, $refresh_token ) {
		$id_token     = $this->get_payload( $jwt_id_token );
		$access_token = $this->get_payload( $jwt_access_token );

		if ( is_wp_error( $id_token ) ) {
			return is_wp_error( $access_token ) ? $access_token : $id_token;
		}

		return array(
			'client_id'     => $access_token['client_id'],
			'jti'           => $id_token['jti'],
			'auth_time'     => intval( $id_token['auth_time'] ),
			'iss'           => $id_token['iss'],
			'refresh_token' => $refresh_token,
		);
	}
}
