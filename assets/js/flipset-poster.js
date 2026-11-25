/**
 * Flipset poster mode.
 *
 * A poster is the PDF's cover, its title and an Open link. Nothing heavy is
 * downloaded until a visitor clicks it: then this script loads the bundled
 * PDF.js and the viewer script (once per page), removes the poster and starts
 * the viewer in place. Without JavaScript the link simply opens the PDF.
 */
( function () {
	'use strict';

	var cfg = window.FlipsetPosterConfig || {};
	var PDFJS_KEY = 'pdfjs-dist/build/pdf';
	var loading = null;

	function loadScript( src ) {
		return new Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = src;
			script.async = false;
			script.onload = resolve;
			script.onerror = reject;
			( document.body || document.head ).appendChild( script );
		} );
	}

	/**
	 * Load PDF.js, keeping a private reference to it and putting back any other
	 * copy the page already had (same job as Flipset_Viewer::PDFJS_BEFORE/AFTER).
	 *
	 * @return {Promise} Resolves once Flipset's PDF.js is available.
	 */
	function loadPdfjs() {
		var w = window;
		var flipset = ( w.Flipset = w.Flipset || {} );
		if ( flipset.pdfjsLib ) {
			return Promise.resolve();
		}
		var prev = { g: w.pdfjsLib, u: w[ PDFJS_KEY ] };

		return loadScript( cfg.pdfjs ).then( function () {
			var lib = w.pdfjsLib;
			if ( flipset.pdfjsLib || ! lib || lib === prev.g || lib.version !== cfg.pdfjsVersion ) {
				return;
			}
			flipset.pdfjsLib = lib;
			if ( prev.g ) {
				w.pdfjsLib = prev.g;
			} else {
				try {
					delete w.pdfjsLib;
				} catch ( e ) {
					w.pdfjsLib = undefined;
				}
			}
			if ( prev.u ) {
				w[ PDFJS_KEY ] = prev.u;
			} else {
				try {
					delete w[ PDFJS_KEY ];
				} catch ( e ) {
					w[ PDFJS_KEY ] = undefined;
				}
			}
		} );
	}

	/**
	 * Make sure window.FlipsetViewer exists, loading the scripts the first time.
	 *
	 * @return {Promise} Resolves when the viewer can be bound.
	 */
	function ensureViewer() {
		if ( window.FlipsetViewer ) {
			return Promise.resolve();
		}
		if ( loading ) {
			return loading;
		}
		if ( ! window.FlipsetConfig ) {
			window.FlipsetConfig = cfg.viewerConfig || {};
		}
		loading = loadPdfjs()
			.then( function () {
				return loadScript( cfg.viewer );
			} )
			.then( function () {
				if ( ! window.FlipsetViewer ) {
					throw new Error( 'Flipset viewer did not start' );
				}
			} );
		loading.catch( function () {
			loading = null;
		} );
		return loading;
	}

	function open( poster, link ) {
		if ( poster.classList.contains( 'is-loading' ) ) {
			return;
		}
		var shell = poster.closest( '.flipset-shell' );
		var viewer = shell ? shell.querySelector( '.flipset-viewer[data-flipset-poster]' ) : null;
		if ( ! viewer ) {
			window.location.href = link.href;
			return;
		}

		poster.classList.add( 'is-loading' );
		link.setAttribute( 'aria-busy', 'true' );
		var label = poster.querySelector( '.flipset-poster-btn' );
		var original = label ? label.textContent : '';
		if ( label && cfg.i18n && cfg.i18n.loading ) {
			label.textContent = cfg.i18n.loading;
		}

		ensureViewer().then(
			function () {
				var hadFocus = document.activeElement === link;
				poster.remove();
				shell.classList.remove( 'has-poster' );
				viewer.removeAttribute( 'data-flipset-defer' );
				viewer.removeAttribute( 'data-flipset-poster' );
				// The click was the visitor's request: no second "click to load" step.
				viewer.setAttribute( 'data-loading', 'eager' );
				window.FlipsetViewer.bind( viewer );
				if ( hadFocus ) {
					try {
						viewer.focus( { preventScroll: true } );
					} catch ( e ) {
						viewer.focus();
					}
				}
			},
			function () {
				// The scripts could not load: open the PDF itself instead.
				poster.classList.remove( 'is-loading' );
				link.removeAttribute( 'aria-busy' );
				if ( label ) {
					label.textContent = original;
				}
				window.location.href = link.href;
			}
		);
	}

	function bind( poster ) {
		if ( poster.getAttribute( 'data-flipset-poster-bound' ) ) {
			return;
		}
		poster.setAttribute( 'data-flipset-poster-bound', '1' );
		var link = poster.querySelector( '.flipset-poster-open' );
		if ( ! link ) {
			return;
		}
		link.setAttribute( 'role', 'button' );
		link.addEventListener( 'click', function ( e ) {
			if ( e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return; // Let "open in new tab" and similar keep working.
			}
			e.preventDefault();
			open( poster, link );
		} );
		link.addEventListener( 'keydown', function ( e ) {
			if ( ' ' === e.key || 'Spacebar' === e.key ) {
				e.preventDefault();
				open( poster, link );
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.flipset-poster' ), bind );
	}

	window.FlipsetPoster = { init: init };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
