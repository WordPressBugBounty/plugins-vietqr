<?php
/**
 * Mã QR và thông tin chuyển khoản trên trang cảm ơn và email.
 *
 * Theme có thể ghi đè bằng cách copy file này vào yourtheme/vietqr/bank-details.php.
 *
 * @var WC_Order $order
 * @var array    $account_fields Kết quả filter woocommerce_vietqr_account_fields.
 * @var string   $qrcode_url     Ảnh QR.
 * @var string   $qrcode_page    Trang tải QR.
 * @var bool     $is_sent_email  Đang render trong email.
 * @var bool     $show_download  Hiện nút lưu ảnh QR (điện thoại, không phải email).
 */

defined('ABSPATH') || exit;

$vietqr_memo = $account_fields['memo']['value'];
?>
<section class="woocommerce-vietqr-qr-scan">
	<?php if ($qrcode_url) : ?>
		<h2 class="wc-vietqr-bank-details-heading" style="text-align: center;"><?php esc_html_e('Bank transfer QR code', 'vietqr'); ?></h2>
		<div>
			<div id="qrcode" style="text-align: center;">
				<img src="<?php echo esc_url($qrcode_url); ?>" alt="<?php esc_attr_e('VietQR QR Image', 'vietqr'); ?>" width="400px" />
			</div>

			<?php if ($is_sent_email) : ?>
				<a style="max-width:200px; margin: 0 auto;background-color: limegreen; color:white; display:block" href="<?php echo esc_url($qrcode_page); ?>" target="_blank">
					<?php esc_html_e('Download QR Code', 'vietqr'); ?>
				</a>
			<?php endif; ?>

			<a id="downloadQR" download="vietqr_<?php echo esc_attr($account_fields['account_number']['value']); ?>.jpg" href="<?php echo esc_url($qrcode_url); ?>">
				<button id="btnDownloadQR">
					<div style="width: 100%;display: flex;align-items: center;justify-content: flex-start;">
						<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 3H11V1H1V11H3V3Z" fill="white"/>
							<path d="M11 11V5H5V11H11ZM7 7H9V9H7V7Z" fill="white"/>
							<path d="M20.5 3H29V11H31V1H20.5V3Z" fill="white"/>
							<path d="M27 11V5H21V11H27ZM23 7H25V9H23V7Z" fill="white"/>
							<path d="M11 29H3V21H1V31H11V29Z" fill="white"/>
							<path d="M11 21H5V27H11V21ZM9 25H7V23H9V25Z" fill="white"/>
							<path d="M29 29H20.5V31H31V21H29V29Z" fill="white"/>
							<path d="M17 19H25V23H27V17H17V19Z" fill="white"/>
							<path d="M27 27V25H15V17H5V19H13V27H27Z" fill="white"/>
							<path d="M13 5H15V11H13V5Z" fill="white"/>
							<path d="M5 15H19V5H17V13H5V15Z" fill="white"/>
							<path d="M21 13H27V15H21V13Z" fill="white"/>
							<path d="M21 21H23V23H21V21Z" fill="white"/>
							<path d="M17 21H19V23H17V21Z" fill="white"/>
						</svg>
						<div style="margin-left: 16px;text-align: left;">
							<span style="display:block; font-size:16px; font-weight:bold;"><?php esc_html_e('SAVE QR IMAGE', 'vietqr'); ?></span>
							<span style="font-size: 12px;"><i><?php esc_html_e('Then open the banking App and scan the transfer QR', 'vietqr'); ?></i></span>
						</div>
					</div>
				</button>
			</a>
		</div>
	<?php endif; ?>
</section>

<section class="woocommerce-vietqr-bank-details">
	<h2 class="wc-vietqr-bank-details-heading" style="text-align: center;"><?php esc_html_e('Our bank details', 'vietqr'); ?></h2>

	<h4 style="color: red;">
		<?php
		/* translators: %s: nội dung chuyển khoản */
		printf(wp_kses(__('Please transfer the correct content <b>%s</b> for we can confirm the payment', 'vietqr'), array('b' => array())), esc_html($vietqr_memo));
		?>
	</h4>

	<table class="table table-bordered" style="font-size: 15px;max-width: 800px;margin-left: auto;margin-right: auto;">
		<tbody>
			<tr>
				<td class="text-right width-column-25" style="text-align: right;"><strong style="color: black;"><?php esc_html_e('Account name', 'vietqr'); ?>:</strong></td>
				<td class="text-left payment-instruction width-column-25" style="text-align: left;"><span style="color: black;"><?php echo esc_html($account_fields['account_name']['value']); ?></span></td>
			</tr>
			<tr style="background-color:#FBFBFB;">
				<td class="text-right width-column-25" style="text-align: right;"><strong style="color: black;"><?php esc_html_e('Account number', 'vietqr'); ?>:</strong></td>
				<td class="text-left payment-instruction width-column-25" style="text-align: left;"><span style="color: black;"><?php echo esc_html($account_fields['account_number']['value']); ?></span></td>
			</tr>
			<tr>
				<td class="text-right width-column-25" style="text-align: right;"><strong style="color: black;"><?php esc_html_e('Bank', 'vietqr'); ?>:</strong></td>
				<td class="text-left payment-instruction width-column-25" style="text-align: left;"><span style="color: black;"><?php echo esc_html($account_fields['bank_name']['value']); ?></span></td>
			</tr>
			<tr>
				<td class="text-right width-column-25" style="text-align: right;"><strong style="color: black;"><?php esc_html_e('Amount', 'vietqr'); ?>:</strong></td>
				<td class="text-left payment-instruction width-column-25" style="text-align: left;"><span style="color: black;"><?php echo wp_kses_post(wc_price($order->get_total(), array('currency' => $order->get_currency()))); ?></span></td>
			</tr>
			<tr>
				<td class="text-right width-column-25" style="text-align: right;"><strong style="color: black;"><?php esc_html_e('Memo', 'vietqr'); ?>*:</strong></td>
				<td class="text-left payment-instruction width-column-25" style="text-align: left;"><strong><?php echo esc_html($vietqr_memo); ?></strong></td>
			</tr>
		</tbody>
	</table>
</section>

<style>
	#downloadQR {
		z-index: 333;
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		display: <?php echo $show_download ? 'block' : 'none'; ?>;
	}
	#btnDownloadQR {
		width: 100%;
		border-radius: 0;
		padding-left: 10px !important;
		padding-right: 10px !important;
		border-color: #0274be;
		background-color: #0274be;
		color: #ffffff;
		line-height: 1;
	}
	.width-column-25 {
		width: 25%;
	}
	.woocommerce-vietqr-qr-scan {
		text-align: center;
		margin-top: 0px;
	}
	.woocommerce-vietqr-bank-details {
		text-align: center;
		margin-top: 10px;
	}
	<?php if ($is_sent_email) : ?>
	.table-bordered {
		border: 1px solid rgba(0,0,0,.1);
	}
	<?php endif; ?>
</style>
