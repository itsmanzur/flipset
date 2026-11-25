/**
 * Flipset document library: expand a grid card in place and lazy-bind
 * the existing Flipset viewer (window.FlipsetViewer.bind).
 *
 * Keyboard / screen reader behaviour:
 * - Opening a card moves focus into the viewer; closing it (the card again, or Esc inside the
 *   viewer) puts focus back on the card that opened it.
 * - After an in-place search or page change the focus lands on something that still exists
 *   (the search field / the first card) instead of falling back to <body>. After a sort change it
 *   stays on the sort menu, which is re-created together with the rest of the controls.
 * - A library with a sort menu also has a polite live region. Every in-place update puts the
 *   summary the server wrote ("12 documents found. Sorted by Newest.") into it.
 *
 * Grid / list switch: the visitor's choice is kept in localStorage (inside try/catch, so the
 * library works the same without it) and shown by the wrapper class `is-layout-list`.
 */
( function () {
	'use strict';

	var LAYOUT_KEY = 'flipset-library-layout';

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function readLayout() {
		try {
			var value = window.localStorage.getItem( LAYOUT_KEY );
			return 'list' === value || 'grid' === value ? value : '';
		} catch ( e ) {
			return '';
		}
	}

	function writeLayout( value ) {
		try {
			window.localStorage.setItem( LAYOUT_KEY, value );
		} catch ( e ) {
			// Storage is blocked: the choice is simply not remembered.
		}
	}

	// Mirror the wrapper's layout class onto the switch buttons (they are re-created on every in-place update).
	function syncLayoutButtons( library ) {
		var layout = library.classList.contains( 'is-layout-list' ) ? 'list' : 'grid';
		Array.prototype.forEach.call( library.querySelectorAll( '.flipset-library-layout-btn' ), function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-layout' ) === layout ? 'true' : 'false' );
		} );
	}

	function applyLayout( library, layout ) {
		library.classList.toggle( 'is-layout-list', 'list' === layout );
		syncLayoutButtons( library );
	}

	// Libraries that show the switch start from the visitor's saved choice, if there is one.
	function applyStoredLayout( library ) {
		var stored = $( '.flipset-library-layout', library ) ? readLayout() : '';
		if ( stored ) {
			applyLayout( library, stored );
		}
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

	// focus: 'search' (a search was submitted), 'sort' (the sort menu was used) or false (paging).
	function focusAfterLoad( library, focus ) {
		var target = null;
		if ( 'sort' === focus ) {
			target = $( '.flipset-library-sort-select', library );
		} else if ( focus ) {
			target = $( '.flipset-library-search input[type="search"]', library );
		}
		if ( target ) {
			target.focus();
			if ( 'search' === target.type && typeof target.setSelectionRange === 'function' && target.value ) {
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

	// Swap in the freshly fetched markup. Screen readers only announce a live region that already
	// existed, so that one element is kept and only its text is replaced.
	function replaceContent( library, fresh ) {
		var status = null;
		Array.prototype.forEach.call( library.children, function ( child ) {
			if ( child.classList.contains( 'flipset-library-status' ) ) {
				status = child;
			}
		} );

		if ( ! status ) {
			library.innerHTML = fresh.innerHTML;
			return;
		}

		var message = '';
		var nodes = document.createDocumentFragment();
		Array.prototype.forEach.call( fresh.children, function ( child ) {
			if ( child.classList.contains( 'flipset-library-status' ) ) {
				message = child.getAttribute( 'data-status' ) || '';
			} else {
				nodes.appendChild( document.importNode( child, true ) );
			}
		} );

		Array.prototype.slice.call( library.children ).forEach( function ( child ) {
			if ( child !== status ) {
				library.removeChild( child );
			}
		} );
		library.appendChild( nodes );

		status.textContent = '';
		window.setTimeout( function () {
			status.textContent = message;
		}, 60 );
	}

	function fetchLibrary( library, targetUrl, focus ) {
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
					replaceContent( library, newLibrary );
					bindLibrary( library );
					focusAfterLoad( library, focus );
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

	// Load the result of the search / sort form in place. A new search or sort order starts on page 1.
	function submitForm( library, form, focus ) {
		var formData = new window.FormData( form );
		var url = new URL( window.location.href );
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
		fetchLibrary( library, url.toString(), focus );
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

		// Search form (search on) or the sort-only form (search off): both submit the same way.
		var form = library.querySelector( '.flipset-library-search, .flipset-library-sort' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				submitForm( library, form, 'search' );
			} );
		}

		// Sort menu: changing it re-sorts in place, keeping the search term and starting on page 1.
		var sort = library.querySelector( '.flipset-library-sort-select' );
		if ( sort && sort.form ) {
			sort.addEventListener( 'change', function () {
				submitForm( library, sort.form, 'sort' );
			} );
		}

		// Grid / list switch.
		Array.prototype.forEach.call( library.querySelectorAll( '.flipset-library-layout-btn' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var layout = 'list' === button.getAttribute( 'data-layout' ) ? 'list' : 'grid';
				applyLayout( library, layout );
				writeLayout( layout );
			} );
		} );
		syncLayoutButtons( library );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.flipset-library' ), bindLibrary );
	}

	window.FlipsetLibrary = { init: init, bind: bindLibrary };

	// The script is printed after the markup, so the saved layout can be applied right away,
	// before first paint, instead of waiting for DOMContentLoaded (no visible jump to list view).
	Array.prototype.forEach.call( document.querySelectorAll( '.flipset-library' ), applyStoredLayout );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
