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
	/** @var WTLW_Plugin|null */
	private static $instance = null;

	/** @var WTLW_Wheel_Engine */
	public $engine;
	/** @var WTLW_Rewards */
	public $rewards;
	/** @var WTLW_Wallet */
	public $wallet;
	/** @var WTLW_WooCommerce */
	public $woocommerce;
	/** @var WTLW_Ajax */
	public $ajax;
	/** @var WTLW_Admin */
	public $admin;
	/** @var WTLW_Shortcode */
	public $shortcode;

	/**
	 * Return the singleton instance.
	 *
	 * @return WTLW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Constructor. */
	private function __construct() {
		$this->wallet      = new WTLW_Wallet();
		$this->woocommerce = new WTLW_WooCommerce();
		$this->rewards     = new WTLW_Rewards( $this->wallet, $this->woocommerce );
		$this->engine      = new WTLW_Wheel_Engine( $this->rewards );
		$this->ajax        = new WTLW_Ajax( $this->engine );
		$this->admin       = new WTLW_Admin( $this->engine );
		$this->shortcode   = new WTLW_Shortcode( $this->engine );
		$this->woocommerce->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'WTLW_Database', 'maybe_upgrade_defaults' ), 1 );
		add_action( 'init', array( $this, 'register_account_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'woocommerce_account_my-rewards_endpoint', array( $this, 'render_rewards_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_filter( 'the_content', array( $this, 'maybe_render_non_wc_endpoint' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
	}

	/** Load translations. */
	public function load_textdomain() {
		load_plugin_textdomain( 'webtanan-lucky-wheel', false, dirname( plugin_basename( WTLW_FILE ) ) . '/languages' );
	}

	/** Register the WooCommerce-compatible account endpoint. */
	public function register_account_endpoint() {
		add_rewrite_endpoint( 'my-rewards', EP_ROOT | EP_PAGES );
	}

	/** Add endpoint query var for non-WooCommerce account implementations. */
	public function register_query_var( $vars ) {
		$vars[] = 'my-rewards';
		return $vars;
	}

	/** Render rewards from the account endpoint. */
	public function render_rewards_endpoint() {
		$this->render_rewards_content();
	}

	/** Add the endpoint to the standard WooCommerce account navigation. */
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

	/**
	 * Render account content. The method is public so themes may reuse it.
	 */
	public function render_rewards_content() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id      = get_current_user_id();
		$balance      = $this->wallet->get_balance( $user_id );
		$transactions = $this->wallet->get_transactions( $user_id );
		$coupons      = WTLW_Database::get_user_coupons( $user_id );

		wp_enqueue_style( 'wtlw-public', WTLW_URL . 'public/css/style.css', array(), WTLW_VERSION );
		include WTLW_DIR . 'public/templates/rewards-account.php';
	}

	/** Fallback endpoint rendering when WooCommerce is not installed. */
	public function maybe_render_non_wc_endpoint( $content ) {
		$endpoint = get_query_var( 'my-rewards', null );
		if ( $this->woocommerce->is_available() || is_admin() || null === $endpoint || false === $endpoint ) {
			return $content;
		}
		ob_start();
		$this->render_rewards_content();
		return $content . ob_get_clean();
	}

	/** Add a useful title for custom endpoint pages. */
	public function endpoint_title( $title ) {
		$endpoint = get_query_var( 'my-rewards', null );
		if ( is_admin() || null === $endpoint || false === $endpoint ) {
			return $title;
		}

		return __( 'جوایز من', 'webtanan-lucky-wheel' );
	}
}
