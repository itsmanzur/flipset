/**
 * Flipset document library: expand a grid card in place and lazy-bind
 * the existing Flipset viewer (window.FlipsetViewer.bind).
 *
 * Keyboard / screen reader behaviour:
 * - Opening a card moves focus into the viewer; closing it (the card again, or Esc inside the
 *   viewer) puts focus back on the card that opened it.
 * - After an in-place search or page change the focus lands on something that still exists
 *   (the search field / the first card) instead of falling back to <body>.
 */
( function () {
	'use strict';

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function closeItem( item, restoreFocus ) {
		var card = $( '.flipset-library-card', item );
		var embed = $( '.flipset-library-embed', item );
		item.classList.remove( 'is-open' );
		if ( card ) {
			card.setAttribute( 'aria-expanded', 'false' );
		}
		if ( embed ) {
			embed.hidden = true;
		}
		if ( restoreFocus && card ) {
			card.focus();
		}
	}

	function openItem( item ) {
		var card = $( '.flipset-library-card', item );
		var embed = $( '.flipset-library-embed', item );
		var viewer = embed ? $( '.flipset-viewer', embed ) : null;

		item.classList.add( 'is-open' );
		if ( card ) {
			card.setAttribute( 'aria-expanded', 'true' );
		}
		if ( embed ) {
			embed.hidden = false;
		}

		if ( viewer && window.FlipsetViewer && typeof window.FlipsetViewer.bind === 'function' ) {
			viewer.removeAttribute( 'data-flipset-defer' );
			window.FlipsetViewer.bind( viewer );
		}

		// The viewer root is focusable (tabindex 0): it takes the arrow/PageUp/Esc keys.
		if ( viewer ) {
			try {
				viewer.focus( { preventScroll: true } );
			} catch ( e ) {
				viewer.focus();
			}
		}
		if ( embed && typeof embed.scrollIntoView === 'function' ) {
			var rect = embed.getBoundingClientRect();
			if ( rect.top < 0 || rect.bottom > ( window.innerHeight || document.documentElement.clientHeight ) ) {
				embed.scrollIntoView( { block: 'nearest' } );
			}
		}
	}

	function onCardClick( event ) {
		var card = event.currentTarget;
		var item = card.closest( '.flipset-library-item' );
		var library = card.closest( '.flipset-library' );
		if ( ! item || ! library ) {
			return;
		}

		var willOpen = ! item.classList.contains( 'is-open' );
		Array.prototype.forEach.call( library.querySelectorAll( '.flipset-library-item.is-open' ), function ( open ) {
			if ( open !== item ) {
				closeItem( open, false );
			}
		} );

		if ( willOpen ) {
			openItem( item );
		} else {
			closeItem( item, false );
		}
	}

	// Esc anywhere inside an open viewer closes it, unless the viewer used the key itself
	// (closing its find box, a dialog or presentation mode calls preventDefault()).
	function onKeydown( event ) {
		if ( event.key !== 'Escape' || event.defaultPrevented ) {
			return;
		}
		if ( document.fullscreenElement || document.webkitFullscreenElement ) {
			return;
		}
		var item = event.target && event.target.closest ? event.target.closest( '.flipset-library-item.is-open' ) : null;
		if ( ! item || ! $( '.flipset-library-embed', item ) || ! $( '.flipset-library-embed', item ).contains( event.target ) ) {
			return;
		}
		if ( $( '.flipset-wrap .is-present-show', item ) || $( '.flipset-shell.is-present-show', item ) ) {
			return;
		}
		event.preventDefault();
		closeItem( item, true );
	}

	function focusAfterLoad( library, focusSearch ) {
		var target = focusSearch ? $( '.flipset-library-search input[type="search"]', library ) : null;
		if ( target ) {
			target.focus();
			if ( typeof target.setSelectionRange === 'function' && target.value ) {
				var end = target.value.length;
				target.setSelectionRange( end, end );
			}
			return;
		}
		target = $( '.flipset-library-card', library ) || $( '.flipset-library-empty', library );
		if ( target ) {
			if ( ! target.matches( 'button, a' ) ) {
				target.setAttribute( 'tabindex', '-1' );
			}
			target.focus();
		}
		var rect = library.getBoundingClientRect();
		if ( rect.top < 0 && typeof library.scrollIntoView === 'function' ) {
			library.scrollIntoView( { block: 'start' } );
		}
	}

	function fetchLibrary( library, targetUrl, focusSearch ) {
		library.classList.add( 'is-loading' );
		library.setAttribute( 'aria-busy', 'true' );
		window
			.fetch( targetUrl, {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			} )
			.then( function ( res ) {
				return res.text();
			} )
			.then( function ( html ) {
				var parser = new window.DOMParser();
				var doc = parser.parseFromString( html, 'text/html' );
				var id = library.id;
				var newLibrary = id ? doc.getElementById( id ) : doc.querySelector( '.flipset-library' );
				if ( newLibrary ) {
					library.innerHTML = newLibrary.innerHTML;
					bindLibrary( library );
					focusAfterLoad( library, focusSearch );
					if ( window.history && window.history.pushState ) {
						window.history.pushState( null, '', targetUrl );
					}
				} else {
					window.location.href = targetUrl;
				}
			} )
			.catch( function () {
				window.location.href = targetUrl;
			} )
			.finally( function () {
				library.classList.remove( 'is-loading' );
				library.removeAttribute( 'aria-busy' );
			} );
	}

	function bindLibrary( library ) {
		if ( ! library ) {
			return;
		}

		if ( ! library._flipsetKeys ) {
			library._flipsetKeys = true;
			library.addEventListener( 'keydown', onKeydown );
		}

		Array.prototype.forEach.call( library.querySelectorAll( '.flipset-library-card' ), function ( card ) {
			card.removeEventListener( 'click', onCardClick );
			card.addEventListener( 'click', onCardClick );
		} );

		Array.prototype.forEach.call( library.querySelectorAll( '.flipset-library-pager a' ), function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				fetchLibrary( library, link.href, false );
			} );
		} );

		var searchForm = library.querySelector( '.flipset-library-search' );
		if ( searchForm ) {
			searchForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var formData = new window.FormData( searchForm );
				var url = new URL( window.location.href );
				// A new search starts on page 1.
				if ( /^flipset-library-\d+$/.test( library.id ) ) {
					url.searchParams.delete( 'flipset_page_' + library.id.replace( 'flipset-library-', '' ) );
				}
				formData.forEach( function ( val, key ) {
					if ( val ) {
						url.searchParams.set( key, val );
					} else {
						url.searchParams.delete( key );
					}
				} );
				fetchLibrary( library, url.toString(), true );
			} );
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.flipset-library' ), bindLibrary );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
