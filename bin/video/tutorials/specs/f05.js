/**
 * F5: Build a searchable PDF document library. Scene ids are the voiceover clip names in tutorials/f05/vo.
 */
module.exports = {
	code: 'F5',
	rects: { l_block4: { sidebar: { x: 1160, y: 96, w: 280, h: 771 } } },
	file: 'flipset-f5-pdf-document-library',
	order: ['hook', 'add', 'search', 'sort', 'layout', 'open', 'block', 'close'],
	scenes: {
		hook: { segs: [
			{ chrome: 'admin', img: 'media_list',
				cam: [{ u: 0, focus: 'media_list.table', zmax: 1.5, pad: .8 }, { u: '100%', full: true }],
				caps: [{ a: '10%', b: '96%', t: 'Lots of PDFs?' }] }
		] },

		add: { segs: [
			{ w: 1.4, chrome: 'admin', img: 'l_sc_editor',
				cam: [{ u: 0, full: true }, { u: '100%', focus: 'l_sc_editor.block', zmax: 1.7, pad: .75 }],
				rings: [{ rect: 'l_sc_editor.block', a: '40%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'The [flipset_library] shortcode, or the block' }] },
			{ w: 2, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_plain',
				cam: [{ u: 0, focus: 'l_plain.grid', zmax: 1.5, pad: .85 }, { u: '100%', focus: 'l_plain.grid', zmax: 1.6, pad: .85 }],
				caps: [{ a: '8%', b: '96%', t: 'Your Media Library PDFs, as a grid of covers' }] }
		] },

		search: { segs: [
			{ w: 1, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_typed',
				cam: [{ u: 0, focus: 'l_typed.controls', zmax: 2, pad: .8 }, { u: '100%', focus: 'l_typed.controls', zmax: 2, pad: .8 }],
				rings: [{ rect: 'l_typed.search', a: '10%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Search the text inside your PDFs' }] },
			{ w: 1.3, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_found',
				cam: [{ u: 0, focus: 'l_found.lib', zmax: 1.5, pad: .9 }, { u: '100%', focus: 'l_found.lib', zmax: 1.5, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'The grid narrows in place' }] }
		] },

		sort: { segs: [
			{ w: 1, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_sort0',
				cam: [{ u: 0, focus: 'l_sort0.controls', zmax: 2, pad: .8 }, { u: '100%', focus: 'l_sort0.controls', zmax: 2, pad: .8 }],
				rings: [{ rect: 'l_sort0.sort', a: '10%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Add the sort menu' }] },
			{ w: 1.2, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_sorted',
				cam: [{ u: 0, focus: 'l_sorted.lib', zmax: 1.5, pad: .9 }, { u: '100%', focus: 'l_sorted.lib', zmax: 1.5, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Newest, oldest, or title' }] }
		] },

		layout: { segs: [
			{ w: 2, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_grid',
				cam: [{ u: 0, focus: 'l_grid.controls', zmax: 2, pad: .8 }, { u: '100%', focus: 'l_grid.controls', zmax: 2, pad: .8 }],
				rings: [{ rect: 'l_grid.layout', a: '10%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Grid or list, the visitor decides' }] },
			{ w: 2.5, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_list',
				cam: [{ u: 0, focus: 'l_list.lib', zmax: 1.4, pad: .9 }, { u: '100%', focus: 'l_list.lib', zmax: 1.4, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Remembered in their own browser' }] },
			{ w: 1.5, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_grid2',
				cam: [{ u: 0, focus: 'l_grid2.lib', zmax: 1.4, pad: .9 }, { u: '100%', focus: 'l_grid2.lib', zmax: 1.4, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Nothing stored on your site' }] }
		] },

		open: { segs: [
			{ w: 2.6, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_opened',
				cam: [{ u: 0, focus: 'l_opened.lib', zmax: 1.3, pad: .95 }, { u: '100%', focus: 'l_opened.viewer', zmax: 1.5, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Click a cover: it opens right there' }] },
			{ w: 1.6, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_closed',
				cam: [{ u: 0, focus: 'l_closed.lib', zmax: 1.4, pad: .9 }, { u: '100%', focus: 'l_closed.lib', zmax: 1.4, pad: .9 }],
				caps: [{ a: '8%', b: '96%', t: 'Press Esc to come back' }] },
			{ w: 2, chrome: 'browser', url: 'yoursite.com/downloads', img: 'l_meta',
				cam: [{ u: 0, focus: 'l_meta.meta', zmax: 2.2, pad: .5 }, { u: '100%', focus: 'l_meta.meta', zmax: 2.3, pad: .5 }],
				rings: [{ rect: 'l_meta.meta', a: '15%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Every card shows the page count' }] }
		] },

		block: { segs: [
			{ w: 2, chrome: 'admin', img: 'l_block',
				cam: [{ u: 0, full: true }, { u: '60%', focus: 'l_block.sidebar', zmax: 1.5, pad: .95 }],
				rings: [{ rect: 'l_block.sidebar', a: '45%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'The block: every option in the sidebar' }] },
			{ w: 2, chrome: 'admin', img: 'l_block4',
				cam: [{ u: 0, focus: 'l_block4.sidebar', zmax: 1.5, pad: .95 }, { u: '100%', full: true }],
				caps: [{ a: '8%', b: '96%', t: 'Four columns: the preview updates' }] }
		] },

		close: { card: { type: 'close', done: 'A library in minutes', next: 'Your own cover images' } }
	}
};
