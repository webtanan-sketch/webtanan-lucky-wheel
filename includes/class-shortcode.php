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
		$ui = class_exists( 'WTLW_UX_Settings' ) ? WTLW_UX_Settings::get_settings() : array(
			'popup_button_text' => __( 'باز کردن گردونه شانس', 'webtanan-lucky-wheel' ),
			'entry_button_text' => __( 'شرکت در قرعه‌کشی', 'webtanan-lucky-wheel' ),
			'spin_button_text'  => __( 'گردونه را بچرخان', 'webtanan-lucky-wheel' ),
			'result_button_text'=> __( 'عالیه', 'webtanan-lucky-wheel' ),
			'hub_logo_id'       => 0,
		);

		$atts = shortcode_atts(
			array(
				'popup'       => '0',
				'button_text' => '',
				'auto_open'   => '0',
				'delay'       => '800',
			),
			$atts,
			'webtanan_lucky_wheel'
		);

		$is_popup  = in_array( strtolower( (string) $atts['popup'] ), array( '1', 'true', 'yes', 'on' ), true );
		$auto_open = in_array( strtolower( (string) $atts['auto_open'] ), array( '1', 'true', 'yes', 'on' ), true );
		$delay     = max( 0, min( 60000, (int) $atts['delay'] ) );

		$button_text = sanitize_text_field( $atts['button_text'] );
		if ( '' === $button_text ) {
			$button_text = $ui['popup_button_text'];
		}

		if ( ! get_option( 'webtanan_lucky_wheel_active', 1 ) ) {
			return '<div class="wtlw-notice" dir="rtl">' . esc_html__( 'گردونه شانس موقتاً غیرفعال است.', 'webtanan-lucky-wheel' ) . '</div>';
		}

		$sections = $this->engine->get_sections();
		if ( empty( $sections ) ) {
			return '<div class="wtlw-notice" dir="rtl">' . esc_html__( 'در حال حاضر جایزه فعالی برای گردونه تعریف نشده است.', 'webtanan-lucky-wheel' ) . '</div>';
		}

		$is_member      = is_user_logged_in();
		$member_attempts = array( 'remaining_attempts' => 0 );
		$member_name   = '';

		if ( $is_member ) {
			$member_attempts = $this->engine->get_attempts( get_current_user_id() );
			$user            = wp_get_current_user();
			$member_name     = $user ? $user->display_name : '';

			if ( $is_popup && (int) $member_attempts['remaining_attempts'] < 1 ) {
				return '';
			}
		}

		wp_enqueue_style( 'wtlw-public', WTLW_URL . 'public/css/style.css', array(), WTLW_VERSION );
		wp_enqueue_style( 'wtlw-theme', WTLW_URL . 'public/css/theme-overrides.css', array( 'wtlw-public' ), WTLW_VERSION );
		wp_enqueue_style( 'wtlw-ux-v15', WTLW_URL . 'public/css/ux-v15.css', array( 'wtlw-theme' ), WTLW_VERSION );
		wp_enqueue_script( 'wtlw-public', WTLW_URL . 'public/js/wheel.js', array(), WTLW_VERSION, true );

		$default_attempts = max( 0, (int) get_option( 'webtanan_lucky_wheel_default_attempts', 1 ) );

		wp_localize_script(
			'wtlw-public',
			'WTLW_DATA',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wtlw_frontend' ),
				'defaultAttempts' => $default_attempts,
				'labels'          => array(
					'spinning'       => __( 'گردونه در حال چرخش است...', 'webtanan-lucky-wheel' ),
					'entering'       => __( 'در حال آماده‌سازی شانس شما...', 'webtanan-lucky-wheel' ),
					'entryFailed'    => __( 'ثبت اطلاعات انجام نشد. دوباره تلاش کنید.', 'webtanan-lucky-wheel' ),
					'spinFailed'     => __( 'چرخش گردونه انجام نشد. دوباره تلاش کنید.', 'webtanan-lucky-wheel' ),
					'discountCode'   => __( 'کد تخفیف شما', 'webtanan-lucky-wheel' ),
					'noLuck'         => __( 'این بار برنده نشدید؛ اگر شانس دیگری دارید دوباره امتحان کنید.', 'webtanan-lucky-wheel' ),
					'extraAdded'     => __( 'شانس اضافه برای شما ثبت شد.', 'webtanan-lucky-wheel' ),
					'walletAdded'    => __( 'اعتبار جایزه برای شما ثبت شد.', 'webtanan-lucky-wheel' ),
					'sessionInvalid' => __( 'برای ادامه، نام و شماره موبایل را دوباره وارد کنید.', 'webtanan-lucky-wheel' ),
				),
			)
		);

		$title     = get_option( 'webtanan_lucky_wheel_title', __( 'گردونه شانس و جایزه', 'webtanan-lucky-wheel' ) );
		$angle     = 360 / count( $sections );
		$colors    = array_column( $sections, 'color' );
		$gradient  = 'conic-gradient(';

		foreach ( $colors as $index => $color ) {
			$gradient .= esc_attr( $color ) . ' ' . ( $index * $angle ) . 'deg ' . ( ( $index + 1 ) * $angle ) . 'deg' . ( $index < count( $colors ) - 1 ? ', ' : '' );
		}
		$gradient .= ')';

		$theme_vars = class_exists( 'WTLW_Appearance' ) ? WTLW_Appearance::css_variables() : '';
		$remaining  = $is_member ? (int) $member_attempts['remaining_attempts'] : 0;
		$hub_logo   = class_exists( 'WTLW_UX_Settings' ) ? WTLW_UX_Settings::hub_logo_url() : '';

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

		<div class="wtlw-app"
			dir="rtl"
			data-sections="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>"
			data-member="<?php echo esc_attr( $is_member ? '1' : '0' ); ?>"
			data-initial-remaining="<?php echo esc_attr( $remaining ); ?>"
			data-initial-name="<?php echo esc_attr( $member_name ); ?>"
			data-default-attempts="<?php echo esc_attr( $default_attempts ); ?>">

			<div class="wtlw-hero">
				<span class="wtlw-kicker"><?php echo esc_html__( 'باشگاه مشتریان • شانس ویژه خرید', 'webtanan-lucky-wheel' ); ?></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $is_member ? __( 'شانس شما آماده است؛ گردونه را بچرخانید و نتیجه را همان لحظه دریافت کنید.', 'webtanan-lucky-wheel' ) : __( 'فقط نام و شماره موبایل را وارد کنید؛ نیازی به ساخت حساب کاربری نیست.', 'webtanan-lucky-wheel' ) ); ?></p>
			</div>

			<?php if ( ! $is_member ) : ?>
			<form class="wtlw-register-form wtlw-entry-form" novalidate>
				<div class="wtlw-form-heading">
					<span class="wtlw-badge">۱</span>
					<div>
						<h3><?php echo esc_html__( 'ورود سریع به قرعه‌کشی', 'webtanan-lucky-wheel' ); ?></h3>
						<p><?php echo esc_html__( 'نام و موبایل شما برای ثبت شانس و جایزه استفاده می‌شود.', 'webtanan-lucky-wheel' ); ?></p>
					</div>
				</div>
				<div class="wtlw-form-grid wtlw-form-grid-two">
					<label><?php echo esc_html__( 'نام و نام خانوادگی', 'webtanan-lucky-wheel' ); ?><input type="text" name="name" autocomplete="name" required /></label>
					<label><?php echo esc_html__( 'شماره موبایل', 'webtanan-lucky-wheel' ); ?><input type="tel" name="phone" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۱۲۳۴۵۶۷" required /></label>
				</div>
				<button type="submit" class="wtlw-button wtlw-register-button"><?php echo esc_html( $ui['entry_button_text'] ); ?><span>←</span></button>
				<div class="wtlw-form-message" role="alert" aria-live="polite"></div>
			</form>
			<?php endif; ?>

			<div class="wtlw-game <?php echo esc_attr( $is_member ? '' : 'wtlw-is-hidden' ); ?>" data-remaining="<?php echo esc_attr( $remaining ); ?>">
				<div class="wtlw-welcome"><span><?php echo esc_html__( 'خوش آمدید', 'webtanan-lucky-wheel' ); ?></span> <strong class="wtlw-participant-name"><?php echo esc_html( $member_name ); ?></strong></div>
				<div class="wtlw-attempts">
					<span class="wtlw-attempt-icon">✦</span>
					<span><?php echo esc_html__( 'شانس باقی‌مانده', 'webtanan-lucky-wheel' ); ?></span>
					<strong class="wtlw-attempt-count"><?php echo esc_html( number_format_i18n( $remaining ) ); ?></strong>
				</div>

				<div class="wtlw-wheel-stage">
					<div class="wtlw-pointer" aria-hidden="true"></div>
					<div class="wtlw-wheel" style="--wtlw-gradient: <?php echo esc_attr( $gradient ); ?>" role="img" aria-label="<?php echo esc_attr__( 'گردونه شانس', 'webtanan-lucky-wheel' ); ?>">
						<div class="wtlw-wheel-labels">
							<?php foreach ( $sections as $index => $section ) :
								$label_angle = ( $index * $angle ) + ( $angle / 2 );
								$flip        = ( $label_angle > 90 && $label_angle < 270 ) ? 180 : 0;
							?>
							<span style="--wtlw-label-angle:<?php echo esc_attr( $label_angle ); ?>deg;--wtlw-label-flip:<?php echo esc_attr( $flip ); ?>deg;" data-label-angle="<?php echo esc_attr( $label_angle ); ?>" title="<?php echo esc_attr( $section['name'] ); ?>">
								<i class="wtlw-segment-icon"><?php echo esc_html( $section['icon'] ); ?></i>
								<b><?php echo esc_html( $section['name'] ); ?></b>
							</span>
							<?php endforeach; ?>
						</div>

						<div class="wtlw-wheel-hub <?php echo esc_attr( $hub_logo ? 'wtlw-wheel-hub-logo' : '' ); ?>">
							<?php if ( $hub_logo ) : ?>
								<img class="wtlw-hub-logo" src="<?php echo esc_url( $hub_logo ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="eager" decoding="async" />
							<?php else : ?>
								<span><?php echo esc_html__( 'شانس', 'webtanan-lucky-wheel' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<button type="button" class="wtlw-button wtlw-spin-button" <?php disabled( $is_member && $remaining < 1 ); ?>><?php echo esc_html( $ui['spin_button_text'] ); ?><span>✦</span></button>
				<div class="wtlw-spin-message" role="status" aria-live="polite"></div>
			</div>

			<div class="wtlw-trust-row">
				<span>🔒 <?php echo esc_html( $is_member ? __( 'شناسایی خودکار کاربر واردشده', 'webtanan-lucky-wheel' ) : __( 'بدون ساخت حساب کاربری', 'webtanan-lucky-wheel' ) ); ?></span>
				<span>✧ <?php echo esc_html__( 'انتخاب تصادفی و منصفانه', 'webtanan-lucky-wheel' ); ?></span>
				<span>⚡ <?php echo esc_html__( 'اعلام نتیجه فوری', 'webtanan-lucky-wheel' ); ?></span>
			</div>

			<div class="wtlw-modal" aria-hidden="true">
				<div class="wtlw-modal-backdrop"></div>
				<div class="wtlw-modal-card" role="dialog" aria-modal="true">
					<button type="button" class="wtlw-modal-close" aria-label="<?php echo esc_attr__( 'بستن', 'webtanan-lucky-wheel' ); ?>">×</button>
					<div class="wtlw-confetti" aria-hidden="true"></div>
					<span class="wtlw-result-sparkle">✦</span>
					<h3><?php echo esc_html__( 'تبریک!', 'webtanan-lucky-wheel' ); ?></h3>
					<p class="wtlw-result-name"></p>
					<div class="wtlw-result-code"></div>
					<button type="button" class="wtlw-button wtlw-modal-ok"><?php echo esc_html( $ui['result_button_text'] ); ?></button>
				</div>
			</div>
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
