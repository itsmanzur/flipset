/**
 * Flipset admin: dashboard, shortcode builder and documents copy.
 */
( function () {
	'use strict';

	var config = window.FlipsetAdmin || {};

	function $( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function $$( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function onReady( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	function copiedLabel() {
		return ( config.i18n && config.i18n.copied ) || 'Copied!';
	}

	function post( action, data, onOk, onErr ) {
		var body = new window.URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', config.nonce || '' );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.set( key, data[ key ] );
		} );

		window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			} )
			.then( function ( res ) {
				return res.text();
			} )
			.then( function ( text ) {
				var clean = String( text || '' ).replace( /^\uFEFF+/g, '' ).trim();
				var start = clean.indexOf( '{' );
				if ( start > 0 ) {
					clean = clean.slice( start );
				}
				return JSON.parse( clean );
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					onOk( json.data || {} );
				} else if ( onErr ) {
					onErr( json && json.data ? json.data : {} );
				}
			} )
			.catch( function () {
				if ( onErr ) {
					onErr( {} );
				}
			} );
	}

	function t( key, fallback ) {
		return ( config.i18n && config.i18n[ key ] ) ? config.i18n[ key ] : fallback;
	}

	function toast( message ) {
		var el = $( '#flipset-toast' );
		if ( ! el || ! message ) {
			return;
		}
		el.textContent = message;
		el.hidden = false;
		el.classList.add( 'is-on' );
		window.clearTimeout( toast._timer );
		toast._timer = window.setTimeout( function () {
			el.classList.remove( 'is-on' );
		}, 1800 );
	}

	function flashCopied( button ) {
		var original = button ? button.textContent : '';
		if ( button ) {
			button.textContent = copiedLabel();
			window.setTimeout( function () {
				button.textContent = original;
			}, 1500 );
		}
		var code = $( '#flipset-code' );
		if ( code ) {
			code.classList.add( 'is-flash' );
			window.setTimeout( function () {
				code.classList.remove( 'is-flash' );
			}, 700 );
		}
		toast( t( 'copiedToast', 'Shortcode copied' ) );
	}

	function copyText( text, button, onCopied ) {
		if ( ! text ) {
			return;
		}
		var done = function () {
			flashCopied( button );
			if ( onCopied ) {
				onCopied();
			}
		};

		var fallbackCopy = function () {
			try {
				var textarea = document.createElement( 'textarea' );
				textarea.value = text;
				textarea.setAttribute( 'readonly', '' );
				textarea.style.position = 'fixed';
				textarea.style.left = '-9999px';
				textarea.style.top = '0';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.focus();
				textarea.select();
				var successful = document.execCommand( 'copy' );
				document.body.removeChild( textarea );
				if ( successful ) {
					done();
					return;
				}
			} catch ( err ) {
				// Fallback failed.
			}
			done();
		};

		if ( window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
			navigator.clipboard
				.writeText( text )
				.then( done )
				.catch( function () {
					fallbackCopy();
				} );
		} else {
			fallbackCopy();
		}
	}

	function markCopiedStep() {
		post( 'flipset_mark_setup_copied', {}, function () {
			var step = $( '[data-setup-step="copied"]' );
			if ( step ) {
				step.classList.add( 'is-done' );
			}
			refreshSetupUi();
		} );
	}

	function refreshSetupUi() {
		var items = $$( '.fa-steps li' );
		if ( ! items.length ) {
			return;
		}
		var done = items.filter( function ( li ) {
			return li.classList.contains( 'is-done' );
		} ).length;
		var total = items.length;
		var complete = done === total;
		var bar = $( '.fa-progress span' );
		var barWrap = $( '.fa-progress' );
		if ( bar ) {
			bar.style.width = Math.round( ( done / total ) * 100 ) + '%';
		}
		if ( barWrap ) {
			barWrap.setAttribute( 'aria-valuenow', String( Math.round( ( done / total ) * 100 ) ) );
		}
		var metric = $( '#flipset-metric-setup .fa-metric__value' );
		if ( metric ) {
			metric.textContent = done + '/' + total;
		}
		var metricCard = $( '#flipset-metric-setup' );
		if ( metricCard ) {
			metricCard.classList.toggle( 'is-complete', complete );
		}
		var ringFg = $( '#flipset-ring-fg' );
		var ringLabel = $( '#flipset-ring-label' );
		var ringWrap = $( '#flipset-ring-wrap' );
		if ( ringFg ) {
			ringFg.setAttribute( 'stroke-dasharray', Math.round( ( done / total ) * 100 ) + ' 100' );
		}
		if ( ringLabel ) {
			ringLabel.textContent = done + '/' + total;
		}
		if ( ringWrap ) {
			ringWrap.classList.toggle( 'is-complete', complete );
		}
		var panel = $( '#flipset-setup-panel' );
		var status = $( '#flipset-setup-status' );
		if ( panel ) {
			panel.classList.toggle( 'is-complete', complete );
		}
		if ( status ) {
			if ( complete && config.i18n && config.i18n.setupDone ) {
				status.textContent = config.i18n.setupDone;
			} else if ( config.i18n && config.i18n.stepsComplete ) {
				status.textContent = config.i18n.stepsComplete
					.replace( '%1$s', String( done ) )
					.replace( '%2$s', String( total ) );
			}
		}
	}

	function pulsePanel( id ) {
		var panel = $( id );
		if ( ! panel ) {
			return;
		}
		panel.classList.add( 'is-pulse' );
		if ( panel.scrollIntoView ) {
			panel.scrollIntoView( { block: 'nearest' } );
		}
		window.setTimeout( function () {
			panel.classList.remove( 'is-pulse' );
		}, 900 );
	}

	function setShortcode( url, title, thumbUrl, attachmentId ) {
		var input = $( '#flipset-shortcode-output' );
		var fileLabel = $( '#flipset-selected-file' );
		var createBtn = $( '#flipset-create-test-page' );
		var copyBtn = $( '#flipset-copy-shortcode' );
		if ( ! input ) {
			return;
		}
		input.value = url ? '[flipset file="' + url + '"]' : '';
		if ( fileLabel ) {
			fileLabel.textContent = title || url || t( 'selectPdf', 'Select PDF' );
			fileLabel.setAttribute( 'title', title || url || '' );
		}
		var openBuilder = $( '#fa-open-builder' );
		if ( openBuilder ) {
			var base = openBuilder.getAttribute( 'data-base' ) || openBuilder.getAttribute( 'href' ) || '';
			var idNum = parseInt( attachmentId, 10 ) || 0;
			openBuilder.setAttribute( 'href', idNum && base ? base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'pdf_id=' + idNum : base );
		}
		if ( createBtn ) {
			createBtn.disabled = ! url;
		}
		if ( copyBtn ) {
			copyBtn.disabled = ! url;
		}
		var stage = $( '#flipset-stage' );
		if ( stage ) {
			stage.classList.toggle( 'is-empty', ! url );
		}
		var thumb = $( '#flipset-stage-thumb' );
		var fallback = $( '#flipset-stage-fallback' );
		if ( thumb ) {
			if ( thumbUrl ) {
				thumb.src = thumbUrl;
				thumb.hidden = false;
				if ( fallback ) {
					fallback.hidden = true;
				}
			} else {
				thumb.removeAttribute( 'src' );
				thumb.hidden = true;
				if ( fallback ) {
					fallback.hidden = false;
				}
			}
		}
		$$( '.flipset-recent-item' ).forEach( function ( item ) {
			var selected = !!( url && item.getAttribute( 'data-url' ) === url );
			item.classList.toggle( 'is-selected', selected );
			item.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
		} );
	}

	function openMedia() {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: config.i18n && config.i18n.selectPdf ? config.i18n.selectPdf : 'Select PDF',
			button: { text: config.i18n && config.i18n.usePdf ? config.i18n.usePdf : 'Use this PDF' },
			library: { type: 'application/pdf' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			if ( file && file.url ) {
				setShortcode( file.url, file.filename || file.title || '', ( file.image && file.image.src ) || '', file.id );
				pulsePanel( '#flipset-embed-panel' );
			}
		} );
		frame.open();
	}

	function createTestPage( button ) {
		var input = $( '#flipset-shortcode-output' );
		var match = input && input.value ? input.value.match( /file="([^"]+)"/ ) : null;
		var file = match ? match[ 1 ] : '';
		if ( ! file ) {
			return;
		}
		button.disabled = true;
		var original = button.textContent;
		button.textContent = t( 'creating', 'Creating page…' );
		post(
			'flipset_create_test_page',
			{ file: file },
			function ( data ) {
				button.disabled = false;
				button.textContent = original;
				var step = $( '[data-setup-step="embedded"]' );
				if ( step ) {
					step.classList.add( 'is-done' );
				}
				var embeds = $( '#flipset-metric-pages .fa-metric__value' );
				if ( embeds ) {
					embeds.textContent = String( ( parseInt( embeds.textContent, 10 ) || 0 ) + 1 );
				}
				refreshSetupUi();
				if ( data.url ) {
					window.open( data.url, '_blank', 'noopener,noreferrer' );
				}
			},
			function () {
				button.disabled = false;
				button.textContent = original;
			}
		);
	}

	/** "Upload PDF" quick action: same upload flow as dropping a file on the stage. */
	function bindUploadTrigger() {
		var trigger = $( '#fa-upload-trigger' );
		var input = $( '#fa-upload-input' );
		if ( ! trigger || ! input ) {
			return;
		}
		trigger.addEventListener( 'click', function () {
			if ( ! config.canUpload ) {
				openMedia();
				return;
			}
			input.click();
		} );
		input.addEventListener( 'change', function () {
			var file = input.files && input.files[ 0 ];
			input.value = '';
			if ( ! file ) {
				return;
			}
			if ( ! isPdfFile( file ) ) {
				toast( t( 'notPdf', 'Please drop a PDF file.' ) );
				return;
			}
			uploadPdf( file );
		} );
	}

	/** Complete setup collapses to one line; the toggle remembers its state on this device. */
	function bindSetupToggle() {
		var panel = $( '#flipset-setup-panel' );
		var toggle = $( '#flipset-setup-toggle' );
		if ( ! panel || ! toggle ) {
			return;
		}
		var KEY = 'flipset_setup_open';
		var apply = function ( open ) {
			panel.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = open ? ( toggle.getAttribute( 'data-hide' ) || 'Hide steps' ) : ( toggle.getAttribute( 'data-show' ) || 'Show steps' );
		};
		var saved = false;
		try {
			saved = '1' === window.localStorage.getItem( KEY );
		} catch ( err ) {
			saved = false;
		}
		apply( saved );
		toggle.addEventListener( 'click', function () {
			var open = ! panel.classList.contains( 'is-open' );
			apply( open );
			try {
				window.localStorage.setItem( KEY, open ? '1' : '0' );
			} catch ( err ) {
				// Storage blocked; the state just won't persist.
			}
		} );
	}

	/** Copy icon buttons in the Recent saved embeds list. */
	function bindCopyButtons() {
		$$( '[data-fa-copy]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				copyText( btn.getAttribute( 'data-fa-copy' ) || '', null, function () {
					btn.classList.add( 'is-copied' );
					window.setTimeout( function () {
						btn.classList.remove( 'is-copied' );
					}, 1200 );
				} );
			} );
		} );
	}

	function bindDashboard() {
		var pick = $( '#flipset-pick-pdf' );
		var copy = $( '#flipset-copy-shortcode' );
		var create = $( '#flipset-create-test-page' );
		if ( pick ) {
			pick.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openMedia();
			} );
		}
		if ( copy ) {
			copy.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var input = $( '#flipset-shortcode-output' );
				copyText( input ? input.value : '', copy, markCopiedStep );
			} );
		}
		if ( create ) {
			create.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				createTestPage( create );
			} );
		}
		$$( '.flipset-recent-item' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				setShortcode(
					item.getAttribute( 'data-url' ) || '',
					item.getAttribute( 'data-title' ) || '',
					item.getAttribute( 'data-thumb' ) || '',
					item.getAttribute( 'data-id' ) || ''
				);
			} );
		} );
		var code = $( '#flipset-code' );
		if ( code ) {
			code.addEventListener( 'click', function ( e ) {
				if ( e.target && e.target.closest && e.target.closest( '#flipset-copy-shortcode' ) ) {
					return;
				}
				var input = $( '#flipset-shortcode-output' );
				copyText( input ? input.value : '', copy, markCopiedStep );
			} );
		}
		$$( '[data-flipset-focus]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var target = button.getAttribute( 'data-flipset-focus' );
				pulsePanel( '#flipset-embed-panel' );
				if ( 'embed-create' === target && create && ! create.disabled ) {
					create.focus();
				} else if ( pick ) {
					pick.focus();
				}
			} );
		} );
		bindUploadTrigger();
		bindSetupToggle();
		bindCopyButtons();
		bindDrop();
		bindCountUp();
	}

	function isPdfFile( file ) {
		if ( ! file ) {
			return false;
		}
		if ( file.type === 'application/pdf' ) {
			return true;
		}
		return /\.pdf$/i.test( file.name || '' );
	}

	function parseAjax( text ) {
		var clean = String( text || '' ).replace( /^\uFEFF+/g, '' ).trim();
		var start = clean.indexOf( '{' );
		if ( start > 0 ) {
			clean = clean.slice( start );
		}
		return JSON.parse( clean );
	}

	function uploadPdf( file ) {
		toast( t( 'uploading', 'Uploading…' ) );
		var body = new window.FormData();
		body.append( 'async-upload', file );
		body.append( 'name', file.name );
		body.append( 'action', 'upload-attachment' );
		body.append( '_wpnonce', config.uploadNonce || '' );

		window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
			.then( function ( res ) {
				return res.text();
			} )
			.then( parseAjax )
			.then( function ( json ) {
				if ( json && json.success && json.data && json.data.url ) {
					setShortcode( json.data.url, json.data.filename || json.data.title || file.name, '', json.data.id );
					var uploaded = $( '[data-setup-step="uploaded"]' );
					if ( uploaded ) {
						uploaded.classList.add( 'is-done' );
					}
					var pdfs = $( '#flipset-metric-pdfs .fa-metric__value' );
					if ( pdfs ) {
						pdfs.textContent = String( ( parseInt( pdfs.textContent, 10 ) || 0 ) + 1 );
					}
					refreshSetupUi();
					pulsePanel( '#flipset-embed-panel' );
				} else {
					toast( t( 'uploadFail', 'Upload failed. Try the Media Library.' ) );
				}
			} )
			.catch( function () {
				toast( t( 'uploadFail', 'Upload failed. Try the Media Library.' ) );
			} );
	}

	function bindDrop() {
		var panel = $( '#flipset-embed-panel' );
		var overlay = $( '#flipset-drop' );
		if ( ! panel || ! overlay ) {
			return;
		}
		var dragCount = 0;
		panel.addEventListener( 'dragenter', function ( e ) {
			e.preventDefault();
			dragCount += 1;
			overlay.hidden = false;
		} );
		panel.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			if ( e.dataTransfer ) {
				e.dataTransfer.dropEffect = 'copy';
			}
		} );
		panel.addEventListener( 'dragleave', function ( e ) {
			e.preventDefault();
			dragCount -= 1;
			if ( dragCount <= 0 ) {
				dragCount = 0;
				overlay.hidden = true;
			}
		} );
		panel.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			dragCount = 0;
			overlay.hidden = true;
			var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[ 0 ];
			if ( ! file ) {
				return;
			}
			if ( ! isPdfFile( file ) ) {
				toast( t( 'notPdf', 'Please drop a PDF file.' ) );
				return;
			}
			if ( ! config.canUpload ) {
				openMedia();
				return;
			}
			uploadPdf( file );
		} );
	}

	function bindCountUp() {
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}
		$$( '.fa-metric__value[data-count]' ).forEach( function ( el ) {
			var to = parseInt( el.getAttribute( 'data-count' ), 10 );
			if ( ! to ) {
				return;
			}
			var steps = Math.min( 18, Math.max( 8, to ) );
			var i = 0;
			el.textContent = '0';
			var timer = window.setInterval( function () {
				i += 1;
				el.textContent = String( Math.round( ( to * i ) / steps ) );
				if ( i >= steps ) {
					el.textContent = String( to );
					window.clearInterval( timer );
				}
			}, 28 );
		} );
	}

	function flashButton( el ) {
		el.classList.add( 'is-copied' );
		window.setTimeout( function () {
			el.classList.remove( 'is-copied' );
		}, 1500 );
	}

	/** Create (or update) the shared test page for one PDF row. */
	function bindDocCreateTestPage() {
		$$( '.flipset-doc-create-test-btn' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var file = button.getAttribute( 'data-pdf-url' ) || '';
				if ( ! file || button.disabled ) {
					return;
				}
				var original = button.textContent;
				button.disabled = true;
				button.textContent = t( 'creating', 'Creating page…' );
				post(
					'flipset_create_test_page',
					{ file: file },
					function ( data ) {
						button.disabled = false;
						button.textContent = original;
						toast( t( 'testPageReady', 'Test page ready' ) );
						if ( data.url ) {
							window.open( data.url, '_blank', 'noopener,noreferrer' );
						}
					},
					function () {
						button.disabled = false;
						button.textContent = original;
						toast( t( 'createFail', 'Could not create the test page.' ) );
					}
				);
			} );
		} );
	}

	/** Pick a custom cover image for a PDF row, or reset it to the generated first page. */
	function bindDocCover() {
		var frame = null;

		function apply( row, imageId ) {
			var reset = row.querySelector( '.flipset-doc-cover-reset' );
			post(
				'flipset_set_cover',
				{ attachment_id: row.getAttribute( 'data-pdf-id' ) || '0', image_id: imageId },
				function ( data ) {
					if ( data.needs ) {
						// No generated image yet: reload so the first-page preview is generated again.
						window.location.reload();
						return;
					}
					var cell = row.querySelector( '.fa-col-thumb' );
					if ( cell && data.url ) {
						var img = cell.querySelector( 'img' );
						if ( ! img ) {
							img = document.createElement( 'img' );
							img.className = 'flipset-docs-thumb-img';
							img.alt = row.getAttribute( 'data-pdf-title' ) || '';
							cell.textContent = '';
							cell.appendChild( img );
						}
						img.removeAttribute( 'srcset' );
						img.removeAttribute( 'sizes' );
						img.src = data.url;
					}
					row.setAttribute( 'data-has-cover', data.custom ? '1' : '0' );
					if ( reset ) {
						reset.hidden = ! data.custom;
					}
					toast( data.custom ? t( 'coverSaved', 'Cover updated' ) : t( 'coverReset', 'Cover reset to the first page' ) );
				},
				function () {
					toast( t( 'coverFailed', 'Could not change the cover.' ) );
				}
			);
		}

		$$( '.flipset-doc-cover-btn' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var row = button.closest( 'tr' );
				if ( ! row || ! window.wp || ! window.wp.media ) {
					return;
				}
				frame = window.wp.media( {
					title: t( 'selectCover', 'Choose a cover image' ),
					button: { text: t( 'useCover', 'Use as cover' ) },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					if ( image && image.id ) {
						apply( row, String( image.id ) );
					}
				} );
				frame.open();
			} );
		} );

		$$( '.flipset-doc-cover-reset' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var row = button.closest( 'tr' );
				if ( row ) {
					apply( row, '0' );
				}
			} );
		} );
	}

	/** Collapsible "4 ways to embed" card; the state is a per-browser convenience. */
	function bindHowto() {
		var card = $( '#fa-howto' );
		var toggle = $( '#fa-howto-toggle' );
		var body = $( '#fa-howto-body' );
		if ( ! card || ! toggle || ! body ) {
			return;
		}
		var KEY = 'flipset_howto_collapsed';
		var apply = function ( collapsed ) {
			body.hidden = collapsed;
			card.classList.toggle( 'is-collapsed', collapsed );
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			toggle.textContent = collapsed ? toggle.getAttribute( 'data-show' ) : toggle.getAttribute( 'data-hide' );
		};
		var stored = false;
		try {
			stored = '1' === window.localStorage.getItem( KEY );
		} catch ( err ) {
			stored = false;
		}
		apply( stored );
		toggle.addEventListener( 'click', function () {
			var next = ! body.hidden;
			apply( next );
			try {
				window.localStorage.setItem( KEY, next ? '1' : '0' );
			} catch ( err ) {
				// Storage unavailable; the state stays for this view only.
			}
		} );
	}

	/** Highlight the guide sub-nav chip of the section being read. */
	function bindGuideSpy() {
		var links = $$( '#fa-guide-nav a[href^="#guide-"]' );
		if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var byId = {};
		links.forEach( function ( link ) {
			byId[ link.getAttribute( 'href' ).slice( 1 ) ] = link;
		} );
		var setActive = function ( id ) {
			links.forEach( function ( link ) {
				var on = byId[ id ] === link;
				link.classList.toggle( 'is-active', on );
				if ( on ) {
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
		};
		var ids = Object.keys( byId );
		// The active chip is the last section whose top has passed the sticky bar.
		var update = function () {
			var current = ids[ 0 ];
			ids.forEach( function ( id ) {
				var section = document.getElementById( id );
				if ( section && section.getBoundingClientRect().top <= 140 ) {
					current = id;
				}
			} );
			setActive( current );
		};
		var observer = new window.IntersectionObserver( update, { rootMargin: '-120px 0px -65% 0px', threshold: [ 0, 1 ] } );
		ids.forEach( function ( id ) {
			var section = document.getElementById( id );
			if ( section ) {
				observer.observe( section );
			}
		} );
		update();
	}

	function bindDocuments() {
		$$( '.flipset-copy-btn, .flipset-copy-preset-btn, .flipset-copy-snippet-btn' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var text = button.getAttribute( 'data-flipset-copy' ) || '';
				// Icon buttons keep their markup; the check state is a CSS class.
				copyText( text, null, function () {
					flashButton( button );
					markCopiedStep();
				} );
			} );
		} );
		$$( '.flipset-code-chip-copy' ).forEach( function ( chip ) {
			var run = function () {
				copyText( chip.getAttribute( 'data-flipset-copy' ) || '', null, function () {
					flashButton( chip );
				} );
			};
			chip.setAttribute( 'role', 'button' );
			chip.setAttribute( 'tabindex', '0' );
			chip.setAttribute( 'aria-label', t( 'copyShortcode', 'Copy' ) + ': ' + ( chip.textContent || '' ).trim() );
			chip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				run();
			} );
			chip.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					run();
				}
			} );
		} );
		bindDocCreateTestPage();
		bindDocCover();
		bindHowto();
		bindGuideSpy();
	}

	function bindShortcodeBuilder() {
		var builder = $( '.flipset-builder-wrap' );
		if ( ! builder ) {
			return;
		}

		var fileInput = $( '#flipset-builder-file' );
		var nameInput = $( '#flipset-builder-name' );
		var idInput = $( '#flipset-builder-id' );
		var titleInput = $( '#flipset-builder-title' );
		var widthInput = $( '#flipset-builder-width' );
		var heightInput = $( '#flipset-builder-height' );
		var pageInput = $( '#flipset-builder-page' );
		var lazyCheck = $( '#flipset-builder-lazy' );
		var hashCheck = $( '#flipset-builder-hash' );
		var resumeCheck = $( '#flipset-builder-resume' );
		var outputArea = $( '#flipset-builder-output' );
		var saveBtn = $( '#flipset-builder-save' );
		var saveBtnText = $( '#flipset-save-btn-text' );
		var copyBtn = $( '#flipset-builder-copy' );
		var createPageBtn = $( '#flipset-builder-create-page' );
		var rawShortcodeToggle = $( '#flipset-toggle-raw-shortcode' );
		var frame = $( '#flipset-builder-frame' );
		var stage = $( '#flipset-builder-stage' );
		var currentFormat = 'shortcode';
		var debounceTimer = null;

		function getSelectedTheme() {
			var checked = $( 'input[name="flipset_b_theme"]:checked' );
			return checked ? checked.value : 'light';
		}

		function getSelectedView() {
			var checked = $( 'input[name="flipset_b_view"]:checked' );
			return checked ? checked.value : 'page';
		}

		function getHiddenTools() {
			var hidden = [];
			$$( 'input.flipset-tool-toggle' ).forEach( function ( cb ) {
				if ( ! cb.checked ) {
					var tool = cb.getAttribute( 'data-tool' );
					if ( tool ) {
						hidden.push( tool );
					}
				}
			} );
			return hidden;
		}

		function buildRawShortcodeString() {
			var file = fileInput ? fileInput.value.trim() : '';
			if ( ! file ) {
				return '';
			}
			var parts = [ 'flipset', 'file="' + file + '"' ];

			var width = widthInput ? widthInput.value.trim() : '';
			if ( width && width !== '100%' ) {
				parts.push( 'width="' + width + '"' );
			}

			var height = heightInput ? heightInput.value.trim() : '';
			if ( height && height !== '600px' && height !== '520px' ) {
				parts.push( 'height="' + height + '"' );
			}

			var theme = getSelectedTheme();
			if ( theme && theme !== 'light' ) {
				parts.push( 'theme="' + theme + '"' );
			}

			var title = titleInput ? titleInput.value.trim() : '';
			if ( title ) {
				parts.push( 'title="' + title.replace( /"/g, '&quot;' ) + '"' );
			}

			var page = pageInput ? parseInt( pageInput.value, 10 ) : 1;
			if ( page && page > 1 ) {
				parts.push( 'page="' + page + '"' );
			}

			var view = getSelectedView();
			if ( view && view !== 'page' ) {
				parts.push( 'view="' + view + '"' );
			}

			var hidden = getHiddenTools();
			if ( hidden.length > 0 ) {
				parts.push( 'hide="' + hidden.join( ',' ) + '"' );
			}

			// Force-enable tools checked here but disabled in global settings
			$$( 'input.flipset-tool-toggle[data-attr]' ).forEach( function ( cb ) {
				if ( cb.checked && cb.getAttribute( 'data-global' ) === '0' ) {
					parts.push( cb.getAttribute( 'data-attr' ) + '="true"' );
				}
			} );

			if ( lazyCheck && lazyCheck.checked ) {
				parts.push( 'loading="lazy"' );
			}

			if ( hashCheck && ! hashCheck.checked ) {
				parts.push( 'hash="false"' );
			}

			if ( resumeCheck && ! resumeCheck.checked ) {
				parts.push( 'resume="false"' );
			}

			return '[' + parts.join( ' ' ) + ']';
		}

		function getEmbedId() {
			return idInput ? parseInt( idInput.value, 10 ) || 0 : 0;
		}

		function buildActiveShortcode() {
			var currentId = getEmbedId();
			if ( rawShortcodeToggle && rawShortcodeToggle.checked ) {
				return buildRawShortcodeString();
			}
			if ( currentId > 0 ) {
				return '[flipset id="' + currentId + '"]';
			}
			return buildRawShortcodeString();
		}

		function updateOutput() {
			if ( ! outputArea ) {
				return;
			}
			var code = buildActiveShortcode();
			if ( ! code ) {
				outputArea.value = '';
				return;
			}

			if ( currentFormat === 'php' ) {
				outputArea.value = "<?php echo do_shortcode( '" + code + "' ); ?>";
			} else if ( currentFormat === 'block' ) {
				outputArea.value = "<!-- wp:flipset/viewer -->\n" + code + "\n<!-- /wp:flipset/viewer -->";
			} else {
				outputArea.value = code;
			}
		}

		function updateLivePreview() {
			var wrap = $( '.flipset-wrap', stage );
			var shell = $( '.flipset-shell', stage );
			var viewer = $( '.flipset-viewer', stage );
			var file = fileInput ? fileInput.value.trim() : '';

			if ( ! file ) {
				stage.innerHTML = '<div class="flipset-builder-empty-state"><span class="dashicons dashicons-pdf" aria-hidden="true"></span><p>' + t( 'previewEmpty', 'Select or upload a PDF above to see live interactive preview.' ) + '</p></div>';
				return;
			}

			if ( wrap && shell && viewer ) {
				var width = widthInput ? widthInput.value.trim() : '100%';
				var height = heightInput ? heightInput.value.trim() : '520px';
				wrap.style.width = width || '100%';
				shell.style.height = height || '520px';

				var theme = getSelectedTheme();
				[ 'light', 'dark', 'sepia' ].forEach( function ( tName ) {
					shell.classList.remove( 'flipset-theme-' + tName );
				} );
				shell.classList.add( 'flipset-theme-' + theme );

				var toolbar = $( '.flipset-toolbar', shell );
				if ( toolbar ) {
					var hideTools = getHiddenTools();
					var dl = $( '.flipset-download', toolbar );
					if ( dl ) { dl.style.display = hideTools.indexOf( 'download' ) !== -1 ? 'none' : ''; }
					var pr = $( '.flipset-print', toolbar );
					if ( pr ) { pr.style.display = hideTools.indexOf( 'print' ) !== -1 ? 'none' : ''; }
					var se = $( '.flipset-find-group', toolbar );
					if ( se ) { se.style.display = hideTools.indexOf( 'search' ) !== -1 ? 'none' : ''; }
					var th = $( '.flipset-theme-toggle', toolbar );
					if ( th ) { th.style.display = hideTools.indexOf( 'theme' ) !== -1 ? 'none' : ''; }
					var sh = $( '.flipset-share-btn', toolbar );
					if ( sh ) { sh.style.display = hideTools.indexOf( 'share' ) !== -1 ? 'none' : ''; }
					var sc = $( '.flipset-shortcuts-btn', toolbar );
					if ( sc ) { sc.style.display = hideTools.indexOf( 'shortcuts' ) !== -1 ? 'none' : ''; }
					var fs = $( '.flipset-fs', toolbar );
					if ( fs ) { fs.style.display = hideTools.indexOf( 'fullscreen' ) !== -1 ? 'none' : ''; }
					var ps = $( '.flipset-present', toolbar );
					if ( ps ) { ps.style.display = hideTools.indexOf( 'presentation' ) !== -1 ? 'none' : ''; }
					var zm = $( '.flipset-zoom-in, .flipset-zoom-out, .flipset-zoom-label', toolbar );
					if ( zm ) {
						$$( '.flipset-zoom-in, .flipset-zoom-out, .flipset-zoom-label', toolbar ).forEach( function ( el ) {
							el.style.display = hideTools.indexOf( 'zoom' ) !== -1 ? 'none' : '';
						} );
					}
					var ro = $( '.flipset-rotate', toolbar );
					if ( ro ) { ro.style.display = hideTools.indexOf( 'rotate' ) !== -1 ? 'none' : ''; }
					var pa = $( '.flipset-pan', toolbar );
					if ( pa ) { pa.style.display = hideTools.indexOf( 'pan' ) !== -1 ? 'none' : ''; }
				}

				var currentFile = viewer.getAttribute( 'data-file' );
				var currentView = viewer.getAttribute( 'data-view' ) || 'page';
				var selectedView = getSelectedView();

				if ( currentFile !== file ) {
					viewer.setAttribute( 'data-file', file );
					viewer.setAttribute( 'data-view', selectedView );
					if ( window.FlipsetViewer && window.FlipsetViewer.rebind ) {
						window.FlipsetViewer.rebind( viewer );
					}
				} else if ( currentView !== selectedView ) {
					if ( window.FlipsetViewer && window.FlipsetViewer.setViewMode ) {
						window.FlipsetViewer.setViewMode( viewer, selectedView );
					}
				}
			}
		}

		function triggerChange() {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( function () {
				updateOutput();
				updateLivePreview();
			}, 50 );
		}

		[ fileInput, titleInput, nameInput, widthInput, heightInput, pageInput ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', triggerChange );
				input.addEventListener( 'change', triggerChange );
			}
		} );

		$$( 'input[name="flipset_b_theme"], input[name="flipset_b_view"], input.flipset-tool-toggle, #flipset-builder-lazy, #flipset-builder-hash, #flipset-builder-resume' ).forEach( function ( el ) {
			el.addEventListener( 'change', triggerChange );
		} );

		if ( rawShortcodeToggle ) {
			rawShortcodeToggle.addEventListener( 'change', updateOutput );
		}

		$$( '.flipset-quick-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				var targetSel = chip.getAttribute( 'data-target' );
				var val = chip.getAttribute( 'data-val' );
				var target = $( targetSel );
				if ( target && val ) {
					target.value = val;
					triggerChange();
				}
			} );
		} );

		var mediaFrame = null;
		function openBuilderMedia( uploadOnly ) {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}
			mediaFrame = wp.media( {
				title: t( 'selectPdf', 'Select PDF' ),
				button: { text: t( 'usePdf', 'Use this PDF' ) },
				multiple: false,
				library: { type: 'application/pdf' },
			} );
			mediaFrame.on( 'select', function () {
				var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				if ( attachment && attachment.url ) {
					if ( fileInput ) {
						fileInput.value = attachment.url;
					}
					if ( nameInput && ( ! nameInput.value || nameInput.value === '' ) ) {
						nameInput.value = attachment.title || attachment.filename || '';
					}
					if ( titleInput && ( ! titleInput.value || titleInput.value === '' ) ) {
						titleInput.value = attachment.title || attachment.filename || '';
					}
					$$( '.flipset-recent-btn' ).forEach( function ( btn ) {
						btn.classList.toggle( 'is-active', btn.getAttribute( 'data-url' ) === attachment.url );
					} );
					triggerChange();
				}
			} );
			mediaFrame.open();
		}

		var pickBtn = $( '#flipset-builder-pick-pdf' );
		var uploadBtn = $( '#flipset-builder-upload-pdf' );
		if ( pickBtn ) {
			pickBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openBuilderMedia( false );
			} );
		}
		if ( uploadBtn ) {
			uploadBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openBuilderMedia( true );
			} );
		}

		$$( '.flipset-recent-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				$$( '.flipset-recent-btn' ).forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );
				var url = btn.getAttribute( 'data-url' ) || '';
				var title = btn.getAttribute( 'data-title' ) || '';
				if ( fileInput ) { fileInput.value = url; }
				if ( titleInput && ! titleInput.value ) { titleInput.value = title; }
				if ( nameInput && ! nameInput.value ) { nameInput.value = title; }
				triggerChange();
			} );
		} );

		var presets = {
			flipbook: {
				theme: 'light',
				view: 'flip',
				width: '100%',
				height: '620px',
				tools: { download: true, print: true, search: true, theme: true, share: true, shortcuts: true, fullscreen: true, presentation: true, zoom: true, rotate: false, pan: false },
				lazy: false
			},
			ebook: {
				theme: 'sepia',
				view: 'scroll',
				width: '100%',
				height: '650px',
				tools: { download: false, print: false, search: true, theme: true, share: true, shortcuts: true, fullscreen: true, presentation: false, zoom: true, rotate: false, pan: false },
				lazy: true
			},
			corporate: {
				theme: 'light',
				view: 'page',
				width: '100%',
				height: '600px',
				tools: { download: true, print: true, search: true, theme: false, share: true, shortcuts: true, fullscreen: true, presentation: true, zoom: true, rotate: true, pan: true },
				lazy: false
			},
			night: {
				theme: 'dark',
				view: 'scroll',
				width: '100%',
				height: '600px',
				tools: { download: true, print: true, search: true, theme: true, share: true, shortcuts: true, fullscreen: true, presentation: true, zoom: true, rotate: true, pan: true },
				lazy: false
			},
			viewonly: {
				theme: 'light',
				view: 'page',
				width: '100%',
				height: '550px',
				tools: { download: false, print: false, search: true, theme: true, share: true, shortcuts: true, fullscreen: true, presentation: false, zoom: true, rotate: false, pan: true },
				lazy: false
			},
			minimal: {
				theme: 'light',
				view: 'page',
				width: '100%',
				height: '500px',
				tools: { download: false, print: false, search: false, theme: false, share: false, shortcuts: false, fullscreen: true, presentation: false, zoom: true, rotate: false, pan: false },
				lazy: true
			}
		};

		$$( '.flipset-preset-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var pKey = chip.getAttribute( 'data-preset' );
				var p = presets[ pKey ];
				if ( ! p ) {
					return;
				}

				var themeRadio = $( 'input[name="flipset_b_theme"][value="' + p.theme + '"]' );
				if ( themeRadio ) { themeRadio.checked = true; }

				var viewRadio = $( 'input[name="flipset_b_view"][value="' + p.view + '"]' );
				if ( viewRadio ) { viewRadio.checked = true; }

				if ( widthInput ) { widthInput.value = p.width; }
				if ( heightInput ) { heightInput.value = p.height; }

				$$( 'input.flipset-tool-toggle' ).forEach( function ( cb ) {
					var tool = cb.getAttribute( 'data-tool' );
					if ( typeof p.tools[ tool ] !== 'undefined' ) {
						cb.checked = p.tools[ tool ];
					}
				} );

				if ( lazyCheck ) { lazyCheck.checked = p.lazy; }

				$$( '.flipset-preset-chip' ).forEach( function ( c ) { c.classList.remove( 'is-active' ); } );
				chip.classList.add( 'is-active' );

				toast( t( 'presetApplied', 'Preset applied' ) + ': ' + chip.textContent.trim() );
				triggerChange();
			} );
		} );

		function switchControlTab( tabKey ) {
			$$( '.flipset-ctrl-tab' ).forEach( function ( b ) {
				var match = b.getAttribute( 'data-tab' ) === tabKey;
				b.classList.toggle( 'is-active', match );
				b.setAttribute( 'aria-selected', match ? 'true' : 'false' );
			} );
			$$( '.flipset-ctrl-pane' ).forEach( function ( pane ) {
				pane.style.display = ( pane.id === 'flipset-pane-' + tabKey ) ? '' : 'none';
				pane.classList.toggle( 'is-active', pane.id === 'flipset-pane-' + tabKey );
			} );
		}

		$$( '.flipset-ctrl-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var tabKey = tab.getAttribute( 'data-tab' );
				if ( tabKey ) {
					switchControlTab( tabKey );
				}
			} );
		} );

		$$( '.flipset-tab-step' ).forEach( function ( stepBtn ) {
			stepBtn.addEventListener( 'click', function () {
				var targetKey = stepBtn.getAttribute( 'data-step-to' );
				if ( targetKey ) {
					switchControlTab( targetKey );
				}
			} );
		} );

		function switchMainView( viewName ) {
			$$( '.flipset-view-tab-btn' ).forEach( function ( btn ) {
				var match = btn.getAttribute( 'data-view' ) === viewName;
				btn.classList.toggle( 'is-active', match );
			} );
			var builderView = $( '#flipset-view-builder' );
			var embedsView = $( '#flipset-view-embeds' );
			if ( builderView && embedsView ) {
				builderView.style.display = ( viewName === 'builder' ) ? '' : 'none';
				embedsView.style.display = ( viewName === 'embeds' ) ? '' : 'none';
			}
		}

		$$( '.flipset-view-tab-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var v = btn.getAttribute( 'data-view' );
				if ( v ) {
					switchMainView( v );
				}
			} );
		} );

		try {
			if ( 'embeds' === new window.URLSearchParams( window.location.search ).get( 'view' ) ) {
				switchMainView( 'embeds' );
			}
		} catch ( err ) {
			// URLSearchParams unavailable; stay on the builder view.
		}

		$$( '.flipset-switch-to-builder' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				switchMainView( 'builder' );
			} );
		} );

		$$( '.flipset-output-tabs .flipset-tab-btn' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				$$( '.flipset-output-tabs .flipset-tab-btn' ).forEach( function ( tBtn ) { tBtn.classList.remove( 'is-active' ); } );
				tab.classList.add( 'is-active' );
				currentFormat = tab.getAttribute( 'data-format' ) || 'shortcode';
				updateOutput();
			} );
		} );

		$$( '.flipset-device-switcher .flipset-device-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				$$( '.flipset-device-switcher .flipset-device-btn' ).forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );
				var device = btn.getAttribute( 'data-device' ) || 'desktop';
				if ( frame ) {
					frame.classList.remove( 'is-desktop', 'is-tablet', 'is-mobile' );
					frame.classList.add( 'is-' + device );
				}
			} );
		} );

		if ( copyBtn && outputArea ) {
			copyBtn.addEventListener( 'click', function () {
				copyText( outputArea.value, copyBtn, markCopiedStep );
			} );
		}

		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var file = fileInput ? fileInput.value.trim() : '';
				if ( ! file ) {
					alert( t( 'selectPdfFirst', 'Please select a PDF file.' ) );
					return;
				}

				var curId = getEmbedId();
				var nameVal = nameInput ? nameInput.value.trim() : '';
				if ( ! nameVal ) {
					nameVal = ( titleInput && titleInput.value.trim() ) || 'PDF Embed';
					if ( nameInput ) {
						nameInput.value = nameVal;
					}
				}

				var payload = {
					embed_id: curId,
					name: nameVal,
					file: file,
					title: titleInput ? titleInput.value.trim() : '',
					width: widthInput ? widthInput.value.trim() : '100%',
					height: heightInput ? heightInput.value.trim() : '520px',
					theme: getSelectedTheme(),
					view: getSelectedView(),
					page: pageInput ? ( parseInt( pageInput.value, 10 ) || 1 ) : 1,
					hide: getHiddenTools().join( ',' ),
					loading: ( lazyCheck && lazyCheck.checked ) ? 'lazy' : '',
					hash: ( hashCheck && ! hashCheck.checked ) ? 'false' : 'true',
					resume: ( resumeCheck && ! resumeCheck.checked ) ? 'false' : 'true'
				};

				saveBtn.disabled = true;
				var originalLabel = saveBtnText ? saveBtnText.textContent : 'Save Embed';
				if ( saveBtnText ) {
					saveBtnText.textContent = t( 'saving', 'Saving…' );
				}

				post(
					'flipset_save_embed',
					payload,
					function ( data ) {
						saveBtn.disabled = false;
						if ( data && data.embed_id ) {
							if ( idInput ) {
								idInput.value = data.embed_id;
							}
							if ( saveBtnText ) {
								saveBtnText.textContent = t( 'savedCopiedBtn', '✓ Saved! Copied' );
							}

							updateOutput();

							var codeToCopy = data.shortcode || ( '[flipset id="' + data.embed_id + '"]' );
							copyText( codeToCopy, saveBtn, markCopiedStep );
							toast( t( 'toastSaved', 'Saved! %s copied to clipboard.' ).replace( '%s', codeToCopy ) );

							window.setTimeout( function () {
								if ( saveBtnText ) {
									saveBtnText.textContent = t( 'updateEmbed', 'Update Embed & Copy' );
								}
							}, 2200 );
						}
					},
					function ( err ) {
						saveBtn.disabled = false;
						if ( saveBtnText ) {
							saveBtnText.textContent = originalLabel;
						}
						alert( ( err && err.message ) || t( 'saveFailed', 'Could not save embed.' ) );
					}
				);
			} );
		}

		if ( createPageBtn ) {
			createPageBtn.addEventListener( 'click', function () {
				var file = fileInput ? fileInput.value.trim() : '';
				if ( ! file ) {
					alert( t( 'selectPdfFirst', 'Please select a PDF file.' ) );
					return;
				}
				var shortcode = buildActiveShortcode();
				var originalContent = createPageBtn.innerHTML;
				createPageBtn.disabled = true;
				createPageBtn.textContent = t( 'creating', 'Creating…' );

				post(
					'flipset_create_test_page',
					{ file: file, shortcode: shortcode },
					function ( data ) {
						createPageBtn.disabled = false;
						createPageBtn.innerHTML = originalContent;
						if ( data && data.url ) {
							window.open( data.url, '_blank' );
						}
					},
					function ( err ) {
						createPageBtn.disabled = false;
						createPageBtn.innerHTML = originalContent;
						alert( ( err && err.message ) || t( 'testPageFailed', 'Could not create test page.' ) );
					}
				);
			} );
		}

		var searchInput = $( '#flipset-embeds-search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				var term = searchInput.value.toLowerCase().trim();
				$$( '#flipset-embeds-tbody tr' ).forEach( function ( tr ) {
					var text = tr.getAttribute( 'data-search-text' ) || '';
					tr.style.display = ( ! term || text.indexOf( term ) !== -1 ) ? '' : 'none';
				} );
			} );
		}

		$$( '.flipset-shortcode-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var text = chip.getAttribute( 'data-copy' ) || chip.textContent.trim();
				copyText( text, chip, function () {
					toast( t( 'copiedItem', 'Copied: %s' ).replace( '%s', text ) );
				} );
			} );
		} );

		$$( '.flipset-embed-duplicate-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var embId = btn.getAttribute( 'data-id' );
				if ( ! embId ) {
					return;
				}
				btn.disabled = true;
				post(
					'flipset_duplicate_embed',
					{ embed_id: embId },
					function ( data ) {
						toast( t( 'embedDuplicated', 'Embed duplicated successfully.' ) );
						window.location.reload();
					},
					function ( err ) {
						btn.disabled = false;
						alert( ( err && err.message ) || t( 'duplicateFailed', 'Could not duplicate embed.' ) );
					}
				);
			} );
		} );

		$$( '.flipset-embed-delete-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var embId = btn.getAttribute( 'data-id' );
				if ( ! embId ) {
					return;
				}
				if ( ! window.confirm( t( 'confirmDelete', 'Are you sure you want to delete this embed? Any shortcode using id="%s" will no longer work.' ).replace( '%s', embId ) ) ) {
					return;
				}
				btn.disabled = true;
				post(
					'flipset_delete_embed',
					{ embed_id: embId },
					function () {
						var tr = btn.closest( 'tr' );
						if ( tr ) {
							tr.remove();
						}
						var badge = $( '#flipset-embeds-count-badge' );
						if ( badge ) {
							var currentCount = parseInt( badge.textContent, 10 ) || 0;
							badge.textContent = String( Math.max( 0, currentCount - 1 ) );
						}
						toast( t( 'embedDeleted', 'Embed deleted.' ) );
					},
					function ( err ) {
						btn.disabled = false;
						alert( ( err && err.message ) || t( 'deleteFailed', 'Could not delete embed.' ) );
					}
				);
			} );
		} );

		updateOutput();
	}

	function bindWelcome() {
		var wrap = $( '#flipset-welcome' );
		var btn = $( '#flipset-welcome-dismiss' );
		if ( ! wrap || ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			wrap.style.display = 'none';
			post( 'flipset_dismiss_welcome', {}, function () {} );
		} );
	}

	onReady( function () {
		bindDashboard();
		bindDocuments();
		bindShortcodeBuilder();
		bindWelcome();
	} );
} )();

