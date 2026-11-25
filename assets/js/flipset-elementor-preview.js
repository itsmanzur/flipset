/**
 * Flipset in the Elementor editor preview.
 *
 * Elementor renders a widget over AJAX after the preview page has loaded, so the
 * viewer, poster and library scripts never see it. This starts each Flipset widget
 * when Elementor reports it ready. Loaded only in the editor preview.
 */
( function ( $ ) {
	'use strict';

	var VIEWER = '.flipset-viewer[data-file]';
	var renamed = 0;

	/**
	 * A widget rendered over AJAX can repeat an id another widget already uses, because
	 * each render is its own request. Give such a viewer a fresh id, and point its
	 * toolbar at it, so the viewer finds its own toolbar.
	 *
	 * @param {Element} root      Widget element.
	 * @param {Element} container Viewer element.
	 */
	function makeIdUnique( root, container ) {
		var id = container.id;
		if ( ! id || document.querySelectorAll( '[id="' + id + '"]' ).length < 2 ) {
			return;
		}
		var fresh = id + '-e' + ( ++renamed );
		container.id = fresh;
		Array.prototype.forEach.call(
			root.querySelectorAll( '.flipset-toolbar[data-target="' + id + '"]' ),
			function ( toolbar ) {
				toolbar.setAttribute( 'data-target', fresh );
				toolbar.setAttribute( 'aria-controls', fresh );
			}
		);
	}

	function start( $scope ) {
		var root = $scope && $scope[ 0 ];
		if ( ! root || ! root.querySelectorAll ) {
			return;
		}

		Array.prototype.forEach.call( root.querySelectorAll( VIEWER ), function ( container ) {
			makeIdUnique( root, container );
		} );

		if ( window.FlipsetLibrary && window.FlipsetLibrary.bind ) {
			Array.prototype.forEach.call( root.querySelectorAll( '.flipset-library' ), window.FlipsetLibrary.bind );
		}
		if ( window.FlipsetPoster ) {
			window.FlipsetPoster.init();
		}
		if ( window.FlipsetViewer ) {
			Array.prototype.forEach.call(
				root.querySelectorAll( VIEWER + ':not([data-flipset-defer]):not([data-flipset-bound])' ),
				function ( container ) {
					window.FlipsetViewer.bind( container );
				}
			);
		}
	}

	var registered = false;
	function register() {
		var frontend = window.elementorFrontend;
		if ( registered || ! frontend || ! frontend.hooks ) {
			return;
		}
		registered = true;
		frontend.hooks.addAction( 'frontend/element_ready/flipset-viewer.default', start );
		frontend.hooks.addAction( 'frontend/element_ready/flipset-library.default', start );
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		register();
	}
	$( window ).on( 'elementor/frontend/init', register );
} )( window.jQuery );
