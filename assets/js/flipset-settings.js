/**
 * Flipset Settings screen: section tabs, dirty tracking, validation,
 * quick presets, reset, and the live preview.
 *
 * Everything is progressive enhancement. Without JavaScript the form still
 * posts to options.php and every section is shown stacked.
 */
( function () {
	'use strict';

	var cfg = window.FlipsetSettings || {};
	var i18n = cfg.i18n || {};

	function $( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function $$( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function format( template, a, b ) {
		return String( template || '' ).replace( '%1$s', a ).replace( '%2$s', b );
	}

	var DIMENSION = /^(0|\d+(\.\d+)?(px|%|em|rem|vh|vw|svh|dvh|lvh))$/;

	// Setting key => selector of the toolbar element it shows or hides.
	var TOOLBAR_FLAGS = {
		allow_download: '.flipset-download',
		allow_print: '.flipset-print',
		allow_search: '.flipset-find-group',
		allow_theme: '.flipset-theme-toggle',
		allow_share: '.flipset-share-btn',
		allow_shortcuts: '.flipset-shortcuts-btn',
		allow_fullscreen: '.flipset-fs',
		allow_presentation: '.flipset-present'
	};

	var DEVICE_WIDTH = { desktop: '100%', tablet: '768px', mobile: '375px' };

	function init() {
		var form = $( '#flipset-settings-form' );
		var root = $( '.flipset-settings-wrap' );
		if ( ! form || ! root ) {
			return;
		}
		root.classList.add( 'has-js' );

		var saveButtons = $$( '[data-fa-save]' );
		var dirtyPill = $( '#fa-dirty-pill' );
		var savebar = $( '#fa-savebar' );
		var tabsBar = $( '.fa-settings-tabs', root );
		var tabs = $$( '[data-fa-tab]', root );
		var panels = $$( '[data-fa-panel]', root );
		var defaults = {};
		var initial = '';
		var dirty = false;
		var submitting = false;
		var activeTab = 'viewer';

		try {
			defaults = JSON.parse( form.getAttribute( 'data-defaults' ) || '{}' );
		} catch ( e ) {
			defaults = {};
		}

		function field( key ) {
			return form.elements[ 'flipset_settings[' + key + ']' ];
		}

		function checked( key ) {
			var el = field( key );
			return !! ( el && el.checked );
		}

		function value( key ) {
			var el = field( key );
			if ( ! el ) {
				return '';
			}
			if ( el.length ) {
				// Radio group.
				for ( var i = 0; i < el.length; i++ ) {
					if ( el[ i ].checked ) {
						return el[ i ].value;
					}
				}
				return '';
			}
			return el.value;
		}

		function serialize() {
			var parts = [];
			Array.prototype.forEach.call( form.elements, function ( el ) {
				if ( ! el.name || el.disabled ) {
					return;
				}
				if ( ( el.type === 'checkbox' || el.type === 'radio' ) && ! el.checked ) {
					return;
				}
				parts.push( el.name + '=' + el.value );
			} );
			return parts.join( '&' );
		}

		/* ---- Tabs ------------------------------------------------------ */
		function selectTab( slug, moveFocus ) {
			var found = tabs.some( function ( t ) {
				return t.getAttribute( 'data-fa-tab' ) === slug;
			} );
			if ( ! found ) {
				slug = 'viewer';
			}
			activeTab = slug;
			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-fa-tab' ) === slug;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', on ? '0' : '-1' );
				if ( on && moveFocus ) {
					tab.focus();
				}
			} );
			panels.forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-fa-panel' ) !== slug;
			} );
			try {
				window.sessionStorage.setItem( 'flipset_settings_tab', slug );
			} catch ( e ) {
				// Session storage unavailable; the hash still carries the tab.
			}
			if ( window.history && typeof window.history.replaceState === 'function' ) {
				window.history.replaceState( null, '', '#' + slug );
			}
		}

		if ( tabsBar && tabs.length ) {
			tabsBar.hidden = false;
			tabs.forEach( function ( tab, index ) {
				tab.addEventListener( 'click', function () {
					selectTab( tab.getAttribute( 'data-fa-tab' ), false );
				} );
				tab.addEventListener( 'keydown', function ( e ) {
					var next = null;
					if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
						next = ( index + 1 ) % tabs.length;
					} else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
						next = ( index - 1 + tabs.length ) % tabs.length;
					} else if ( 'Home' === e.key ) {
						next = 0;
					} else if ( 'End' === e.key ) {
						next = tabs.length - 1;
					}
					if ( null !== next ) {
						e.preventDefault();
						selectTab( tabs[ next ].getAttribute( 'data-fa-tab' ), true );
					}
				} );
			} );

			var fromHash = ( window.location.hash || '' ).replace( '#', '' );
			var remembered = '';
			try {
				remembered = window.sessionStorage.getItem( 'flipset_settings_tab' ) || '';
			} catch ( e ) {
				remembered = '';
			}
			selectTab( fromHash || remembered || 'viewer', false );

			window.addEventListener( 'hashchange', function () {
				var slug = ( window.location.hash || '' ).replace( '#', '' );
				if ( slug && slug !== activeTab ) {
					selectTab( slug, false );
				}
			} );
		}

		/* ---- Switch counts and tile state ------------------------------- */
		function updateCounts() {
			panels.forEach( function ( panel ) {
				var slug = panel.getAttribute( 'data-fa-panel' );
				var pill = $( '[data-fa-count="' + slug + '"]', root );
				if ( ! pill ) {
					return;
				}
				var switches = $$( 'input[role="switch"]', panel );
				var on = switches.filter( function ( s ) {
					return s.checked;
				} ).length;
				pill.textContent = format( i18n.countOn || '%1$s/%2$s on', on, switches.length );
			} );
			$$( '.fa-toggle-tile', root ).forEach( function ( tile ) {
				var input = $( 'input[role="switch"]', tile );
				tile.classList.toggle( 'is-on', !! ( input && input.checked ) );
			} );
		}

		/* ---- Dimension validation --------------------------------------- */
		function validateDimensions() {
			var allValid = true;
			$$( '[data-fa-dimension]', form ).forEach( function ( input ) {
				var ok = DIMENSION.test( input.value.trim() );
				var error = document.getElementById( input.id + '-error' );
				input.classList.toggle( 'is-invalid', ! ok );
				if ( ok ) {
					input.removeAttribute( 'aria-invalid' );
				} else {
					input.setAttribute( 'aria-invalid', 'true' );
					allValid = false;
				}
				if ( error ) {
					error.hidden = ok;
				}
			} );
			saveButtons.forEach( function ( btn ) {
				btn.disabled = ! allValid || submitting;
			} );
			return allValid;
		}

		/* ---- Dirty state ------------------------------------------------ */
		function refreshDirty() {
			dirty = serialize() !== initial;
			if ( dirtyPill ) {
				dirtyPill.hidden = ! dirty;
			}
			if ( savebar ) {
				savebar.hidden = ! dirty;
			}
		}

		/* ---- Live preview ----------------------------------------------- */
		var stage = $( '#fa-preview-stage' );
		var device = $( '#fa-preview-device' );

		function previewContainer() {
			return device ? $( '.flipset-viewer', device ) : null;
		}

		function applyPreview() {
			if ( ! device ) {
				return;
			}
			var wrap = $( '.flipset-wrap', device );
			var shell = $( '.flipset-shell', device );
			if ( ! wrap || ! shell ) {
				return;
			}

			var width = value( 'default_width' ).trim();
			var height = value( 'default_height' ).trim();
			if ( DIMENSION.test( width ) ) {
				wrap.style.width = width;
			}
			if ( DIMENSION.test( height ) ) {
				shell.style.height = height;
			}

			var theme = value( 'default_theme' ) || 'light';
			var container = previewContainer();
			if ( container ) {
				container.setAttribute( 'data-theme', theme );
				if ( container._flipsetInstance && typeof container._flipsetInstance.setTheme === 'function' ) {
					container._flipsetInstance.setTheme( theme );
				}
			}
			[ 'light', 'dark', 'sepia' ].forEach( function ( name ) {
				shell.classList.toggle( 'flipset-theme-' + name, name === theme );
			} );

			// The viewer's own CSS uses display: ... !important, so only an
			// inline !important declaration can hide a button reliably.
			Object.keys( TOOLBAR_FLAGS ).forEach( function ( key ) {
				var on = checked( key );
				$$( TOOLBAR_FLAGS[ key ], shell ).forEach( function ( el ) {
					el.classList.toggle( 'fa-is-off', ! on );
					if ( on ) {
						el.style.removeProperty( 'display' );
					} else {
						el.style.setProperty( 'display', 'none', 'important' );
					}
				} );
			} );

			var sizeChip = $( '#fa-preview-size' );
			if ( sizeChip ) {
				sizeChip.textContent = String( i18n.heightChip || 'Height %s · scaled to fit' ).replace( '%s', height || '—' );
			}
		}

		function refreshAll() {
			updateCounts();
			validateDimensions();
			applyPreview();
			refreshDirty();
		}

		function bindPreviewViewer( bindNow ) {
			var container = previewContainer();
			if ( bindNow && container && window.FlipsetViewer && typeof window.FlipsetViewer.bind === 'function' ) {
				window.FlipsetViewer.bind( container );
			}
			// The viewer builds parts of its chrome on bind; apply once more.
			applyPreview();
			window.setTimeout( applyPreview, 400 );
		}

		function post( data, onOk, onErr ) {
			var body = new window.URLSearchParams();
			body.set( 'action', 'flipset_settings_preview' );
			body.set( 'nonce', cfg.nonce || '' );
			Object.keys( data ).forEach( function ( key ) {
				body.set( key, data[ key ] );
			} );
			window.fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						onOk( json.data || {} );
					} else {
						onErr();
					}
				} )
				.catch( onErr );
		}

		var previewSelect = $( '#fa-preview-pdf' );
		if ( previewSelect && device ) {
			var lastPdf = previewSelect.value;
			previewSelect.addEventListener( 'change', function () {
				var requested = previewSelect.value;
				stage.setAttribute( 'aria-busy', 'true' );
				stage.classList.add( 'is-loading' );
				post(
					{
						pdf_id: requested,
						width: value( 'default_width' ),
						height: value( 'default_height' ),
						theme: value( 'default_theme' )
					},
					function ( data ) {
						var old = previewContainer();
						if ( old && old._flipsetInstance && typeof old._flipsetInstance.destroy === 'function' ) {
							old._flipsetInstance.destroy();
						}
						device.innerHTML = data.html || '';
						lastPdf = requested;
						stage.removeAttribute( 'aria-busy' );
						stage.classList.remove( 'is-loading' );
						bindPreviewViewer( true );
					},
					function () {
						previewSelect.value = lastPdf;
						stage.removeAttribute( 'aria-busy' );
						stage.classList.remove( 'is-loading' );
						toast( i18n.previewFail || 'Could not load the preview.' );
					}
				);
			} );
		}

		$$( '[data-device]', root ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var name = btn.getAttribute( 'data-device' );
				$$( '[data-device]', root ).forEach( function ( other ) {
					var on = other === btn;
					other.classList.toggle( 'is-active', on );
					other.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
				if ( device ) {
					device.style.maxWidth = DEVICE_WIDTH[ name ] || '100%';
				}
			} );
		} );

		// Preview card collapses under 1100px.
		var previewToggle = $( '#fa-preview-toggle' );
		var previewBody = $( '#fa-preview-body' );
		if ( previewToggle && previewBody && window.matchMedia ) {
			var mq = window.matchMedia( '(max-width: 1100px)' );
			var setCollapsed = function ( collapsed ) {
				previewBody.hidden = collapsed;
				previewToggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				previewToggle.textContent = collapsed
					? previewToggle.getAttribute( 'data-show' )
					: previewToggle.getAttribute( 'data-hide' );
			};
			var sync = function () {
				previewToggle.hidden = ! mq.matches;
				setCollapsed( mq.matches );
			};
			sync();
			if ( typeof mq.addEventListener === 'function' ) {
				mq.addEventListener( 'change', sync );
			}
			previewToggle.addEventListener( 'click', function () {
				setCollapsed( ! previewBody.hidden );
			} );
		}

		/* ---- Presets, bulk and reset ------------------------------------ */
		function fire( el ) {
			el.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			el.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		}

		$$( '[data-fa-preset]', form ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var input = document.getElementById( btn.getAttribute( 'data-target' ) );
				if ( input ) {
					input.value = btn.getAttribute( 'data-fa-preset' );
					fire( input );
					input.focus();
				}
			} );
		} );

		$$( '[data-fa-bulk]', form ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var on = 'on' === btn.getAttribute( 'data-fa-bulk' );
				var panel = btn.closest( '[data-fa-panel]' );
				$$( 'input[role="switch"]', panel ).forEach( function ( input ) {
					if ( input.checked !== on ) {
						input.checked = on;
						fire( input );
					}
				} );
			} );
		} );

		$$( '[data-fa-reset]', form ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( i18n.confirmReset || 'Reset to defaults?' ) ) {
					return;
				}
				var panel = btn.closest( '[data-fa-panel]' );
				$$( 'input', panel ).forEach( function ( input ) {
					var match = /^flipset_settings\[(.+)\]$/.exec( input.name || '' );
					if ( ! match || ! ( match[ 1 ] in defaults ) ) {
						return;
					}
					var def = defaults[ match[ 1 ] ];
					if ( input.type === 'checkbox' ) {
						input.checked = !! def;
					} else if ( input.type === 'radio' ) {
						input.checked = input.value === def;
					} else {
						input.value = def;
					}
				} );
				refreshAll();
			} );
		} );

		var discard = $( '#fa-discard' );
		if ( discard ) {
			discard.addEventListener( 'click', function () {
				form.reset();
				refreshAll();
			} );
		}

		/* ---- Change + submit + unload ----------------------------------- */
		form.addEventListener( 'input', refreshAll );
		form.addEventListener( 'change', refreshAll );

		form.addEventListener( 'submit', function ( e ) {
			if ( ! validateDimensions() ) {
				e.preventDefault();
				var bad = $( '.is-invalid', form );
				if ( bad ) {
					var panel = bad.closest( '[data-fa-panel]' );
					if ( panel ) {
						selectTab( panel.getAttribute( 'data-fa-panel' ), false );
					}
					bad.focus();
				}
				return;
			}
			submitting = true;
			var referer = form.elements._wp_http_referer;
			if ( referer ) {
				referer.value = String( referer.value ).replace( /#.*$/, '' ) + '#' + activeTab;
			}
			window.setTimeout( function () {
				saveButtons.forEach( function ( btn ) {
					btn.disabled = true;
					btn.classList.add( 'is-loading' );
				} );
			}, 0 );
		} );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! submitting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		window.addEventListener( 'pageshow', function () {
			submitting = false;
			saveButtons.forEach( function ( btn ) {
				btn.classList.remove( 'is-loading' );
			} );
			refreshAll();
		} );

		/* ---- Initial state ---------------------------------------------- */
		initial = serialize();
		updateCounts();
		validateDimensions();
		// The viewer script binds the server-rendered preview itself.
		bindPreviewViewer( false );

		// WordPress strips settings-updated from the URL, so also look for its notice.
		if ( $( '#setting-error-settings_updated' ) || /(^|[?&])settings-updated=true/.test( window.location.search ) ) {
			toast( i18n.saved || 'Settings saved' );
		}
	}

	function toast( message ) {
		var el = $( '#flipset-toast' );
		if ( ! el || ! message ) {
			return;
		}
		el.textContent = message;
		el.hidden = false;
		el.classList.add( 'is-on' );
		window.clearTimeout( toast.timer );
		toast.timer = window.setTimeout( function () {
			el.classList.remove( 'is-on' );
		}, 2200 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
