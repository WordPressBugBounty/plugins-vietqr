<?php
/**
 * Khối thanh toán VietQR dùng chung cho các plugin Casso (VietQR, Casso Flow): mã QR, thông tin chuyển khoản,
 * nút mở app ngân hàng (deeplink), email, danh sách ngân hàng.
 *
 * Mã nguồn gốc ở shared/vietqr-ui/ trong repo. devops/scripts/sync-shared.sh chép vào plugins/<plugin>/shared/
 * và đổi namespace, text domain theo từng plugin: chỉ sửa ở shared/vietqr-ui/ rồi chạy lại script.
 *
 * @package VietQR
 */

namespace VietQR\Shared;

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook vietqr_* dùng chung giữa các plugin dùng giao diện này

class Payment_Box
{
	const BANKS_URL         = 'https://api.vietqr.io/v2/banks';
	const DEEPLINK_APPS_URL = 'https://api.vietqr.io/v2/%s-app-deeplinks';

	// Cache dữ liệu từ API: transient, bản tốt gần nhất lưu vĩnh viễn ({key}_last_good), cuối cùng là file đóng gói sẵn.
	// Các plugin dùng chung tên cache nên chỉ gọi API một lần dù cài nhiều plugin.
	const BANKS_TRANSIENT          = 'vietqr_banks';
	const BANKS_CACHE_LIFE         = HOUR_IN_SECONDS;
	const DEEPLINK_APPS_CACHE_LIFE = 12 * HOUR_IN_SECONDS;
	const RETRY_AFTER              = 5 * MINUTE_IN_SECONDS;

	/**
	 * url: URL thư mục shared/ của plugin, version: version để làm mới cache CSS/JS,
	 * handle: tên style/script, template_path: thư mục trong theme để ghi đè template.
	 *
	 * @var array
	 */
	private static $config = array(
		'url'           => '',
		'version'       => '',
		'handle'        => 'vietqr',
		'template_path' => 'vietqr/',
	);

	public static function init(array $config)
	{
		self::$config = array_merge(self::$config, $config);
	}

	/**
	 * Danh sách ngân hàng dạng array('data' => [...]), không bao giờ null.
	 * Chỉ gọi API khi cache hết hạn, lỗi API thì dùng bản tốt gần nhất hoặc file đóng gói sẵn.
	 */
	public static function get_banks()
	{
		return self::cached_remote_json(
			self::BANKS_TRANSIENT,
			self::BANKS_URL,
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
			sprintf(self::DEEPLINK_APPS_URL, $platform),
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
			return $data;
		}

		// API lỗi: dùng tạm bản cũ, thử lại sau vài phút thay vì gọi API ở mọi request.
		$data = get_option($key . '_last_good');
		if (!$is_valid($data)) {
			$data = $fallback();
		}
		set_transient($key, $data, self::RETRY_AFTER);
		return $data;
	}

	private static function read_bundled_json($filename)
	{
		$file = __DIR__ . '/assets/data/' . $filename;
		$json = is_readable($file) ? file_get_contents($file) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file trong plugin
		return $json ? json_decode($json, true) : null;
	}

	/**
	 * Mã ngân hàng viết thường (vd. vcb) theo BIN, dùng cho tham số ba của deeplink.
	 */
	public static function get_bank_code($bin)
	{
		$bank = self::get_bank($bin);
		return $bank && !empty($bank['code']) ? strtolower(preg_replace('/[^a-z0-9]/i', '', $bank['code'])) : '';
	}

	/**
	 * Thông tin một ngân hàng theo BIN (name, shortName, code, logo...), null nếu không có.
	 */
	public static function get_bank($bin)
	{
		$banks = self::get_banks();
		foreach ($banks['data'] as $bank) {
			if ((string) ($bank['bin'] ?? '') === (string) $bin) {
				return $bank;
			}
		}
		return null;
	}

	/**
	 * Vài ngân hàng đại diện để hiển thị ở bước chọn phương thức thanh toán, phần còn lại gộp thành "+N ngân hàng & ví khác".
	 *
	 * @return array{banks: array, others_count: int, others_label: string, others_names: string}
	 */
	public static function get_featured_banks()
	{
		// Vietcombank, VietinBank, BIDV, Agribank, Techcombank, MBBank
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
	 * Dải logo ngân hàng đại diện cho mô tả phương thức thanh toán ở trang checkout cổ điển.
	 */
	public static function featured_banks_html()
	{
		self::enqueue_assets();
		$featured = self::get_featured_banks();
		$html = '<span class="vietqr-banks">';
		foreach ($featured['banks'] as $bank) {
			$html .= '<span class="vietqr-banks__item"><img class="vietqr-banks__logo" src="' . esc_url($bank['logo']) . '" alt="' . esc_attr($bank['shortName'] ?? '') . '" title="' . esc_attr($bank['shortName'] ?? '') . '" loading="lazy" decoding="async"></span>';
		}
		if ($featured['others_count'] > 0) {
			$html .= '<span class="vietqr-banks__item vietqr-banks__item--more" title="' . esc_attr($featured['others_names']) . '">' . esc_html($featured['others_label']) . '</span>';
		}
		return $html . '</span>';
	}

	/**
	 * Dữ liệu ngân hàng đại diện cho Checkout Block (dùng với class vietqr-banks trong JS).
	 */
	public static function featured_banks_data()
	{
		$featured = self::get_featured_banks();
		return array(
			'banks'        => array_map(function ($bank) {
				return array(
					'bin'       => $bank['bin'],
					'shortName' => $bank['shortName'] ?? '',
					'logo'      => $bank['logo'],
				);
			}, $featured['banks']),
			'others_count' => $featured['others_count'],
			'others_label' => $featured['others_label'],
			'others_names' => $featured['others_names'],
		);
	}

	/**
	 * URL ảnh QR trên img.vietqr.io.
	 *
	 * Trang web hiển thị SVG. PNG chỉ dùng khi tải về và trong email (Gmail/Outlook không hiển thị SVG);
	 * img.vietqr.io chỉ trả PNG khi có tham số goal cho biết mục đích sử dụng.
	 *
	 * @param array  $fields   Thông tin chuyển khoản (xem render()).
	 * @param string $template Mẫu QR của img.vietqr.io: compact, payos...
	 * @return array img_url (SVG), png_url (goal=download), email_url (goal=email), pay_url.
	 */
	public static function qr_urls($fields, $template = 'compact')
	{
		$account_number = rawurlencode((string) $fields['account_number']['value']);
		$bin = rawurlencode((string) $fields['bank_id']['value']);
		$template = preg_replace('/[^a-z0-9_]/i', '', (string) $template) ?: 'compact';

		$query = array_map('rawurlencode', array(
			'amount'      => $fields['amount']['value'],
			'addInfo'     => $fields['memo']['value'],
			'accountName' => $fields['account_name']['value'],
		));
		$base = "https://img.vietqr.io/image/{$bin}-{$account_number}-{$template}";
		return array(
			'img_url'   => add_query_arg($query, $base . '.svg'),
			'png_url'   => add_query_arg($query + array('goal' => 'download'), $base . '.png'),
			'email_url' => add_query_arg($query + array('goal' => 'email'), $base . '.png'),
			'pay_url'   => "https://api.vietqr.io/{$bin}/{$account_number}/" . rawurlencode((string) $fields['amount']['value']) . '/' . rawurlencode((string) $fields['memo']['value']),
		);
	}

	/**
	 * Link mở app ngân hàng (deeplink dl.vietqr.io) cho từng nền tảng, app tự điền thông tin lên trước.
	 *
	 * @return array array('android' => [...], 'ios' => [...]), mỗi app: id, name, logo, autofill, href.
	 */
	public static function deeplink_links($order, $fields)
	{
		$bank_code = self::get_bank_code($fields['bank_id']['value']);
		if ('' === $bank_code || empty($fields['account_number']['value'])) {
			return array();
		}

		// Quy ước của dl.vietqr.io: ba giữ nguyên "@", mã ngân hàng viết thường, dấu cách thành "+",
		// url để nguyên trừ khi có "&" (dl.vietqr.io sẽ cắt url tại "&").
		$return_url = $order->get_checkout_order_received_url();
		$encode = function ($value) {
			return str_replace('%20', '+', rawurlencode((string) $value));
		};
		$query = 'am=' . $encode($fields['amount']['value'])
			. '&tn=' . $encode($fields['memo']['value'])
			. '&ba=' . $encode($fields['account_number']['value']) . '@' . $bank_code
			. '&bn=' . $encode($fields['account_name']['value'])
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
	 * Hiển thị khối thanh toán.
	 *
	 * @param string   $context thankyou | view_order | email | email_plain.
	 * @param WC_Order $order   Đơn hàng.
	 * @param array    $fields  Mỗi trường array('label' => ..., 'value' => ...): bank_name, bank_id (BIN), account_number,
	 *                          account_name, amount (số nguyên VND), memo (nội dung chuyển khoản).
	 * @param array    $args    template: mẫu QR (mặc định compact); deeplink: hiện nút mở app (mặc định true);
	 *                          status: pending | paid; status_check: array('status_url' => ..., 'check_url' => ...)
	 *                          để trang tự kiểm tra thanh toán và tải lại khi đã nhận tiền.
	 */
	public static function render($context, $order, $fields, $args = array())
	{
		$args = wp_parse_args($args, array(
			'template'     => 'compact',
			'deeplink'     => true,
			'status'       => 'pending',
			'status_check' => null,
		));
		$is_sent_email = in_array($context, array('email', 'email_plain'), true);
		$urls = self::qr_urls($fields, $args['template']);

		$template_args = array(
			'order'          => $order,
			'account_fields' => $fields,
			// email không hiển thị SVG
			'qrcode_url'     => $is_sent_email ? $urls['email_url'] : $urls['img_url'],
			'qrcode_png'     => $urls['png_url'],
			'qrcode_page'    => $urls['pay_url'],
			'context'        => $context,
			'is_sent_email'  => $is_sent_email,
			// nút mở app ngân hàng theo nền tảng (JS chỉ hiện trên điện thoại)
			'deeplink_apps'  => $is_sent_email || !$args['deeplink'] || 'paid' === $args['status'] ? array() : self::deeplink_links($order, $fields),
			'status'         => 'paid' === $args['status'] ? 'paid' : 'pending',
			'status_check'   => $is_sent_email ? null : $args['status_check'],
		);

		$templates = array(
			'email'       => 'emails/bank-details.php',
			'email_plain' => 'plain/bank-details.php',
		);
		if (!$is_sent_email) {
			self::enqueue_assets(true);
		}
		wc_get_template($templates[$context] ?? 'bank-details.php', $template_args, self::$config['template_path'], __DIR__ . '/templates/');
	}

	/**
	 * CSS dùng chung cho checkout và trang thanh toán; JS chỉ cần ở trang thanh toán (QR, sao chép, mở app).
	 */
	public static function enqueue_assets($with_script = false)
	{
		$handle = self::$config['handle'];
		wp_enqueue_style($handle, self::$config['url'] . 'assets/vietqr.css', array(), self::$config['version']);
		static $theme_css_added = false;
		if (!$theme_css_added && !wp_style_is($handle, 'done')) {
			$theme_css_added = true;
			$theme_css = self::theme_button_css();
			if ($theme_css) {
				wp_add_inline_style($handle, $theme_css);
			}
		}
		if ($with_script) {
			wp_enqueue_script($handle, self::$config['url'] . 'assets/vietqr.js', array(), self::$config['version'], true);
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
}
