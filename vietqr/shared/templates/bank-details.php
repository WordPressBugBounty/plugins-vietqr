<?php
/**
 * Khối thanh toán VietQR trên trang cảm ơn và trang xem đơn hàng.
 *
 * Theme có thể ghi đè bằng cách copy file này vào yourtheme/vietqr/bank-details.php
 * (Casso Flow: yourtheme/casso/bank-details.php).
 * Mọi class đều có tiền tố "vietqr", hành vi JS gắn qua thuộc tính data-vietqr-*.
 *
 * @var WC_Order $order
 * @var array    $account_fields Kết quả filter woocommerce_vietqr_account_fields.
 * @var string   $qrcode_url     Ảnh QR (SVG) để hiển thị.
 * @var string   $qrcode_png     Ảnh QR (PNG, goal=download) cho nút lưu ảnh.
 * @var string   $context        thankyou | view_order.
 * @var array    $deeplink_apps  array('android' => [...], 'ios' => [...]), mỗi app: id, name, logo, autofill, href.
 * @var string   $status         pending | paid.
 * @var array    $status_check   null, hoặc array('status_url' => ..., 'check_url' => ...): trang tự kiểm tra thanh toán.
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- template chạy trong wc_get_template() nên biến là biến cục bộ; hook vietqr_* dùng chung giữa các plugin

$vietqr_memo     = $account_fields['memo']['value'];
$vietqr_title_id = wp_unique_id('vietqr-title-');
$vietqr_amount   = wc_price($order->get_total(), array('currency' => $order->get_currency()));
$status          = isset($status) ? $status : 'pending';
$status_check    = isset($status_check) ? $status_check : null;

if ('paid' === $status) :
	?>
	<section class="vietqr vietqr--paid" aria-labelledby="<?php echo esc_attr($vietqr_title_id); ?>">
		<div class="vietqr__paid">
			<svg class="vietqr__paid-icon" viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="m7 12.5 3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			<h2 class="vietqr__title" id="<?php echo esc_attr($vietqr_title_id); ?>"><?php esc_html_e('Payment received', 'vietqr'); ?></h2>
			<p class="vietqr__hint">
				<?php
				/* translators: %s: số tiền */
				printf(esc_html__('We have received your transfer of %s. Thank you!', 'vietqr'), wp_kses_post($vietqr_amount));
				?>
			</p>
		</div>
	</section>
	<?php
	return;
endif;

$vietqr_copy_button = function ($value, $label) {
	return sprintf(
		'<button type="button" class="vietqr__copy" data-vietqr-copy="%1$s" aria-label="%2$s">%3$s</button>',
		esc_attr($value),
		/* translators: %s: tên trường, vd. Số tài khoản */
		esc_attr(sprintf(__('Copy %s', 'vietqr'), $label)),
		esc_html__('Copy', 'vietqr')
	);
};
$vietqr_allowed_button = array(
	'button' => array(
		'type'             => true,
		'class'            => true,
		'data-vietqr-copy' => true,
		'aria-label'       => true,
	),
);

// label, giá trị (HTML đã escape), giá trị để sao chép, là dòng nội dung chuyển khoản
$vietqr_rows = array(
	array(__('Bank', 'vietqr'), esc_html($account_fields['bank_name']['value']), '', false),
	array(__('Account name', 'vietqr'), esc_html($account_fields['account_name']['value']), '', false),
	array(__('Account number', 'vietqr'), esc_html($account_fields['account_number']['value']), $account_fields['account_number']['value'], false),
	array(__('Amount', 'vietqr'), wp_kses_post($vietqr_amount), (string) $account_fields['amount']['value'], false),
	array(__('Memo', 'vietqr'), '<strong>' . esc_html($vietqr_memo) . '</strong>', $vietqr_memo, true),
);
?>
<section class="vietqr" data-vietqr
	data-vietqr-copied="<?php esc_attr_e('Copied', 'vietqr'); ?>"
	<?php if ($status_check) : ?>
	data-vietqr-status-url="<?php echo esc_url($status_check['status_url']); ?>"
	<?php if (!empty($status_check['check_url'])) : ?>
	data-vietqr-check-url="<?php echo esc_url($status_check['check_url']); ?>"
	<?php endif; ?>
	data-vietqr-checking="<?php esc_attr_e('Checking for your transfer…', 'vietqr'); ?>"
	data-vietqr-not-found="<?php esc_attr_e('Your transfer has not arrived yet. It can take a few minutes, this page keeps checking.', 'vietqr'); ?>"
	data-vietqr-stopped="<?php esc_attr_e('Still waiting for your transfer. Reload this page to check again.', 'vietqr'); ?>"
	<?php endif; ?>
	aria-labelledby="<?php echo esc_attr($vietqr_title_id); ?>">

	<header class="vietqr__header">
		<h2 class="vietqr__title" id="<?php echo esc_attr($vietqr_title_id); ?>"><?php esc_html_e('Pay by bank transfer', 'vietqr'); ?></h2>
		<p class="vietqr__amount">
			<span class="vietqr__amount-label"><?php esc_html_e('Amount to pay', 'vietqr'); ?></span>
			<span class="vietqr__amount-value"><?php echo wp_kses_post($vietqr_amount); ?></span>
		</p>
	</header>

	<div class="vietqr__body">
		<div class="vietqr__transfer">
			<dl class="vietqr__details">
				<?php foreach ($vietqr_rows as $vietqr_row) : ?>
					<div class="vietqr__row<?php echo $vietqr_row[3] ? ' vietqr__row--memo' : ''; ?>">
						<dt class="vietqr__label"><?php echo esc_html($vietqr_row[0]); ?></dt>
						<dd class="vietqr__field">
							<span class="vietqr__value"><?php echo wp_kses_post($vietqr_row[1]); ?></span>
							<?php if ('' !== $vietqr_row[2]) : ?>
								<?php echo wp_kses($vietqr_copy_button($vietqr_row[2], $vietqr_row[0]), $vietqr_allowed_button); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<p class="vietqr__notice" role="note">
				<?php
				/* translators: %s: nội dung chuyển khoản */
				printf(wp_kses(__('Please transfer the correct content <b>%s</b> for we can confirm the payment', 'vietqr'), array('b' => array())), esc_html($vietqr_memo));
				?>
			</p>
			<?php if ($status_check) : ?>
				<div class="vietqr__status">
					<p class="vietqr__status-text" role="status" aria-live="polite">
						<span class="vietqr__spinner" aria-hidden="true"></span>
						<span data-vietqr-status-text><?php esc_html_e('Waiting for your transfer. This page updates automatically once the payment is received.', 'vietqr'); ?></span>
					</p>
					<?php if (!empty($status_check['check_url'])) : ?>
						<button type="button" class="vietqr__button vietqr__button--secondary" data-vietqr-check><?php esc_html_e('I have transferred', 'vietqr'); ?></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if (!empty($deeplink_apps)) : ?>
			<?php
			// Ô app: logo, tên, tag "Tự động điền" cho app điền sẵn số tài khoản, số tiền, nội dung.
			$vietqr_app_tile = function ($app) {
				ob_start();
				?>
				<li class="vietqr__app-item">
					<a class="vietqr__app<?php echo $app['autofill'] ? ' vietqr__app--autofill' : ''; ?>" href="<?php echo esc_url($app['href']); ?>" rel="nofollow noopener">
						<span class="vietqr__app-logo-wrap">
							<?php if ($app['logo']) : ?>
								<img class="vietqr__app-logo" src="<?php echo esc_url($app['logo']); ?>" alt="" width="44" height="44" loading="lazy" decoding="async">
							<?php endif; ?>
						</span>
						<span class="vietqr__app-name" title="<?php echo esc_attr($app['name']); ?>"><?php echo esc_html($app['name']); ?></span>
						<?php if ($app['autofill']) : ?>
							<span class="vietqr__badge"><?php esc_html_e('Auto-fill', 'vietqr'); ?></span>
						<?php endif; ?>
					</a>
				</li>
				<?php
				return ob_get_clean();
			};
			// Số app hiện sẵn (app tự động điền xếp trước), phần còn lại nằm trong "Xem thêm".
			$vietqr_visible = (int) apply_filters('vietqr_deeplink_visible_apps', 8);
			?>
			<div class="vietqr__apps" data-vietqr-apps hidden>
				<p class="vietqr__section-title"><?php esc_html_e('Open your banking app to pay', 'vietqr'); ?></p>
				<?php foreach ($deeplink_apps as $vietqr_platform => $vietqr_apps) : ?>
					<div class="vietqr__platform" data-vietqr-platform="<?php echo esc_attr($vietqr_platform); ?>" hidden>
						<ul class="vietqr__app-list">
							<?php
							foreach (array_slice($vietqr_apps, 0, $vietqr_visible) as $vietqr_app) {
								echo $vietqr_app_tile($vietqr_app); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong $vietqr_app_tile
							}
							?>
						</ul>
						<?php $vietqr_more = array_slice($vietqr_apps, $vietqr_visible); ?>
						<?php if ($vietqr_more) : ?>
							<details class="vietqr__more-apps">
								<summary>
									<?php
									/* translators: %d: số app ngân hàng còn lại */
									echo esc_html(sprintf(_n('Show %d more app', 'Show %d more apps', count($vietqr_more), 'vietqr'), count($vietqr_more)));
									?>
								</summary>
								<ul class="vietqr__app-list">
									<?php
									foreach ($vietqr_more as $vietqr_app) {
										echo $vietqr_app_tile($vietqr_app); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong $vietqr_app_tile
									}
									?>
								</ul>
							</details>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<p class="vietqr__hint vietqr__apps-legend">
					<span class="vietqr__badge"><?php esc_html_e('Auto-fill', 'vietqr'); ?></span>
					<?php esc_html_e('opens with the account, amount and memo filled in. Other apps: save the QR image, then scan it in the app.', 'vietqr'); ?>
				</p>
				<p class="vietqr__divider"><span><?php esc_html_e('or scan the QR code', 'vietqr'); ?></span></p>
			</div>
		<?php endif; ?>

		<?php if ($qrcode_url) : ?>
			<figure class="vietqr__qr">
				<img class="vietqr__qr-image" src="<?php echo esc_url($qrcode_url); ?>" alt="<?php esc_attr_e('VietQR QR Image', 'vietqr'); ?>" width="490" height="490" decoding="async" crossorigin="anonymous">
				<figcaption class="vietqr__hint"><?php esc_html_e('Scan with any banking app or e-wallet', 'vietqr'); ?></figcaption>
				<button type="button" class="vietqr__button" data-vietqr-save="<?php echo esc_url($qrcode_png); ?>" data-filename="vietqr_<?php echo esc_attr($vietqr_memo); ?>.png">
					<svg class="vietqr__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 3v11m0 0-4-4m4 4 4-4M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<span><?php esc_html_e('Save QR image', 'vietqr'); ?></span>
				</button>
				<p class="vietqr__hint vietqr__save-hint" data-vietqr-save-hint hidden><?php esc_html_e('Touch and hold the QR code, then choose "Save image".', 'vietqr'); ?></p>
			</figure>
		<?php endif; ?>
	</div>
</section>
