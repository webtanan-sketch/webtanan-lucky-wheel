<?php
/** Public shortcode and asset loader. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Shortcode {
	private $engine;

	public function __construct( WTLW_Wheel_Engine $engine ) {
		$this->engine = $engine;
		add_shortcode( 'webtanan_lucky_wheel', array( $this, 'render' ) );
	}

	public function render( $atts = array() ) {
		$atts = shortcode_atts(
			array( 'popup' => '0', 'button_text' => __( 'باز کردن گردونه شانس', 'webtanan-lucky-wheel' ), 'auto_open' => '0', 'delay' => '800' ),
			$atts,
			'webtanan_lucky_wheel'
		);
		$is_popup = in_array( strtolower( (string) $atts['popup'] ), array( '1', 'true', 'yes', 'on' ), true );
		$auto_open = in_array( strtolower( (string) $atts['auto_open'] ), array( '1', 'true', 'yes', 'on' ), true );
		$delay = max( 0, min( 60000, (int) $atts['delay'] ) );
		$button_text = sanitize_text_field( $atts['button_text'] );
		if ( '' === $button_text ) {
			$button_text = __( 'باز کردن گردونه شانس', 'webtanan-lucky-wheel' );
		}
		if ( ! get_option( 'webtanan_lucky_wheel_active', 1 ) ) {
			return '<div class="wtlw-notice" dir="rtl">' . esc_html__( 'گردونه شانس موقتاً غیرفعال است.', 'webtanan-lucky-wheel' ) . '</div>';
		}
		$sections = $this->engine->get_sections();
		if ( empty( $sections ) ) {
			return '<div class="wtlw-notice" dir="rtl">' . esc_html__( 'در حال حاضر جایزه فعالی برای گردونه تعریف نشده است.', 'webtanan-lucky-wheel' ) . '</div>';
		}

		wp_enqueue_style( 'wtlw-public', WTLW_URL . 'public/css/style.css', array(), WTLW_VERSION );
		wp_enqueue_style( 'wtlw-theme', WTLW_URL . 'public/css/theme-overrides.css', array( 'wtlw-public' ), WTLW_VERSION );
		wp_enqueue_script( 'wtlw-public', WTLW_URL . 'public/js/wheel.js', array(), WTLW_VERSION, true );
		wp_localize_script(
			'wtlw-public',
			'WTLW_DATA',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wtlw_frontend' ),
				'labels' => array(
					'spinning' => __( 'گردونه در حال چرخش است...', 'webtanan-lucky-wheel' ),
					'entering' => __( 'در حال ثبت اطلاعات...', 'webtanan-lucky-wheel' ),
					'entryFailed' => __( 'ثبت اطلاعات انجام نشد. دوباره تلاش کنید.', 'webtanan-lucky-wheel' ),
					'spinFailed' => __( 'چرخش گردونه انجام نشد. دوباره تلاش کنید.', 'webtanan-lucky-wheel' ),
					'discountCode' => __( 'کد تخفیف شما', 'webtanan-lucky-wheel' ),
					'noLuck' => __( 'این بار برنده نشدید؛ اگر شانس دیگری دارید دوباره امتحان کنید.', 'webtanan-lucky-wheel' ),
					'extraAdded' => __( 'شانس اضافه برای شما ثبت شد.', 'webtanan-lucky-wheel' ),
					'walletAdded' => __( 'اعتبار جایزه برای شماره موبایل شما ثبت شد.', 'webtanan-lucky-wheel' ),
					'sessionInvalid' => __( 'برای ادامه، نام و شماره موبایل را دوباره وارد کنید.', 'webtanan-lucky-wheel' ),
				),
			)
		);

		$title = get_option( 'webtanan_lucky_wheel_title', __( 'گردونه شانس و جایزه', 'webtanan-lucky-wheel' ) );
		$angle = 360 / count( $sections );
		$colors = array_column( $sections, 'color' );
		$gradient = 'conic-gradient(';
		foreach ( $colors as $index => $color ) {
			$gradient .= esc_attr( $color ) . ' ' . ( $index * $angle ) . 'deg ' . ( ( $index + 1 ) * $angle ) . 'deg' . ( $index < count( $colors ) - 1 ? ', ' : '' );
		}
		$gradient .= ')';
		$theme_vars = class_exists( 'WTLW_Appearance' ) ? WTLW_Appearance::css_variables() : '';

		ob_start();
		?>
		<div class="wtlw-theme-scope" dir="rtl" style="<?php echo esc_attr( $theme_vars ); ?>">
		<?php if ( $is_popup ) : ?>
			<div class="wtlw-popup-shell" data-auto-open="<?php echo esc_attr( $auto_open ? '1' : '0' ); ?>" data-delay="<?php echo esc_attr( $delay ); ?>">
				<button type="button" class="wtlw-button wtlw-popup-trigger"><span>✦</span><?php echo esc_html( $button_text ); ?></button>
				<div class="wtlw-popup" aria-hidden="true">
					<div class="wtlw-popup-backdrop" data-wtlw-popup-close></div>
					<div class="wtlw-popup-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $title ); ?>">
						<button type="button" class="wtlw-popup-close" data-wtlw-popup-close aria-label="<?php echo esc_attr__( 'بستن گردونه', 'webtanan-lucky-wheel' ); ?>">×</button>
		<?php endif; ?>
		<div class="wtlw-app" dir="rtl" data-sections="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>">
			<div class="wtlw-hero">
				<span class="wtlw-kicker"><?php echo esc_html__( 'باشگاه مشتریان • شانس ویژه خرید', 'webtanan-lucky-wheel' ); ?></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html__( 'فقط نام و شماره موبایل را وارد کنید؛ نیازی به ساخت حساب کاربری نیست.', 'webtanan-lucky-wheel' ); ?></p>
			</div>
			<form class="wtlw-register-form wtlw-entry-form" novalidate>
				<div class="wtlw-form-heading"><span class="wtlw-badge">۱</span><div><h3><?php echo esc_html__( 'ورود سریع به قرعه‌کشی', 'webtanan-lucky-wheel' ); ?></h3><p><?php echo esc_html__( 'نام و موبایل شما برای ثبت شانس و جایزه استفاده می‌شود.', 'webtanan-lucky-wheel' ); ?></p></div></div>
				<div class="wtlw-form-grid wtlw-form-grid-two">
					<label><?php echo esc_html__( 'نام و نام خانوادگی', 'webtanan-lucky-wheel' ); ?><input type="text" name="name" autocomplete="name" required /></label>
					<label><?php echo esc_html__( 'شماره موبایل', 'webtanan-lucky-wheel' ); ?><input type="tel" name="phone" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۱۲۳۴۵۶۷" required /></label>
				</div>
				<button type="submit" class="wtlw-button wtlw-register-button"><?php echo esc_html__( 'شرکت در قرعه‌کشی', 'webtanan-lucky-wheel' ); ?><span>←</span></button>
				<div class="wtlw-form-message" role="alert" aria-live="polite"></div>
			</form>
			<div class="wtlw-game wtlw-is-hidden" data-remaining="0">
				<div class="wtlw-welcome"><span><?php echo esc_html__( 'خوش آمدید', 'webtanan-lucky-wheel' ); ?></span> <strong class="wtlw-participant-name"></strong></div>
				<div class="wtlw-attempts"><span class="wtlw-attempt-icon">✦</span><span><?php echo esc_html__( 'شانس باقی‌مانده', 'webtanan-lucky-wheel' ); ?></span><strong class="wtlw-attempt-count">۰</strong></div>
				<div class="wtlw-wheel-stage">
					<div class="wtlw-pointer" aria-hidden="true"></div>
					<div class="wtlw-wheel" style="--wtlw-gradient: <?php echo esc_attr( $gradient ); ?>" role="img" aria-label="<?php echo esc_attr__( 'گردونه شانس', 'webtanan-lucky-wheel' ); ?>">
						<div class="wtlw-wheel-labels">
							<?php foreach ( $sections as $index => $section ) : ?><span style="--wtlw-label-angle: <?php echo esc_attr( ( $index * $angle ) + ( $angle / 2 ) ); ?>deg;" title="<?php echo esc_attr( $section['name'] ); ?>"><?php echo esc_html( $section['icon'] ); ?><b><?php echo esc_html( $section['name'] ); ?></b></span><?php endforeach; ?>
						</div>
						<div class="wtlw-wheel-hub"><span><?php echo esc_html__( 'شانس', 'webtanan-lucky-wheel' ); ?></span></div>
					</div>
				</div>
				<button type="button" class="wtlw-button wtlw-spin-button"><?php echo esc_html__( 'گردونه را بچرخان', 'webtanan-lucky-wheel' ); ?><span>✦</span></button>
				<div class="wtlw-spin-message" role="status" aria-live="polite"></div>
			</div>
			<div class="wtlw-trust-row"><span>🔒 <?php echo esc_html__( 'بدون ساخت حساب کاربری', 'webtanan-lucky-wheel' ); ?></span><span>✧ <?php echo esc_html__( 'انتخاب تصادفی و منصفانه', 'webtanan-lucky-wheel' ); ?></span><span>⚡ <?php echo esc_html__( 'اعلام نتیجه فوری', 'webtanan-lucky-wheel' ); ?></span></div>
			<div class="wtlw-modal" aria-hidden="true"><div class="wtlw-modal-backdrop"></div><div class="wtlw-modal-card" role="dialog" aria-modal="true"><button type="button" class="wtlw-modal-close" aria-label="<?php echo esc_attr__( 'بستن', 'webtanan-lucky-wheel' ); ?>">×</button><div class="wtlw-confetti" aria-hidden="true"></div><span class="wtlw-result-sparkle">✦</span><h3><?php echo esc_html__( 'تبریک!', 'webtanan-lucky-wheel' ); ?></h3><p class="wtlw-result-name"></p><div class="wtlw-result-code"></div><button type="button" class="wtlw-button wtlw-modal-ok"><?php echo esc_html__( 'عالیه', 'webtanan-lucky-wheel' ); ?></button></div></div>
		</div>
		<?php if ( $is_popup ) : ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
