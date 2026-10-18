<?php
/**
 * Mã QR và thông tin chuyển khoản trong email HTML.
 *
 * Email client không tải CSS ngoài nên dùng bảng và style inline.
 * Theme có thể ghi đè bằng cách copy file này vào yourtheme/vietqr/emails/bank-details.php.
 *
 * @var WC_Order $order
 * @var array    $account_fields Kết quả filter woocommerce_vietqr_account_fields.
 * @var string   $qrcode_url     Ảnh QR.
 * @var string   $qrcode_page    Link tải ảnh QR.
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- template chạy trong wc_get_template() nên biến là biến cục bộ; hook vietqr_* dùng chung giữa các plugin

$vietqr_memo = $account_fields['memo']['value'];
// Nút theo màu cơ bản của email WooCommerce (WooCommerce > Cài đặt > Email).
$vietqr_button_bg   = sanitize_hex_color(get_option('woocommerce_email_base_color')) ?: '#3858e9';
$vietqr_button_text = function_exists('wc_light_or_dark') ? wc_light_or_dark($vietqr_button_bg, '#1e1e1e', '#ffffff') : '#ffffff';
$vietqr_rows = array(
	__('Bank', 'vietqr')           => esc_html($account_fields['bank_name']['value']),
	__('Account name', 'vietqr')   => esc_html($account_fields['account_name']['value']),
	__('Account number', 'vietqr') => esc_html($account_fields['account_number']['value']),
	__('Amount', 'vietqr')         => wp_kses_post(wc_price($order->get_total(), array('currency' => $order->get_currency()))),
	__('Memo', 'vietqr')           => '<strong>' . esc_html($vietqr_memo) . '</strong>',
);
?>
<table class="vietqr-email" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px; border: 1px solid #e0e0e0; border-radius: 8px; border-collapse: separate;">
	<tr>
		<td style="padding: 24px; text-align: center;">
			<h2 style="margin: 0 0 4px; font-size: 18px;"><?php esc_html_e('Pay by bank transfer', 'vietqr'); ?></h2>
			<p style="margin: 0 0 16px; color: #757575;"><?php esc_html_e('Scan with any banking app or e-wallet', 'vietqr'); ?></p>
			<?php if ($qrcode_url) : ?>
				<img src="<?php echo esc_url($qrcode_url); ?>" alt="<?php esc_attr_e('VietQR QR Image', 'vietqr'); ?>" width="240" height="240" style="display: block; margin: 0 auto 16px; width: 240px; max-width: 100%; height: auto; border: 0;">
				<a href="<?php echo esc_url($qrcode_page); ?>" target="_blank" style="display: inline-block; padding: 10px 20px; border-radius: 4px; background-color: <?php echo esc_attr($vietqr_button_bg); ?>; color: <?php echo esc_attr($vietqr_button_text); ?>; font-weight: 600; text-decoration: none;"><?php esc_html_e('Download QR Code', 'vietqr'); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td style="padding: 0 24px 16px;">
			<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
				<?php foreach ($vietqr_rows as $vietqr_label => $vietqr_value) : ?>
					<tr>
						<td style="padding: 8px 0; border-top: 1px solid #f0f0f0; color: #757575; width: 40%;"><?php echo esc_html($vietqr_label); ?></td>
						<td style="padding: 8px 0; border-top: 1px solid #f0f0f0; text-align: right;"><?php echo wp_kses_post($vietqr_value); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor="#fcf9e8" style="padding: 12px 24px; background-color: #fcf9e8; border-radius: 0 0 8px 8px; color: #1e1e1e;">
			<?php
			/* translators: %s: nội dung chuyển khoản */
			printf(wp_kses(__('Please transfer the correct content <b>%s</b> for we can confirm the payment', 'vietqr'), array('b' => array())), esc_html($vietqr_memo));
			?>
		</td>
	</tr>
</table>
