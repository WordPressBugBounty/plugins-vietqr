/**
 * Đăng ký phương thức thanh toán VietQR cho WooCommerce Checkout Block.
 *
 * Mã nguồn của vietqr/assets/blocks/index.js, build bằng `npm run build` trong thư mục devops/.
 * Dùng createElement thay vì JSX để không phụ thuộc react-jsx-runtime (chỉ có từ WordPress 6.6).
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { createElement as el } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

const settings = getSetting( 'vietqr_data', {} );
const title = decodeEntities( settings.title || '' );
const banks = Array.isArray( settings.banks ) ? settings.banks : [];

const Banks = () =>
	el(
		'span',
		{ className: 'vietqr-banks' },
		banks.map( ( bank ) =>
			el(
				'span',
				{ className: 'vietqr-banks__item', key: bank.bin },
				el( 'img', {
					className: 'vietqr-banks__logo',
					src: bank.logo,
					alt: bank.shortName,
					title: bank.shortName,
					loading: 'lazy',
					decoding: 'async',
				} )
			)
		),
		settings.others_count > 0 &&
			el(
				'span',
				{
					className: 'vietqr-banks__item vietqr-banks__item--more',
					title: settings.others_names,
				},
				settings.others_label
			)
	);

const Content = () =>
	el(
		'div',
		{ className: 'vietqr-checkout' },
		el( 'p', { className: 'vietqr-checkout__desc' }, decodeEntities( settings.description || '' ) ),
		el( Banks )
	);

const Label = () =>
	el(
		'span',
		{ className: 'vietqr-checkout__label' },
		el( 'span', null, title ),
		settings.logo_url &&
			el( 'img', { className: 'vietqr-checkout__label-logo', src: settings.logo_url, alt: '' } )
	);

registerPaymentMethod( {
	name: 'vietqr',
	label: el( Label ),
	ariaLabel: __( 'VietQR payment method', 'vietqr' ),
	canMakePayment: () => true,
	content: el( Content ),
	edit: el( Content ),
	supports: {
		features: settings.supports ?? [],
	},
} );
