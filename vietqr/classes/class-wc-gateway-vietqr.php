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
	public $developer_id;
	public $account_details;

	static private $URL_BANKS_LIST = "https://api.vietqr.io/v2/banks";

	//cache danh sách ngân hàng: transient 1h, bản tốt gần nhất lưu vĩnh viễn, cuối cùng là file đóng gói sẵn.
	const BANKS_TRANSIENT = 'vietqr_banks';
	const BANKS_LAST_GOOD_OPTION = 'vietqr_banks_last_good';
	const BANKS_CACHE_LIFE = HOUR_IN_SECONDS;
	const BANKS_RETRY_AFTER = 5 * MINUTE_IN_SECONDS;

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
		$this->developer_id = $this->get_option('developer_id');
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
		$banks = get_transient(self::BANKS_TRANSIENT);
		if (self::is_valid_banks($banks)) {
			return $banks;
		}

		$banks = self::fetch_banks();
		if (self::is_valid_banks($banks)) {
			set_transient(self::BANKS_TRANSIENT, $banks, self::BANKS_CACHE_LIFE);
			update_option(self::BANKS_LAST_GOOD_OPTION, $banks, false);
			self::cleanup_legacy_banks_cache();
			return $banks;
		}

		//API lỗi: dùng tạm bản cũ, thử lại sau vài phút thay vì gọi API ở mọi request.
		$banks = get_option(self::BANKS_LAST_GOOD_OPTION);
		if (!self::is_valid_banks($banks)) {
			$banks = self::bundled_banks();
		}
		set_transient(self::BANKS_TRANSIENT, $banks, self::BANKS_RETRY_AFTER);
		return $banks;
	}

	private static function is_valid_banks($banks)
	{
		return is_array($banks) && !empty($banks['data']) && is_array($banks['data']);
	}

	private static function fetch_banks()
	{
		$response = wp_remote_get(self::$URL_BANKS_LIST, array(
			'timeout' => 3,
			'headers' => array(
				'x-api-key' => 'we-l0v3-v1et-qr',
				"x-client-id" => get_site_url(),
			),
		));
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return null;
		}
		return json_decode(wp_remote_retrieve_body($response), true);
	}

	private static function bundled_banks()
	{
		$file = dirname(__DIR__) . '/assets/data/banks.json';
		$json = is_readable($file) ? file_get_contents($file) : false;
		$banks = $json ? json_decode($json, true) : null;
		return self::is_valid_banks($banks) ? $banks : array('data' => array());
	}

	//bản cũ lưu danh sách ngân hàng trong settings của gateway (autoload), xóa đi cho nhẹ.
	private static function cleanup_legacy_banks_cache()
	{
		$settings = get_option('woocommerce_vietqr_settings');
		if (is_array($settings) && (isset($settings['vietqr_banks']) || isset($settings['vietqr_banks_expired']))) {
			unset($settings['vietqr_banks'], $settings['vietqr_banks_expired']);
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

		wp_enqueue_script('vietqr-select2-script', plugins_url('../assets/js/select2.min.js', __FILE__), array('jquery'), '4.0.3', false);
		wp_enqueue_style('vietqr-select2-styles', plugins_url('../assets/css/select2.min.css', __FILE__), array(), '4.0.3');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ quyết định hiện ô Developer ID
		$is_claim_show = isset($_GET['claim']);
?>
		<script>
			jQuery(document).ready(function() {
				jQuery("#vietqr_bank_selector2").select2({
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
		<tr valign="top" style="<?php if (!$is_claim_show) echo "display: none;"  ?>">
			<th scope="row" class="titledesc"><?php esc_html_e('Developer ID', 'vietqr'); ?>:</th>
			<td class="forminp" id="vietqr_accounts">
			
				<input type="text" value="<?php echo esc_attr($this->developer_id) ?>" name="vietqr_developer_id[0]" style="max-width: 150px;"/>
				</br>
				<label for="bank_transfer" style="font-size: 11px; font-style: oblique;margin-top: 10px;"><?php esc_html_e('We will review and send you a gift via MoMo if you are eligible', 'vietqr'); ?></label>
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

		// Cập nhật developer_id
		if (isset($_POST['vietqr_developer_id'])) {
			$developer_id = map_deep(wp_unslash($_POST['vietqr_developer_id']), 'sanitize_text_field');
			$developer_id = $developer_id[0] ?? '';
			$changed = $developer_id !== (string) $this->developer_id;
			$this->update_option('developer_id', $developer_id);
			//chỉ gửi sang devgioi khi admin nhập/đổi Developer ID.
			if ($developer_id !== '' && $changed) {
				$this->developer_affiliate($developer_id);
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
	public function developer_affiliate($developer_id)
	{
		$url  = 'https://vietqr.devgioi.com/api/claim';
		preg_match('/(http(|s)):\/\/(.*?)\//si',  WC()->api_request_url(''), $output);
		$body = array(
			"developer_id" => $developer_id,
			"domain" => $output[0],
		);
		$args = array(
			'body'        => json_encode($body),
			'headers' => array(
				"content-type" => "application/json"
			),
			'timeout'     => 20,
		);
		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
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
		$this->bank_details($order_id, false);
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
			if ($this->instructions) {
				echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
			}
			$this->bank_details($order->get_id(), true);
		}
	}

	/**
	 * Hiển thị mã QR và thông tin chuyển khoản (template templates/bank-details.php).
	 *
	 * @param int  $order_id      Order ID.
	 * @param bool $is_sent_email Đang render trong email.
	 */
	private function bank_details($order_id = '', $is_sent_email = false)
	{
		if (empty($this->account_details)) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		foreach ($this->account_details as $vietqr_account) {
			$vietqr_account = (object) $vietqr_account;

			// VietQR account fields shown on the thanks page and in emails.
			$account_fields = apply_filters(
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
						'value' =>  $this->prefix . $order_id,
					),
				),
				$order_id
			);

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

			wc_get_template(
				'bank-details.php',
				array(
					'order'          => $order,
					'account_fields' => $account_fields,
					'qrcode_url'     => $data['img_url'],
					'qrcode_page'    => $data['pay_url'],
					'is_sent_email'  => $is_sent_email,
					//hiển thị nút tải trên điện thoại và ko phải email
					'show_download'  => !$is_sent_email && wp_is_mobile(),
				),
				'vietqr/',
				dirname(__DIR__) . '/templates/'
			);
		}
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
		$template = "compact";

		$img_url = add_query_arg(
			array_map('rawurlencode', array(
				'amount'      => $amount,
				'addInfo'     => $addInfo,
				'accountName' => $account_fields['account_name']['value'],
			)),
			"https://img.vietqr.io/image/{$acqId}-{$accountNo}-{$template}.jpg"
		);
		$pay_url = "https://api.vietqr.io/{$acqId}/{$accountNo}/" . rawurlencode($amount) . '/' . rawurlencode($addInfo);

		return array(
			"img_url" => $img_url,
			"pay_url" => $pay_url,
		);
	}

	public function get_description()
	{
		$des = apply_filters('woocommerce_gateway_description', $this->description, $this->id);

		if ($this->id == "vietqr") {
			self::enqueue_checkout_style();
			$featured = self::get_featured_banks();
			$des .= "<div class='vietqr-row'>";
			foreach ($featured['banks'] as $bank) {
				$des .= "<div class='list-bank'><div class='list-bank-box'>";
				$des .= '<img src="' . esc_url($bank['logo']) . '" alt="' . esc_attr($bank['shortName'] ?? '') . '" title="' . esc_attr($bank['shortName'] ?? '') . '">';
				$des .= '</div></div>';
			}
			if ($featured['others_count'] > 0) {
				$des .= "<div class='list-bank list-bank-more'><div class='list-bank-box' title='" . esc_attr($featured['others_names']) . "'>";
				$des .= esc_html($featured['others_label']);
				$des .= '</div></div>';
			}
			$des .= "</div>";
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

	public static function enqueue_checkout_style()
	{
		wp_enqueue_style('vietqr-custome-styles', plugins_url('../assets/css/custom.css', __FILE__), array(), WC_GATEWAY_VIETQR_VERSION);
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
