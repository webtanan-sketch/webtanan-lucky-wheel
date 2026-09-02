<?php
/** WooCommerce coupon integration. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_WooCommerce {
	public function is_available() {
		return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_coupon_id_by_code' );
	}

	public function create_coupon( $user_id, $amount, $discount_type = 'fixed_cart', $expiry_days = 30 ) {
		if ( ! $this->is_available() || ! function_exists( 'wp_insert_post' ) ) {
			return '';
		}
		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user ) {
			return '';
		}
		$post_id = $this->insert_coupon_post( $amount, $discount_type, $expiry_days, (int) $user_id );
		if ( ! $post_id ) {
			return '';
		}
		$code = get_the_title( $post_id );
		update_post_meta( $post_id, '_wtlw_user_id', (int) $user_id );
		update_post_meta( $post_id, 'email_restrictions', array( $user->user_email ) );
		return $code;
	}

	public function create_guest_coupon( $participant_id, $amount, $discount_type = 'fixed_cart', $expiry_days = 30 ) {
		if ( ! $this->is_available() || ! function_exists( 'wp_insert_post' ) ) {
			return '';
		}
		$participant = WTLW_Database::get_participant( $participant_id );
		if ( ! $participant ) {
			return '';
		}
		$post_id = $this->insert_coupon_post( $amount, $discount_type, $expiry_days, 0 );
		if ( ! $post_id ) {
			return '';
		}
		update_post_meta( $post_id, '_wtlw_participant_id', (int) $participant_id );
		return get_the_title( $post_id );
	}

	private function insert_coupon_post( $amount, $discount_type, $expiry_days, $author_id ) {
		$code = 'WTLW-' . strtoupper( wp_generate_password( 10, false, false ) );
		$post_id = wp_insert_post(
			array(
				'post_title' => $code,
				'post_content' => __( 'جایزه گردونه شانس وب‌تنان', 'webtanan-lucky-wheel' ),
				'post_status' => 'publish',
				'post_author' => (int) $author_id,
				'post_type' => 'shop_coupon',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		$discount_type = in_array( $discount_type, array( 'fixed_cart', 'percent' ), true ) ? $discount_type : 'fixed_cart';
		update_post_meta( $post_id, 'discount_type', $discount_type );
		$formatted_amount = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $amount ) : number_format( (float) $amount, 2, '.', '' );
		update_post_meta( $post_id, 'coupon_amount', $formatted_amount );
		update_post_meta( $post_id, 'usage_limit', 1 );
		update_post_meta( $post_id, 'usage_limit_per_user', 1 );
		update_post_meta( $post_id, 'individual_use', 'yes' );
		if ( (int) $expiry_days > 0 ) {
			$expires = time() + ( DAY_IN_SECONDS * (int) $expiry_days );
			update_post_meta( $post_id, 'date_expires', gmdate( 'Y-m-d', $expires ) );
		}
		return (int) $post_id;
	}

	public function register_hooks() {
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_owner' ), 10, 3 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'mark_coupon_used' ) );
	}

	public function validate_coupon_owner( $valid, $coupon, $discount ) {
		if ( ! $valid || ! is_object( $coupon ) ) {
			return $valid;
		}
		$owner_id = (int) get_post_meta( $coupon->get_id(), '_wtlw_user_id', true );
		if ( $owner_id > 0 ) {
			return is_user_logged_in() && $owner_id === get_current_user_id();
		}
		return $valid;
	}

	public function mark_coupon_used( $code ) {
		global $wpdb;
		$wpdb->update( WTLW_Database::logs_table(), array( 'status' => 'used' ), array( 'coupon_code' => sanitize_text_field( $code ) ), array( '%s' ), array( '%s' ) );
	}
}
