<?php

/**
 * Plugin Name: VietQR
 * Plugin URI: https://casso.vn/plugin-vietqr-woocommerce/
 * Description: Bank transfer by VietQR code for WooCommerce, accepted by most Vietnamese banking apps and e-wallets: Vietcombank, VietinBank, BIDV, Agribank, Techcombank, MBBank, MoMo, ZaloPay...
 * Author: Casso Team
 * Author URI: https://casso.vn
 * Text Domain: vietqr
 * Domain Path: /languages
 * Version: 3.8.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.2
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


defined('ABSPATH') or exit;
define( 'WC_GATEWAY_VIETQR_VERSION', '3.8.0' ); // phải trùng Version ở header, CI kiểm tra khi deploy
define( 'WC_GATEWAY_VIETQR_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_VIETQR_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

//load plugin code
add_action('plugins_loaded', 'vietqr_gateway_init', 11);

function vietqr_gateway_init()
{
    require_once __DIR__ . '/classes/class-wc-gateway-vietqr.php';
}

// Khai báo tương thích HPOS và Checkout Block
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

//register vietqr gateway 
add_filter('woocommerce_payment_gateways', 'vietqr_add_gateways');
add_action('plugins_loaded', 'vietqr_load_plugin_textdomain');
function vietqr_add_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_VietQR';
    return $gateways;
}

add_action( 'woocommerce_blocks_loaded', 'woocommerce_vietqr_woocommerce_blocks_support' );

function woocommerce_vietqr_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/classes/class-wc-gateway-vietqr-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_VietQR_Blocks_Support );
			}
		);
	}
}

add_action( 'init', 'vietqr_add_settting');

function vietqr_add_settting(){
    if ( class_exists( 'WooCommerce' ) ) {
        // Add "Settings" link when the plugin is active
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),'vietqr_add_settings_link');
    }
}
function vietqr_add_settings_link( $links ) {
    $settings = array( '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vietqr' ) ) . '">' . esc_html__( 'Settings', 'vietqr' ) . '</a>' );
    $links    = array_reverse( array_merge( $links, $settings ) );

    return $links;
}
function vietqr_load_plugin_textdomain()
{
    load_plugin_textdomain('vietqr', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 

}