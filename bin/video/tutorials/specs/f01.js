/**
 * F1: Install Flipset and embed your first PDF. Scene ids are the voiceover clip names in tutorials/f01/vo.
 * Camera keyframes: { u, focus: 'shot.key' } zooms to a box found at capture time (rects.json); { u, full: true } shows the page.
 */
module.exports = {
	code: 'F1',
	file: 'flipset-f1-embed-a-pdf',
	order: ['hook', 'install', 'upload', 'shortcode', 'block', 'paste', 'same', 'close'],
	scenes: {
		hook: { card: { type: 'title', kicker: 'Flipset tutorial 1', title: 'Embed a PDF in WordPress', sub: 'Three ways, in about ninety seconds' } },

		install: { segs: [
			{ w: 3, chrome: 'admin', img: 'plugin_search',
				cam: [{ u: 0, full: true }, { u: '55%', focus: 'plugin_search.card', zmax: 1.9, pad: .7 }],
				rings: [{ rect: 'plugin_search.install', a: '58%', b: '100%' }],
				caps: [{ a: '5%', b: '55%', t: 'Plugins › Add New › search "Flipset"' }, { a: '60%', b: '98%', t: 'Install it' }] },
			{ w: 2, chrome: 'admin', img: 'plugins_active',
				cam: [{ u: 0, full: true }, { u: '70%', focus: 'plugins_active.row', zmax: 1.8, pad: .8 }],
				rings: [{ rect: 'plugins_active.name', a: '35%', b: '100%' }],
				caps: [{ a: '10%', b: '98%', t: 'Activate it' }] },
			{ w: 2, chrome: 'admin', img: 'dash',
				cam: [{ u: 0, full: true }, { u: '70%', focus: 'dash.hero', zmax: 1.4, pad: .9 }],
				caps: [{ a: '10%', b: '98%', t: 'A short setup checklist' }] }
		] },

		upload: { segs: [
			{ w: 1, chrome: 'admin', img: 'media_list',
				cam: [{ u: 0, full: true }, { u: '90%', focus: 'media_list.table', zmax: 1.25, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Upload it in the Media Library' }] },
			{ w: 1.5, chrome: 'admin', img: 'media_edit',
				cam: [{ u: 0, full: true }, { u: '55%', focus: 'media_edit.url', zmax: 2.1, pad: .55, dx: -120, dy: 30 }],
				rings: [{ rect: 'media_edit.url', a: '45%', b: '100%' }, { rect: 'media_edit.copy', a: '60%', b: '100%' }],
				caps: [{ a: '10%', b: '98%', t: 'Copy the file URL' }] }
		] },

		shortcode: {
			segs: [
				{ a: 0, b: '54%', chrome: 'admin', img: 'sc_editor',
					cam: [{ u: 0, full: true }, { u: '45%', focus: 'sc_editor.block', zmax: 1.9, pad: .75 }],
					caps: [{ a: '3%', b: '25%', t: 'Method 1: the shortcode' }] },
				{ a: '52%', b: null, chrome: 'browser', url: 'yoursite.com/product-catalogue', img: 'view',
					cam: [{ u: 0, focus: 'view.viewer', zmax: 1.5, pad: .8 }, { u: '45%', focus: 'view.toolbar', zmax: 2.0, pad: .85 }, { u: '85%', focus: 'view.viewer', zmax: 1.5, pad: .8 }],
					rings: [{ rect: 'view.toolbar', a: '40%', b: '80%', pad: 4 }],
					caps: [{ a: '18%', b: '95%', t: 'Zoom, search, fullscreen: one toolbar' }] }
			],
			overlays: [{ type: 'code', a: '13%', b: '54%', label: 'Shortcode block', text: '[flipset file="https://yoursite.com/catalogue.pdf"]', typeFrom: .35, typeDur: 3.2 }]
		},

		block: { segs: [
			{ w: 2, chrome: 'admin', img: 'block_empty',
				cam: [{ u: 0, full: true }, { u: '70%', focus: 'block_empty.block', zmax: 1.8, pad: .75 }],
				rings: [{ rect: 'block_empty.block', a: '45%', b: '100%' }],
				caps: [{ a: '8%', b: '98%', t: 'Method 2: the Flipset Viewer block' }] },
			{ w: 3, chrome: 'admin', img: 'block_picked',
				cam: [{ u: 0, full: true }, { u: '60%', focus: 'block_picked.block', zmax: 1.6, pad: .8 }],
				caps: [{ a: '8%', b: '98%', t: 'Choose a PDF: its first page shows as a preview' }] }
		] },

		paste: { segs: [
			{ w: 1, chrome: 'admin', img: 'paste_editor',
				cam: [{ u: 0, full: true }, { u: '75%', focus: 'paste_editor.line', zmax: 2.0, pad: .65 }],
				rings: [{ rect: 'paste_editor.line', a: '30%', b: '100%' }],
				caps: [{ a: '8%', b: '98%', t: 'Method 3: paste a PDF link on its own line' }] },
			{ w: 1, chrome: 'browser', url: 'yoursite.com/pasted-link', img: 'pasted',
				cam: [{ u: 0, focus: 'pasted.viewer', zmax: 1.5, pad: .8 }, { u: '100%', focus: 'pasted.viewer', zmax: 1.6, pad: .8 }],
				caps: [{ a: '10%', b: '98%', t: 'It turns into the viewer by itself' }] }
		] },

		same: { overlays: [
			{ type: 'tiles', a: .15, b: null, items: [
				{ img: 'view', rect: 'view.viewer', label: 'Shortcode' },
				{ img: 'block_picked', rect: 'block_picked.block', label: 'Block' },
				{ img: 'pasted', rect: 'pasted.viewer', label: 'Pasted link' }
			] },
			{ type: 'chips', a: '40%', b: null, items: ['No CDN', 'No account'] }
		] },

		close: { card: { type: 'close', done: 'Three ways, one viewer', next: 'View modes and the 3D flipbook' } }
	}
};
