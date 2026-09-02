<?php
/** Database schema and persistence helpers. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Database {
	/** Return the wheel log table name. */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'webtanan_wheel_logs';
	}

	/** Return the wallet table name. */
	public static function wallet_table() {
		global $wpdb;
		return $wpdb->prefix . 'webtanan_wallet';
	}

	/** Create custom tables and seed default settings. */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$logs_table      = self::logs_table();
		$wallet_table    = self::wallet_table();

		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
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

		dbDelta( $sql_logs );
		dbDelta( $sql_wallet );

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
		update_option( 'webtanan_lucky_wheel_data_version', defined( 'WTLW_VERSION' ) ? WTLW_VERSION : '1.1.0' );

		// Register the endpoint before flushing so the first request works immediately.
		add_rewrite_endpoint( 'my-rewards', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/** Safely migrate only untouched English defaults from version 1.0.0. */
	public static function maybe_upgrade_defaults() {
		$version = (string) get_option( 'webtanan_lucky_wheel_data_version', '1.0.0' );
		if ( version_compare( $version, '1.1.0', '>=' ) ) {
			return;
		}

		$title = get_option( 'webtanan_lucky_wheel_title', '' );
		if ( in_array( $title, array( 'Spin & Win', 'Spin &amp; Win' ), true ) ) {
			update_option( 'webtanan_lucky_wheel_title', __( 'گردونه شانس و جایزه', 'webtanan-lucky-wheel' ) );
		}

		$sections = get_option( 'webtanan_lucky_wheel_sections', array() );
		if ( is_array( $sections ) ) {
			$name_map = array(
				'600,000 Toman purchase credit' => '۶۰۰ هزار تومان اعتبار خرید',
				'300,000 Toman purchase credit' => '۳۰۰ هزار تومان اعتبار خرید',
				'No prize'                       => 'این بار جایزه‌ای نیست',
				'2 extra attempts'                => '۲ شانس اضافه',
				'1 extra attempt'                 => '۱ شانس اضافه',
				'500,000 Toman purchase credit' => '۵۰۰ هزار تومان اعتبار خرید',
				'Special reward'                  => 'هدیه ویژه',
			);
			$icon_map = array(
				'reward-600' => '🎁',
				'reward-300' => '✨',
				'nothing'    => '☘',
				'extra-2'    => '↻',
				'extra-1'    => '+',
				'reward-500' => '🛍',
				'custom'     => '★',
			);
			$changed = false;
			foreach ( $sections as $index => $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				if ( isset( $section['name'], $name_map[ $section['name'] ] ) ) {
					$sections[ $index ]['name'] = $name_map[ $section['name'] ];
					$changed = true;
				}
				$id = isset( $section['id'] ) ? $section['id'] : '';
				if ( isset( $icon_map[ $id ] ) && isset( $section['icon'] ) && in_array( $section['icon'], array( 'D', 'G', '?', '↻', '+', 'W', '★' ), true ) ) {
					$sections[ $index ]['icon'] = $icon_map[ $id ];
					$changed = true;
				}
			}
			if ( $changed ) {
				update_option( 'webtanan_lucky_wheel_sections', $sections );
			}
		}

		update_option( 'webtanan_lucky_wheel_data_version', defined( 'WTLW_VERSION' ) ? WTLW_VERSION : '1.1.0' );
	}

	/** Flush endpoint rules on deactivation without removing user data. */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/** Default seven-segment Persian campaign. */
	public static function default_sections() {
		return array(
			array(
				'id'             => 'reward-600',
				'name'           => __( '۶۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ),
				'type'           => 'coupon',
				'value'          => 600000,
				'probability'    => 10,
				'color'          => '#0f766e',
				'icon'           => '🎁',
				'active'         => 1,
				'extra_attempts' => 0,
				'expiry_days'    => 30,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'reward-300',
				'name'           => __( '۳۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ),
				'type'           => 'coupon',
				'value'          => 300000,
				'probability'    => 15,
				'color'          => '#0b4f6c',
				'icon'           => '✨',
				'active'         => 1,
				'extra_attempts' => 0,
				'expiry_days'    => 30,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'nothing',
				'name'           => __( 'این بار جایزه‌ای نیست', 'webtanan-lucky-wheel' ),
				'type'           => 'nothing',
				'value'          => 0,
				'probability'    => 35,
				'color'          => '#7a3e2d',
				'icon'           => '☘',
				'active'         => 1,
				'extra_attempts' => 0,
				'expiry_days'    => 0,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'extra-2',
				'name'           => __( '۲ شانس اضافه', 'webtanan-lucky-wheel' ),
				'type'           => 'extra_attempts',
				'value'          => 2,
				'probability'    => 12,
				'color'          => '#2a9d8f',
				'icon'           => '↻',
				'active'         => 1,
				'extra_attempts' => 2,
				'expiry_days'    => 0,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'extra-1',
				'name'           => __( '۱ شانس اضافه', 'webtanan-lucky-wheel' ),
				'type'           => 'extra_attempts',
				'value'          => 1,
				'probability'    => 13,
				'color'          => '#e9c46a',
				'icon'           => '+',
				'active'         => 1,
				'extra_attempts' => 1,
				'expiry_days'    => 0,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'reward-500',
				'name'           => __( '۵۰۰ هزار تومان اعتبار خرید', 'webtanan-lucky-wheel' ),
				'type'           => 'coupon',
				'value'          => 500000,
				'probability'    => 10,
				'color'          => '#c58b2a',
				'icon'           => '🛍',
				'active'         => 1,
				'extra_attempts' => 0,
				'expiry_days'    => 30,
				'discount_type'  => 'fixed_cart',
			),
			array(
				'id'             => 'custom',
				'name'           => __( 'هدیه ویژه', 'webtanan-lucky-wheel' ),
				'type'           => 'wallet',
				'value'          => 100000,
				'probability'    => 5,
				'color'          => '#b83244',
				'icon'           => '★',
				'active'         => 1,
				'extra_attempts' => 0,
				'expiry_days'    => 0,
				'discount_type'  => 'fixed_cart',
			),
		);
	}

	/** Insert a spin log and return the inserted id. */
	public static function insert_log( $data ) {
		global $wpdb;
		$defaults = array(
			'user_id'         => 0,
			'ip_address'      => '',
			'reward_id'       => '',
			'reward_name'     => '',
			'reward_value'    => 0,
			'coupon_code'     => '',
			'attempts_before' => 0,
			'attempts_after'  => 0,
			'status'          => 'completed',
			'created_at'      => current_time( 'mysql', true ),
		);
		$data = wp_parse_args( $data, $defaults );
		$wpdb->insert(
			self::logs_table(),
			$data,
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** Return coupons issued by this plugin to a user. */
	public static function get_user_coupons( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::logs_table() . ' WHERE user_id = %d AND coupon_code <> %s ORDER BY created_at DESC',
				$user_id,
				''
			),
			ARRAY_A
		);
	}

	/** Dashboard aggregates. */
	public static function stats() {
		global $wpdb;
		$table = self::logs_table();
		return array(
			'spins'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'users'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" ),
			'rewards' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(reward_value), 0) FROM {$table} WHERE status = 'completed'" ),
			'today'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) ),
		);
	}

	/** Paginated history for the admin screen. */
	public static function get_history( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$limit  = max( 1, min( 500, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' ORDER BY created_at DESC LIMIT %d OFFSET %d', $limit, $offset ) );
	}
}
