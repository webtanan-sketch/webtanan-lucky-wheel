<?php
/** Theme color controls for the lucky wheel. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Appearance {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 25 );
		add_action( 'admin_post_wtlw_save_appearance', array( $this, 'save' ) );
	}

	public static function defaults() {
		return array(
			'primary' => '#171717',
			'secondary' => '#2b2b2b',
			'accent' => '#c7a35a',
			'background' => '#0d0d0f',
			'surface' => '#1b1b1e',
			'text' => '#f7f3e8',
			'muted' => '#c9c0ae',
		);
	}

	public static function get_colors() {
		$saved = get_option( 'webtanan_lucky_wheel_colors', array() );
		$colors = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		foreach ( self::defaults() as $key => $fallback ) {
			$colors[ $key ] = sanitize_hex_color( isset( $colors[ $key ] ) ? $colors[ $key ] : '' );
			if ( ! $colors[ $key ] ) {
				$colors[ $key ] = $fallback;
			}
		}
		return $colors;
	}

	public static function css_variables() {
		$c = self::get_colors();
		return sprintf(
			'--wtlw-primary:%1$s;--wtlw-secondary:%2$s;--wtlw-accent:%3$s;--wtlw-background:%4$s;--wtlw-surface:%5$s;--wtlw-text:%6$s;--wtlw-muted-custom:%7$s;--wtlw-turquoise:%1$s;--wtlw-lapis:%2$s;--wtlw-gold:%3$s;--wtlw-lapis-deep:%4$s;--wtlw-ink:%6$s;--wtlw-muted:%7$s;',
			esc_attr( $c['primary'] ), esc_attr( $c['secondary'] ), esc_attr( $c['accent'] ), esc_attr( $c['background'] ), esc_attr( $c['surface'] ), esc_attr( $c['text'] ), esc_attr( $c['muted'] )
		);
	}

	public function menu() {
		add_submenu_page( 'wtlw-dashboard', __( 'ظاهر و رنگ‌بندی', 'webtanan-lucky-wheel' ), __( 'ظاهر و رنگ‌بندی', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-appearance', array( $this, 'render' ) );
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه انجام این عملیات را ندارید.', 'webtanan-lucky-wheel' ) );
		}
		check_admin_referer( 'wtlw_save_appearance' );
		if ( ! empty( $_POST['reset_altin'] ) ) {
			update_option( 'webtanan_lucky_wheel_colors', self::defaults() );
		} else {
			$colors = array();
			foreach ( self::defaults() as $key => $fallback ) {
				$value = isset( $_POST[ $key ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ) : false;
				$colors[ $key ] = $value ? $value : $fallback;
			}
			update_option( 'webtanan_lucky_wheel_colors', $colors );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'wtlw-appearance', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$colors = self::get_colors();
		$fields = array( 'primary' => 'رنگ اصلی', 'secondary' => 'رنگ دوم', 'accent' => 'رنگ طلایی / تأکیدی', 'background' => 'پس‌زمینه اصلی', 'surface' => 'سطح کارت‌ها و فرم', 'text' => 'رنگ متن اصلی', 'muted' => 'رنگ متن فرعی' );
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<h1><?php echo esc_html__( 'ظاهر و رنگ‌بندی گردونه', 'webtanan-lucky-wheel' ); ?></h1>
			<p><?php echo esc_html__( 'رنگ‌های قالب را بدون ویرایش CSS تغییر دهید. رنگ هر قطعه گردونه همچنان از بخش مدیریت جوایز قابل تنظیم است.', 'webtanan-lucky-wheel' ); ?></p>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'رنگ‌بندی ذخیره شد.', 'webtanan-lucky-wheel' ); ?></p></div><?php endif; ?>
			<div style="display:grid;grid-template-columns:minmax(0,620px) minmax(280px,1fr);gap:22px;max-width:1050px;align-items:start">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtlw-admin-panel" style="padding:24px;background:#fff;border:1px solid #ddd;border-radius:14px">
					<input type="hidden" name="action" value="wtlw_save_appearance" />
					<?php wp_nonce_field( 'wtlw_save_appearance' ); ?>
					<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px">
					<?php foreach ( $fields as $key => $label ) : ?>
						<label style="display:grid;gap:7px;font-weight:600"><?php echo esc_html( $label ); ?><span style="display:flex;gap:10px;align-items:center"><input type="color" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $colors[ $key ] ); ?>" style="width:52px;height:38px;padding:2px" /><code dir="ltr"><?php echo esc_html( $colors[ $key ] ); ?></code></span></label>
					<?php endforeach; ?>
					</div>
					<p style="margin-top:22px"><button class="button button-primary button-large" type="submit">ذخیره رنگ‌بندی</button> <button class="button button-large" type="submit" name="reset_altin" value="1">بازگشت به پالت پیشنهادی آلتین واچ</button></p>
				</form>
				<div class="wtlw-admin-panel" style="padding:24px;border-radius:14px;background:<?php echo esc_attr( $colors['background'] ); ?>;color:<?php echo esc_attr( $colors['text'] ); ?>;border:2px solid <?php echo esc_attr( $colors['accent'] ); ?>">
					<small style="color:<?php echo esc_attr( $colors['accent'] ); ?>">پیش‌نمایش سریع</small><h2 style="color:inherit">گردونه شانس آلتین</h2><p style="color:<?php echo esc_attr( $colors['muted'] ); ?>">ترکیب فعلی رنگ‌های قالب در این کارت نمایش داده می‌شود.</p>
					<div style="padding:15px;border-radius:12px;background:<?php echo esc_attr( $colors['surface'] ); ?>;border:1px solid <?php echo esc_attr( $colors['secondary'] ); ?>">نام و شماره موبایل</div>
					<div style="margin-top:14px;padding:11px;text-align:center;border-radius:10px;background:<?php echo esc_attr( $colors['accent'] ); ?>;color:<?php echo esc_attr( $colors['background'] ); ?>;font-weight:800">شرکت در قرعه‌کشی</div>
				</div>
			</div>
		</div>
		<?php
	}
}

new WTLW_Appearance();
