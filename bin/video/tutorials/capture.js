/**
 * node bin/video/tutorials/capture.js <step> [step ...]
 *
 * Captures real UI footage for the Wave 1 tutorial videos (F1, F2, F4, F5) from a throw-away demo site.
 * Everything goes to bin/video/out/tutorials/shots/ at 2x (git-ignored); the boxes of interesting elements
 * go to shots/rects.json (CSS pixels) so scenes can point a camera or a highlight at them.
 *
 *   SITE   demo site origin, default http://127.0.0.1:8120
 *   bin/video/out/tutorials/site.json   { cat, catUrl, ids, pages, cookies } (admin login cookies for that site)
 *
 * Delete site.json and destroy the admin session when you are done.
 */
const fs = require('fs');
const path = require('path');
const { launch, sleep } = require('../../wporg-assets/cdp');

const SITE = process.env.SITE || 'http://127.0.0.1:8120';
const base = path.join(__dirname, '..', 'out', 'tutorials');
const out = path.join(base, 'shots');
const site = JSON.parse(fs.readFileSync(path.join(base, 'site.json'), 'utf8'));
const P = site.pages;
const W = 1440;
const H = 900;
const file = (n) => path.join(out, n + '.png');
const rectsFile = path.join(out, 'rects.json');
const rects = fs.existsSync(rectsFile) ? JSON.parse(fs.readFileSync(rectsFile, 'utf8')) : {};
// A canvas exists before the first page is drawn, so also wait for the loading text to go.
const viewerReady = `(function(){var v=document.querySelector('.flipset-viewer');return !!v&&v.querySelectorAll('canvas').length>0&&!/Loading document/i.test(v.innerText);})()`;

// Remember the box of an element (CSS pixels, relative to the shot's top-left).
async function remember(b, shot, key, selector, scope = 'document') {
	const r = await b.eval(`(function(){var e=${scope}.querySelector(${JSON.stringify(selector)});if(!e)return null;var r=e.getBoundingClientRect();return {x:Math.round(r.left),y:Math.round(r.top),w:Math.round(r.width),h:Math.round(r.height)};})()`);
	if (r) { rects[shot] = rects[shot] || {}; rects[shot][key] = r; } else { console.log('  (no element for', shot, key, selector + ')'); }
	return r;
}
async function style(b, css) {
	await b.eval(`(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(css)};document.head.appendChild(s);})()`);
}
const ADMIN_CLEAN = '.update-nag,.notice,#update-nag,#wp-admin-bar-updates,.fa-notice{display:none!important}';
async function admin(b, urlPath, name, { h = H, wait = 2500, until = null } = {}) {
	await b.viewport(W, h, 2);
	await b.goto(SITE + urlPath, { wait, until });
	await style(b, ADMIN_CLEAN);
	await sleep(400);
	if (name) { await b.shot(file(name)); }
}
// The viewer remembers theme and last page in localStorage; a clean visitor has neither.
async function clearStore(b) {
	await b.send('Storage.clearDataForOrigin', { origin: SITE, storageTypes: 'local_storage,cookies_none' }).catch(() => {});
}
async function front(b, pageId, name, { top = 130, scrollTo = '.flipset-wrap', wait = 2500, h = H, w = W, query = '' } = {}) {
	await clearStore(b);
	await b.viewport(w, h, 2);
	for (let i = 0; i < 4; i++) {
		await b.goto(SITE + '/?page_id=' + pageId + query, { until: viewerReady, wait });
		if (!(await b.eval(`!!document.querySelector('.flipset-error')`))) { break; }
		console.log('  viewer error, retrying', name);
	}
	await style(b, '#wpadminbar{display:none!important}html{margin-top:0!important}');
	await b.eval(`(function(){var t=document.querySelector(${JSON.stringify(scrollTo)});if(t){window.scrollTo(0,t.getBoundingClientRect().top+scrollY-${top});}})()`);
	await sleep(2200);
	await b.shot(file(name));
}
// Block editor with the welcome guide gone, an empty page, then blocks added by script.
async function editor(b, title, blocksJs, { h = H, select = null } = {}) {
	await b.viewport(W, h, 2);
	await b.goto(SITE + '/wp-admin/post-new.php?post_type=page', { wait: 6000, until: `window.wp&&wp.data&&wp.data.select('core/block-editor')` });
	await b.eval(`(function(){try{var m=document.querySelector('.components-modal__header button[aria-label="Close"]');if(m){m.click();}wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false);wp.data.dispatch('core/preferences').set('core/edit-post','fullscreenMode',false);}catch(e){}})()`);
	await sleep(1500);
	await b.eval(`(function(){
		wp.data.dispatch('core/editor').editPost({ title: ${JSON.stringify(title)} });
		var blocks = ${blocksJs};
		wp.data.dispatch('core/block-editor').resetBlocks(blocks);
		${select !== null ? `wp.data.dispatch('core/block-editor').selectBlock(blocks[${select}].clientId);` : ''}
	})()`);
	await style(b, ADMIN_CLEAN + '.update-plugins,.components-notice,.components-snackbar-list{display:none!important}');
	await sleep(4500);
}
// Box of an element inside the editor canvas iframe, in page CSS pixels.
async function rememberEd(b, shot, key, selector) {
	const r = await b.eval(`(function(){var f=document.querySelector('iframe[name="editor-canvas"]');var d=f?f.contentDocument:document;var e=d.querySelector(${JSON.stringify(selector)});if(!e)return null;var q=e.getBoundingClientRect();var o=f?f.getBoundingClientRect():{left:0,top:0};return {x:Math.round(o.left+q.left),y:Math.round(o.top+q.top),w:Math.round(q.width),h:Math.round(q.height)};})()`);
	if (r) { rects[shot] = rects[shot] || {}; rects[shot][key] = r; } else { console.log('  (no editor element for', shot, key, selector + ')'); }
	return r;
}

const steps = {
	// Thumbnails, text index and the tagged-PDF flag, like a real admin visit.
	async prep(b) {
		await b.viewport(W, H, 1);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 800 });
		for (let i = 0; i < 240; i++) {
			const done = await b.eval(`(function(){var r=[].slice.call(document.querySelectorAll('.fa-table tbody tr[data-pdf-id]'));return r.length>0&&r.every(function(x){return x.querySelector('.flipset-docs-thumb-img')&&!/Pending/.test(x.textContent);});})()`);
			if (done) { break; }
			await sleep(1000);
		}
		console.log('prep done');
	},

	// Smoke test: dashboard and documents screens.
	async smoke(b) {
		await admin(b, '/wp-admin/admin.php?page=flipset', 'dash');
		await remember(b, 'dash', 'hero', '.fa-hero');
		await admin(b, '/wp-admin/admin.php?page=flipset-documents', 'docs', { h: 1100, wait: 3000 });
	},
};

module.exports = { clearStore, rememberEd, steps, SITE, out, site, P, W, H, file, admin, front, editor, style, remember, sleep, viewerReady, rects, rectsFile };

if (require.main === module) {
	(async () => {
		const want = process.argv.slice(2);
		fs.mkdirSync(out, { recursive: true });
		// Further steps live in steps-*.js next to this file and register themselves here.
		fs.readdirSync(__dirname).filter((f) => /^steps-.*\.js$/.test(f)).forEach((f) => require('./' + f)(steps, module.exports));
		const b = await launch(9362);
		try {
			await b.cookies(site.cookies);
			for (const s of want) {
				if (!steps[s]) { console.log('unknown step', s); continue; }
				await steps[s](b);
				console.log('step', s, 'ok');
			}
		} finally {
			await b.close();
			fs.writeFileSync(rectsFile, JSON.stringify(rects, null, 1));
		}
	})().catch((e) => { console.error(e); process.exit(1); });
}
