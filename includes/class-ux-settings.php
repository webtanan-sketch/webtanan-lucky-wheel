<?php
/**
 * Front-end labels and wheel hub media settings.
 *
 * @package WebtananLuckyWheel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_UX_Settings {
	const OPTION_KEY = 'webtanan_lucky_wheel_ui';

	/** @var array|null */
	private static $cache = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 27 );
		add_action( 'admin_post_wtlw_save_ui', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public static function defaults() {
		return array(
			'popup_button_text' => __( 'باز کردن گردونه شانس', 'webtanan-lucky-wheel' ),
			'entry_button_text' => __( 'شرکت در قرعه‌کشی', 'webtanan-lucky-wheel' ),
			'spin_button_text'  => __( 'گردونه را بچرخان', 'webtanan-lucky-wheel' ),
			'result_button_text'=> __( 'عالیه', 'webtanan-lucky-wheel' ),
			'hub_logo_id'       => 0,
		);
	}

	public static function get_settings() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$defaults = self::defaults();
		$saved    = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

		foreach ( array( 'popup_button_text', 'entry_button_text', 'spin_button_text', 'result_button_text' ) as $key ) {
			$value = isset( $settings[ $key ] ) ? sanitize_text_field( $settings[ $key ] ) : '';
			$settings[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		$settings['hub_logo_id'] = isset( $settings['hub_logo_id'] ) ? absint( $settings['hub_logo_id'] ) : 0;
		self::$cache = $settings;
		return self::$cache;
	}

	public static function hub_logo_url() {
		$settings = self::get_settings();
		if ( empty( $settings['hub_logo_id'] ) ) {
			return '';
		}
		$url = wp_get_attachment_image_url( (int) $settings['hub_logo_id'], 'thumbnail' );
		return $url ? esc_url_raw( $url ) : '';
	}

	public function menu() {
		add_submenu_page(
			'wtlw-dashboard',
			__( 'رابط و دکمه‌ها', 'webtanan-lucky-wheel' ),
			__( 'رابط و دکمه‌ها', 'webtanan-lucky-wheel' ),
			'manage_options',
			'wtlw-interface',
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wtlw-interface' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'wtlw-interface-admin',
			WTLW_URL . 'admin/js/ux-settings.js',
			array( 'jquery' ),
			WTLW_VERSION,
			true
		);
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه انجام این عملیات را ندارید.', 'webtanan-lucky-wheel' ) );
		}
		check_admin_referer( 'wtlw_save_ui' );

		$defaults = self::defaults();
		$data     = array();

		foreach ( array( 'popup_button_text', 'entry_button_text', 'spin_button_text', 'result_button_text' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$data[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		$logo_id = isset( $_POST['hub_logo_id'] ) ? absint( $_POST['hub_logo_id'] ) : 0;
		if ( $logo_id && ! wp_attachment_is_image( $logo_id ) ) {
			$logo_id = 0;
		}
		$data['hub_logo_id'] = $logo_id;

		update_option( self::OPTION_KEY, $data, false );
		self::$cache = null;

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wtlw-interface',
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::get_settings();
		$logo_url = self::hub_logo_url();
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<div class="wtlw-admin-heading">
				<span class="wtlw-admin-kicker"><?php echo esc_html__( 'تجربه کاربری', 'webtanan-lucky-wheel' ); ?></span>
				<h1><?php echo esc_html__( 'رابط، دکمه‌ها و لوگوی گردونه', 'webtanan-lucky-wheel' ); ?></h1>
				<p class="description"><?php echo esc_html__( 'متن دکمه‌های اصلی و تصویر مرکز گردونه را بدون ویرایش کد مدیریت کنید.', 'webtanan-lucky-wheel' ); ?></p>
			</div>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'تنظیمات رابط ذخیره شد.', 'webtanan-lucky-wheel' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtlw-settings-form">
				<input type="hidden" name="action" value="wtlw_save_ui" />
				<?php wp_nonce_field( 'wtlw_save_ui' ); ?>

				<div class="wtlw-admin-panel">
					<h2><?php echo esc_html__( 'متن دکمه‌ها', 'webtanan-lucky-wheel' ); ?></h2>
					<div class="wtlw-field-grid">
						<label><?php echo esc_html__( 'دکمه بازکردن پاپ‌آپ', 'webtanan-lucky-wheel' ); ?><input type="text" name="popup_button_text" value="<?php echo esc_attr( $settings['popup_button_text'] ); ?>" /></label>
						<label><?php echo esc_html__( 'دکمه ورود به قرعه‌کشی', 'webtanan-lucky-wheel' ); ?><input type="text" name="entry_button_text" value="<?php echo esc_attr( $settings['entry_button_text'] ); ?>" /></label>
						<label><?php echo esc_html__( 'دکمه چرخاندن گردونه', 'webtanan-lucky-wheel' ); ?><input type="text" name="spin_button_text" value="<?php echo esc_attr( $settings['spin_button_text'] ); ?>" /></label>
						<label><?php echo esc_html__( 'دکمه تأیید نتیجه', 'webtanan-lucky-wheel' ); ?><input type="text" name="result_button_text" value="<?php echo esc_attr( $settings['result_button_text'] ); ?>" /></label>
					</div>
					<p class="description"><?php echo esc_html__( 'اگر در شورت‌کد button_text وارد شود، فقط متن دکمه همان شورت‌کد بر تنظیم بالا اولویت دارد.', 'webtanan-lucky-wheel' ); ?></p>
				</div>

				<div class="wtlw-admin-panel">
					<h2><?php echo esc_html__( 'لوگوی مرکز گردونه', 'webtanan-lucky-wheel' ); ?></h2>
					<input type="hidden" id="wtlw-hub-logo-id" name="hub_logo_id" value="<?php echo esc_attr( $settings['hub_logo_id'] ); ?>" />
					<div id="wtlw-hub-logo-preview" style="display:flex;align-items:center;gap:16px;min-height:88px">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="width:76px;height:76px;object-fit:cover;border-radius:50%;border:1px solid #cbd5e1" />
						<?php else : ?>
							<span class="description"><?php echo esc_html__( 'در حال حاضر لوگویی انتخاب نشده است و متن «شانس» نمایش داده می‌شود.', 'webtanan-lucky-wheel' ); ?></span>
						<?php endif; ?>
					</div>
					<p><button type="button" class="button" id="wtlw-select-hub-logo"><?php echo esc_html__( 'انتخاب تصویر', 'webtanan-lucky-wheel' ); ?></button> <button type="button" class="button-link-delete" id="wtlw-remove-hub-logo"><?php echo esc_html__( 'حذف تصویر', 'webtanan-lucky-wheel' ); ?></button></p>
					<p class="description"><?php echo esc_html__( 'پیشنهاد: تصویر مربعی با پس‌زمینه شفاف یا لوگوی خوانا. افزونه آن را به‌صورت دایره‌ای در مرکز گردونه نمایش می‌دهد.', 'webtanan-lucky-wheel' ); ?></p>
				</div>

				<p><button type="submit" class="button button-primary button-large"><?php echo esc_html__( 'ذخیره تنظیمات رابط', 'webtanan-lucky-wheel' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
