<?php
/** WordPress administration screens and settings persistence. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Admin {
	/** @var WTLW_Wheel_Engine */
	private $engine;

	public function __construct( WTLW_Wheel_Engine $engine ) {
		$this->engine = $engine;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_wtlw_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/** Register menu pages. */
	public function menu() {
		add_menu_page( __( 'Webtanan Lucky Wheel', 'webtanan-lucky-wheel' ), __( 'Lucky Wheel', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-dashboard', array( $this, 'dashboard' ), 'dashicons-controls-repeat', 58 );
		add_submenu_page( 'wtlw-dashboard', __( 'Dashboard', 'webtanan-lucky-wheel' ), __( 'Dashboard', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-dashboard', array( $this, 'dashboard' ) );
		add_submenu_page( 'wtlw-dashboard', __( 'Wheel Settings', 'webtanan-lucky-wheel' ), __( 'Wheel Settings', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-settings', array( $this, 'settings' ) );
		add_submenu_page( 'wtlw-dashboard', __( 'Reward Management', 'webtanan-lucky-wheel' ), __( 'Reward Management', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-rewards', array( $this, 'rewards' ) );
		add_submenu_page( 'wtlw-dashboard', __( 'Users History', 'webtanan-lucky-wheel' ), __( 'Users History', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-history', array( $this, 'history' ) );
	}

	/** Load admin assets only for plugin pages. */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'wtlw' ) ) {
			return;
		}
		wp_enqueue_style( 'wtlw-admin', WTLW_URL . 'admin/css/admin.css', array(), WTLW_VERSION );
		wp_enqueue_script( 'wtlw-admin', WTLW_URL . 'admin/js/admin.js', array( 'jquery' ), WTLW_VERSION, true );
	}

	/** Dashboard cards and quick links. */
	public function dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webtanan-lucky-wheel' ) );
		}
		$stats = WTLW_Database::stats();
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<h1><?php echo esc_html__( 'Webtanan Lucky Wheel', 'webtanan-lucky-wheel' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Campaign overview and wheel performance', 'webtanan-lucky-wheel' ); ?></p>
			<div class="wtlw-stat-grid">
				<?php $this->stat_card( __( 'Total spins', 'webtanan-lucky-wheel' ), number_format_i18n( $stats['spins'] ), 'dashicons-controls-repeat' ); ?>
				<?php $this->stat_card( __( 'Unique users', 'webtanan-lucky-wheel' ), number_format_i18n( $stats['users'] ), 'dashicons-groups' ); ?>
				<?php $this->stat_card( __( 'Rewards issued', 'webtanan-lucky-wheel' ), number_format_i18n( $stats['rewards'] ), 'dashicons-awards' ); ?>
				<?php $this->stat_card( __( 'Spins today', 'webtanan-lucky-wheel' ), number_format_i18n( $stats['today'] ), 'dashicons-chart-area' ); ?>
			</div>
			<div class="wtlw-admin-panel">
				<h2><?php echo esc_html__( 'Quick start', 'webtanan-lucky-wheel' ); ?></h2>
				<p><?php echo esc_html__( 'Add the shortcode below to any page or landing page:', 'webtanan-lucky-wheel' ); ?></p>
				<code class="wtlw-shortcode">[webtanan_lucky_wheel]</code>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wtlw-settings' ) ); ?>"><?php echo esc_html__( 'Configure wheel', 'webtanan-lucky-wheel' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	/** Wheel section configuration. */
	public function settings() {
		$this->render_settings_page( __( 'Wheel Settings', 'webtanan-lucky-wheel' ), false );
	}

	/** Reward-specific configuration view. */
	public function rewards() {
		$this->render_settings_page( __( 'Reward Management', 'webtanan-lucky-wheel' ), true );
	}

	/** Users history table. */
	public function history() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webtanan-lucky-wheel' ) );
		}
		$rows = WTLW_Database::get_history();
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<h1><?php echo esc_html__( 'Users History', 'webtanan-lucky-wheel' ); ?></h1>
			<div class="wtlw-admin-panel wtlw-table-wrap">
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'User', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Email', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Phone', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Reward', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Coupon', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Date', 'webtanan-lucky-wheel' ); ?></th><th><?php echo esc_html__( 'Status', 'webtanan-lucky-wheel' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?><tr><td colspan="7"><?php echo esc_html__( 'No spin records yet.', 'webtanan-lucky-wheel' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $rows as $row ) : $user = get_userdata( $row->user_id ); ?>
						<tr>
							<td><?php echo esc_html( $user ? $user->display_name : '#' . $row->user_id ); ?></td>
							<td><?php echo esc_html( $user ? $user->user_email : '-' ); ?></td>
							<td><?php echo esc_html( $user ? get_user_meta( $row->user_id, 'wtlw_phone', true ) : '-' ); ?></td>
							<td><?php echo esc_html( $row->reward_name ); ?></td>
							<td><code><?php echo esc_html( $row->coupon_code ? $row->coupon_code : '-' ); ?></code></td>
							<td><?php echo esc_html( get_date_from_gmt( $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
							<td><span class="wtlw-status wtlw-status-<?php echo esc_attr( sanitize_key( $row->status ) ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/** Handle both settings forms. */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'webtanan-lucky-wheel' ) );
		}
		check_admin_referer( 'wtlw_save_settings' );

		$raw_sections = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
		$sections     = array();
		foreach ( $raw_sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$type = isset( $section['type'] ) ? sanitize_key( $section['type'] ) : 'nothing';
			if ( ! in_array( $type, array( 'coupon', 'wallet', 'nothing', 'extra_attempts' ), true ) ) {
				$type = 'nothing';
			}
			$sections[] = array(
				'id'             => isset( $section['id'] ) ? sanitize_key( $section['id'] ) : 'section-' . count( $sections ),
				'name'           => isset( $section['name'] ) ? sanitize_text_field( $section['name'] ) : '',
				'type'           => $type,
				'value'          => isset( $section['value'] ) ? max( 0, (float) $section['value'] ) : 0,
				'probability'    => isset( $section['probability'] ) ? max( 0, (float) $section['probability'] ) : 0,
				'color'          => isset( $section['color'] ) && sanitize_hex_color( $section['color'] ) ? sanitize_hex_color( $section['color'] ) : '#7c3aed',
				'icon'           => isset( $section['icon'] ) ? sanitize_text_field( $section['icon'] ) : '+',
				'active'         => ! empty( $section['active'] ) ? 1 : 0,
				'extra_attempts' => isset( $section['extra_attempts'] ) ? max( 0, (int) $section['extra_attempts'] ) : 0,
				'expiry_days'    => isset( $section['expiry_days'] ) ? max( 0, (int) $section['expiry_days'] ) : 0,
				'discount_type'  => isset( $section['discount_type'] ) && 'percent' === $section['discount_type'] ? 'percent' : 'fixed_cart',
			);
		}
		if ( empty( $sections ) ) {
			$sections = WTLW_Database::default_sections();
		}
		update_option( 'webtanan_lucky_wheel_sections', $sections );
		if ( isset( $_POST['wheel_title'] ) ) {
			update_option( 'webtanan_lucky_wheel_title', sanitize_text_field( wp_unslash( $_POST['wheel_title'] ) ) );
		}
		if ( isset( $_POST['wheel_active'] ) || isset( $_POST['return_page'] ) && 'wtlw-settings' === sanitize_key( $_POST['return_page'] ) ) {
			update_option( 'webtanan_lucky_wheel_active', ! empty( $_POST['wheel_active'] ) ? 1 : 0 );
		}
		if ( isset( $_POST['default_attempts'] ) ) {
			update_option( 'webtanan_lucky_wheel_default_attempts', max( 0, (int) $_POST['default_attempts'] ) );
		}

		$redirect = isset( $_POST['return_page'] ) && 'wtlw-rewards' === sanitize_key( $_POST['return_page'] ) ? 'wtlw-rewards' : 'wtlw-settings';
		wp_safe_redirect( add_query_arg( array( 'page' => $redirect, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Render shared settings form. */
	private function render_settings_page( $heading, $reward_mode ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'webtanan-lucky-wheel' ) );
		}
		$sections = get_option( 'webtanan_lucky_wheel_sections', WTLW_Database::default_sections() );
		if ( ! is_array( $sections ) ) {
			$sections = WTLW_Database::default_sections();
		}
		$defaults = WTLW_Database::default_sections();
		foreach ( $sections as $section_index => $section ) {
			$sections[ $section_index ] = wp_parse_args( is_array( $section ) ? $section : array(), isset( $defaults[ $section_index ] ) ? $defaults[ $section_index ] : $defaults[0] );
		}
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<h1><?php echo esc_html( $heading ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'webtanan-lucky-wheel' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtlw-settings-form">
				<input type="hidden" name="action" value="wtlw_save_settings" />
				<input type="hidden" name="return_page" value="<?php echo esc_attr( $reward_mode ? 'wtlw-rewards' : 'wtlw-settings' ); ?>" />
				<?php wp_nonce_field( 'wtlw_save_settings' ); ?>
				<?php if ( ! $reward_mode ) : ?>
				<div class="wtlw-admin-panel wtlw-general-settings">
					<h2><?php echo esc_html__( 'General settings', 'webtanan-lucky-wheel' ); ?></h2>
					<label><?php echo esc_html__( 'Wheel title', 'webtanan-lucky-wheel' ); ?><input type="text" name="wheel_title" value="<?php echo esc_attr( get_option( 'webtanan_lucky_wheel_title', 'Spin & Win' ) ); ?>" /></label>
					<label><?php echo esc_html__( 'Initial chances per user', 'webtanan-lucky-wheel' ); ?><input type="number" min="0" name="default_attempts" value="<?php echo esc_attr( get_option( 'webtanan_lucky_wheel_default_attempts', 1 ) ); ?>" /></label>
					<label class="wtlw-checkbox"><input type="checkbox" name="wheel_active" value="1" <?php checked( get_option( 'webtanan_lucky_wheel_active', 1 ), 1 ); ?> /> <?php echo esc_html__( 'Wheel is active', 'webtanan-lucky-wheel' ); ?></label>
				</div>
				<?php endif; ?>
				<div class="wtlw-admin-panel">
					<h2><?php echo esc_html__( 'Wheel sections', 'webtanan-lucky-wheel' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Probabilities are normalized across active sections.', 'webtanan-lucky-wheel' ); ?></p>
					<div class="wtlw-section-list">
					<?php foreach ( array_values( $sections ) as $index => $section ) : ?>
						<div class="wtlw-section-row">
							<input type="hidden" name="sections[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $section['id'] ); ?>" />
							<div class="wtlw-section-top"><span class="wtlw-drag">☷</span><strong><?php echo esc_html( sprintf( __( 'Section %d', 'webtanan-lucky-wheel' ), $index + 1 ) ); ?></strong><label class="wtlw-checkbox"><input type="checkbox" name="sections[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( ! empty( $section['active'] ) ); ?> /> <?php echo esc_html__( 'Active', 'webtanan-lucky-wheel' ); ?></label></div>
							<div class="wtlw-field-grid">
								<label><?php echo esc_html__( 'Reward label', 'webtanan-lucky-wheel' ); ?><input type="text" name="sections[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $section['name'] ); ?>" /></label>
								<label><?php echo esc_html__( 'Type', 'webtanan-lucky-wheel' ); ?><select name="sections[<?php echo esc_attr( $index ); ?>][type]"><option value="coupon" <?php selected( $section['type'], 'coupon' ); ?>>Coupon</option><option value="wallet" <?php selected( $section['type'], 'wallet' ); ?>>Wallet</option><option value="extra_attempts" <?php selected( $section['type'], 'extra_attempts' ); ?>><?php echo esc_html__( 'Extra attempts', 'webtanan-lucky-wheel' ); ?></option><option value="nothing" <?php selected( $section['type'], 'nothing' ); ?>><?php echo esc_html__( 'No prize', 'webtanan-lucky-wheel' ); ?></option></select></label>
								<label><?php echo esc_html__( 'Value / attempts', 'webtanan-lucky-wheel' ); ?><input type="number" min="0" step="0.01" name="sections[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $section['value'] ); ?>" /></label>
								<label><?php echo esc_html__( 'Win probability', 'webtanan-lucky-wheel' ); ?><input type="number" min="0" step="0.01" name="sections[<?php echo esc_attr( $index ); ?>][probability]" value="<?php echo esc_attr( $section['probability'] ); ?>" /></label>
								<label><?php echo esc_html__( 'Color', 'webtanan-lucky-wheel' ); ?><input type="color" name="sections[<?php echo esc_attr( $index ); ?>][color]" value="<?php echo esc_attr( $section['color'] ); ?>" /></label>
								<label><?php echo esc_html__( 'Icon', 'webtanan-lucky-wheel' ); ?><input type="text" name="sections[<?php echo esc_attr( $index ); ?>][icon]" value="<?php echo esc_attr( $section['icon'] ); ?>" maxlength="8" /></label>
								<label><?php echo esc_html__( 'Expiry (days)', 'webtanan-lucky-wheel' ); ?><input type="number" min="0" name="sections[<?php echo esc_attr( $index ); ?>][expiry_days]" value="<?php echo esc_attr( $section['expiry_days'] ); ?>" /></label>
								<label><?php echo esc_html__( 'Discount type', 'webtanan-lucky-wheel' ); ?><select name="sections[<?php echo esc_attr( $index ); ?>][discount_type]"><option value="fixed_cart" <?php selected( $section['discount_type'], 'fixed_cart' ); ?>><?php echo esc_html__( 'Fixed amount', 'webtanan-lucky-wheel' ); ?></option><option value="percent" <?php selected( $section['discount_type'], 'percent' ); ?>><?php echo esc_html__( 'Percentage', 'webtanan-lucky-wheel' ); ?></option></select></label>
							</div>
						</div>
					<?php endforeach; ?>
					</div>
				</div>
				<p><button type="submit" class="button button-primary button-large"><?php echo esc_html__( 'Save settings', 'webtanan-lucky-wheel' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	private function stat_card( $label, $value, $icon ) {
		printf( '<div class="wtlw-stat-card"><span class="dashicons %1$s"></span><div><strong>%2$s</strong><span>%3$s</span></div></div>', esc_attr( $icon ), esc_html( $value ), esc_html( $label ) );
	}
}
