<?php
/** Reward orchestration: coupon, wallet and extra-attempt outcomes. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Rewards {
	/** @var WTLW_Wallet */
	private $wallet;
	/** @var WTLW_WooCommerce */
	private $woocommerce;

	public function __construct( WTLW_Wallet $wallet, WTLW_WooCommerce $woocommerce ) {
		$this->wallet      = $wallet;
		$this->woocommerce = $woocommerce;
	}

	/** Apply a selected section to the user and return public result data. */
	public function apply( array $section, $user_id ) {
		$type          = isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing';
		$value         = isset( $section['value'] ) ? (float) $section['value'] : 0;
		$coupon_code   = '';
		$status        = 'completed';
		$extra_attempts = isset( $section['extra_attempts'] ) ? (int) $section['extra_attempts'] : 0;

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
					isset( $section['expiry_days'] ) ? (int) $section['expiry_days'] : 30
				);
				if ( ! $coupon_code ) {
					$status = 'failed';
				}
			} else {
				$this->wallet->credit( $user_id, $value, sprintf( __( 'Lucky Wheel: %s', 'webtanan-lucky-wheel' ), $section['name'] ) );
			}
		} elseif ( 'wallet' === $type && $value > 0 ) {
			$this->wallet->credit( $user_id, $value, sprintf( __( 'Lucky Wheel: %s', 'webtanan-lucky-wheel' ), $section['name'] ) );
		}

		return array(
			'coupon_code' => $coupon_code,
			'status'      => $status,
			'value'       => $value,
		);
	}
}
