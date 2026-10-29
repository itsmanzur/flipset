/**
 * node bin/video/capture.js <step> [step ...]
 *
 * Captures real UI footage for the intro video from a throw-away demo site
 * (see bin/wporg-assets/README.md for how that site is built). Everything is
 * written to bin/video/out/shots/ at 2x so it stays sharp at 1080p, and the
 * boxes of the interesting elements go to out/shots/rects.json (CSS pixels)
 * so scenes can point a camera or a highlight at them.
 *
 *   SITE        demo site origin, default http://127.0.0.1:8101
 *   cookies     bin/video/out/cookies.json (admin login cookies for that site)
 *   pages.json  bin/video/out/pages.json: { lib, view, flip, dark, sepia, light, spread, cat, catUrl }
 *
 * Steps: prep, docs, editor, builder, view, themes, lib, search, cover (run after a custom cover is set).
 */
const fs = require('fs');
const path = require('path');
const { launch, sleep } = require('../wporg-assets/cdp');

const SITE = process.env.SITE || 'http://127.0.0.1:8101';
const out = path.join(__dirname, 'out', 'shots');
const cookies = JSON.parse(fs.readFileSync(path.join(__dirname, 'out', 'cookies.json'), 'utf8'));
const pages = JSON.parse(fs.readFileSync(path.join(__dirname, 'out', 'pages.json'), 'utf8'));
const W = 1440;
const H = 900;
const file = (n) => path.join(out, n + '.png');
// A canvas exists before the first page is drawn, so also wait for the loading text to go.
const viewerReady = `(function(){var v=document.querySelector('.flipset-viewer');return !!v&&v.querySelectorAll('canvas').length>0&&!/Loading document/i.test(v.innerText);})()`;
const rectsFile = path.join(out, 'rects.json');
const rects = fs.existsSync(rectsFile) ? JSON.parse(fs.readFileSync(rectsFile, 'utf8')) : {};

// Remember the box of an element, in CSS pixels relative to the page top-left of the shot.
async function remember(b, shotName, key, selector, scope = 'document') {
	const r = await b.eval(`(function(){var e=${scope}.querySelector(${JSON.stringify(selector)});if(!e)return null;var r=e.getBoundingClientRect();return {x:Math.round(r.left),y:Math.round(r.top),w:Math.round(r.width),h:Math.round(r.height)};})()`);
	if (r) { rects[shotName] = rects[shotName] || {}; rects[shotName][key] = r; } else { console.log('  (no element for', shotName, key, selector + ')'); }
}

// Admin screens: hide update nags so the footage is clean.
async function cleanAdmin(b) {
	await b.eval(`(function(){var s=document.createElement('style');s.textContent='.update-nag,.notice,#update-nag,#wp-admin-bar-updates{display:none!important}';document.head.appendChild(s);})()`);
}

async function imagesLoaded(b, selector) {
	for (let i = 0; i < 40; i++) {
		const ok = await b.eval(`(function(){var im=[].slice.call(document.querySelectorAll(${JSON.stringify(selector)}));return im.length>0&&im.every(function(x){return x.complete&&x.naturalWidth>0})})()`);
		if (ok) { return; }
		await sleep(250);
	}
}

// Front-end pages: hide the admin bar so the page looks like a visitor's.
async function front(b, url, until, name, { top = 150, scrollTo = '.flipset-wrap', wait = 2500 } = {}) {
	await b.viewport(W, H, 2);
	for (let i = 0; i < 4; i++) {
		await b.goto(url, { until, wait });
		if (!(await b.eval(`!!document.querySelector('.flipset-error')`))) { break; }
		console.log('  viewer error, retrying', name);
	}
	await b.eval(`(function(){var s=document.createElement('style');s.textContent='#wpadminbar{display:none!important}html{margin-top:0!important}';document.head.appendChild(s);var t=document.querySelector(${JSON.stringify(scrollTo)});if(t){window.scrollTo(0,t.getBoundingClientRect().top+scrollY-${top});}})()`);
	await sleep(700);
	await b.shot(file(name));
}

const steps = {
	// Generate thumbnails, text index and the tagged-PDF flag, like a real admin visit.
	async prep(b) {
		await b.viewport(W, H, 1);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 500 });
		for (let i = 0; i < 200; i++) {
			const done = await b.eval(`(function(){var r=[].slice.call(document.querySelectorAll('.fa-table tbody tr[data-pdf-id]'));return r.length>0&&r.every(function(x){return x.querySelector('.flipset-docs-thumb-img')&&!/Pending/.test(x.textContent);});})()`);
			if (done) { break; }
			await sleep(1000);
		}
		console.log('prep done');
	},

	// Documents screen: previews, Indexed / Tagged badges, row actions.
	async docs(b) {
		await b.viewport(W, 1250, 2);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 3000 });
		await cleanAdmin(b);
		await sleep(300);
		await b.shot(file('docs'));
		await remember(b, 'docs', 'row', 'tr[data-pdf-id="8"]');
		await remember(b, 'docs', 'thumb', 'tr[data-pdf-id="8"] .fa-col-thumb');
		await remember(b, 'docs', 'cover', 'tr[data-pdf-id="8"] .flipset-doc-cover-btn');
		await remember(b, 'docs', 'search', 'tr[data-pdf-id="8"] .fa-col-search');
		await remember(b, 'docs', 'a11y', 'tr[data-pdf-id="8"] .fa-col-a11y');
		await remember(b, 'docs', 'upload', '.fa-hero .fa-btn--primary');
	},

	// Block editor with the Flipset Viewer block selected.
	async editor(b) {
		await b.viewport(W, H, 2);
		await b.goto(SITE + '/wp-admin/post-new.php?post_type=page', { wait: 6000, until: `window.wp&&wp.data&&wp.data.select('core/block-editor')` });
		await b.eval(`(function(){try{var m=document.querySelector('.components-modal__header button[aria-label="Close"]');if(m){m.click();}wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false);wp.data.dispatch('core/preferences').set('core/edit-post','fullscreenMode',false);}catch(e){}})()`);
		await sleep(1500);
		await b.eval(`(function(){
			wp.data.dispatch('core/editor').editPost({ title: 'Our product catalogue' });
			var blocks = [
				wp.blocks.createBlock('core/paragraph', { content: 'Read the full 2026 catalogue below. It opens right here on the page, no download needed.' }),
				wp.blocks.createBlock('flipset/viewer', { file: ${JSON.stringify(pages.catUrl)}, height: '520px', title: '' })
			];
			wp.data.dispatch('core/block-editor').resetBlocks(blocks);
			wp.data.dispatch('core/block-editor').selectBlock(blocks[1].clientId);
		})()`);
		await sleep(5000);
		await b.shot(file('editor'));
		await remember(b, 'editor', 'block', '.wp-block-flipset-viewer, [data-type="flipset/viewer"]');
	},

	// Shortcode Builder: default look, then a preset applied with the live preview.
	async builder(b) {
		await b.viewport(W, 1400, 2);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-shortcode-builder&pdf_id=' + pages.cat, { wait: 2000, until: `document.querySelectorAll('#flipset-builder-preview canvas, .flipset-preview-stage canvas, .flipset-viewer canvas').length>0` });
		await cleanAdmin(b);
		await sleep(4500);
		await b.shot(file('builder_a'));
		await remember(b, 'builder_a', 'presets', '.flipset-preset-chip');
		await remember(b, 'builder_a', 'preview', '.flipset-viewer');
		const clicked = await b.eval(`(function(){var c=[].slice.call(document.querySelectorAll('.flipset-preset-chip')).filter(function(x){return /Night/i.test(x.textContent)})[0];if(!c)return false;c.click();return true})()`);
		console.log('  preset clicked:', clicked);
		await sleep(7000);
		await b.shot(file('builder_b'));
		await remember(b, 'builder_b', 'night', '.flipset-preset-chip.is-active');
		await remember(b, 'builder_b', 'code', '#flipset-code, .flipset-code-output, pre');
		await remember(b, 'builder_b', 'preview', '.flipset-viewer');
	},

	// Front-end viewer, single-page view.
	async view(b) {
		await front(b, SITE + '/?page_id=' + pages.view, viewerReady, 'view');
		await remember(b, 'view', 'viewer', '.flipset-wrap');
		await remember(b, 'view', 'toolbar', '.flipset-toolbar');
	},

	// Three reading themes, same page.
	async themes(b) {
		await b.send('Page.addScriptToEvaluateOnNewDocument', { source: `try{localStorage.clear()}catch(e){}` });
		for (const [name, id] of [['theme_light', pages.light], ['theme_dark', pages.dark], ['theme_sepia', pages.sepia]]) {
			await front(b, SITE + '/?page_id=' + id, viewerReady, name, { wait: 3000 });
			await remember(b, name, 'viewer', '.flipset-wrap');
		}
	},

	// Library grid and one document opened in place.
	async lib(b) {
		await front(b, SITE + '/?page_id=' + pages.lib, `document.querySelectorAll('.flipset-library-item').length>0`, 'lib', { scrollTo: '.flipset-library', top: 170 });
		await imagesLoaded(b, '.flipset-library img');
		await sleep(500);
		await b.shot(file('lib'));
		await remember(b, 'lib', 'grid', '.flipset-library');
		await remember(b, 'lib', 'first', '.flipset-library-item');
		await b.eval(`var it=document.querySelector('.flipset-library-item');(it.querySelector('a,button')||it).click();`);
		for (let i = 0; i < 30; i++) {
			if (await b.eval(viewerReady)) { break; }
			await sleep(500);
		}
		await sleep(2500);
		await b.eval(`var v=document.querySelector('.flipset-library .flipset-wrap, .flipset-library-viewer, .flipset-wrap');if(v){window.scrollTo(0,v.getBoundingClientRect().top+scrollY-140);}`);
		await sleep(800);
		await b.shot(file('lib_open'));
		await remember(b, 'lib_open', 'viewer', '.flipset-wrap');
	},

	// Site search finding text inside a PDF.
	async search(b) {
		await b.viewport(W, H, 2);
		await b.goto(SITE + '/?s=Seating', { wait: 2000 });
		await b.eval(`(function(){var s=document.createElement('style');s.textContent='#wpadminbar{display:none!important}html{margin-top:0!important}.flipset-wrap{display:none!important}';document.head.appendChild(s);})()`);
		await sleep(500);
		await b.shot(file('search'));
		await remember(b, 'search', 'results', '.wp-block-query, main');
	},

	// After a custom cover is set on the Meridian PDF: Docs row and library grid.
	async cover(b) {
		await b.viewport(W, 1250, 2);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 3000 });
		await cleanAdmin(b);
		await sleep(300);
		await b.shot(file('docs_cover'));
		await remember(b, 'docs_cover', 'thumb', 'tr[data-pdf-id="8"] .fa-col-thumb');
		await front(b, SITE + '/?page_id=' + pages.lib, `document.querySelectorAll('.flipset-library-item').length>0`, 'lib_cover', { scrollTo: '.flipset-library', top: 170 });
		await imagesLoaded(b, '.flipset-library img');
		await sleep(500);
		await b.shot(file('lib_cover'));
		await remember(b, 'lib_cover', 'grid', '.flipset-library');
	},
};

module.exports = { steps, SITE, out, cookies, pages, W, H, file, viewerReady };

if (require.main === module) {
	(async () => {
		const want = process.argv.slice(2);
		fs.mkdirSync(out, { recursive: true });
		const b = await launch(9362);
		try {
			await b.cookies(cookies);
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
