<?php
/** Admin history view for phone-only participants. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_Guest_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'replace_history' ), 99 );
	}

	public function replace_history() {
		remove_submenu_page( 'wtlw-dashboard', 'wtlw-history' );
		add_submenu_page( 'wtlw-dashboard', __( 'تاریخچه قرعه‌کشی', 'webtanan-lucky-wheel' ), __( 'تاریخچه قرعه‌کشی', 'webtanan-lucky-wheel' ), 'manage_options', 'wtlw-history', array( $this, 'render' ) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows = WTLW_Database::get_history();
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<h1><?php echo esc_html__( 'تاریخچه قرعه‌کشی', 'webtanan-lucky-wheel' ); ?></h1>
			<p><?php echo esc_html__( 'شرکت‌کنندگان جدید بدون ساخت حساب وردپرس و با نام و شماره موبایل ثبت می‌شوند.', 'webtanan-lucky-wheel' ); ?></p>
			<div class="wtlw-admin-panel wtlw-table-wrap">
				<table class="widefat striped">
					<thead><tr><th>نام</th><th>موبایل</th><th>جایزه</th><th>کد تخفیف</th><th>شانس بعد از چرخش</th><th>اعتبار ثبت‌شده</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?><tr><td colspan="8">هنوز سابقه‌ای ثبت نشده است.</td></tr><?php endif; ?>
					<?php foreach ( $rows as $row ) :
						$name = isset( $row->participant_name ) && $row->participant_name ? $row->participant_name : '';
						$phone = isset( $row->participant_phone ) && $row->participant_phone ? $row->participant_phone : '';
						if ( ! $name && ! empty( $row->user_id ) ) {
							$user = get_userdata( $row->user_id );
							$name = $user ? $user->display_name : '#' . $row->user_id;
							$phone = $user ? get_user_meta( $row->user_id, 'wtlw_phone', true ) : '-';
						}
						$status_map = array( 'completed' => 'ثبت‌شده', 'used' => 'استفاده‌شده', 'failed' => 'ناموفق' );
						$status = isset( $status_map[ $row->status ] ) ? $status_map[ $row->status ] : $row->status;
					?>
					<tr>
						<td><?php echo esc_html( $name ? $name : '—' ); ?></td>
						<td dir="ltr"><?php echo esc_html( $phone ? $phone : '—' ); ?></td>
						<td><?php echo esc_html( $row->reward_name ); ?></td>
						<td><code dir="ltr"><?php echo esc_html( $row->coupon_code ? $row->coupon_code : '—' ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->attempts_after ) ); ?></td>
						<td><?php echo esc_html( isset( $row->participant_credit ) ? number_format_i18n( (float) $row->participant_credit ) : '—' ); ?></td>
						<td><?php echo esc_html( get_date_from_gmt( $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td><span class="wtlw-status wtlw-status-<?php echo esc_attr( sanitize_key( $row->status ) ); ?>"><?php echo esc_html( $status ); ?></span></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}

new WTLW_Guest_Admin();
