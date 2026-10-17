/**
 * VietQR: nút mở app ngân hàng (deeplink), sao chép thông tin chuyển khoản, lưu ảnh QR.
 *
 * Không tạo biến toàn cục, không phụ thuộc jQuery. Sự kiện chỉ gắn trong từng khối [data-vietqr],
 * bản dịch đọc từ thuộc tính data-vietqr-* của khối đó.
 */
( function () {
	'use strict';

	// Nền tảng của điện thoại; null trên máy tính (deeplink chỉ mở được app trên điện thoại).
	function detectPlatform() {
		var ua = navigator.userAgent || '';
		if ( /android/i.test( ua ) ) {
			return 'android';
		}
		// iPadOS 13+ báo là Macintosh nhưng có màn hình cảm ứng.
		if ( /iPhone|iPad|iPod/.test( ua ) || ( /Macintosh/.test( ua ) && navigator.maxTouchPoints > 1 ) ) {
			return 'ios';
		}
		return null;
	}

	function showApps( root ) {
		var apps = root.querySelector( '[data-vietqr-apps]' );
		var platform = detectPlatform();
		if ( ! apps || ! platform ) {
			return;
		}
		var list = apps.querySelector( '[data-vietqr-platform="' + platform + '"]' );
		if ( list && list.querySelector( 'a' ) ) {
			list.hidden = false;
			apps.hidden = false;
		}
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		// Trang http hoặc trình duyệt cũ: dùng textarea tạm.
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.top = '0';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			var ok = document.execCommand( 'copy' );
			document.body.removeChild( area );
			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	function onCopy( root, button ) {
		if ( button.classList.contains( 'is-copied' ) ) {
			return;
		}
		var label = button.textContent;
		copyText( button.getAttribute( 'data-vietqr-copy' ) ).then( function () {
			button.textContent = root.getAttribute( 'data-vietqr-copied' ) || '✓';
			button.classList.add( 'is-copied' );
			setTimeout( function () {
				button.textContent = label;
				button.classList.remove( 'is-copied' );
			}, 1500 );
		} );
	}

	// Trình duyệt nhúng trong app (Zalo, Facebook, Instagram, Line, TikTok...) thường chặn tải file và chia sẻ.
	function isInAppBrowser() {
		return /Zalo|FBAN|FBAV|FB_IAB|Instagram|Line\/|TikTok|musical_ly/i.test( navigator.userAgent || '' );
	}

	// Ảnh QR nằm ở img.vietqr.io (khác domain) nên thuộc tính download của thẻ <a> không có tác dụng:
	// tải sẵn ảnh dạng blob ngay khi trang mở, để lúc bấm nút có thể gọi navigator.share ngay
	// (iOS chỉ cho chia sẻ trong lúc người dùng đang bấm, chờ tải xong mới gọi sẽ bị từ chối).
	function prefetchQr( button ) {
		var state = { blob: null, promise: null };
		state.promise = fetch( button.getAttribute( 'data-vietqr-save' ), { mode: 'cors', credentials: 'omit' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( response.status );
				}
				return response.blob();
			} )
			.then( function ( blob ) {
				state.blob = blob;
				return blob;
			} );
		state.promise.catch( function () {} );
		return state;
	}

	function downloadBlob( root, blob, filename ) {
		var link = document.createElement( 'a' );
		link.href = URL.createObjectURL( blob );
		link.download = filename;
		link.style.display = 'none';
		root.appendChild( link );
		link.click();
		root.removeChild( link );
		setTimeout( function () {
			URL.revokeObjectURL( link.href );
		}, 1000 );
	}

	// Không lưu tự động được: hướng dẫn nhấn giữ ảnh QR để lưu (cách này chạy được ở mọi trình duyệt điện thoại).
	function showSaveHint( root ) {
		var hint = root.querySelector( '[data-vietqr-save-hint]' );
		var image = root.querySelector( '.vietqr__qr-image' );
		if ( hint ) {
			hint.hidden = false;
		}
		if ( image && image.scrollIntoView ) {
			image.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}

	function saveBlob( root, blob, filename, isMobile ) {
		var file = new File( [ blob ], filename, { type: blob.type || 'image/png' } );
		// Điện thoại: menu chia sẻ có mục "Lưu ảnh" vào thư viện. Máy tính: tải file về luôn.
		if ( isMobile && navigator.canShare && navigator.canShare( { files: [ file ] } ) ) {
			return navigator.share( { files: [ file ] } ).catch( function ( error ) {
				if ( error && error.name === 'AbortError' ) {
					return; // người dùng đóng menu chia sẻ
				}
				if ( /android/i.test( navigator.userAgent ) ) {
					downloadBlob( root, blob, filename );
				} else {
					showSaveHint( root );
				}
			} );
		}
		// iOS không có menu chia sẻ (WebView) thì thẻ <a download> cũng không chạy.
		if ( isMobile && ! /android/i.test( navigator.userAgent ) ) {
			showSaveHint( root );
			return Promise.resolve();
		}
		downloadBlob( root, blob, filename );
		return Promise.resolve();
	}

	function onSave( root, button, state ) {
		var filename = button.getAttribute( 'data-filename' ) || 'vietqr.png';
		var isMobile = detectPlatform() !== null;

		if ( isMobile && isInAppBrowser() ) {
			showSaveHint( root );
			return;
		}
		// Ảnh đã tải sẵn: gọi chia sẻ/tải ngay trong sự kiện bấm.
		if ( state.blob ) {
			saveBlob( root, state.blob, filename, isMobile );
			return;
		}
		button.disabled = true;
		state.promise
			.then( function ( blob ) {
				return saveBlob( root, blob, filename, isMobile );
			} )
			.catch( function () {
				showSaveHint( root );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	// Theme cổ điển (không có Global Styles cho nút): đo nút của theme/WooCommerce rồi dùng màu đó.
	function applyThemeButtonColors( root ) {
		if ( getComputedStyle( root ).getPropertyValue( '--vietqr-theme-colors' ).trim() ) {
			return; // theme khối: PHP đã lấy màu từ Global Styles
		}
		var host = root.closest( '.woocommerce' ) || root.parentNode;
		var probe = document.createElement( 'div' );
		probe.setAttribute( 'aria-hidden', 'true' );
		probe.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden;pointer-events:none';
		probe.innerHTML = '<button type="button"></button><a href="#" class="button alt">x</a><button type="button" class="button">x</button>';
		host.appendChild( probe );
		var baseline = getComputedStyle( probe.children[ 0 ] ).backgroundColor;
		for ( var i = 1; i < probe.children.length; i++ ) {
			var style = getComputedStyle( probe.children[ i ] );
			var background = style.backgroundColor;
			if ( background && background !== baseline && ! /rgba\(\s*0,\s*0,\s*0,\s*0\s*\)|transparent/.test( background ) ) {
				root.style.setProperty( '--vietqr-accent', background );
				root.style.setProperty( '--vietqr-accent-contrast', style.color );
				root.style.setProperty( '--vietqr-button-radius', style.borderTopLeftRadius );
				break;
			}
		}
		host.removeChild( probe );
	}

	function init( root ) {
		if ( root.getAttribute( 'data-vietqr-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-vietqr-ready', '1' );
		applyThemeButtonColors( root );
		showApps( root );

		var saveButton = root.querySelector( '[data-vietqr-save]' );
		var saveState = saveButton ? prefetchQr( saveButton ) : null;

		root.addEventListener( 'click', function ( event ) {
			var target = event.target instanceof Element ? event.target : null;
			if ( ! target ) {
				return;
			}
			var copy = target.closest( '[data-vietqr-copy]' );
			if ( copy && root.contains( copy ) ) {
				event.preventDefault();
				onCopy( root, copy );
				return;
			}
			var save = target.closest( '[data-vietqr-save]' );
			if ( save && root.contains( save ) ) {
				event.preventDefault();
				onSave( root, save, saveState );
			}
		} );
	}

	function initAll() {
		var roots = document.querySelectorAll( '[data-vietqr]' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
