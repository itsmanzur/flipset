/**
 * F2: View modes and the 3D flipbook. Scene ids are the voiceover clip names in tutorials/f02/vo.
 */
module.exports = {
	code: 'F2',
	file: 'flipset-f2-view-modes-and-3d-flipbook',
	order: ['hook', 'modes', 'setting', 'curl', 'toolbar', 'mobile', 'close'],
	// The flip frames come from an earlier capture of the same page; the viewer sits at this box in them.
	rects: { flip: { viewer: { x: 398, y: 150, w: 645, h: 640 } } },
	scenes: {
		hook: { overlays: [
			{ type: 'tiles', a: .1, b: null, w: 400, stagger: .2, items: [
				{ img: 'v_page', rect: 'v_page.viewer', label: 'Page' },
				{ img: 'v_scroll', rect: 'v_scroll.viewer', label: 'Scroll' },
				{ img: 'v_spread2', rect: 'v_spread2.viewer', label: 'Spread' },
				{ img: 'v_flip2', rect: 'v_flip2.viewer', label: 'Flipbook' }
			] }
		] },

		modes: { segs: [
			{ chrome: 'browser', url: 'yoursite.com/catalogue', img: 'v_page',
				cam: [{ u: 0, focus: 'v_page.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'v_page.viewer', zmax: 1.58, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Page view' }] },
			{ chrome: 'browser', url: 'yoursite.com/catalogue-scroll', img: 'v_scroll',
				cam: [{ u: 0, focus: 'v_scroll.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'v_scroll.viewer', zmax: 1.58, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Continuous scroll' }] },
			{ chrome: 'browser', url: 'yoursite.com/catalogue-spread', img: 'v_spread2',
				cam: [{ u: 0, focus: 'v_spread2.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'v_spread2.viewer', zmax: 1.58, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Two-page spread' }] },
			{ chrome: 'browser', url: 'yoursite.com/catalogue-flipbook', img: 'v_flip2',
				cam: [{ u: 0, focus: 'v_flip2.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'v_flip2.viewer', zmax: 1.58, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: '3D flipbook' }] }
		] },

		setting: {
			segs: [
				{ chrome: 'admin', img: 'block_view',
					cam: [{ u: 0, full: true }, { u: '55%', full: true }, { u: '80%', focus: 'block_view.view', zmax: 2.2, pad: .4 }],
					rings: [{ rect: 'block_view.view', a: '62%', b: '100%' }],
					caps: [{ a: '58%', b: '98%', t: 'Block: View › 3D FlipBook' }] }
			],
			overlays: [{ type: 'code', a: .15, b: '46%', label: 'In the shortcode', text: '[flipset file="https://yoursite.com/catalogue.pdf" view="flip"]', typeFrom: .3, typeDur: 1.8, hl: ['view'] }]
		},

		curl: { segs: [
			{ chrome: 'browser', url: 'yoursite.com/catalogue-flipbook', flip: [[0, 0], ['12%', .75], ['88%', 3.98], ['100%', 3.98]],
				cam: [{ u: 0, focus: 'flip.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'flip.viewer', zmax: 1.6, pad: .8 }],
				caps: [{ a: '5%', b: '48%', t: 'Drag a corner: the page curls' }, { a: '52%', b: '97%', t: 'Soft page-turn sound, made in the browser' }] }
		] },

		toolbar: { segs: [
			{ chrome: 'browser', url: 'yoursite.com/catalogue', img: 'toolbar_more',
				cam: [{ u: 0, focus: 'toolbar_more.toolbar', zmax: 1.9, pad: .9 }, { u: '55%', focus: 'toolbar_more.menu', zmax: 1.7, pad: .7, dx: -160 }],
				rings: [{ rect: 'toolbar_more.menu', a: '45%', b: '100%' }],
				caps: [{ a: '8%', b: '98%', t: 'More tools › View Mode' }] }
		] },

		mobile: { segs: [
			{ chrome: 'phone', img: 'mobile_flip',
				cam: [{ u: 0, full: true }, { u: '100%', full: true, z: 1.03 }],
				caps: [{ a: '5%', b: '48%', t: 'A single page on a phone' }, { a: '52%', b: '97%', t: 'Swipe to turn, pinch to zoom' }] }
		] },

		close: { card: { type: 'close', done: 'Four views, all free', next: 'The Shortcode Builder' } }
	}
};
