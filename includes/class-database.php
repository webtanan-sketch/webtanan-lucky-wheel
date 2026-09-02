<?php
/** Database schema and persistence helpers. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Database {
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'webtanan_wheel_logs';
	}

	public static function wallet_table() {
		global $wpdb;
		return $wpdb->prefix . 'webtanan_wallet';
	}

	public static function participants_table() {
		global $wpdb;
		return $wpdb->prefix . 'webtanan_wheel_participants';
	}

	private static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate    = $wpdb->get_charset_collate();
		$logs_table         = self::logs_table();
		$wallet_table       = self::wallet_table();
		$participants_table = self::participants_table();

		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			participant_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_address varchar(100) NOT NULL DEFAULT '',
			reward_id varchar(100) NOT NULL DEFAULT '',
			reward_name varchar(255) NOT NULL DEFAULT '',
			reward_value decimal(18,2) NOT NULL DEFAULT 0,
			coupon_code varchar(100) NOT NULL DEFAULT '',
			attempts_before int(11) NOT NULL DEFAULT 0,
			attempts_after int(11) NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'completed',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY participant_id (participant_id),
			KEY created_at (created_at),
			KEY coupon_code (coupon_code)
		) {$charset_collate};";

		$sql_wallet = "CREATE TABLE {$wallet_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			amount decimal(18,2) NOT NULL DEFAULT 0,
			transaction_type varchar(30) NOT NULL DEFAULT 'credit',
			description text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_participants = "CREATE TABLE {$participants_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			initial_attempts int(11) NOT NULL DEFAULT 1,
			remaining_attempts int(11) NOT NULL DEFAULT 1,
			credit_balance decimal(18,2) NOT NULL DEFAULT 0,
			token_hash varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $sql_logs );
		dbDelta( $sql_wallet );
		dbDelta( $sql_participants );
	}

	public static function activate() {
		self::install_schema();
		if ( false === get_option( 'webtanan_lucky_wheel_sections', false ) ) {
			update_option( 'webtanan_lucky_wheel_sections', self::default_sections() );
		}
		if ( false === get_option( 'webtanan_lucky_wheel_title', false ) ) {
			update_option( 'webtanan_lucky_wheel_title', __( 'گردونه شانس و جایزه', 'webtanan-lucky-wheel' ) );
		}
		if ( false === get_option( 'webtanan_lucky_wheel_active', false ) ) {
			update_option( 'webtanan_lucky_wheel_active', 1 );
		}
		if ( false === get_option( 'webtanan_lucky_wheel_default_attempts', false ) ) {
			update_option( 'webtanan_lucky_wheel_default_attempts', 1 );
		}
		if ( false === get_option( 'webtanan_lucky_wheel_colors', false ) && class_exists( 'WTLW_Appearance' ) ) {
			update_option( 'webtanan_lucky_wheel_colors', WTLW_Appearance::defaults() );
		}
		update_option( 'webtanan_lucky_wheel_data_version', defined( 'WTLW_VERSION' ) ? WTLW_VERSION : '1.3.0' );
		add_rewrite_endpoint( 'my-rewards', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade_defaults() {
		$version = (string) get_option( 'webtanan_lucky_wheel_data_version', '1.0.0' );
		if ( version_compare( $version, '1.1.0', '<' ) ) {
			$title = get_option( 'webtanan_lucky_wheel_title', '' );
			if ( in_array( $title, array( 'Spin & Win', 'Spin &amp; Win' ), true ) ) {
				update_option( 'webtanan_lucky_wheel_title', __( 'گردونه شانس و جایزه', 'webtanan-lucky-wheel' ) );
			}
			$sections = get_option( 'webtanan_lucky_wheel_sections', array() );
			if ( is_array( $sections ) ) {
				$name_map = array(
					'600,000 Toman purchase credit' => '۶۰۰ هزار تومان اعتبار خرید',
					'300,000 Toman purchase credit' => '۳۰۰ هزار تومان اعتبار خرید',
					'No prize' => 'این بار جایزه‌ای نیست',
					'2 extra attempts' => '۲ شانس اضافه',
					'1 extra attempt' => '۱ شانس اضافه',
					'500,000 Toman purchase credit' => '۵۰۰ هزار تومان اعتبار خرید',
					'Special reward' => 'هدیه ویژه',
				);
				$changed = false;
				foreach ( $sections as $index => $section ) {
					if ( isset( $section['name'], $name_map[ $section['name'] ] ) ) {
						$sections[ $index ]['name'] = $name_map[ $section['name'] ];
						$changed = true;
					}
				}
				if ( $changed ) {
					update_option( 'webtanan_lucky_wheel_sections', $sections );
				}
			}
		}
		if ( version_compare( $version, '1.3.0', '<' ) ) {
			self::install_schema();
			if ( false === get_option( 'webtanan_lucky_wheel_colors', false ) && class_exists( 'WTLW_Appearance' ) ) {
				update_option( 'webtanan_lucky_wheel_colors', WTLW_Appearance::defaults() );
			}
		}
		if ( version_compare( $version, defined( 'WTLW_VERSION' ) ? WTLW_VERSION : '1.3.0', '<' ) ) {
			update_option( 'webtanan_lucky_wheel_data_version', defined( 'WTLW_VERSION' ) ? WTLW_VERSION : '1.3.0' );
		}
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function default_sections() {
		return array(
			array( 'id' => 'reward-600', 'name' => __( '۶۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ), 'type' => 'coupon', 'value' => 600000, 'probability' => 10, 'color' => '#111111', 'icon' => '🎁', 'active' => 1, 'extra_attempts' => 0, 'expiry_days' => 30, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'reward-300', 'name' => __( '۳۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ), 'type' => 'coupon', 'value' => 300000, 'probability' => 15, 'color' => '#c7a35a', 'icon' => '✨', 'active' => 1, 'extra_attempts' => 0, 'expiry_days' => 30, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'nothing', 'name' => __( 'این بار جایزه‌ای نیست', 'webtanan-lucky-wheel' ), 'type' => 'nothing', 'value' => 0, 'probability' => 35, 'color' => '#2b2b2b', 'icon' => '☘', 'active' => 1, 'extra_attempts' => 0, 'expiry_days' => 0, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'extra-2', 'name' => __( '۲ شانس اضافه', 'webtanan-lucky-wheel' ), 'type' => 'extra_attempts', 'value' => 2, 'probability' => 12, 'color' => '#9a7b3f', 'icon' => '↻', 'active' => 1, 'extra_attempts' => 2, 'expiry_days' => 0, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'extra-1', 'name' => __( '۱ شانس اضافه', 'webtanan-lucky-wheel' ), 'type' => 'extra_attempts', 'value' => 1, 'probability' => 13, 'color' => '#1c1c1c', 'icon' => '+', 'active' => 1, 'extra_attempts' => 1, 'expiry_days' => 0, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'reward-500', 'name' => __( '۵۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ), 'type' => 'coupon', 'value' => 500000, 'probability' => 10, 'color' => '#e0c47a', 'icon' => '🛍', 'active' => 1, 'extra_attempts' => 0, 'expiry_days' => 30, 'discount_type' => 'fixed_cart' ),
			array( 'id' => 'custom', 'name' => __( 'هدیه ویژه', 'webtanan-lucky-wheel' ), 'type' => 'wallet', 'value' => 100000, 'probability' => 5, 'color' => '#3a3121', 'icon' => '★', 'active' => 1, 'extra_attempts' => 0, 'expiry_days' => 0, 'discount_type' => 'fixed_cart' ),
		);
	}

	public static function create_participant_session( $name, $phone ) {
		global $wpdb;
		$table = self::participants_table();
		$now   = current_time( 'mysql', true );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s LIMIT 1", $phone ) );
		$token = wp_generate_password( 48, false, false );
		$hash  = wp_hash_password( $token );
		if ( $row ) {
			$wpdb->update( $table, array( 'name' => $name, 'token_hash' => $hash, 'updated_at' => $now ), array( 'id' => (int) $row->id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			$row = self::get_participant( (int) $row->id );
		} else {
			$initial = max( 0, (int) get_option( 'webtanan_lucky_wheel_default_attempts', 1 ) );
			$wpdb->insert(
				$table,
				array( 'name' => $name, 'phone' => $phone, 'initial_attempts' => $initial, 'remaining_attempts' => $initial, 'credit_balance' => 0, 'token_hash' => $hash, 'created_at' => $now, 'updated_at' => $now ),
				array( '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s' )
			);
			$row = self::get_participant( (int) $wpdb->insert_id );
		}
		if ( ! $row ) {
			return new WP_Error( 'participant_failed', __( 'ثبت اطلاعات شرکت‌کننده انجام نشد.', 'webtanan-lucky-wheel' ) );
		}
		return array( 'id' => (int) $row->id, 'name' => $row->name, 'phone' => $row->phone, 'initial_attempts' => (int) $row->initial_attempts, 'remaining_attempts' => (int) $row->remaining_attempts, 'credit_balance' => (float) $row->credit_balance, 'token' => $token );
	}

	public static function get_participant( $participant_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::participants_table() . ' WHERE id = %d LIMIT 1', (int) $participant_id ) );
	}

	public static function authenticate_participant( $participant_id, $token ) {
		$row = self::get_participant( $participant_id );
		return ( $row && $token && $row->token_hash && wp_check_password( $token, $row->token_hash ) ) ? $row : false;
	}

	public static function set_participant_attempts( $participant_id, $remaining ) {
		global $wpdb;
		return false !== $wpdb->update( self::participants_table(), array( 'remaining_attempts' => max( 0, (int) $remaining ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $participant_id ), array( '%d', '%s' ), array( '%d' ) );
	}

	public static function add_participant_attempts( $participant_id, $amount ) {
		global $wpdb;
		$amount = max( 0, (int) $amount );
		if ( 0 === $amount ) {
			return true;
		}
		$table = self::participants_table();
		return false !== $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET remaining_attempts = remaining_attempts + %d, updated_at = %s WHERE id = %d", $amount, current_time( 'mysql', true ), (int) $participant_id ) );
	}

	public static function credit_participant( $participant_id, $amount ) {
		global $wpdb;
		$amount = max( 0, (float) $amount );
		if ( $amount <= 0 ) {
			return false;
		}
		$table = self::participants_table();
		return false !== $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET credit_balance = credit_balance + %f, updated_at = %s WHERE id = %d", $amount, current_time( 'mysql', true ), (int) $participant_id ) );
	}

	public static function insert_log( $data ) {
		global $wpdb;
		$defaults = array( 'user_id' => 0, 'participant_id' => 0, 'ip_address' => '', 'reward_id' => '', 'reward_name' => '', 'reward_value' => 0, 'coupon_code' => '', 'attempts_before' => 0, 'attempts_after' => 0, 'status' => 'completed', 'created_at' => current_time( 'mysql', true ) );
		$data = wp_parse_args( $data, $defaults );
		$wpdb->insert( self::logs_table(), $data, array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	public static function get_user_coupons( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' WHERE user_id = %d AND coupon_code <> %s ORDER BY created_at DESC', $user_id, '' ), ARRAY_A );
	}

	public static function stats() {
		global $wpdb;
		$logs = self::logs_table();
		$participants = self::participants_table();
		$legacy_users = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$logs} WHERE user_id > 0" );
		$guest_users  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$participants}" );
		return array(
			'spins' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs}" ),
			'users' => $legacy_users + $guest_users,
			'rewards' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(reward_value), 0) FROM {$logs} WHERE status = 'completed'" ),
			'today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) ),
		);
	}

	public static function get_history( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$limit = max( 1, min( 500, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		$logs = self::logs_table();
		$participants = self::participants_table();
		$sql = "SELECT l.*, p.name AS participant_name, p.phone AS participant_phone, p.remaining_attempts AS participant_remaining, p.credit_balance AS participant_credit FROM {$logs} l LEFT JOIN {$participants} p ON p.id = l.participant_id ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $limit, $offset ) );
	}
}
