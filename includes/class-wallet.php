<?php
/** Lightweight internal wallet fallback for installations without WooCommerce. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Wallet {
	/** Credit a user and return transaction id. */
	public function credit( $user_id, $amount, $description ) {
		global $wpdb;
		$amount = (float) $amount;
		if ( $amount <= 0 || ! get_user_by( 'id', (int) $user_id ) ) {
			return 0;
		}

		$wpdb->insert(
			WTLW_Database::wallet_table(),
			array(
				'user_id'          => (int) $user_id,
				'amount'           => $amount,
				'transaction_type' => 'credit',
				'description'      => sanitize_text_field( $description ),
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%f', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/** Debit a user when a merchant integration needs it. */
	public function debit( $user_id, $amount, $description ) {
		$amount = (float) $amount;
		if ( $amount <= 0 || $this->get_balance( $user_id ) < $amount ) {
			return 0;
		}

		global $wpdb;
		$wpdb->insert(
			WTLW_Database::wallet_table(),
			array(
				'user_id'          => (int) $user_id,
				'amount'           => -$amount,
				'transaction_type' => 'debit',
				'description'      => sanitize_text_field( $description ),
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%f', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/** Calculate current balance from an immutable transaction ledger. */
	public function get_balance( $user_id ) {
		global $wpdb;
		$balance = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount), 0) FROM ' . WTLW_Database::wallet_table() . ' WHERE user_id = %d', (int) $user_id ) );
		return (float) $balance;
	}

	/** Return user wallet history. */
	public function get_transactions( $user_id, $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . WTLW_Database::wallet_table() . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT %d',
				(int) $user_id,
				max( 1, min( 500, (int) $limit ) )
			),
			ARRAY_A
		);
	}
}
