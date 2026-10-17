<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * VietQR payment method integration
 *
 * @since 1.5.0
 */
final class WC_VietQR_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'vietqr';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vietqr_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$gateway = $this->get_gateway();
		return $gateway ? $gateway->is_available() : false;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = WC_GATEWAY_VIETQR_PATH . '/assets/blocks/index.asset.php';
		$version      = WC_GATEWAY_VIETQR_VERSION;
		$dependencies = [];
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-vietqr-blocks-integration',
			WC_GATEWAY_VIETQR_URL . '/assets/blocks/index.js',
			$dependencies,
			$version,
			true
		);
		wp_set_script_translations(
			'wc-vietqr-blocks-integration',
			'vietqr'
		);
		WC_Gateway_VietQR::enqueue_checkout_style();
		return [ 'wc-vietqr-blocks-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$featured = WC_Gateway_VietQR::get_featured_banks();
		return [
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'supports'     => $this->get_supported_features(),
			'logo_url'     => WC_GATEWAY_VIETQR_URL . '/assets/img/vietqr.png',
			'banks'        => array_map( function ( $bank ) {
				return [
					'bin'       => $bank['bin'],
					'shortName' => $bank['shortName'] ?? '',
					'logo'      => $bank['logo'],
				];
			}, $featured['banks'] ),
			'others_count' => $featured['others_count'],
			'others_label' => $featured['others_label'],
			'others_names' => $featured['others_names'],
		];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateway = $this->get_gateway();
		return $gateway ? $gateway->supports : [];
	}

	private function get_gateway() {
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		return $payment_gateways['vietqr'] ?? null;
	}
}
