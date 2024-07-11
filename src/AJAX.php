<?php //phpcs:ignore -- PCR-4 compliant
namespace Krokedil\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIWK AJAX class
 */
class AJAX {

	/**
	 * JWT interface.
	 *
	 * @var JWT
	 */
	private $jwt;

	/**
	 * Handles metadata associated with a WordPress user.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Class constructor.
	 *
	 * The AJAX request should only be enqueued ONCE, and only ONCE.
	 *
	 * @param JWT  $jwt JWT instance.
	 * @param User $user User instance.
	 */
	public function __construct( $jwt, $user ) {
		$this->jwt  = $jwt;
		$this->user = $user;

		$ajax_events = array(
			'siwk_sign_in' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
				add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
			}
		}
	}

	/**
	 * Handle sign-in request via client.
	 *
	 * @return void
	 */
	public function siwk_sign_in() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'siwk_sign_in' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		if ( ! isset( $_POST['id_token'], $_POST['refresh_token'] ) ) {
			wp_send_json_error( 'missing parameters' );
		}

		$refresh_token = sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) );
		$tokens        = $this->jwt->get_fresh_tokens( $refresh_token );
		if ( is_wp_error( $tokens ) ) {
			$error_message = $tokens->get_error_message();
			if ( is_array( $error_message ) ) {
				$error_message = implode( $error_message );
			}

			wp_send_json_error( 'could not retrieve tokens: ' . $error_message );
		}

		$id_token = $this->jwt->get_payload( $tokens['id_token'] );
		$userdata = $this->user->get_user_data( $id_token, $refresh_token );

		if ( username_exists( $userdata['user_login'] ) || email_exists( $userdata['user_email'] ) ) {
			$result = $this->user->merge_with_existing_user( $userdata );
		} else {
			$result = $this->user->register_new_user( $userdata );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$guest   = 0;
		$user_id = get_current_user_id();
		if ( $guest !== $user_id ) {
			$this->user->sign_in_user( $user_id, $tokens, $refresh_token );
		}

		wp_send_json_success( array( 'user_id' => $user_id ) );
	}
}
