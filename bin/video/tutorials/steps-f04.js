/**
 * Footage for F4 (Shortcode Builder). Loaded by capture.js.
 */
module.exports = (steps, c) => {
	const { SITE, site, P, W, H, file, admin, front, style, remember, sleep } = c;
	const BH = 1250; // tall enough for the preview and the shortcode box together
	const builder = async (b, qs = '') => {
		await admin(b, '/wp-admin/index.php', null, { h: BH, wait: 800 });
		await b.eval('try{localStorage.clear();}catch(e){}');
		await admin(b, '/wp-admin/admin.php?page=flipset-shortcode-builder' + qs, null, { h: BH, wait: 3500, until: `!!document.querySelector('#flipset-builder-frame')` });
		await style(b, '.update-plugins{display:none!important}');
		await sleep(2500);
	};
	const top = async (b) => { await b.eval('window.scrollTo(0,0)'); await sleep(300); };
	const rec = async (b, shot, map) => { for (const k of Object.keys(map)) { await remember(b, shot, k, map[k]); } };
	const BASE = { header: '.flipset-builder-header', presets: '.flipset-builder-presets', controls: '.flipset-builder-controls', stage: '#flipset-builder-stage', output: '#flipset-builder-output', save: '#flipset-builder-save', copy: '#flipset-builder-copy', device: '#flipset-device-switcher', tabs: '.flipset-builder-view-nav' };

	steps.f4_builder = async (b) => {
		await builder(b, '&pdf_id=' + site.cat);
		await b.shot(file('b_default'));
		await rec(b, 'b_default', Object.assign({ pick: '#flipset-builder-pick-pdf' }, BASE));
		for (const t of ['layout', 'controls']) {
			await b.eval(`document.querySelector('.flipset-ctrl-tab[data-tab="${t}"]').click()`);
			await sleep(900);
			await top(b);
			await b.shot(file('b_tab_' + t));
			await rec(b, 'b_tab_' + t, Object.assign({ pane: '#flipset-pane-' + t }, BASE));
		}
	};

	steps.f4_presets = async (b) => {
		await builder(b, '&pdf_id=' + site.cat);
		for (const p of ['night', 'ebook', 'corporate', 'flipbook']) {
			await b.eval(`document.querySelector('.flipset-preset-chip[data-preset="${p}"]').click()`);
			await sleep(3000);
			await top(b);
			await b.shot(file('b_preset_' + p));
			await rec(b, 'b_preset_' + p, Object.assign({ chip: '.flipset-preset-chip[data-preset="' + p + '"]' }, BASE));
		}
	};

	// Name it, pick Night Reader, save: the clean [flipset id="N"] shortcode appears.
	steps.f4_save = async (b) => {
		await builder(b, '&pdf_id=' + site.cat);
		await b.eval(`(function(){var n=document.querySelector('#flipset-builder-name');n.value='Catalogue, night';n.dispatchEvent(new Event('input',{bubbles:true}));n.dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('.flipset-preset-chip[data-preset="night"]').click();})()`);
		await sleep(3000);
		await top(b);
		await b.shot(file('b_named'));
		await rec(b, 'b_named', Object.assign({ name: '#flipset-builder-name' }, BASE));
		await b.eval(`document.querySelector('#flipset-builder-save').click()`);
		await sleep(3500);
		// This is the first embed on a fresh site, so it is id 1 there; the demo database numbers it by post ID, so show the same.
		const raw = await b.eval("document.querySelector('#flipset-builder-output').value");
		const real = (String(raw).match(/id="([0-9]+)"/) || [0, 0])[1];
		await b.eval('document.querySelector("#flipset-builder-output").value = ' + JSON.stringify(String(raw).replace('id="' + real + '"', 'id="1"')));
		console.log('  embed id was', real);
		await top(b);
		await b.shot(file('b_saved'));
		await rec(b, 'b_saved', Object.assign({ code: '#flipset-builder-output code, #flipset-builder-output pre, #flipset-builder-output' }, BASE));
		console.log('  output:', await b.eval(`(document.querySelector('#flipset-builder-output')||{}).value`));
		// My Saved Embeds
		await b.eval(`document.querySelector('.flipset-view-tab-btn[data-view="embeds"]').click()`);
		await sleep(1500);
		await b.shot(file('b_embeds'));
		await rec(b, 'b_embeds', { list: '#flipset-view-embeds', tbody: '#flipset-embeds-tbody', tabs: '.flipset-builder-view-nav' });
	};

	// Saved embeds list, then the same embed on two pages.
	steps.f4_reuse = async (b) => {
		await builder(b);
		await b.eval(`document.querySelector('.flipset-view-tab-btn[data-view="embeds"]').click()`);
		await sleep(1500);
		await b.shot(file('b_embeds'));
		await rec(b, 'b_embeds', { list: '#flipset-view-embeds', tabs: '.flipset-builder-view-nav', row: '#flipset-view-embeds tbody tr, #flipset-view-embeds .flipset-embed-card' });
		await front(b, P.emb1, 'emb_page1');
		await remember(b, 'emb_page1', 'viewer', '.flipset-wrap');
		await front(b, P.emb2, 'emb_page2');
		await remember(b, 'emb_page2', 'viewer', '.flipset-wrap');
	};

	// Edit the saved embed (back to the light theme), then both pages change.
	steps.f4_edit = async (b) => {
		await builder(b, '&embed_id=1');
		await b.shot(file('b_edit'));
		await rec(b, 'b_edit', Object.assign({ banner: '.flipset-builder-edit-banner' }, BASE));
		await b.eval("(function(){var els=[].slice.call(document.querySelectorAll('#flipset-pane-doc *'));var t=els.filter(function(e){return e.children.length===0&&e.textContent.trim()==='Light';})[0];if(t){(t.closest('label,button')||t).click();}})()");
		await sleep(2500);
		await top(b);
		await b.shot(file('b_edit_light'));
		await rec(b, 'b_edit_light', Object.assign({}, BASE));
		await b.eval("document.querySelector('#flipset-builder-save').click()");
		await sleep(3000);
		await front(b, P.emb1, 'emb_page1_new');
		await remember(b, 'emb_page1_new', 'viewer', '.flipset-wrap');
		await front(b, P.emb2, 'emb_page2_new');
		await remember(b, 'emb_page2_new', 'viewer', '.flipset-wrap');
	};

	// Put the saved embed back on the dark theme so "before" and "after" differ.
	steps.f4_setdark = async (b) => {
		await builder(b, '&embed_id=1');
		await b.eval("(function(){var els=[].slice.call(document.querySelectorAll('#flipset-pane-doc *'));var t=els.filter(function(e){return e.children.length===0&&/^Dark/.test(e.textContent.trim());})[0];if(t){(t.closest('label,button')||t).click();}})()");
		await sleep(1500);
		await b.eval("document.querySelector('#flipset-builder-save').click()");
		await sleep(3000);
	};
};
