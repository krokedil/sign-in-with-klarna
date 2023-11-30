<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage user state and associated tokens.
 */
class User {

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
	 * Settings.
	 *
	 * @var Settings $settings
	 */
	private $settings;

	/**
	 * JWT interface.
	 *
	 * @var JWT
	 */
	private $jwt;

	/**
	 * Class constructor.
	 *
	 * @param JWT      $jwt JWT.
	 * @param Settings $settings Settings.
	 */
	public function __construct( $jwt, $settings ) {
		$this->settings = $settings;
		$this->jwt      = $jwt;
	}

	/**
	 * Update the refresh token, and generate a new access token.
	 *
	 * Assumes refresh token is saved to user's metadata.
	 *
	 * @return bool TRUE if a new access token was set, otherwise FALSE.
	 */
	public function update_refresh_token() {
		$user_id       = get_current_user_id();
		$refresh_token = get_user_meta( $user_id, self::$refresh_token_key, true );

		// We need an existing refresh token to issue a new one.
		if ( empty( $refresh_token ) ) {
			return false;
		}

		$region   = $this->settings->region;
		$iss      = $refresh_token['iss'];
		$response = wp_remote_post(
			"{$iss}/{$region}/lp/idp/oauth2/token",
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'refresh_token' => $refresh_token['refresh_token'],
					'client_id'     => $refresh_token['client_id'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || ( $code < 200 || $code > 299 ) ) {
			// Delete all instances of SIWK refresh token in the user's metadata.
			// This should ensure the SIWK button should appear again on the frontend.
			delete_user_meta( $user_id, self::$refresh_token_key );
			return false;
		}

		$body          = json_decode( wp_remote_retrieve_body( $response ), true );
		$refresh_token = $this->jwt->get_refresh_token( $body['access_token'], $body['id_token'], $body['refresh_token'] );

		if ( empty( $refresh_token ) ) {

			// Mostly likely the merchant changed environment.
			// Delete the user meta to ensure a new refresh token is issued next time in the new environment.
			delete_user_meta( $user_id, self::$refresh_token_key );
			return false;
		}

		update_user_meta( $user_id, self::$refresh_token_key, $refresh_token );
		return $this->set_access_token( $user_id, $body['access_token'], intval( $body['expires_in'] ) ?? 299 );
	}

	/**
	 * Get the access token or generate a new one if it has already expired.
	 *
	 * @param int $user_id The user ID.
	 * @return string|false The access token or FALSE.
	 */
	public function get_access_token( $user_id ) {
		// Guest user has no access token.
		if ( 0 === $user_id ) {
			return false;
		}

		$access_token_key = $user_id . self::$access_token_key;

		// Check for existing transient.
		$access_token = get_transient( $access_token_key );
		if ( ! empty( $access_token ) ) {
			return $access_token;
		}
		// Check if the user has refresh token.
		$refresh_token = get_user_meta( $user_id, self::$refresh_token_key, true );
		if ( empty( $refresh_token ) ) {
			return false;
		}

		// Update refresh token, and fetch new access token.
		$this->update_refresh_token();
		return get_transient( $access_token_key );
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
		return set_transient( $user_id . self::$access_token_key, $jwt_access_token, $expires_in );
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

		// Set Klarna as the selected payment method (if available).
		if ( apply_filters( 'siwk_set_gateway_to_klarna', '__return_false' ) ) {
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
