<?php
/** AJAX endpoints with nonce, authentication and abuse protection. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Ajax {
	/** @var WTLW_Wheel_Engine */
	private $engine;

	public function __construct( WTLW_Wheel_Engine $engine ) {
		$this->engine = $engine;
		add_action( 'wp_ajax_wtlw_spin', array( $this, 'spin' ) );
		add_action( 'wp_ajax_nopriv_wtlw_spin', array( $this, 'spin' ) );
		add_action( 'wp_ajax_wtlw_register', array( $this, 'register' ) );
		add_action( 'wp_ajax_nopriv_wtlw_register', array( $this, 'register' ) );
	}

	/** Perform a server-side spin. */
	public function spin() {
		check_ajax_referer( 'wtlw_frontend', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please register or log in before spinning.', 'webtanan-lucky-wheel' ) ), 401 );
		}

		$ip = $this->request_ip();
		if ( ! $this->allow_ip( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again shortly.', 'webtanan-lucky-wheel' ) ), 429 );
		}

		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$result     = $this->engine->spin( get_current_user_id(), $ip, $request_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/** Register a user and initialize the campaign attempts. */
	public function register() {
		check_ajax_referer( 'wtlw_frontend', 'nonce' );
		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'message' => __( 'You are already logged in.', 'webtanan-lucky-wheel' ) ) );
		}

		$ip = $this->request_ip();
		if ( ! $this->allow_ip( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again shortly.', 'webtanan-lucky-wheel' ) ), 429 );
		}

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? preg_replace( '/[^0-9+]/', '', wp_unslash( $_POST['phone'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( ! $name || ! $phone || ! is_email( $email ) || strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Name, phone, a valid email and a password of at least 8 characters are required.', 'webtanan-lucky-wheel' ) ), 422 );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'This email is already registered.', 'webtanan-lucky-wheel' ) ), 409 );
		}
		if ( $this->phone_exists( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'This phone number is already registered.', 'webtanan-lucky-wheel' ) ), 409 );
		}

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! $username ) {
			$username = 'wtlw_' . wp_generate_password( 8, false, false );
		}
		$base_username = $username;
		$suffix        = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $suffix;
			++$suffix;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 400 );
		}
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
		update_user_meta( $user_id, 'wtlw_phone', $phone );
		$attempts = $this->engine->ensure_user_attempts( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success(
			array(
				'user_id'            => (int) $user_id,
				'attempts_remaining' => $attempts['remaining_attempts'],
				'message'            => __( 'Registration completed successfully.', 'webtanan-lucky-wheel' ),
			)
		);
	}

	/** Get a proxy-safe client IP. */
	private function request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** Sliding one-minute request limit. */
	private function allow_ip( $ip ) {
		$key   = 'wtlw_ip_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/** Check normalized phone against registered users. */
	private function phone_exists( $phone ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'wtlw_phone',
				$phone
			)
		);
	}
}
