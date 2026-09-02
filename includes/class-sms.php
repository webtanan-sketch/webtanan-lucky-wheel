<?php
/** IPPanel Edge SMS integration and admin settings. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTLW_SMS {
	const OPTION_KEY = 'webtanan_lucky_wheel_sms';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_wtlw_save_sms', array( $this, 'save' ) );
	}

	public static function defaults() {
		return array(
			'enabled'        => 0,
			'base_url'       => 'https://edge.ippanel.com/v1',
			'api_key'        => '',
			'sender'         => '',
			'active_pattern' => 'winner',
			'patterns'       => array(
				array(
					'id'           => 'winner',
					'name'         => 'پیام برنده کد تخفیف',
					'code'         => '',
					'message'      => 'تبریک {name}، کد تخفیف شما {coupon} است و تا {expiry} اعتبار دارد.',
					'param_name'   => 'name',
					'param_coupon' => 'code',
					'param_expiry' => 'expiry',
					'param_reward' => 'reward',
					'param_message'=> 'message',
				),
			),
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		$settings['enabled']  = ! empty( $settings['enabled'] ) ? 1 : 0;
		$settings['base_url'] = esc_url_raw( isset( $settings['base_url'] ) ? $settings['base_url'] : self::defaults()['base_url'] );
		$settings['api_key']  = isset( $settings['api_key'] ) ? sanitize_text_field( $settings['api_key'] ) : '';
		$settings['sender']   = isset( $settings['sender'] ) ? sanitize_text_field( $settings['sender'] ) : '';
		$settings['active_pattern'] = isset( $settings['active_pattern'] ) ? sanitize_key( $settings['active_pattern'] ) : 'winner';
		$patterns = array();
		if ( ! empty( $settings['patterns'] ) && is_array( $settings['patterns'] ) ) {
			foreach ( $settings['patterns'] as $pattern ) {
				if ( ! is_array( $pattern ) ) {
					continue;
				}
				$id = isset( $pattern['id'] ) ? sanitize_key( $pattern['id'] ) : '';
				if ( ! $id ) {
					$id = 'pattern-' . ( count( $patterns ) + 1 );
				}
				$patterns[] = array(
					'id'            => $id,
					'name'          => isset( $pattern['name'] ) ? sanitize_text_field( $pattern['name'] ) : '',
					'code'          => isset( $pattern['code'] ) ? sanitize_text_field( $pattern['code'] ) : '',
					'message'       => isset( $pattern['message'] ) ? sanitize_textarea_field( $pattern['message'] ) : '',
					'param_name'    => isset( $pattern['param_name'] ) ? sanitize_key( $pattern['param_name'] ) : 'name',
					'param_coupon'  => isset( $pattern['param_coupon'] ) ? sanitize_key( $pattern['param_coupon'] ) : 'code',
					'param_expiry'  => isset( $pattern['param_expiry'] ) ? sanitize_key( $pattern['param_expiry'] ) : 'expiry',
					'param_reward'  => isset( $pattern['param_reward'] ) ? sanitize_key( $pattern['param_reward'] ) : 'reward',
					'param_message' => isset( $pattern['param_message'] ) ? sanitize_key( $pattern['param_message'] ) : 'message',
				);
			}
		}
		if ( empty( $patterns ) ) {
			$patterns = self::defaults()['patterns'];
		}
		$settings['patterns'] = $patterns;
		return $settings;
	}

	public function menu() {
		add_submenu_page(
			'wtlw-dashboard',
			__( 'پیامک IPPanel', 'webtanan-lucky-wheel' ),
			__( 'پیامک IPPanel', 'webtanan-lucky-wheel' ),
			'manage_options',
			'wtlw-sms',
			array( $this, 'render' )
		);
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه انجام این عملیات را ندارید.', 'webtanan-lucky-wheel' ) );
		}
		check_admin_referer( 'wtlw_save_sms' );
		$old = self::get_settings();
		$patterns = array();
		$raw_patterns = isset( $_POST['patterns'] ) && is_array( $_POST['patterns'] ) ? wp_unslash( $_POST['patterns'] ) : array();
		foreach ( $raw_patterns as $index => $pattern ) {
			if ( ! is_array( $pattern ) ) {
				continue;
			}
			$id = isset( $pattern['id'] ) ? sanitize_key( $pattern['id'] ) : '';
			if ( ! $id ) {
				$id = 'pattern-' . ( $index + 1 );
			}
			$patterns[] = array(
				'id'            => $id,
				'name'          => isset( $pattern['name'] ) ? sanitize_text_field( $pattern['name'] ) : '',
				'code'          => isset( $pattern['code'] ) ? sanitize_text_field( $pattern['code'] ) : '',
				'message'       => isset( $pattern['message'] ) ? sanitize_textarea_field( $pattern['message'] ) : '',
				'param_name'    => isset( $pattern['param_name'] ) ? sanitize_key( $pattern['param_name'] ) : 'name',
				'param_coupon'  => isset( $pattern['param_coupon'] ) ? sanitize_key( $pattern['param_coupon'] ) : 'code',
				'param_expiry'  => isset( $pattern['param_expiry'] ) ? sanitize_key( $pattern['param_expiry'] ) : 'expiry',
				'param_reward'  => isset( $pattern['param_reward'] ) ? sanitize_key( $pattern['param_reward'] ) : 'reward',
				'param_message' => isset( $pattern['param_message'] ) ? sanitize_key( $pattern['param_message'] ) : 'message',
			);
		}
		if ( empty( $patterns ) ) {
			$patterns = self::defaults()['patterns'];
		}
		$api_key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( '' === $api_key ) {
			$api_key = $old['api_key'];
		}
		$settings = array(
			'enabled'        => ! empty( $_POST['enabled'] ) ? 1 : 0,
			'base_url'       => isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : self::defaults()['base_url'],
			'api_key'        => $api_key,
			'sender'         => isset( $_POST['sender'] ) ? sanitize_text_field( wp_unslash( $_POST['sender'] ) ) : '',
			'active_pattern' => isset( $_POST['active_pattern'] ) ? sanitize_key( wp_unslash( $_POST['active_pattern'] ) ) : 'winner',
			'patterns'       => $patterns,
		);
		update_option( self::OPTION_KEY, $settings, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wtlw-sms', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::get_settings();
		?>
		<div class="wrap wtlw-admin-wrap" dir="rtl">
			<div class="wtlw-admin-heading">
				<span class="wtlw-admin-kicker"><?php echo esc_html__( 'اتصال پیامکی', 'webtanan-lucky-wheel' ); ?></span>
				<h1><?php echo esc_html__( 'تنظیمات IPPanel و پترن‌ها', 'webtanan-lucky-wheel' ); ?></h1>
				<p class="description"><?php echo esc_html__( 'ارسال کد تخفیف برنده از طریق IPPanel Edge API و پترن‌های قابل تعریف.', 'webtanan-lucky-wheel' ); ?></p>
			</div>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'تنظیمات پیامک ذخیره شد.', 'webtanan-lucky-wheel' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtlw-settings-form">
				<input type="hidden" name="action" value="wtlw_save_sms" />
				<?php wp_nonce_field( 'wtlw_save_sms' ); ?>
				<div class="wtlw-admin-panel">
					<h2><?php echo esc_html__( 'اتصال API', 'webtanan-lucky-wheel' ); ?></h2>
					<label class="wtlw-checkbox"><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> /> <?php echo esc_html__( 'ارسال پیامک جایزه فعال باشد', 'webtanan-lucky-wheel' ); ?></label>
					<div class="wtlw-field-grid">
						<label><?php echo esc_html__( 'آدرس پایه API', 'webtanan-lucky-wheel' ); ?><input type="url" name="base_url" dir="ltr" value="<?php echo esc_attr( $settings['base_url'] ); ?>" placeholder="https://edge.ippanel.com/v1" /></label>
						<label><?php echo esc_html__( 'API Key / Token', 'webtanan-lucky-wheel' ); ?><input type="password" name="api_key" dir="ltr" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['api_key'] ? 'برای حفظ کلید فعلی خالی بگذارید' : 'کلید دسترسی IPPanel' ); ?>" /></label>
						<label><?php echo esc_html__( 'شماره ارسال‌کننده', 'webtanan-lucky-wheel' ); ?><input type="text" name="sender" dir="ltr" value="<?php echo esc_attr( $settings['sender'] ); ?>" placeholder="+983000505" /></label>
					</div>
					<p class="description"><?php echo esc_html__( 'طبق مستندات Edge، درخواست پترن به /api/send ارسال و توکن در هدر Authorization قرار می‌گیرد.', 'webtanan-lucky-wheel' ); ?></p>
				</div>

				<div class="wtlw-admin-panel">
					<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
						<div><h2 style="margin-bottom:4px"><?php echo esc_html__( 'مدیریت پترن‌ها', 'webtanan-lucky-wheel' ); ?></h2><p class="description"><?php echo esc_html__( 'می‌توانید چند پترن تعریف کنید و یکی را برای پیام برنده فعال کنید.', 'webtanan-lucky-wheel' ); ?></p></div>
						<button type="button" class="button" id="wtlw-add-pattern"><?php echo esc_html__( 'افزودن پترن', 'webtanan-lucky-wheel' ); ?></button>
					</div>
					<div id="wtlw-pattern-list">
					<?php foreach ( array_values( $settings['patterns'] ) as $index => $pattern ) : ?>
						<div class="wtlw-section-row wtlw-sms-pattern" data-index="<?php echo esc_attr( $index ); ?>">
							<input type="hidden" name="patterns[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $pattern['id'] ); ?>" />
							<div class="wtlw-section-top"><strong><?php echo esc_html( $pattern['name'] ? $pattern['name'] : sprintf( 'پترن %d', $index + 1 ) ); ?></strong><label class="wtlw-checkbox"><input type="radio" name="active_pattern" value="<?php echo esc_attr( $pattern['id'] ); ?>" <?php checked( $settings['active_pattern'], $pattern['id'] ); ?> /> <?php echo esc_html__( 'پترن فعال', 'webtanan-lucky-wheel' ); ?></label><button type="button" class="button-link-delete wtlw-remove-pattern"><?php echo esc_html__( 'حذف', 'webtanan-lucky-wheel' ); ?></button></div>
							<div class="wtlw-field-grid">
								<label><?php echo esc_html__( 'نام پترن', 'webtanan-lucky-wheel' ); ?><input type="text" name="patterns[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $pattern['name'] ); ?>" /></label>
								<label><?php echo esc_html__( 'کد پترن IPPanel', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $pattern['code'] ); ?>" /></label>
								<label style="grid-column:1/-1"><?php echo esc_html__( 'متن قابل مدیریت', 'webtanan-lucky-wheel' ); ?><textarea rows="3" name="patterns[<?php echo esc_attr( $index ); ?>][message]"><?php echo esc_textarea( $pattern['message'] ); ?></textarea><small>{name}، {coupon}، {expiry}، {reward}</small></label>
								<label><?php echo esc_html__( 'پارامتر نام', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][param_name]" value="<?php echo esc_attr( $pattern['param_name'] ); ?>" /></label>
								<label><?php echo esc_html__( 'پارامتر کد تخفیف', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][param_coupon]" value="<?php echo esc_attr( $pattern['param_coupon'] ); ?>" /></label>
								<label><?php echo esc_html__( 'پارامتر تاریخ انقضا', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][param_expiry]" value="<?php echo esc_attr( $pattern['param_expiry'] ); ?>" /></label>
								<label><?php echo esc_html__( 'پارامتر نام جایزه', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][param_reward]" value="<?php echo esc_attr( $pattern['param_reward'] ); ?>" /></label>
								<label><?php echo esc_html__( 'پارامتر متن کامل', 'webtanan-lucky-wheel' ); ?><input type="text" dir="ltr" name="patterns[<?php echo esc_attr( $index ); ?>][param_message]" value="<?php echo esc_attr( $pattern['param_message'] ); ?>" /></label>
							</div>
						</div>
					<?php endforeach; ?>
					</div>
				</div>
				<p><button type="submit" class="button button-primary button-large"><?php echo esc_html__( 'ذخیره تنظیمات پیامک', 'webtanan-lucky-wheel' ); ?></button></p>
			</form>
		</div>
		<script>
		(function(){
			var list=document.getElementById('wtlw-pattern-list'),add=document.getElementById('wtlw-add-pattern');
			if(!list||!add){return;}
			function bindRemove(scope){scope.querySelectorAll('.wtlw-remove-pattern').forEach(function(btn){btn.onclick=function(){var rows=list.querySelectorAll('.wtlw-sms-pattern');if(rows.length>1){btn.closest('.wtlw-sms-pattern').remove();}};});}
			add.addEventListener('click',function(){var i=Date.now(),id='pattern-'+i,box=document.createElement('div');box.className='wtlw-section-row wtlw-sms-pattern';box.innerHTML='<input type="hidden" name="patterns['+i+'][id]" value="'+id+'"><div class="wtlw-section-top"><strong>پترن جدید</strong><label class="wtlw-checkbox"><input type="radio" name="active_pattern" value="'+id+'"> پترن فعال</label><button type="button" class="button-link-delete wtlw-remove-pattern">حذف</button></div><div class="wtlw-field-grid"><label>نام پترن<input type="text" name="patterns['+i+'][name]"></label><label>کد پترن IPPanel<input type="text" dir="ltr" name="patterns['+i+'][code]"></label><label style="grid-column:1/-1">متن قابل مدیریت<textarea rows="3" name="patterns['+i+'][message]">تبریک {name}، کد تخفیف شما {coupon} است و تا {expiry} اعتبار دارد.</textarea><small>{name}، {coupon}، {expiry}، {reward}</small></label><label>پارامتر نام<input type="text" dir="ltr" name="patterns['+i+'][param_name]" value="name"></label><label>پارامتر کد تخفیف<input type="text" dir="ltr" name="patterns['+i+'][param_coupon]" value="code"></label><label>پارامتر تاریخ انقضا<input type="text" dir="ltr" name="patterns['+i+'][param_expiry]" value="expiry"></label><label>پارامتر نام جایزه<input type="text" dir="ltr" name="patterns['+i+'][param_reward]" value="reward"></label><label>پارامتر متن کامل<input type="text" dir="ltr" name="patterns['+i+'][param_message]" value="message"></label></div>';list.appendChild(box);bindRemove(box);});
			bindRemove(list);
		}());
		</script>
		<?php
	}

	public function send_user_coupon( $user_id, $coupon_code, $expiry_days, $reward_name ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return array( 'sent' => false, 'message' => 'user_not_found' );
		}
		$phone = WTLW_Database::get_user_mobile( (int) $user_id );
		return $this->send_coupon( $user->display_name, $phone, $coupon_code, $expiry_days, $reward_name );
	}

	public function send_participant_coupon( $participant_id, $coupon_code, $expiry_days, $reward_name ) {
		$participant = WTLW_Database::get_participant( (int) $participant_id );
		if ( ! $participant ) {
			return array( 'sent' => false, 'message' => 'participant_not_found' );
		}
		return $this->send_coupon( $participant->name, $participant->phone, $coupon_code, $expiry_days, $reward_name );
	}

	private function send_coupon( $name, $phone, $coupon_code, $expiry_days, $reward_name ) {
		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return array( 'sent' => false, 'message' => 'disabled' );
		}
		$pattern = $this->find_pattern( $settings['patterns'], $settings['active_pattern'] );
		if ( ! $pattern || empty( $pattern['code'] ) || empty( $settings['api_key'] ) || empty( $settings['sender'] ) ) {
			return array( 'sent' => false, 'message' => 'incomplete_settings' );
		}
		$recipient = self::to_e164( $phone );
		if ( ! $recipient ) {
			return array( 'sent' => false, 'message' => 'invalid_mobile' );
		}
		$expiry_timestamp = time() + ( DAY_IN_SECONDS * max( 0, (int) $expiry_days ) );
		$expiry = self::jalali_date( $expiry_timestamp );
		$message = strtr(
			$pattern['message'],
			array( '{name}' => (string) $name, '{coupon}' => (string) $coupon_code, '{expiry}' => $expiry, '{reward}' => (string) $reward_name )
		);
		$params = array();
		$this->put_param( $params, $pattern['param_name'], (string) $name );
		$this->put_param( $params, $pattern['param_coupon'], (string) $coupon_code );
		$this->put_param( $params, $pattern['param_expiry'], $expiry );
		$this->put_param( $params, $pattern['param_reward'], (string) $reward_name );
		$this->put_param( $params, $pattern['param_message'], $message );
		$base = rtrim( $settings['base_url'], '/' );
		$endpoint = preg_match( '#/api/send$#', $base ) ? $base : $base . '/api/send';
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => $settings['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body' => wp_json_encode(
					array(
						'sending_type' => 'pattern',
						'from_number'  => $settings['sender'],
						'code'         => $pattern['code'],
						'recipients'   => array( $recipient ),
						'params'       => (object) $params,
					),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'sent' => false, 'message' => $response->get_error_message() );
		}
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok = $status_code >= 200 && $status_code < 300 && ( ! isset( $body['meta']['status'] ) || true === $body['meta']['status'] );
		return array( 'sent' => $ok, 'message' => $ok ? 'sent' : ( isset( $body['meta']['message'] ) ? sanitize_text_field( $body['meta']['message'] ) : 'api_error' ), 'expiry' => $expiry );
	}

	private function find_pattern( $patterns, $id ) {
		foreach ( $patterns as $pattern ) {
			if ( isset( $pattern['id'] ) && $id === $pattern['id'] ) {
				return $pattern;
			}
		}
		return isset( $patterns[0] ) ? $patterns[0] : false;
	}

	private function put_param( &$params, $key, $value ) {
		$key = sanitize_key( $key );
		if ( $key ) {
			$params[ $key ] = $value;
		}
	}

	public static function to_e164( $phone ) {
		$mobile = WTLW_Database::normalize_iran_mobile( $phone );
		return $mobile ? '+98' . substr( $mobile, 1 ) : '';
	}

	public static function jalali_date( $timestamp ) {
		$timezone = wp_timezone();
		$gy = (int) wp_date( 'Y', $timestamp, $timezone );
		$gm = (int) wp_date( 'n', $timestamp, $timezone );
		$gd = (int) wp_date( 'j', $timestamp, $timezone );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}

	private static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2 = $gm > 2 ? $gy + 1 : $gy;
		$days = 355666 + ( 365 * $gy ) + (int) floor( ( $gy2 + 3 ) / 4 ) - (int) floor( ( $gy2 + 99 ) / 100 ) + (int) floor( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy = -1595 + ( 33 * (int) floor( $days / 12053 ) );
		$days %= 12053;
		$jy += 4 * (int) floor( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy += (int) floor( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) floor( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) floor( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}
}
