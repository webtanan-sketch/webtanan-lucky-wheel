<?php
/** @var float  $balance */
/** @var array  $transactions */
/** @var array  $coupons */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="wtlw-account" dir="rtl">
	<div class="wtlw-account-header"><div><span class="wtlw-kicker"><?php echo esc_html__( 'Customer club', 'webtanan-lucky-wheel' ); ?></span><h2><?php echo esc_html__( 'My Rewards', 'webtanan-lucky-wheel' ); ?></h2></div><div class="wtlw-balance"><small><?php echo esc_html__( 'Wallet balance', 'webtanan-lucky-wheel' ); ?></small><strong><?php echo esc_html( number_format_i18n( $balance ) ); ?> <em><?php echo esc_html__( 'Toman', 'webtanan-lucky-wheel' ); ?></em></strong></div></div>
	<div class="wtlw-account-grid">
		<div class="wtlw-account-card"><h3><?php echo esc_html__( 'Discount codes', 'webtanan-lucky-wheel' ); ?></h3><?php if ( empty( $coupons ) ) : ?><p class="wtlw-muted"><?php echo esc_html__( 'You have not received a code yet.', 'webtanan-lucky-wheel' ); ?></p><?php else : ?><ul class="wtlw-coupon-list"><?php foreach ( $coupons as $coupon ) : ?><li><code><?php echo esc_html( $coupon['coupon_code'] ); ?></code><span><?php echo esc_html( $coupon['reward_name'] ); ?></span><small><?php echo esc_html( $coupon['status'] ); ?></small></li><?php endforeach; ?></ul><?php endif; ?></div>
		<div class="wtlw-account-card"><h3><?php echo esc_html__( 'Credit history', 'webtanan-lucky-wheel' ); ?></h3><?php if ( empty( $transactions ) ) : ?><p class="wtlw-muted"><?php echo esc_html__( 'No transactions yet.', 'webtanan-lucky-wheel' ); ?></p><?php else : ?><ul class="wtlw-transaction-list"><?php foreach ( $transactions as $transaction ) : ?><li><span><?php echo esc_html( $transaction['description'] ); ?></span><strong class="<?php echo esc_attr( (float) $transaction['amount'] >= 0 ? 'is-credit' : 'is-debit' ); ?>"><?php echo esc_html( ( (float) $transaction['amount'] >= 0 ? '+' : '' ) . number_format_i18n( $transaction['amount'] ) ); ?></strong></li><?php endforeach; ?></ul><?php endif; ?></div>
	</div>
</section>
