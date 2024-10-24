<?php
namespace Krokedil\SignInWithKlarna;

use WP_Error;

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
		$tokens = $this->jwt->get_tokens( $refresh_token );
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
	 * @param int    $user_id The Woo user ID.
	 * @param array  $tokens Klarna tokens.
	 * @param string $refresh_token The refresh token (optional).
	 * @return bool Whether the tokens were saved.
	 */
	public function set_tokens( $user_id, $tokens, $refresh_token = null ) {
		$result = update_user_meta( $user_id, self::TOKENS_KEY, wp_json_encode( $tokens ) );
		if ( ! empty( $refresh_token ) ) {
			$result = $this->set_refresh_token( $user_id, $refresh_token );
		}

		return $result;
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

	/**
	 * Save tokens and refresh token to the user's metadata to an already logged in user.
	 *
	 * @param int    $user_id The user ID.
	 * @param array  $tokens The Klarna tokens.
	 * @param string $refresh_token The refresh token.
	 * @return void
	 */
	public function sign_in_user( $user_id, $tokens, $refresh_token ) {
		$this->set_tokens( $user_id, $tokens, $refresh_token );
		$this->set_current_user( $user_id );
	}

	/**
	 * Merge user data with an existing user (identified by email). Assumes the user is not already signed in.
	 *
	 * @param array $userdata The user data from Klarna.
	 * @return int|WP_Error The user's ID if was successfully merged, WP_Error otherwise.
	 */
	public function merge_with_existing_user( $userdata ) {
		$user = get_user_by( 'login', $userdata['user_login'] );
		$user = ! empty( $user ) ? $user : get_user_by( 'email', $userdata['user_email'] );
		if ( empty( $user ) ) {
			return new WP_Error( 'user_exists', 'failed to retrieve user data' );
		}

		// Add the retrieved user ID to the userdata so that Woo knows which user to update.
		$userdata['ID'] = $user->ID;

		// Since we only receive the billing address from Klarna, we use it as the shipping address too. However, if these fields are already set in the existing user's metadata, and are non-empty, we don't want to overwrite them.
		$userdata['meta_input'] = array_filter(
			$userdata['meta_input'],
			function ( $key ) use ( $user ) {
				$is_shipping = strpos( $key, 'shipping_' ) === 0;
				if ( $is_shipping && ! empty( get_user_meta( $user->ID, $key, true ) ) ) {
					return false;
				}

				return true;
			},
			ARRAY_FILTER_USE_KEY
		);

		$user_id = wp_update_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		do_action( 'siwk_merge_with_existing_user', $user_id, $userdata );
		return $user_id;
	}

	/**
	 * Register a new customer and log them in. Assumes the user is not already signed in.
	 *
	 * @param array $userdata The user data from Klarna.
	 * @return int|WP_Error The new user's ID or WP_Error.
	 */
	public function register_new_user( $userdata ) {
		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'register_new_user', 'could not create user' );
		}

		do_action( 'woocommerce_created_customer', $user_id, $userdata, false );
		return $user_id;
	}

	/**
	 * Extract the user data from the ID token.
	 *
	 * @param string $id_token The ID token.
	 * @param string $refresh_token The refresh token.
	 * @return array An userdata array to be consumed by wp_insert_user.
	 */
	public function get_user_data( $id_token, $refresh_token ) {
		$id_token = wp_parse_args(
			$id_token,
			array(
				'locale'          => str_replace( '-', '_', get_locale() ),
				'billing_address' => array(),
			)
		);

		$userdata = array(
			'role'        => 'customer',
			'user_login'  => sanitize_user( $id_token['email'] ),
			'user_pass'   => wp_generate_password(),
			'user_email'  => sanitize_email( $id_token['email'] ),
			'first_name'  => sanitize_text_field( $id_token['given_name'] ),
			'last_name'   => sanitize_text_field( $id_token['family_name'] ),
			'description' => __( 'Sign in with Klarna', 'siwk' ),
			'locale'      => $id_token['locale'],
		);

		// Clean fields, and use default values to avoid undefined index.
		$billing_address = array_map(
			function ( $field ) {
				if ( empty( $field ) ) {
					return '';
				}
				return wc_clean( $field );
			},
			$id_token['billing_address']
		);

		$userdata['meta_input'] = array(
			'billing_first_name'    => $userdata['first_name'],
			'billing_last_name'     => $userdata['last_name'],
			'billing_city'          => $billing_address['city'] ?? '',
			'billing_state'         => $billing_address['region'] ?? '',
			'billing_country'       => $billing_address['country'] ?? '',
			'billing_postcode'      => $billing_address['postal_code'] ?? '',
			'billing_address_1'     => $billing_address['street_address'] ?? '',
			'billing_address_2'     => $billing_address['street_address_2'] ?? '',
			'billing_phone'         => $id_token['phone'] ?? '',
			'billing_email'         => $userdata['user_email'],
			'shipping_first_name'   => $userdata['first_name'],
			'shipping_last_name'    => $userdata['last_name'],
			'shipping_city'         => $billing_address['city'] ?? '',
			'shipping_country'      => $billing_address['country'] ?? '',
			'shipping_state'        => $billing_address['region'] ?? '',
			'shipping_postcode'     => $billing_address['postal_code'] ?? '',
			'shipping_address_1'    => $billing_address['street_address'] ?? '',
			'shipping_address_2'    => $billing_address['street_address_2'] ?? '',
			'shipping_phone'        => $id_token['phone'] ?? '',
			'shipping_email'        => $userdata['user_email'],
			self::REFRESH_TOKEN_KEY => $refresh_token,
		);

		// Remove empty fields (based on default value).
		$userdata['meta_input'] = array_filter(
			$userdata['meta_input'],
			function ( $field ) {
				return ! empty( $field );
			}
		);

		return apply_filters( 'siwk_userdata', $userdata );
	}
}
