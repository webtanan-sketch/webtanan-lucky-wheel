<?php
/**
 * Plugin Name: گردونه شانس وب‌تنان
 * Plugin URI: https://github.com/webtanan-sketch/webtanan-lucky-wheel
 * Description: افزونه فارسی و امن گردونه شانس برای کمپین‌های ثبت‌نام، کد تخفیف ووکامرس، اعتبار کیف پول و باشگاه مشتریان.
 * Version: 1.1.0
 * Author: Webtanan
 * Author URI: https://webtanan.ir
 * Text Domain: webtanan-lucky-wheel
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTLW_VERSION', '1.1.0' );
define( 'WTLW_FILE', __FILE__ );
define( 'WTLW_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTLW_URL', plugin_dir_url( __FILE__ ) );

require_once WTLW_DIR . 'includes/class-database.php';
require_once WTLW_DIR . 'includes/class-wallet.php';
require_once WTLW_DIR . 'includes/class-woocommerce.php';
require_once WTLW_DIR . 'includes/class-rewards.php';
require_once WTLW_DIR . 'includes/class-wheel-engine.php';
require_once WTLW_DIR . 'includes/class-ajax.php';
require_once WTLW_DIR . 'includes/class-admin.php';
require_once WTLW_DIR . 'includes/class-shortcode.php';
require_once WTLW_DIR . 'includes/class-plugin.php';

register_activation_hook( WTLW_FILE, array( 'WTLW_Database', 'activate' ) );
register_deactivation_hook( WTLW_FILE, array( 'WTLW_Database', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WTLW_Plugin::instance();
	}
);
