/**
 * F4: Shortcode Builder and saved embeds. Scene ids are the voiceover clip names in tutorials/f04/vo.
 */
module.exports = {
	code: 'F4',
	file: 'flipset-f4-shortcode-builder',
	order: ['hook', 'open', 'live', 'presets', 'save', 'reuse', 'close'],
	scenes: {
		hook: { overlays: [{ type: 'code', a: .1, b: null, label: 'The long way', text: '[flipset file="https://yoursite.com/report.pdf" theme="dark" height="650px" view="scroll" hide="download,print,presentation,rotate" loading="lazy" page="1"]', typeFrom: .2, typeDur: 1.4, strikeAt: 2.2 }] },

		open: { segs: [
			{ chrome: 'admin', img: 'b_default',
				cam: [{ u: 0, full: true }, { u: '70%', full: true, z: 1.05 }],
				rings: [{ rect: 'b_default.pick', a: '55%', b: '100%' }],
				caps: [{ a: '5%', b: '48%', t: 'Flipset › Shortcode Builder' }, { a: '52%', b: '97%', t: 'Pick a PDF: the preview loads' }] }
		] },

		live: { segs: [
			{ w: 1, chrome: 'admin', img: 'b_default',
				cam: [{ u: 0, full: true }, { u: '100%', full: true, z: 1.05 }],
				caps: [{ a: '8%', b: '96%', t: 'Change a setting…' }] },
			{ w: 1.2, chrome: 'admin', img: 'b_tab_layout',
				cam: [{ u: 0, focus: 'b_tab_layout.pane', zmax: 1.25, pad: .95 }, { u: '100%', focus: 'b_tab_layout.pane', zmax: 1.3, pad: .95 }],
				rings: [{ rect: 'b_tab_layout.pane', a: '10%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Size, layout, toolbar' }] },
			{ w: 1.3, chrome: 'admin', img: 'b_named',
				cam: [{ u: 0, full: true }, { u: '100%', full: true, z: 1.05 }],
				caps: [{ a: '8%', b: '96%', t: '…and the preview updates at once' }] }
		] },

		presets: { segs: [
			{ chrome: 'admin', img: 'b_preset_ebook',
				cam: [{ u: 0, focus: 'b_preset_ebook.header', zmax: 1.2, pad: 1 }, { u: '100%', focus: 'b_preset_ebook.header', zmax: 1.2, pad: 1 }],
				rings: [{ rect: 'b_preset_ebook.chip', a: '5%', b: '100%' }], caps: [{ a: '8%', b: '96%', t: 'E-Book Reader' }] },
			{ chrome: 'admin', img: 'b_preset_corporate',
				cam: [{ u: 0, focus: 'b_preset_corporate.header', zmax: 1.2, pad: 1 }, { u: '100%', focus: 'b_preset_corporate.header', zmax: 1.2, pad: 1 }],
				rings: [{ rect: 'b_preset_corporate.chip', a: '5%', b: '100%' }], caps: [{ a: '8%', b: '96%', t: 'Corporate Report' }] },
			{ chrome: 'admin', img: 'b_preset_night',
				cam: [{ u: 0, focus: 'b_preset_night.header', zmax: 1.2, pad: 1 }, { u: '100%', focus: 'b_preset_night.header', zmax: 1.2, pad: 1 }],
				rings: [{ rect: 'b_preset_night.chip', a: '5%', b: '100%' }], caps: [{ a: '8%', b: '96%', t: 'Night Reader' }] },
			{ chrome: 'admin', img: 'b_preset_flipbook',
				cam: [{ u: 0, focus: 'b_preset_flipbook.header', zmax: 1.2, pad: 1 }, { u: '100%', focus: 'b_preset_flipbook.header', zmax: 1.2, pad: 1 }],
				rings: [{ rect: 'b_preset_flipbook.chip', a: '5%', b: '100%' }], caps: [{ a: '8%', b: '96%', t: '3D FlipBook' }] }
		] },

		save: { segs: [
			{ w: 1.1, chrome: 'admin', img: 'b_named',
				cam: [{ u: 0, full: true }, { u: '100%', full: true, z: 1.05 }],
				rings: [{ rect: 'b_named.name', a: '15%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'Name it, then save the embed' }] },
			{ w: 2, chrome: 'admin', img: 'b_saved',
				cam: [{ u: 0, focus: 'b_saved.output', zmax: 1.7, pad: .6, dy: 120 }, { u: '100%', focus: 'b_saved.output', zmax: 1.7, pad: .6, dy: 120 }],
				rings: [{ rect: 'b_saved.output', a: '15%', b: '100%' }],
				caps: [{ a: '8%', b: '96%', t: 'A short shortcode: [flipset id="1"]' }] }
		] },

		reuse: { segs: [
			{ w: 1.1, chrome: 'admin', img: 'b_embeds',
				cam: [{ u: 0, focus: 'b_embeds.list', zmax: 1.2, pad: .95 }, { u: '100%', focus: 'b_embeds.list', zmax: 1.25, pad: .95 }],
				caps: [{ a: '8%', b: '96%', t: 'Saved embeds' }] },
			{ w: 1, chrome: 'browser', url: 'yoursite.com/catalogue-one', img: 'emb_page1',
				cam: [{ u: 0, focus: 'emb_page1.viewer', zmax: 1.45, pad: .8 }, { u: '100%', focus: 'emb_page1.viewer', zmax: 1.5, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Paste it into any page' }] },
			{ w: 1, chrome: 'browser', url: 'yoursite.com/catalogue-two', img: 'emb_page2',
				cam: [{ u: 0, focus: 'emb_page2.viewer', zmax: 1.45, pad: .8 }, { u: '100%', focus: 'emb_page2.viewer', zmax: 1.5, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Same embed, second page' }] },
			{ w: 1.3, chrome: 'admin', img: 'b_edit_light',
				cam: [{ u: 0, full: true }, { u: '100%', full: true, z: 1.05 }],
				caps: [{ a: '8%', b: '96%', t: 'Edit the saved embed once' }] },
			{ w: 1.3, chrome: 'browser', url: 'yoursite.com/catalogue-two', img: 'emb_page2_new',
				cam: [{ u: 0, focus: 'emb_page2_new.viewer', zmax: 1.45, pad: .8 }, { u: '100%', focus: 'emb_page2_new.viewer', zmax: 1.5, pad: .8 }],
				caps: [{ a: '8%', b: '96%', t: 'Every page that uses it updates' }] }
		] },

		close: { card: { type: 'close', done: 'One embed, every page', next: 'A searchable PDF library' } }
	}
};
