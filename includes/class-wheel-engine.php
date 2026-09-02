<?php
/** Secure server-side weighted wheel selection and attempt accounting. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Wheel_Engine {
	/** @var WTLW_Rewards */
	private $rewards;

	public function __construct( WTLW_Rewards $rewards ) {
		$this->rewards = $rewards;
	}

	/** Return sanitized active sections. */
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
				'id'             => isset( $section['id'] ) ? sanitize_key( $section['id'] ) : 'section-' . $index,
				'name'           => isset( $section['name'] ) ? sanitize_text_field( $section['name'] ) : __( 'جایزه', 'webtanan-lucky-wheel' ),
				'type'           => isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing',
				'value'          => isset( $section['value'] ) ? (float) $section['value'] : 0,
				'probability'    => max( 0, (float) ( isset( $section['probability'] ) ? $section['probability'] : 1 ) ),
				'color'          => isset( $section['color'] ) ? sanitize_hex_color( $section['color'] ) : '#0f766e',
				'icon'           => isset( $section['icon'] ) ? sanitize_text_field( $section['icon'] ) : '★',
				'active'         => 1,
				'extra_attempts' => isset( $section['extra_attempts'] ) ? (int) $section['extra_attempts'] : 0,
				'expiry_days'    => isset( $section['expiry_days'] ) ? (int) $section['expiry_days'] : 30,
				'discount_type'  => isset( $section['discount_type'] ) ? sanitize_key( $section['discount_type'] ) : 'fixed_cart',
			);
		}

		return $clean;
	}

	/** Ensure users have attempt metadata. */
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

	/** Return attempts as integers. */
	public function get_attempts( $user_id ) {
		return $this->ensure_user_attempts( $user_id );
	}

	/**
	 * Atomically select and apply a reward.
	 *
	 * @param int    $user_id WordPress user id.
	 * @param string $ip_address Request IP.
	 * @param string $request_id Client-generated idempotency key.
	 * @return array|WP_Error
	 */
	public function spin( $user_id, $ip_address, $request_id ) {
		$user_id = (int) $user_id;
		$this->ensure_user_attempts( $user_id );
		$lock_key = 'wtlw_spin_lock_' . $user_id;
		$locked   = add_option( $lock_key, time(), '', 'no' );
		if ( ! $locked && ( time() - (int) get_option( $lock_key, time() ) ) > 15 ) {
			delete_option( $lock_key );
			$locked = add_option( $lock_key, time(), '', 'no' );
		}
		if ( ! $locked ) {
			return new WP_Error( 'too_fast', __( 'چند لحظه صبر کنید و دوباره تلاش کنید.', 'webtanan-lucky-wheel' ) );
		}

		try {
			if ( ! $request_id || get_transient( 'wtlw_spin_request_' . $user_id . '_' . md5( $request_id ) ) ) {
				return new WP_Error( 'replay', __( 'این درخواست قبلاً پردازش شده است.', 'webtanan-lucky-wheel' ) );
			}
			set_transient( 'wtlw_spin_request_' . $user_id . '_' . md5( $request_id ), 1, DAY_IN_SECONDS );

			$attempts = $this->get_attempts( $user_id );
			if ( $attempts['remaining_attempts'] < 1 ) {
				return new WP_Error( 'no_attempts', __( 'شانس دیگری برای شما باقی نمانده است.', 'webtanan-lucky-wheel' ) );
			}

			$sections = $this->get_sections();
			if ( empty( $sections ) ) {
				return new WP_Error( 'no_rewards', __( 'گردونه در حال حاضر در دسترس نیست.', 'webtanan-lucky-wheel' ) );
			}
			$selected = $this->weighted_pick( $sections );
			$after    = max( 0, $attempts['remaining_attempts'] - 1 );
			update_user_meta( $user_id, 'remaining_attempts', $after );
			$reward = $this->rewards->apply( $selected, $user_id );
			$log_id = WTLW_Database::insert_log(
				array(
					'user_id'         => $user_id,
					'ip_address'      => sanitize_text_field( $ip_address ),
					'reward_id'       => $selected['id'],
					'reward_name'     => $selected['name'],
					'reward_value'    => $selected['value'],
					'coupon_code'     => $reward['coupon_code'],
					'attempts_before' => $attempts['remaining_attempts'],
					'attempts_after'  => (int) get_user_meta( $user_id, 'remaining_attempts', true ),
					'status'          => $reward['status'],
				)
			);

			$index         = array_search( $selected['id'], array_column( $sections, 'id' ), true );
			$segment_angle = 360 / count( $sections );
			$angle         = ( 360 - ( ( (int) $index * $segment_angle ) + ( $segment_angle / 2 ) ) ) + ( 360 * 5 );

			return array(
				'log_id'             => $log_id,
				'reward_id'          => $selected['id'],
				'reward_name'        => $selected['name'],
				'reward_type'        => $selected['type'],
				'reward_value'       => $selected['value'],
				'coupon_code'        => $reward['coupon_code'],
				'angle'              => round( $angle, 2 ),
				'attempts_remaining' => (int) get_user_meta( $user_id, 'remaining_attempts', true ),
			);
		} finally {
			delete_option( $lock_key );
		}
	}

	/** Weighted random selection using cryptographically secure random_int. */
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
