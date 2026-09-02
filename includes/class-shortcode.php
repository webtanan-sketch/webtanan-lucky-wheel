<?php
/** Public shortcode and asset loader. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Shortcode {
	/** @var WTLW_Wheel_Engine */
	private $engine;

	public function __construct( WTLW_Wheel_Engine $engine ) {
		$this->engine = $engine;
		add_shortcode( 'webtanan_lucky_wheel', array( $this, 'render' ) );
	}

	/** Render a complete registration gate or game board. */
	public function render( $atts = array() ) {
		if ( ! get_option( 'webtanan_lucky_wheel_active', 1 ) ) {
			return '<div class="wtlw-notice">' . esc_html__( 'The wheel is temporarily disabled.', 'webtanan-lucky-wheel' ) . '</div>';
		}

		$sections = $this->engine->get_sections();
		if ( empty( $sections ) ) {
			return '<div class="wtlw-notice">' . esc_html__( 'No active rewards are configured.', 'webtanan-lucky-wheel' ) . '</div>';
		}
		wp_enqueue_style( 'wtlw-public', WTLW_URL . 'public/css/style.css', array(), WTLW_VERSION );
		wp_enqueue_script( 'wtlw-public', WTLW_URL . 'public/js/wheel.js', array(), WTLW_VERSION, true );
		wp_localize_script(
			'wtlw-public',
			'WTLW_DATA',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wtlw_frontend' ),
				'labels'  => array(
					'spin'       => __( 'Spin the wheel', 'webtanan-lucky-wheel' ),
					'spinning'   => __( 'Spinning...', 'webtanan-lucky-wheel' ),
					'close'      => __( 'Close', 'webtanan-lucky-wheel' ),
					'loginFirst' => __( 'Please register first.', 'webtanan-lucky-wheel' ),
				),
			)
		);

		$attempts = is_user_logged_in() ? $this->engine->get_attempts( get_current_user_id() ) : array( 'remaining_attempts' => 0 );
		$title    = get_option( 'webtanan_lucky_wheel_title', __( 'Spin & Win', 'webtanan-lucky-wheel' ) );
		$angle    = 360 / count( $sections );
		$colors   = array();
		foreach ( $sections as $section ) {
			$colors[] = $section['color'];
		}
		$gradient = 'conic-gradient(';
		foreach ( $colors as $index => $color ) {
			$gradient .= esc_attr( $color ) . ' ' . ( $index * $angle ) . 'deg ' . ( ( $index + 1 ) * $angle ) . 'deg' . ( $index < count( $colors ) - 1 ? ', ' : '' );
		}
		$gradient .= ')';
		ob_start();
		?>
		<div class="wtlw-app" dir="rtl" data-sections="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>">
			<div class="wtlw-hero">
				<span class="wtlw-kicker"><?php echo esc_html__( 'Golden chance campaign', 'webtanan-lucky-wheel' ); ?></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html__( 'One spin, one reward. Try your luck.', 'webtanan-lucky-wheel' ); ?></p>
			</div>
			<?php if ( ! is_user_logged_in() ) : ?>
				<form class="wtlw-register-form" novalidate>
					<div class="wtlw-form-heading"><span class="wtlw-badge">01</span><div><h3><?php echo esc_html__( 'Register to get started', 'webtanan-lucky-wheel' ); ?></h3><p><?php echo esc_html__( 'Your details are only used to securely assign your reward.', 'webtanan-lucky-wheel' ); ?></p></div></div>
					<div class="wtlw-form-grid">
						<label><?php echo esc_html__( 'Full name', 'webtanan-lucky-wheel' ); ?><input type="text" name="name" autocomplete="name" required /></label>
						<label><?php echo esc_html__( 'Mobile phone', 'webtanan-lucky-wheel' ); ?><input type="tel" name="phone" autocomplete="tel" required /></label>
						<label><?php echo esc_html__( 'Email', 'webtanan-lucky-wheel' ); ?><input type="email" name="email" autocomplete="email" required /></label>
						<label><?php echo esc_html__( 'Password (8+ characters)', 'webtanan-lucky-wheel' ); ?><input type="password" name="password" autocomplete="new-password" minlength="8" required /></label>
					</div>
					<button type="submit" class="wtlw-button wtlw-register-button"><?php echo esc_html__( 'Register and claim a chance', 'webtanan-lucky-wheel' ); ?><span>↗</span></button>
					<div class="wtlw-form-message" role="alert" aria-live="polite"></div>
				</form>
			<?php else : ?>
				<div class="wtlw-game" data-remaining="<?php echo esc_attr( $attempts['remaining_attempts'] ); ?>">
					<div class="wtlw-attempts"><span class="wtlw-attempt-icon">✦</span><span><?php echo esc_html__( 'Remaining chances', 'webtanan-lucky-wheel' ); ?></span><strong class="wtlw-attempt-count"><?php echo esc_html( $attempts['remaining_attempts'] ); ?></strong></div>
					<div class="wtlw-wheel-stage">
						<div class="wtlw-pointer" aria-hidden="true"></div>
						<div class="wtlw-wheel" style="--wtlw-gradient: <?php echo esc_attr( $gradient ); ?>" role="img" aria-label="<?php echo esc_attr__( 'Lucky wheel', 'webtanan-lucky-wheel' ); ?>">
							<div class="wtlw-wheel-labels">
								<?php foreach ( $sections as $index => $section ) : ?><span style="--wtlw-label-angle: <?php echo esc_attr( ( $index * $angle ) + ( $angle / 2 ) ); ?>deg;" title="<?php echo esc_attr( $section['name'] ); ?>"><?php echo esc_html( $section['icon'] ); ?><b><?php echo esc_html( $section['name'] ); ?></b></span><?php endforeach; ?>
							</div>
							<div class="wtlw-wheel-hub"><span>WT</span></div>
						</div>
					</div>
					<button type="button" class="wtlw-button wtlw-spin-button" <?php disabled( $attempts['remaining_attempts'] < 1 ); ?>><?php echo esc_html__( 'Spin the wheel', 'webtanan-lucky-wheel' ); ?><span>✦</span></button>
					<div class="wtlw-spin-message" role="status" aria-live="polite"></div>
				</div>
			<?php endif; ?>
			<div class="wtlw-trust-row"><span>🔒 <?php echo esc_html__( 'Secure reward assignment', 'webtanan-lucky-wheel' ); ?></span><span>✧ <?php echo esc_html__( 'Fair random selection', 'webtanan-lucky-wheel' ); ?></span><span>⚡ <?php echo esc_html__( 'Instant result', 'webtanan-lucky-wheel' ); ?></span></div>
			<div class="wtlw-modal" aria-hidden="true"><div class="wtlw-modal-backdrop"></div><div class="wtlw-modal-card" role="dialog" aria-modal="true" aria-labelledby="wtlw-result-title"><button type="button" class="wtlw-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'webtanan-lucky-wheel' ); ?>">×</button><div class="wtlw-confetti" aria-hidden="true"></div><span class="wtlw-result-sparkle">✦</span><h3 id="wtlw-result-title"><?php echo esc_html__( 'Congratulations!', 'webtanan-lucky-wheel' ); ?></h3><p class="wtlw-result-name"></p><div class="wtlw-result-code"></div><button type="button" class="wtlw-button wtlw-modal-ok"><?php echo esc_html__( 'Great!', 'webtanan-lucky-wheel' ); ?></button></div></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
