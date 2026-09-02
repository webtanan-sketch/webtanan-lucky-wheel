<?php
/**
 * Main plugin service container and WordPress integration points.
 *
 * @package WebtananLuckyWheel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Plugin {
	private static $instance = null;

	public $engine;
	public $rewards;
	public $wallet;
	public $woocommerce;
	public $sms;
	public $ajax;
	public $admin;
	public $shortcode;
	public $interface_settings;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Core services are lightweight and shared by front-end, AJAX, cron and admin contexts.
		$this->wallet      = new WTLW_Wallet();
		$this->woocommerce = new WTLW_WooCommerce();
		$this->sms         = new WTLW_SMS();
		$this->rewards     = new WTLW_Rewards( $this->wallet, $this->woocommerce, $this->sms );
		$this->engine      = new WTLW_Wheel_Engine( $this->rewards );

		// Context-aware boot: do not construct admin or front-end controllers when they are not needed.
		if ( wp_doing_ajax() && class_exists( 'WTLW_Ajax' ) ) {
			$this->ajax = new WTLW_Ajax( $this->engine );
		} elseif ( is_admin() ) {
			if ( class_exists( 'WTLW_Admin' ) ) {
				$this->admin = new WTLW_Admin( $this->engine );
			}
			if ( class_exists( 'WTLW_Appearance' ) ) {
				new WTLW_Appearance();
			}
			if ( class_exists( 'WTLW_UX_Settings' ) ) {
				$this->interface_settings = new WTLW_UX_Settings();
			}
		} elseif ( ! wp_doing_cron() && class_exists( 'WTLW_Shortcode' ) ) {
			$this->shortcode = new WTLW_Shortcode( $this->engine );
		}

		$this->woocommerce->register_hooks();

		// SMS delivery is queued outside the spin request so external API latency never blocks the wheel.
		add_action( 'wtlw_send_user_coupon_sms', array( $this->sms, 'send_user_coupon' ), 10, 4 );
		add_action( 'wtlw_send_participant_coupon_sms', array( $this->sms, 'send_participant_coupon' ), 10, 4 );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'WTLW_Database', 'maybe_upgrade_defaults' ), 1 );
		add_action( 'init', array( $this, 'ensure_daily_reset_schedule' ), 1 );
		add_action( 'init', array( 'WTLW_Database', 'maybe_daily_reset_attempts' ), 2 );
		add_action( 'wtlw_daily_reset_attempts', array( 'WTLW_Database', 'maybe_daily_reset_attempts' ) );
		add_action( 'init', array( $this, 'register_account_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'woocommerce_account_my-rewards_endpoint', array( $this, 'render_rewards_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_filter( 'the_content', array( $this, 'maybe_render_non_wc_endpoint' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'webtanan-lucky-wheel', false, dirname( plugin_basename( WTLW_FILE ) ) . '/languages' );
	}

	/** Make sure upgrades get the daily reset cron even without plugin reactivation. */
	public function ensure_daily_reset_schedule() {
		if ( wp_next_scheduled( 'wtlw_daily_reset_attempts' ) ) {
			return;
		}

		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		$next = $now->modify( 'tomorrow' )->setTime( 0, 5 );
		wp_schedule_event( $next->getTimestamp(), 'daily', 'wtlw_daily_reset_attempts' );
	}

	public function register_account_endpoint() {
		add_rewrite_endpoint( 'my-rewards', EP_ROOT | EP_PAGES );
	}

	public function register_query_var( $vars ) {
		$vars[] = 'my-rewards';
		return $vars;
	}

	public function render_rewards_endpoint() {
		$this->render_rewards_content();
	}

	public function account_menu_items( $items ) {
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
		if ( null !== $logout ) {
			unset( $items['customer-logout'] );
		}
		$items['my-rewards'] = __( 'جوایز من', 'webtanan-lucky-wheel' );
		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	public function render_rewards_content() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id      = get_current_user_id();
		$balance      = $this->wallet->get_balance( $user_id );
		$transactions = $this->wallet->get_transactions( $user_id );
		$coupons      = WTLW_Database::get_user_coupons( $user_id );

		wp_enqueue_style( 'wtlw-public', WTLW_URL . 'public/css/style.css', array(), WTLW_VERSION );
		wp_enqueue_style( 'wtlw-theme', WTLW_URL . 'public/css/theme-overrides.css', array( 'wtlw-public' ), WTLW_VERSION );
		wp_enqueue_style( 'wtlw-ux-v15', WTLW_URL . 'public/css/ux-v15.css', array( 'wtlw-theme' ), WTLW_VERSION );
		include WTLW_DIR . 'public/templates/rewards-account.php';
	}

	public function maybe_render_non_wc_endpoint( $content ) {
		$endpoint = get_query_var( 'my-rewards', null );
		if ( $this->woocommerce->is_available() || is_admin() || null === $endpoint || false === $endpoint ) {
			return $content;
		}

		ob_start();
		$this->render_rewards_content();
		return $content . ob_get_clean();
	}

	public function endpoint_title( $title ) {
		$endpoint = get_query_var( 'my-rewards', null );
		if ( is_admin() || null === $endpoint || false === $endpoint ) {
			return $title;
		}
		return __( 'جوایز من', 'webtanan-lucky-wheel' );
	}
}
