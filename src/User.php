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
	public const REFRESH_TOKEN_KEY = 'siwk_refresh_token';
	/**
	 * The meta key for access token.
	 *
	 * @var string
	 */
	public const TOKENS_KEY = '_siwk_tokens';


	/**
	 * JWT interface.
	 *
	 * @var JWT
	 */
	private $jwt;

	/**
	 * Class constructor.
	 *
	 * @param JWT $jwt JWT.
	 */
	public function __construct( $jwt ) {
		$this->jwt = $jwt;
	}

	/**
	 * Get the access token or generate a new one if it has already expired.
	 *
	 * @param int $user_id The user ID (guest = 0).
	 * @return string|false The access token or FALSE.
	 */
	public function get_access_token( $user_id ) {
		// Guest user has no access token.
		if ( 0 === $user_id ) {
			return false;
		}

		$tokens = json_decode( get_user_meta( $user_id, self::TOKENS_KEY, true ) );
		if ( empty( $tokens ) ) {
			return false;
		}

		// If the token is not valid or has expired, try to refresh it.
		$access_token = $this->jwt->get_payload( $tokens['access_token'] );
		if ( ! is_wp_error( $access_token ) && ( $tokens['expires_in'] - 30_000 ) > time() ) {
			return $access_token;
		}

		// Check if the user has refresh token.
		$refresh_token = get_user_meta( $user_id, self::REFRESH_TOKEN_KEY, true );
		if ( empty( $refresh_token ) ) {
			return false;
		}

		// Update refresh token, and fetch new access token.
		$tokens = $this->jwt->get_fresh_tokens( $refresh_token );
		if ( ! is_wp_error( $tokens ) ) {
			$this->set_tokens( $user_id, $tokens );

			// Store the refresh token as-is, a JSON encoded string.
			$this->set_refresh_token( $user_id, $tokens['refresh_token'] );
			return $tokens['access_token'];
		}

		// Mostly likely the merchant changed environment.
		// Delete the user meta to ensure a new refresh token is issued next time in the new environment, and to make the SIWK button appear again on the frontend.
		delete_user_meta( $user_id, self::REFRESH_TOKEN_KEY );
		return false;
	}

	/**
	 * Store the Klarna tokens retrieved from the "refresh token" request to the user's metadata.
	 *
	 * @param int   $user_id The Woo user ID.
	 * @param array $tokens Klarna tokens.
	 * @return bool Whether the tokens were saved.
	 */
	public function set_tokens( $user_id, $tokens ) {
		return update_user_meta( $user_id, self::TOKENS_KEY, wp_json_encode( $tokens ) );
	}

	/**
	 * Store the access token as a transient associated with user ID.
	 *
	 * @param int    $user_id The Woo user ID.
	 * @param string $refresh_token The refresh token.
	 * @return bool Whether the refresh token were saved.
	 */
	public function set_refresh_token( $user_id, $refresh_token ) {
		return update_user_meta( $user_id, self::TOKENS_KEY, $refresh_token );
	}

	/**
	 * Simulate logging in as user ID.
	 *
	 * Also sets the selected payment method to Klarna if possible.
	 *
	 * @param int  $user_id The user to login as.
	 * @param bool $set_gateway Whether to set Klarna Checkout or Klarna Payments (whichever has highest order) as the chosen payment method (default: false).
	 * @return void
	 */
	public static function set_current_user( $user_id, $set_gateway = false ) {
		wc_set_customer_auth_cookie( $user_id );
		wp_set_current_user( $user_id );

		// Set Klarna as the selected payment method (if available).
		if ( apply_filters( 'siwk_set_gateway_to_klarna', $set_gateway ) ) {
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $gateway ) {
				if ( in_array( $gateway->id, array( 'kco', 'klarna_payments' ), true ) ) {

					// Set the highest ordered Klarna payment gateway.
					WC()->session->set( 'chosen_payment_method', $gateway->id );
					WC()->payment_gateways->set_current_gateway( $gateway->id );

					return;
				}
			}
		}
	}
}
