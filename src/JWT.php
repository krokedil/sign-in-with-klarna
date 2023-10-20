<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Used for working with JWT tokens and their data.
 */
class JWT {

	/**
	 * Extract the JWT payload.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array The payload.
	 */
	public static function get_jwt_payload( $jwt_token ) {
		$segments = explode( '.', $jwt_token );
		return json_decode( base64_decode( $segments[1] ), true );
	}

	/**
	 * Get the refresh token metadata required for issuing new access token.
	 *
	 * @param string $jwt_access_token JWT access token.
	 * @param string $jwt_id_token JWT id token.
	 * @param string $refresh_token Opaque refresh token.
	 * @return array
	 */
	public static function get_refresh_token( $jwt_access_token, $jwt_id_token, $refresh_token ) {
		$id_token     = self::get_jwt_payload( $jwt_id_token );
		$access_token = self::get_jwt_payload( $jwt_access_token );

		return array(
			'client_id'     => $access_token['client_id'],
			'jti'           => $id_token['jti'],
			'auth_time'     => intval( $id_token['auth_time'] ),
			'iss'           => $id_token['iss'],
			'refresh_token' => $refresh_token,
		);
	}
}
