<?php
/**
 * Thông tin chuyển khoản trong email dạng văn bản thuần (plain text).
 *
 * Theme có thể ghi đè bằng cách copy file này vào yourtheme/vietqr/plain/bank-details.php.
 *
 * @var WC_Order $order
 * @var array    $account_fields Kết quả filter woocommerce_vietqr_account_fields.
 * @var string   $qrcode_page    Link ảnh QR.
 */

defined('ABSPATH') || exit;

$vietqr_amount = html_entity_decode(wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency()))), ENT_QUOTES, 'UTF-8');

$vietqr_lines = array(
	'',
	wc_strtoupper(__('Our bank details', 'vietqr')),
	'',
	/* translators: %s: nội dung chuyển khoản */
	sprintf(wp_strip_all_tags(__('Please transfer the correct content <b>%s</b> for we can confirm the payment', 'vietqr')), $account_fields['memo']['value']),
	'',
	__('Account name', 'vietqr') . ': ' . $account_fields['account_name']['value'],
	__('Account number', 'vietqr') . ': ' . $account_fields['account_number']['value'],
	__('Bank', 'vietqr') . ': ' . $account_fields['bank_name']['value'],
	__('Amount', 'vietqr') . ': ' . $vietqr_amount,
	__('Memo', 'vietqr') . ': ' . $account_fields['memo']['value'],
	'',
	__('Bank transfer QR code', 'vietqr') . ': ' . esc_url_raw($qrcode_page),
	'',
);

// Email văn bản thuần: chỉ bỏ thẻ HTML, không mã hoá ký tự (escape HTML sẽ biến & thành &amp;).
echo wp_strip_all_tags(implode("\n", $vietqr_lines)) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
