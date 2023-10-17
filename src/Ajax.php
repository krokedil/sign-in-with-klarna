<?php
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna Payments AJAX class
 */
class AJAX extends \WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'siwk_sign_in' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Handle sign-in request via client.
	 *
	 * @return void
	 */
	public static function siwk_sign_in() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'siwk_sign_in' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		if ( ! isset( $_POST['id_token'], $_POST['refresh_token'], $_POST['access_token'] ) ) {
			wp_send_json_error( 'missing parameters' );
		}

		$refresh_token    = sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) );
		$jwt_id_token     = sanitize_text_field( wp_unslash( $_POST['id_token'] ) );
		$jwt_access_token = sanitize_text_field( wp_unslash( $_POST['access_token'] ) );
		$expires_in       = intval( wp_unslash( $_POST['expires_in'] ?? 299 ) );

		$id_token      = SignInWithKlarna::get_jwt_payload( $jwt_id_token );
		$refresh_token = SignInWithKlarna::get_refresh_token( $jwt_access_token, $jwt_id_token, $refresh_token );

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
			function( $field ) {
				if ( empty( $field ) ) {
					return '';
				}
				return wc_clean( $field );
			},
			$id_token['billing_address']
		);

		$userdata['meta_input'] = array(
			'billing_first_name'                 => $userdata['first_name'],
			'billing_last_name'                  => $userdata['last_name'],
			'billing_city'                       => $billing_address['city'],
			'billing_state'                      => $billing_address['region'],
			'billing_country'                    => $billing_address['country'],
			'billing_postcode'                   => $billing_address['postal_code'],
			'billing_address_1'                  => $billing_address['street_address'],
			'billing_address_2'                  => $billing_address['street_address_2'],
			'billing_phone'                      => $id_token['phone'],
			'billing_email'                      => $userdata['user_email'],
			'shipping_first_name'                => $userdata['first_name'],
			'shipping_last_name'                 => $userdata['last_name'],
			'shipping_city'                      => $billing_address['city'],
			'shipping_country'                   => $billing_address['country'],
			'shipping_state'                     => $billing_address['region'],
			'shipping_postcode'                  => $billing_address['postal_code'],
			'shipping_address_1'                 => $billing_address['street_address'],
			'shipping_address_2'                 => $billing_address['street_address_2'],
			'shipping_phone'                     => $id_token['phone'],
			'shipping_email'                     => $userdata['user_email'],
			SignInWithKlarna::$refresh_token_key => $refresh_token,
		);

		// Remove empty fields (based on default value).
		$userdata['meta_input'] = array_filter(
			$userdata['meta_input'],
			function ( $field ) {
				return ! empty( $field );
			}
		);

		// If the user is already logged in, save the refresh token (to be used for klarna_access_token), and return.
		// Otherwise, their cart will be emptied when changing account.
		$user_id = get_current_user_id();
		$guest   = 0;
		if ( $guest !== $user_id ) {
			SignInWithKlarna::set_access_token( $user_id, $jwt_access_token, $expires_in );
			update_user_meta( $user_id, SignInWithKlarna::$refresh_token_key, $refresh_token );

			wp_send_json_success( 'user already logged in' );
		}

		if ( username_exists( $userdata['user_login'] ) || email_exists( $userdata['user_email'] ) ) {
			$user = get_user_by( 'login', $userdata['user_login'] );
			$user = ! empty( $user ) ? $user : get_user_by( 'email', $userdata['user_email'] );
			if ( empty( $user ) ) {
				wp_send_json_error( 'user exists, failed to retrieve user data' );
			}

			// Skip if the user is already logged in.
			if ( get_current_user_id() === $user->ID ) {
				wp_send_json_success( 'user exists, already logged in' );
			}

			// Try to log the user in. The client should refresh the page.
			SignInWithKlarna::set_current_user( $user->ID );
			SignInWithKlarna::set_access_token( $user->ID, $jwt_access_token, $expires_in );

			wp_send_json_success( 'user exists, logging in' );
		}

		$user_id = wp_insert_user( apply_filters( 'siwk_create_new_user', $userdata ) );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( 'could not create user' );
		}

		do_action( 'woocommerce_created_customer', $user_id, $userdata, false );

		// Try to log the user in. The page should be automatically refreshed in the client.
		SignInWithKlarna::set_current_user( $user_id );
		SignInWithKlarna::set_access_token( $user_id, $jwt_access_token, $expires_in );

		wp_send_json_success( 'user created, logging in' );
	}
}
