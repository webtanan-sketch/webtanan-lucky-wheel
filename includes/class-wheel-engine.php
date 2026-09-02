<?php
/** Secure server-side weighted wheel selection and attempt accounting. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Wheel_Engine {
	private $rewards;

	public function __construct( WTLW_Rewards $rewards ) {
		$this->rewards = $rewards;
	}

	public function get_sections() {
		$sections = get_option( 'webtanan_lucky_wheel_sections', WTLW_Database::default_sections() );
		if ( ! is_array( $sections ) ) {
			$sections = WTLW_Database::default_sections();
		}
		$clean = array();
		foreach ( array_values( $sections ) as $index => $section ) {
			if ( ! is_array( $section ) || empty( $section['active'] ) ) {
				continue;
			}
			$clean[] = array(
				'id' => isset( $section['id'] ) ? sanitize_key( $section['id'] ) : 'section-' . $index,
				'name' => isset( $section['name'] ) ? sanitize_text_field( $section['name'] ) : __( 'جایزه', 'webtanan-lucky-wheel' ),
				'type' => isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing',
				'value' => isset( $section['value'] ) ? (float) $section['value'] : 0,
				'probability' => max( 0, (float) ( isset( $section['probability'] ) ? $section['probability'] : 1 ) ),
				'color' => isset( $section['color'] ) && sanitize_hex_color( $section['color'] ) ? sanitize_hex_color( $section['color'] ) : '#111111',
				'icon' => isset( $section['icon'] ) ? sanitize_text_field( $section['icon'] ) : '★',
				'active' => 1,
				'extra_attempts' => isset( $section['extra_attempts'] ) ? (int) $section['extra_attempts'] : 0,
				'expiry_days' => isset( $section['expiry_days'] ) ? (int) $section['expiry_days'] : 30,
				'discount_type' => isset( $section['discount_type'] ) ? sanitize_key( $section['discount_type'] ) : 'fixed_cart',
			);
		}
		return $clean;
	}

	public function ensure_user_attempts( $user_id ) {
		$initial = get_user_meta( $user_id, 'initial_attempts', true );
		if ( '' === $initial ) {
			$initial = max( 0, (int) get_option( 'webtanan_lucky_wheel_default_attempts', 1 ) );
			update_user_meta( $user_id, 'initial_attempts', $initial );
		}
		$remaining = get_user_meta( $user_id, 'remaining_attempts', true );
		if ( '' === $remaining ) {
			update_user_meta( $user_id, 'remaining_attempts', $initial );
			$remaining = $initial;
		}
		return array( 'initial_attempts' => (int) $initial, 'remaining_attempts' => (int) $remaining );
	}

	public function get_attempts( $user_id ) {
		return $this->ensure_user_attempts( $user_id );
	}

	public function spin_guest( $participant_id, $participant_token, $ip_address, $request_id ) {
		$participant_id = (int) $participant_id;
		$participant = WTLW_Database::authenticate_participant( $participant_id, $participant_token );
		if ( ! $participant ) {
			return new WP_Error( 'invalid_participant', __( 'نشست قرعه‌کشی معتبر نیست. نام و شماره موبایل را دوباره وارد کنید.', 'webtanan-lucky-wheel' ) );
		}

		$lock_key = 'wtlw_guest_spin_lock_' . $participant_id;
		$locked = add_option( $lock_key, time(), '', 'no' );
		if ( ! $locked && ( time() - (int) get_option( $lock_key, time() ) ) > 15 ) {
			delete_option( $lock_key );
			$locked = add_option( $lock_key, time(), '', 'no' );
		}
		if ( ! $locked ) {
			return new WP_Error( 'too_fast', __( 'چند لحظه صبر کنید و دوباره تلاش کنید.', 'webtanan-lucky-wheel' ) );
		}

		try {
			$request_key = 'wtlw_guest_request_' . $participant_id . '_' . md5( $request_id );
			if ( ! $request_id || get_transient( $request_key ) ) {
				return new WP_Error( 'replay', __( 'این درخواست قبلاً پردازش شده است.', 'webtanan-lucky-wheel' ) );
			}
			set_transient( $request_key, 1, DAY_IN_SECONDS );

			$participant = WTLW_Database::get_participant( $participant_id );
			$before = $participant ? (int) $participant->remaining_attempts : 0;
			if ( $before < 1 ) {
				return new WP_Error( 'no_attempts', __( 'شانس دیگری برای این شماره موبایل باقی نمانده است.', 'webtanan-lucky-wheel' ) );
			}
			$sections = $this->get_sections();
			if ( empty( $sections ) ) {
				return new WP_Error( 'no_rewards', __( 'گردونه در حال حاضر در دسترس نیست.', 'webtanan-lucky-wheel' ) );
			}

			$selected = $this->weighted_pick( $sections );
			WTLW_Database::set_participant_attempts( $participant_id, $before - 1 );
			$reward = $this->rewards->apply_guest( $selected, $participant_id );
			$participant_after = WTLW_Database::get_participant( $participant_id );
			$after = $participant_after ? (int) $participant_after->remaining_attempts : max( 0, $before - 1 );

			$log_id = WTLW_Database::insert_log(
				array(
					'user_id' => 0,
					'participant_id' => $participant_id,
					'ip_address' => sanitize_text_field( $ip_address ),
					'reward_id' => $selected['id'],
					'reward_name' => $selected['name'],
					'reward_value' => $selected['value'],
					'coupon_code' => $reward['coupon_code'],
					'attempts_before' => $before,
					'attempts_after' => $after,
					'status' => $reward['status'],
				)
			);

			$index = array_search( $selected['id'], array_column( $sections, 'id' ), true );
			$segment_angle = 360 / count( $sections );
			$segment_center = ( (int) $index * $segment_angle ) + ( $segment_angle / 2 );
			$target_angle = fmod( 360 - $segment_center + 360, 360 );
			$legacy_angle = $target_angle + ( 360 * 5 );

			return array(
				'log_id' => $log_id,
				'reward_id' => $selected['id'],
				'reward_name' => $selected['name'],
				'reward_type' => $selected['type'],
				'reward_value' => $selected['value'],
				'coupon_code' => $reward['coupon_code'],
				'angle' => round( $legacy_angle, 4 ),
				'target_angle' => round( $target_angle, 4 ),
				'attempts_remaining' => $after,
				'credit_balance' => $participant_after ? (float) $participant_after->credit_balance : 0,
			);
		} finally {
			delete_option( $lock_key );
		}
	}

	private function weighted_pick( array $sections ) {
		$total = array_sum( array_column( $sections, 'probability' ) );
		if ( $total <= 0 ) {
			return $sections[ random_int( 0, count( $sections ) - 1 ) ];
		}
		$needle = random_int( 1, 1000000 ) / 1000000 * $total;
		$cursor = 0;
		foreach ( $sections as $section ) {
			$cursor += $section['probability'];
			if ( $needle <= $cursor ) {
				return $section;
			}
		}
		return end( $sections );
	}
}
