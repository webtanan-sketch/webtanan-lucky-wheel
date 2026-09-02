<?php
/** Reward orchestration: coupon, credit and extra-attempt outcomes. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Rewards {
	private $wallet;
	private $woocommerce;
	private $sms;

	public function __construct( WTLW_Wallet $wallet, WTLW_WooCommerce $woocommerce, WTLW_SMS $sms ) {
		$this->wallet      = $wallet;
		$this->woocommerce = $woocommerce;
		$this->sms         = $sms;
	}

	public function apply( array $section, $user_id ) {
		$type           = isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing';
		$value          = isset( $section['value'] ) ? (float) $section['value'] : 0;
		$coupon_code    = '';
		$status         = 'completed';
		$sms_sent       = false;
		$extra_attempts = isset( $section['extra_attempts'] ) ? (int) $section['extra_attempts'] : 0;
		$expiry_days    = isset( $section['expiry_days'] ) ? max( 0, (int) $section['expiry_days'] ) : 30;

		if ( 'extra_attempts' === $type || $extra_attempts > 0 ) {
			$attempts = (int) get_user_meta( $user_id, 'remaining_attempts', true );
			$attempts = max( 0, $attempts ) + max( 0, $extra_attempts ? $extra_attempts : (int) $value );
			update_user_meta( $user_id, 'remaining_attempts', $attempts );
		} elseif ( 'coupon' === $type && $value > 0 ) {
			if ( $this->woocommerce->is_available() ) {
				$coupon_code = $this->woocommerce->create_coupon(
					$user_id,
					$value,
					isset( $section['discount_type'] ) ? $section['discount_type'] : 'fixed_cart',
					$expiry_days
				);

				if ( ! $coupon_code ) {
					$status = 'failed';
				} else {
					$sms_sent = $this->queue_sms(
						'wtlw_send_user_coupon_sms',
						array(
							(int) $user_id,
							$coupon_code,
							$expiry_days,
							isset( $section['name'] ) ? $section['name'] : '',
						)
					);
				}
			} else {
				$this->wallet->credit( $user_id, $value, sprintf( __( 'گردونه شانس: %s', 'webtanan-lucky-wheel' ), $section['name'] ) );
			}
		} elseif ( 'wallet' === $type && $value > 0 ) {
			$this->wallet->credit( $user_id, $value, sprintf( __( 'گردونه شانس: %s', 'webtanan-lucky-wheel' ), $section['name'] ) );
		}

		return array(
			'coupon_code' => $coupon_code,
			'status'      => $status,
			'value'       => $value,
			'sms_sent'    => $sms_sent,
		);
	}

	public function apply_guest( array $section, $participant_id ) {
		$type           = isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing';
		$value          = isset( $section['value'] ) ? (float) $section['value'] : 0;
		$coupon_code    = '';
		$status         = 'completed';
		$sms_sent       = false;
		$extra_attempts = isset( $section['extra_attempts'] ) ? (int) $section['extra_attempts'] : 0;
		$expiry_days    = isset( $section['expiry_days'] ) ? max( 0, (int) $section['expiry_days'] ) : 30;

		if ( 'extra_attempts' === $type || $extra_attempts > 0 ) {
			WTLW_Database::add_participant_attempts( $participant_id, $extra_attempts ? $extra_attempts : (int) $value );
		} elseif ( 'coupon' === $type && $value > 0 ) {
			if ( $this->woocommerce->is_available() ) {
				$coupon_code = $this->woocommerce->create_guest_coupon(
					$participant_id,
					$value,
					isset( $section['discount_type'] ) ? $section['discount_type'] : 'fixed_cart',
					$expiry_days
				);

				if ( ! $coupon_code ) {
					$status = 'failed';
				} else {
					$sms_sent = $this->queue_sms(
						'wtlw_send_participant_coupon_sms',
						array(
							(int) $participant_id,
							$coupon_code,
							$expiry_days,
							isset( $section['name'] ) ? $section['name'] : '',
						)
					);
				}
			} else {
				WTLW_Database::credit_participant( $participant_id, $value );
			}
		} elseif ( 'wallet' === $type && $value > 0 ) {
			WTLW_Database::credit_participant( $participant_id, $value );
		}

		return array(
			'coupon_code' => $coupon_code,
			'status'      => $status,
			'value'       => $value,
			'sms_sent'    => $sms_sent,
		);
	}

	/**
	 * Queue external SMS delivery instead of blocking the spin AJAX response.
	 * WooCommerce Action Scheduler is preferred when available; WP-Cron is the fallback.
	 */
	private function queue_sms( $hook, array $args ) {
		$settings = WTLW_SMS::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( $hook, $args, 'webtanan-lucky-wheel' );
			return ! empty( $action_id );
		}

		return (bool) wp_schedule_single_event( time() + 1, $hook, $args );
	}
}
