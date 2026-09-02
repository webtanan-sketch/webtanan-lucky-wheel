<?php
/** WooCommerce coupon integration. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_WooCommerce {
	/** Whether WooCommerce is available. */
	public function is_available() {
		return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_coupon_id_by_code' );
	}

	/** Create a one-use coupon restricted to a user. */
	public function create_coupon( $user_id, $amount, $discount_type = 'fixed_cart', $expiry_days = 30 ) {
		if ( ! $this->is_available() || ! function_exists( 'wp_insert_post' ) ) {
			return '';
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user ) {
			return '';
		}

		$code = 'WTLW-' . strtoupper( wp_generate_password( 10, false, false ) );
		$post_id = wp_insert_post(
			array(
				'post_title'   => $code,
				'post_content' => __( 'Webtanan Lucky Wheel reward', 'webtanan-lucky-wheel' ),
				'post_status'  => 'publish',
				'post_author'  => (int) $user_id,
				'post_type'    => 'shop_coupon',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return '';
		}

		$discount_type = in_array( $discount_type, array( 'fixed_cart', 'percent' ), true ) ? $discount_type : 'fixed_cart';
		update_post_meta( $post_id, 'discount_type', $discount_type );
		$formatted_amount = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $amount ) : number_format( (float) $amount, 2, '.', '' );
		update_post_meta( $post_id, 'coupon_amount', $formatted_amount );
		update_post_meta( $post_id, 'usage_limit', 1 );
		update_post_meta( $post_id, 'usage_limit_per_user', 1 );
		update_post_meta( $post_id, 'individual_use', 'yes' );
		update_post_meta( $post_id, '_wtlw_user_id', (int) $user_id );
		update_post_meta( $post_id, 'email_restrictions', array( $user->user_email ) );

		if ( (int) $expiry_days > 0 ) {
			$expires = time() + ( DAY_IN_SECONDS * (int) $expiry_days );
			update_post_meta( $post_id, 'date_expires', gmdate( 'Y-m-d', $expires ) );
		}

		return $code;
	}

	/** Enforce user ownership on every coupon validation. */
	public function register_hooks() {
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_owner' ), 10, 3 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'mark_coupon_used' ) );
	}

	/** @param bool $valid Coupon validity. */
	public function validate_coupon_owner( $valid, $coupon, $discount ) {
		if ( ! $valid || ! is_user_logged_in() || ! is_object( $coupon ) ) {
			return $valid;
		}
		$owner_id = (int) get_post_meta( $coupon->get_id(), '_wtlw_user_id', true );
		return $owner_id && $owner_id !== get_current_user_id() ? false : $valid;
	}

	/** Mark a reward as used in the campaign history. */
	public function mark_coupon_used( $code ) {
		global $wpdb;
		$wpdb->update(
			WTLW_Database::logs_table(),
			array( 'status' => 'used' ),
			array( 'coupon_code' => sanitize_text_field( $code ) ),
			array( '%s' ),
			array( '%s' )
		);
	}
}
