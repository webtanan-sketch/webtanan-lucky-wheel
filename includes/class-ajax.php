<?php
/** AJAX endpoints with nonce and abuse protection. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Ajax {
	private $engine;

	public function __construct( WTLW_Wheel_Engine $engine ) {
		$this->engine = $engine;
		add_action( 'wp_ajax_wtlw_spin', array( $this, 'spin' ) );
		add_action( 'wp_ajax_nopriv_wtlw_spin', array( $this, 'spin' ) );
		add_action( 'wp_ajax_wtlw_register', array( $this, 'register' ) );
		add_action( 'wp_ajax_nopriv_wtlw_register', array( $this, 'register' ) );
		add_action( 'wp_ajax_wtlw_guest_status', array( $this, 'guest_status' ) );
		add_action( 'wp_ajax_nopriv_wtlw_guest_status', array( $this, 'guest_status' ) );
	}

	public function spin() {
		check_ajax_referer( 'wtlw_frontend', 'nonce' );
		$ip = $this->request_ip();
		if ( ! $this->allow_ip( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.', 'webtanan-lucky-wheel' ) ), 429 );
		}
		$participant_id    = isset( $_POST['participant_id'] ) ? absint( $_POST['participant_id'] ) : 0;
		$participant_token = isset( $_POST['participant_token'] ) ? sanitize_text_field( wp_unslash( $_POST['participant_token'] ) ) : '';
		$request_id        = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$result = $this->engine->spin_guest( $participant_id, $participant_token, $ip, $request_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	public function register() {
		check_ajax_referer( 'wtlw_frontend', 'nonce' );
		$ip = $this->request_ip();
		if ( ! $this->allow_ip( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.', 'webtanan-lucky-wheel' ) ), 429 );
		}
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone     = $this->normalize_iran_mobile( $phone_raw );
		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'نام و نام خانوادگی را وارد کنید.', 'webtanan-lucky-wheel' ) ), 422 );
		}
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر مانند 09121234567 وارد کنید.', 'webtanan-lucky-wheel' ) ), 422 );
		}
		$session = WTLW_Database::create_participant_session( $name, $phone );
		if ( is_wp_error( $session ) ) {
			wp_send_json_error( array( 'message' => $session->get_error_message() ), 400 );
		}
		wp_send_json_success(
			array(
				'participant_id' => $session['id'],
				'participant_token' => $session['token'],
				'name' => $session['name'],
				'phone' => $session['phone'],
				'attempts_remaining' => $session['remaining_attempts'],
				'credit_balance' => $session['credit_balance'],
				'message' => __( 'اطلاعات ثبت شد؛ شانس شما آماده است.', 'webtanan-lucky-wheel' ),
			)
		);
	}

	public function guest_status() {
		check_ajax_referer( 'wtlw_frontend', 'nonce' );
		$participant_id    = isset( $_POST['participant_id'] ) ? absint( $_POST['participant_id'] ) : 0;
		$participant_token = isset( $_POST['participant_token'] ) ? sanitize_text_field( wp_unslash( $_POST['participant_token'] ) ) : '';
		$row = WTLW_Database::authenticate_participant( $participant_id, $participant_token );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'نشست قرعه‌کشی معتبر نیست. نام و شماره موبایل را دوباره وارد کنید.', 'webtanan-lucky-wheel' ) ), 401 );
		}
		wp_send_json_success( array( 'participant_id' => (int) $row->id, 'name' => $row->name, 'phone' => $row->phone, 'attempts_remaining' => (int) $row->remaining_attempts, 'credit_balance' => (float) $row->credit_balance ) );
	}

	private function normalize_digits( $value ) {
		return strtr( (string) $value, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ) );
	}

	private function normalize_iran_mobile( $value ) {
		$digits = preg_replace( '/\D+/', '', $this->normalize_digits( $value ) );
		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( 10 === strlen( $digits ) && '9' === substr( $digits, 0, 1 ) ) {
			$digits = '0' . $digits;
		}
		return preg_match( '/^09\d{9}$/', $digits ) ? $digits : '';
	}

	private function request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private function allow_ip( $ip ) {
		$key = 'wtlw_ip_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
