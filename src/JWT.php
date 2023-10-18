<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWT {

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

	public function() {

	}

	/**
	 * Update the refresh token, and generate a new access token.
	 *
	 * Assumes refresh token is saved to user's metadata.
	 *
	 * @return bool TRUE if the access token was set, otherwise FALSE.
	 */
	public static function update_refresh_token() {
		$user_id       = get_current_user_id();
		$refresh_token = get_user_meta( $user_id, $this->refresh_token_key, true );
		if ( empty( $refresh_token ) ) {
			return false;
		}

		$payload  = array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'refresh_token' => $refresh_token['refresh_token'],
				'client_id'     => $refresh_token['client_id'],
				'grant_type'    => 'refresh_token',
			),
		);
		$region   = 'eu';
		$iss      = $refresh_token['iss'];
		$response = wp_remote_post( "{$iss}/{$region}/lp/idp/oauth2/token", $payload );
		if ( is_wp_error( $response ) ) {
			$has_expired = 403;
			$code        = $response->get_error_code();

			// Delete all instances of SIWK refresh token in the user's metadata. This should ensure the SIWK button should appear again on the frontend.
			if ( $has_expired === $code ) {
				delete_user_meta( $user_id, $this->refresh_token_key );
			}

			return false;
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			$refresh_token = self::get_refresh_token( $body['access_token'], $body['id_token'], $body['refresh_token'] );
			update_user_meta( $user_id, $this->refresh_token_key, $refresh_token );
			return self::set_access_token( $user_id, $body['access_token'], intval( $body['expires_in'] ) ?? 299 );
		}
	}


	/**
	 * Get the refresh token metadata required for issuing new access token.
	 *
	 * @param string $jwt_access_token JWT access token.
	 * @param string $jwt_id_token JWT id token.
	 * @param string $refresh_token Opaque refresh token.
	 * @return array
	 */
	public function get_refresh_token( $jwt_access_token, $jwt_id_token, $refresh_token ) {
		$id_token     = $this->get_jwt_payload( $jwt_id_token );
		$access_token = $this->get_jwt_payload( $jwt_access_token );

		return array(
			'client_id'     => $access_token['client_id'],
			'jti'           => $id_token['jti'],
			'auth_time'     => intval( $id_token['auth_time'] ),
			'iss'           => $id_token['iss'],
			'refresh_token' => $refresh_token,
		);
	}

	/**
	 * Store the access token as a transient associated with user ID.
	 *
	 * @param int    $user_id The user to associate the access token with.
	 * @param string $jwt_access_token JWT access token.
	 * @param int    $expires_in How long the transient is valid (seconds).
	 * @return bool TRUE if the access token was set, otherwise FALSE.
	 */
	public function set_access_token( $user_id, $jwt_access_token, $expires_in ) {
		return set_transient( $user_id . $this->access_token_key, $jwt_access_token, $expires_in );
	}

	/**
	 * Get the access token or generate a new one if it has already expired.
	 *
	 * @param int $user_id The user ID.
	 * @return string|false The access token or FALSE.
	 */
	public function get_access_token( $user_id ) {
		// Guest user.
		if ( 0 === $user_id ) {
			return false;
		}

		$access_token_key = $user_id . $this->access_token_key;

		// Check for existing transient.
		$access_token = get_transient( $access_token_key );
		if ( ! empty( $access_token ) ) {
			return $access_token;
		}
		// Check if the user has refresh token.
		$refresh_token = get_user_meta( $user_id, $this->refresh_token_key, true );
		if ( empty( $refresh_token ) ) {
			return false;
		}

		// Update refresh token, and fetch new access token.
		$this->update_refresh_token();
		return get_transient( $access_token_key );
	}

	/**
	 * Extract the JWT payload.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array The payload.
	 */
	public function get_jwt_payload( $jwt_token ) {
		$segments = explode( '.', $jwt_token );
		return json_decode( base64_decode( $segments[1] ), true );
	}



}
