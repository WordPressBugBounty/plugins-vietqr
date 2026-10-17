<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * VietQR Transfer Payment Gateway.
 *
 * Provides a VietQR Payment Gateway. Based on code by Mike Pepper.
 *
 * @class       WC_Gateway_VietQR
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_VietQR extends WC_Payment_Gateway
{

	public $instructions;
	public $prefix;
	public $account_details;

	static private $URL_BANKS_LIST = "https://api.vietqr.io/v2/banks";
	static private $URL_DEEPLINK_APPS = "https://api.vietqr.io/v2/%s-app-deeplinks";

	//cache dữ liệu từ API: transient, bản tốt gần nhất lưu vĩnh viễn ({key}_last_good), cuối cùng là file đóng gói sẵn.
	const BANKS_TRANSIENT = 'vietqr_banks';
	const BANKS_CACHE_LIFE = HOUR_IN_SECONDS;
	const DEEPLINK_APPS_CACHE_LIFE = 12 * HOUR_IN_SECONDS;
	const RETRY_AFTER = 5 * MINUTE_IN_SECONDS;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		$this->id                 = 'vietqr';
		$this->has_fields         = false;
		$this->icon               = apply_filters('woocommerce_icon_vietqr', plugins_url('../assets/img/vietqr.png', __FILE__));
		$this->method_title       = __('Direct bank transfer (Scan VietQR)', 'vietqr');
		$this->method_description = __('Take payments by scanning QR code with Vietnamese banking App. Supported by most major banks in Vietnam', 'vietqr');

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title        = $this->get_option('title');
		$this->description  = $this->get_option('description');

		$this->instructions = $this->get_option('instructions');
		$this->prefix = $this->get_option('prefix');
		// VietQR account fields shown on the thanks page and in emails.
		$this->account_details = get_option(
			'woocommerce_vietqr_accounts',
			array(
				array(
					'account_name'   => $this->get_option('account_name'),
					'account_number' => $this->get_option('account_number'),
					'bank_name'      => $this->get_option('bank_name'),
					'bank_id'      => $this->get_option('bank_id'),
				),
			)
		);

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_account_details'));
		add_action('woocommerce_thankyou_vietqr', array($this, 'thankyou_page'));
		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

	}

	/**
	 * Danh sách ngân hàng dạng array('data' => [...]), không bao giờ null.
	 * Chỉ gọi API khi cache hết hạn, lỗi API thì dùng bản tốt gần nhất hoặc file đóng gói sẵn.
	 */
	public static function get_banks()
	{
		return self::cached_remote_json(
			self::BANKS_TRANSIENT,
			self::$URL_BANKS_LIST,
			self::BANKS_CACHE_LIFE,
			function ($data) {
				return is_array($data) && !empty($data['data']) && is_array($data['data']);
			},
			function () {
				$banks = self::read_bundled_json('banks.json');
				return is_array($banks) && !empty($banks['data']) ? $banks : array('data' => array());
			},
			array(
				'x-api-key'   => 'we-l0v3-v1et-qr',
				'x-client-id' => get_site_url(),
			)
		);
	}

	/**
	 * Danh sách app ngân hàng hỗ trợ deeplink (dl.vietqr.io) cho một nền tảng.
	 *
	 * @param string $platform android | ios.
	 * @return array Mỗi app: appId, appName, appLogo, autofill, monthlyInstall...
	 */
	public static function get_deeplink_apps($platform)
	{
		$platform = 'ios' === $platform ? 'ios' : 'android';
		$data = self::cached_remote_json(
			'vietqr_deeplink_apps_' . $platform,
			sprintf(self::$URL_DEEPLINK_APPS, $platform),
			self::DEEPLINK_APPS_CACHE_LIFE,
			function ($data) {
				return is_array($data) && !empty($data['apps']) && is_array($data['apps']);
			},
			function () use ($platform) {
				$apps = self::read_bundled_json('deeplink-apps.json');
				return array('apps' => isset($apps[$platform]) && is_array($apps[$platform]) ? $apps[$platform] : array());
			}
		);
		return $data['apps'];
	}

	/**
	 * @param string   $key       Tên transient; bản tốt gần nhất lưu ở option {$key}_last_good.
	 * @param string   $url       API.
	 * @param int      $ttl       Thời gian cache khi API trả về hợp lệ.
	 * @param callable $is_valid  Kiểm tra dữ liệu.
	 * @param callable $fallback  Dữ liệu đóng gói sẵn khi chưa từng gọi API thành công.
	 * @param array    $headers   Header gửi kèm.
	 */
	private static function cached_remote_json($key, $url, $ttl, $is_valid, $fallback, $headers = array())
	{
		$data = get_transient($key);
		if ($is_valid($data)) {
			return $data;
		}

		$response = wp_remote_get($url, array('timeout' => 3, 'headers' => $headers));
		$data = (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response))
			? json_decode(wp_remote_retrieve_body($response), true)
			: null;
		if ($is_valid($data)) {
			set_transient($key, $data, $ttl);
			update_option($key . '_last_good', $data, false);
			if (self::BANKS_TRANSIENT === $key) {
				self::cleanup_legacy_banks_cache();
			}
			return $data;
		}

		//API lỗi: dùng tạm bản cũ, thử lại sau vài phút thay vì gọi API ở mọi request.
		$data = get_option($key . '_last_good');
		if (!$is_valid($data)) {
			$data = $fallback();
		}
		set_transient($key, $data, self::RETRY_AFTER);
		return $data;
	}

	private static function read_bundled_json($filename)
	{
		$file = dirname(__DIR__) . '/assets/data/' . $filename;
		$json = is_readable($file) ? file_get_contents($file) : false;
		return $json ? json_decode($json, true) : null;
	}

	//bản cũ lưu danh sách ngân hàng trong settings của gateway (autoload), xóa đi cho nhẹ.
	private static function cleanup_legacy_banks_cache()
	{
		$settings = get_option('woocommerce_vietqr_settings');
		if (is_array($settings) && (isset($settings['vietqr_banks']) || isset($settings['vietqr_banks_expired']) || isset($settings['developer_id']))) {
			unset($settings['vietqr_banks'], $settings['vietqr_banks_expired'], $settings['developer_id']);
			update_option('woocommerce_vietqr_settings', $settings);
		}
	}

	public function init_form_fields()
	{
		$default_prefix = "WCORDER";

		//Tự động sinh prefix đơn hàng cho website.
		//ví dụ website my.casso.vn thì prefix là CASSO.
		$server_domain = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		$better_prefix = $this->guess_company_name_from_domain($server_domain);
		if (strlen($better_prefix) >= 4 && strlen($better_prefix) <= 8) {
			$default_prefix  = $better_prefix;
		}

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __('Enable/Disable', 'vietqr'),
				'type'    => 'checkbox',
				'label'   => __('Enable bank transfer', 'vietqr'),
				'default' => 'yes',
			),
			'title'           => array(
				'title'       => __('Title', 'vietqr'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'vietqr'),
				'default'     => __('Scan the bank transfer code', 'vietqr'),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __('Description', 'vietqr'),
				'type'        => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'vietqr'),
				'default'     => __('Transfer payments using the banking App to scan the code. Supported by almost Vietnamese banking apps', 'vietqr'),
				'desc_tip'    => true,
			),
			'instructions'    => array(
				'title'       => __('Instructions', 'vietqr'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page and emails.', 'vietqr'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type' => 'account_details',
			),
			'deeplink'        => array(
				'title'       => __('Bank app buttons', 'vietqr'),
				'type'        => 'checkbox',
				'label'       => __('Show "Open banking app" buttons on mobile (VietQR deeplink)', 'vietqr'),
				'description' => __('Customers on a phone can open their banking app with the account, amount and transfer memo already filled in.', 'vietqr'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'prefix'           => array(
				'title'       => __('Prefix', 'vietqr'),
				'type'        => 'text',
				'description' => __('Prefix used to combine with order code to create money transfer content, Set rules: no spaces, no more than 15 characters and no special characters. Violations will be deleted', 'vietqr'),
				'default'     => $default_prefix,
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html()
	{


		ob_start();

		// selectWoo (bản select2 của WooCommerce), CSS có sẵn trong woocommerce_admin_styles.
		wp_enqueue_script('selectWoo');
?>
		<script>
			jQuery(document).ready(function() {
				jQuery("#vietqr_bank_selector2").selectWoo({
					templateResult: formatOptions
				});
			});

			function formatOptions(state) {
				if (!state.id) {
					return state.text;
				}
				var $img = jQuery('<img style="vertical-align: middle;" width="80px"/>').attr('src', state.title);
				return jQuery('<span></span>').append($img).append(document.createTextNode(' ' + state.text));
			}
		</script>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e('Bank name', 'vietqr'); ?>:</th>
			<td class="forminp" id="vietqr_accounts">
				<select id='vietqr_bank_selector2' style='width: 100%' name="vietqr_bank_name[0]">
					<?php

					$banks = self::get_banks();
					foreach ($banks['data'] as $bank) {
						$option_name  = "vietqr_bank_id_" . $bank['bin'];
						$option_value = $bank['bin'] . ":" . $bank['name'];
						$option_title = $bank['logo'];
						$option_text  = ($bank['short_name'] ?? $bank['shortName'] ?? '') . ' - ' . $bank['name'];

						echo '<option name="' . esc_attr($option_name) . '" value="' . esc_attr($option_value) . '" title="' . esc_url($option_title) . '" ' . selected($this->account_details[0]['bank_id'], $bank['bin'], false) . '>' . esc_html($option_text) . '</option>';
					}
					if (empty($banks['data']) && !empty($this->account_details[0]['bank_id'])) {
						$saved = $this->account_details[0];
						echo '<option value="' . esc_attr($saved['bank_id'] . ':' . $saved['bank_name']) . '" selected>' . esc_html($saved['bank_name']) . '</option>';
					}
					?>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e('Account name', 'vietqr'); ?>:</th>
			<td class="forminp" id="vietqr_accounts">
				<?php
				echo '<input type="text" value="' . esc_attr($this->account_details[0]['account_name']) . '" name="vietqr_account_name[0]" />';
				?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e('Account number', 'vietqr'); ?>:</th>
			<td class="forminp" id="vietqr_accounts">
				<?php
				echo '<input type="text" value="' . esc_attr($this->account_details[0]['account_number']) . '" name="vietqr_account_number[0]" />';
				?>
			</td>
			</td>
		</tr>
<?php
		return ob_get_clean();
	}

	/**
	 * Save account details table.
	 */
	public function save_account_details()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
		if (!isset($_POST['vietqr_account_name'], $_POST['vietqr_account_number'])) {
			return;
		}

		$account_names   = map_deep(wp_unslash($_POST['vietqr_account_name']), 'sanitize_text_field');
		$account_numbers = map_deep(wp_unslash($_POST['vietqr_account_number']), 'sanitize_text_field');
		$bank_names      = isset($_POST['vietqr_bank_name']) ? map_deep(wp_unslash($_POST['vietqr_bank_name']), 'sanitize_text_field') : array();
		$bank_names      = explode(":", $bank_names[0] ?? '', 2);
		//không tải được danh sách ngân hàng thì giữ ngân hàng đã lưu.
		if (count($bank_names) < 2) {
			$bank_names = array($this->account_details[0]['bank_id'] ?? '', $this->account_details[0]['bank_name'] ?? '');
		}
		update_option('woocommerce_vietqr_accounts', array(
			array(
				'account_name'   => $account_names[0] ?? '',
				'account_number' => $account_numbers[0] ?? '',
				'bank_name'      => $bank_names[1],
				'bank_id'        => $bank_names[0],
			),
		));

		//Update prefix nếu điền sai
		$prefix = isset($_POST['woocommerce_vietqr_prefix']) ? sanitize_text_field(wp_unslash($_POST['woocommerce_vietqr_prefix'])) : '';
		$this->update_option('prefix', $this->clean_prefix($prefix));

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
	public function clean_prefix($string)
	{
		$string = str_replace(' ', '', $string); // Replaces all spaces with hyphens.
		if (strlen($string) > 15) {
			$string = substr($string, 0, 15);
		}
		return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
	}
	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page($order_id)
	{
		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize(wp_kses_post($this->instructions))));
		}
		$this->bank_details($order_id, 'thankyou');
	}

	/**
	 * QR ở trang Tài khoản → Xem đơn hàng, cho đơn VietQR chưa thanh toán (khách quay lại trả sau).
	 *
	 * @param int $order_id Order ID.
	 */
	public function view_order_page($order_id)
	{
		$order = wc_get_order($order_id);
		if (!$order || 'vietqr' !== $order->get_payment_method()) {
			return;
		}
		if (!$order->has_status(apply_filters('vietqr_view_order_statuses', array('on-hold', 'pending')))) {
			return;
		}
		$this->bank_details($order_id, 'view_order');
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if (!$sent_to_admin && 'vietqr' === $order->get_payment_method() && $order->has_status('on-hold')) {
			if ($plain_text) {
				if ($this->instructions) {
					echo esc_html(wp_strip_all_tags(wptexturize($this->instructions))) . PHP_EOL . PHP_EOL;
				}
				$this->bank_details($order->get_id(), 'email_plain');
				return;
			}
			if ($this->instructions) {
				echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
			}
			$this->bank_details($order->get_id(), 'email');
		}
	}

	/**
	 * Hiển thị mã QR và thông tin chuyển khoản.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $context  thankyou | view_order | email | email_plain.
	 */
	private function bank_details($order_id = '', $context = 'thankyou')
	{
		if (empty($this->account_details)) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		$is_sent_email = in_array($context, array('email', 'email_plain'), true);
		foreach ($this->account_details as $vietqr_account) {
			$account_fields = $this->get_account_fields($order, (object) $vietqr_account);

			$has_details = false;
			foreach ($account_fields as $field) {
				if (!empty($field['value'])) {
					$has_details = true;
					break;
				}
			}
			if (!$has_details) {
				continue;
			}

			$data = $this->get_qrcode_vietqr_img_url($account_fields);
			$args = array(
				'order'          => $order,
				'account_fields' => $account_fields,
				// email không hiển thị SVG
				'qrcode_url'     => $is_sent_email ? $data['email_url'] : $data['img_url'],
				'qrcode_png'     => $data['png_url'],
				'qrcode_page'    => $data['pay_url'],
				'context'        => $context,
				'is_sent_email'  => $is_sent_email,
				//nút mở app ngân hàng theo nền tảng (JS chỉ hiện trên điện thoại)
				'deeplink_apps'  => $is_sent_email ? array() : $this->get_deeplink_links($order, $account_fields),
			);

			$templates = array(
				'email'       => 'emails/bank-details.php',
				'email_plain' => 'plain/bank-details.php',
			);
			if (!$is_sent_email) {
				self::enqueue_assets(true);
			}
			wc_get_template($templates[$context] ?? 'bank-details.php', $args, 'vietqr/', dirname(__DIR__) . '/templates/');
		}
	}

	/**
	 * Link mở app ngân hàng (deeplink dl.vietqr.io) cho từng nền tảng, app tự điền thông tin lên trước.
	 *
	 * @return array array('android' => [...], 'ios' => [...]), mỗi app: id, name, logo, autofill, href.
	 */
	private function get_deeplink_links($order, $account_fields)
	{
		if ('yes' !== $this->get_option('deeplink', 'yes')) {
			return array();
		}
		$bank_code = self::get_bank_code($account_fields['bank_id']['value']);
		if ('' === $bank_code || empty($account_fields['account_number']['value'])) {
			return array();
		}

		//Quy ước của dl.vietqr.io: ba giữ nguyên "@", mã ngân hàng viết thường, dấu cách thành "+",
		//url để nguyên trừ khi có "&" (dl.vietqr.io sẽ cắt url tại "&").
		$return_url = $order->get_checkout_order_received_url();
		$encode = function ($value) {
			return str_replace('%20', '+', rawurlencode((string) $value));
		};
		$query = 'am=' . $encode($account_fields['amount']['value'])
			. '&tn=' . $encode($account_fields['memo']['value'])
			. '&ba=' . $encode($account_fields['account_number']['value']) . '@' . $bank_code
			. '&bn=' . $encode($account_fields['account_name']['value'])
			. '&url=' . (preg_match('/[&#\s]/', $return_url) ? rawurlencode($return_url) : $return_url);

		$links = array();
		foreach (array('android', 'ios') as $platform) {
			$apps = self::get_deeplink_apps($platform);
			usort($apps, function ($a, $b) {
				$by_autofill = (int) !empty($b['autofill']) - (int) !empty($a['autofill']);
				return $by_autofill ?: (int) ($b['monthlyInstall'] ?? 0) - (int) ($a['monthlyInstall'] ?? 0);
			});
			$links[$platform] = array();
			foreach ($apps as $app) {
				if (empty($app['appId']) || !preg_match('/^[a-z0-9_-]+$/i', $app['appId'])) {
					continue;
				}
				$links[$platform][] = array(
					'id'       => $app['appId'],
					'name'     => $app['appName'] ?? $app['appId'],
					'logo'     => $app['appLogo'] ?? '',
					'autofill' => !empty($app['autofill']),
					'href'     => 'https://dl.vietqr.io/pay?app=' . $app['appId'] . '&' . $query,
				);
			}
		}
		return apply_filters('vietqr_deeplink_apps', $links, $order);
	}

	/**
	 * Mã ngân hàng viết thường (vd. vcb) theo BIN, dùng cho tham số ba của deeplink.
	 */
	public static function get_bank_code($bin)
	{
		$banks = self::get_banks();
		foreach ($banks['data'] as $bank) {
			if ((string) ($bank['bin'] ?? '') === (string) $bin && !empty($bank['code'])) {
				return strtolower(preg_replace('/[^a-z0-9]/i', '', $bank['code']));
			}
		}
		return '';
	}

	/**
	 * Các trường thông tin chuyển khoản của một đơn hàng (filter woocommerce_vietqr_account_fields).
	 */
	private function get_account_fields($order, $vietqr_account)
	{
		return apply_filters(
			'woocommerce_vietqr_account_fields',
			array(
				'bank_name'      => array(
					'label' => __('Bank', 'vietqr'),
					'value' => $vietqr_account->bank_name,
				),
				'bank_id'      => array(
					'label' => __('Bank Id', 'vietqr'),
					'value' => $vietqr_account->bank_id,
				),
				'account_number' => array(
					'label' => __('Account number', 'vietqr'),
					'value' => $vietqr_account->account_number,
				),
				'account_name' => array(
					'label' => __('Account name', 'vietqr'),
					'value' => $vietqr_account->account_name,
				),
				'amount'            => array(
					'label' => __('Amount', 'vietqr'),
					//QR chỉ nhận số nguyên VND.
					'value' => (int) round((float) $order->get_total()),
				),
				'memo'            => array(
					'label' => __('Memo', 'vietqr'),
					'value' =>  $this->prefix . $order->get_id(),
				),
			),
			$order->get_id()
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		if ($order->get_total() > 0) {
			// Mark as on-hold (we're awaiting the payment).
			$order->update_status(apply_filters('woocommerce_vietqr_process_payment_order_status', 'on-hold', $order), __('Awaiting payment', 'vietqr'));
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url($order),
		);
	}

	
	//generate 
	public function get_qrcode_vietqr_img_url($account_fields)
	{
		$accountNo = rawurlencode($account_fields['account_number']['value']);
		$acqId = rawurlencode($account_fields['bank_id']['value']);
		$addInfo = $account_fields['memo']['value'];
		$amount = $account_fields['amount']['value'];
		$template = "payos";

		$query = array_map('rawurlencode', array(
			'amount'      => $amount,
			'addInfo'     => $addInfo,
			'accountName' => $account_fields['account_name']['value'],
		));
		$base = "https://img.vietqr.io/image/{$acqId}-{$accountNo}-{$template}";
		// Trang web hiển thị SVG. PNG chỉ dùng khi tải về và trong email (Gmail/Outlook không hiển thị SVG);
		// img.vietqr.io chỉ trả PNG khi có tham số goal cho biết mục đích sử dụng.
		$img_url = add_query_arg($query, $base . '.svg');
		$png_url = add_query_arg($query + array('goal' => 'download'), $base . '.png');
		$email_url = add_query_arg($query + array('goal' => 'email'), $base . '.png');
		$pay_url = "https://api.vietqr.io/{$acqId}/{$accountNo}/" . rawurlencode($amount) . '/' . rawurlencode($addInfo);

		return array(
			"img_url" => $img_url,
			"png_url" => $png_url,
			"email_url" => $email_url,
			"pay_url" => $pay_url,
		);
	}

	public function get_description()
	{
		$des = apply_filters('woocommerce_gateway_description', $this->description, $this->id);

		if ($this->id == "vietqr") {
			self::enqueue_assets();
			$featured = self::get_featured_banks();
			$des .= '<span class="vietqr-banks">';
			foreach ($featured['banks'] as $bank) {
				$des .= '<span class="vietqr-banks__item"><img class="vietqr-banks__logo" src="' . esc_url($bank['logo']) . '" alt="' . esc_attr($bank['shortName'] ?? '') . '" title="' . esc_attr($bank['shortName'] ?? '') . '" loading="lazy" decoding="async"></span>';
			}
			if ($featured['others_count'] > 0) {
				$des .= '<span class="vietqr-banks__item vietqr-banks__item--more" title="' . esc_attr($featured['others_names']) . '">' . esc_html($featured['others_label']) . '</span>';
			}
			$des .= '</span>';
		}
		return $des;
	}

	/**
	 * Vài ngân hàng đại diện để hiển thị ở bước chọn phương thức thanh toán, phần còn lại gộp thành "+N ngân hàng & ví khác".
	 *
	 * @return array{banks: array, others_count: int, others_label: string, others_names: string}
	 */
	public static function get_featured_banks()
	{
		//Vietcombank, VietinBank, BIDV, Agribank, Techcombank, MBBank
		$featured_bins = apply_filters('vietqr_featured_bank_bins', array('970436', '970415', '970418', '970405', '970407', '970422'));

		$banks = self::get_banks();
		$supported = array_values(array_filter($banks['data'], function ($bank) {
			return !empty($bank['logo']) && !empty($bank['transferSupported']) && !empty($bank['bin']);
		}));

		$by_bin = array();
		foreach ($supported as $bank) {
			$by_bin[(string) $bank['bin']] = $bank;
		}
		$featured = array();
		foreach ($featured_bins as $bin) {
			if (isset($by_bin[(string) $bin])) {
				$featured[] = $by_bin[(string) $bin];
				unset($by_bin[(string) $bin]);
			}
		}

		$others_count = count($by_bin);
		return array(
			'banks'        => $featured,
			'others_count' => $others_count,
			/* translators: %d: số ngân hàng và ví điện tử còn lại */
			'others_label' => sprintf(_n('+%d other bank & e-wallet', '+%d other banks & e-wallets', $others_count, 'vietqr'), $others_count),
			'others_names' => implode(', ', array_map(function ($bank) {
				return $bank['shortName'] ?? $bank['name'];
			}, array_values($by_bin))),
		);
	}

	/**
	 * CSS dùng chung cho checkout và trang thanh toán; JS chỉ cần ở trang thanh toán (QR, sao chép, mở app).
	 */
	public static function enqueue_assets($with_script = false)
	{
		wp_enqueue_style('vietqr', plugins_url('../assets/css/vietqr.css', __FILE__), array(), WC_GATEWAY_VIETQR_VERSION);
		static $theme_css_added = false;
		if (!$theme_css_added && !wp_style_is('vietqr', 'done')) {
			$theme_css_added = true;
			$theme_css = self::theme_button_css();
			if ($theme_css) {
				wp_add_inline_style('vietqr', $theme_css);
			}
		}
		if ($with_script) {
			wp_enqueue_script('vietqr', plugins_url('../assets/js/vietqr.js', __FILE__), array(), WC_GATEWAY_VIETQR_VERSION, true);
		}
	}

	/**
	 * Màu và bo góc nút lấy từ Global Styles của theme khối (Giao diện > Trình chỉnh sửa > Kiểu > Nút).
	 * Giữ nguyên dạng var(--wp--preset--...) để đổi màu trong Site Editor là nút của plugin đổi theo.
	 * Theme cổ điển không có Global Styles cho nút: JS sẽ đo nút .button của theme.
	 */
	private static function theme_button_css()
	{
		if (!function_exists('wp_is_block_theme') || !wp_is_block_theme() || !function_exists('wp_get_global_styles')) {
			return '';
		}
		$button = wp_get_global_styles(array('elements', 'button'));
		$vars = array(
			'--vietqr-accent'          => $button['color']['background'] ?? '',
			'--vietqr-accent-contrast' => $button['color']['text'] ?? '',
			'--vietqr-accent-hover'    => $button[':hover']['color']['background'] ?? '',
			'--vietqr-button-radius'   => is_string($button['border']['radius'] ?? null) ? $button['border']['radius'] : '',
		);
		$css = '';
		foreach ($vars as $name => $value) {
			// chỉ nhận giá trị CSS an toàn (màu, var(), color-mix(), px...)
			if (is_string($value) && '' !== $value && preg_match('/^[a-zA-Z0-9#%(),.\s_-]+$/', $value)) {
				$css .= $name . ':' . $value . ';';
			}
		}
		return $css ? '.vietqr{--vietqr-theme-colors:1;' . $css . '}' : '';
	}

	/**
	 * Mã QR VietQR chỉ dùng cho VND.
	 */
	public function is_available()
	{
		$currencies = apply_filters('vietqr_supported_currencies', array('VND'));
		return parent::is_available() && in_array(get_woocommerce_currency(), $currencies, true);
	}

	public function guess_company_name_from_domain($domain)
	{
		$domain = strtolower(trim((string) $domain));

		//xóa https
		$domain = str_replace("http://", "", $domain);
		$domain = str_replace("https://", "", $domain);

		//xóa port
		$domain = preg_replace('/:[0-9]{2,5}/', '', $domain);


		//xóa kí tự đặc biệt
		$domain = preg_replace('/[^a-z0-9\.]/', '', $domain);

		//chia thành từng block.
		$blocks = explode(".", $domain);

		// trường hợp domain cấp 1 ***.casso.vn
		if (count($blocks) >= 2 && strlen($blocks[count($blocks) - 2]) >= 4)
			return strtoupper($blocks[count($blocks) - 2]);

		// trường họp top levep domain cấp 2: ***.casso.com.vn
		if (count($blocks) >= 3 && strlen($blocks[count($blocks) - 3]) >= 4)
			return strtoupper($blocks[count($blocks) - 3]);

		return '';
	}
}
