/**
 * node bin/wporg-assets/screenshots.js [step ...]
 *
 * Takes the WordPress.org screenshots from a throw-away demo site (see
 * bin/wporg-assets/README.md). Steps: prep, 1 .. 8. No step = all.
 * Needs out/cookies.json (admin login cookies) and SITE (default http://127.0.0.1:8100).
 */
const fs = require('fs');
const path = require('path');
const { launch, sleep } = require('./cdp');

const SITE = process.env.SITE || 'http://127.0.0.1:8100';
const out = path.join(__dirname, '..', '..', '.wordpress-org');
const cookies = JSON.parse(fs.readFileSync(path.join(__dirname, 'out', 'cookies.json'), 'utf8'));
const pages = JSON.parse(fs.readFileSync(path.join(__dirname, 'out', 'pages.json'), 'utf8'));
const W = 1280;
const H = 800;

const steps = {
	// Generate thumbnails, text index and the tagged-PDF flag in the browser, like a real visit would.
	async prep(b) {
		await b.viewport(W, H);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 500 });
		for (let i = 0; i < 160; i++) {
			const done = await b.eval(`(function(){var r=[].slice.call(document.querySelectorAll('.fa-table tbody tr[data-pdf-id]'));return r.length>0&&r.every(function(x){return x.querySelector('.flipset-docs-thumb-img')&&!/Pending/.test(x.textContent);});})()`);
			if (done) { break; }
			await sleep(1000);
		}
		console.log('prep done');
	},

	// 1. Front-end viewer, compact toolbar.
	async 1(b) {
		await b.viewport(W, H);
		await b.goto(SITE + '/?page_id=' + pages.view, { until: `document.querySelectorAll('.flipset-viewer canvas').length>0`, wait: 2500 });
		await b.eval(`window.scrollTo(0, document.querySelector('.flipset-wrap').getBoundingClientRect().top + scrollY - 120)`);
		await sleep(600);
		await b.shot(path.join(out, 'screenshot-1.png'));
	},

	// 2. 3D flipbook: a spread with the book open.
	async 2(b) {
		await b.viewport(W, H);
		// The single-threaded dev server can drop a request while the page loads; retry if the viewer shows an error.
		for (let i = 0; i < 4; i++) {
			await b.goto(SITE + '/?page_id=' + pages.flip, { until: `document.querySelectorAll('.flipset-viewer canvas').length>0`, wait: 4000 });
			await b.eval(`window.scrollTo(0, document.querySelector('.flipset-wrap').getBoundingClientRect().top + scrollY - 140)`);
			await sleep(800);
			if (!(await b.eval(`!!document.querySelector('.flipset-error')`))) { break; }
			console.log('flip view errored, retrying');
		}
		await b.shot(path.join(out, 'screenshot-2.png'));
	},

	// 3. Dashboard.
	async 3(b) {
		await b.viewport(W, H + 140);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-settings', { wait: 1500 });
		await b.shot(path.join(out, 'screenshot-3.png'));
	},

	// 4. Shortcode Builder with live preview.
	async 4(b) {
		await b.viewport(W, H + 120);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-shortcode-builder', { wait: 4000 });
		await b.shot(path.join(out, 'screenshot-4.png'));
	},

	// 5. Documents library with previews and statuses.
	async 5(b) {
		await b.viewport(W, H + 160);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-documents', { wait: 2500 });
		// Fold the "4 ways to embed" tips away so more rows (and the custom cover) fit in the shot.
		await b.eval(`(function(){var h=[].slice.call(document.querySelectorAll('button')).filter(function(x){return x.textContent.trim()==='Hide';})[0];if(h){h.click();}})()`);
		await sleep(600);
		await b.shot(path.join(out, 'screenshot-5.png'));
	},

	// 6. Settings with live preview.
	async 6(b) {
		await b.viewport(W, H + 120);
		await b.goto(SITE + '/wp-admin/admin.php?page=flipset-viewer-settings', { until: `document.querySelectorAll('#fa-preview-stage canvas').length>0&&!/Loading document/.test(document.querySelector('#fa-preview-stage').textContent)`, wait: 2500 });
		// The preview draws a moment after the canvas appears; wait for the placeholder to go.
		for (let i = 0; i < 30 && (await b.eval(`/Loading document/.test(document.body.innerText)`)); i++) { await sleep(1000); }
		await sleep(800);
		await b.shot(path.join(out, 'screenshot-6.png'));
	},

	// 7. Block editor.
	async 7(b) {
		await b.viewport(W, H + 60);
		await b.goto(SITE + '/wp-admin/post-new.php?post_type=page', { wait: 6000, until: `window.wp&&wp.data&&wp.data.select('core/block-editor')` });
		await b.eval(`(function(){try{var d=document.querySelector('.editor-start-page-options__modal button[aria-label="Close"], .components-modal__header button[aria-label="Close"]');if(d){d.click();}wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false);wp.data.dispatch('core/preferences').set('core/edit-post','fullscreenMode',false);}catch(e){}})()`);
		await sleep(1500);
		await b.eval(`(function(){
			var cat = ${JSON.stringify(pages.catUrl)};
			wp.data.dispatch('core/editor').editPost({ title: 'Our product catalogue' });
			var blocks = [
				wp.blocks.createBlock('core/paragraph', { content: 'Read the full 2026 catalogue below. It opens right here on the page, no download needed.' }),
				wp.blocks.createBlock('flipset/viewer', { file: cat, height: '520px', title: '' })
			];
			wp.data.dispatch('core/block-editor').resetBlocks(blocks);
			wp.data.dispatch('core/block-editor').selectBlock(blocks[1].clientId);
		})()`);
		await sleep(5000);
		await b.shot(path.join(out, 'screenshot-7.png'));
	},

	// 8. Front-end library grid.
	async 8(b) {
		await b.viewport(W, 935);
		await b.goto(SITE + '/?page_id=' + pages.lib, { until: `document.querySelectorAll('.flipset-library-item').length>0`, wait: 2000 });
		await b.eval(`window.scrollTo(0, document.querySelector('.flipset-library').getBoundingClientRect().top + scrollY - 200)`);
		await sleep(600);
		await b.shot(path.join(out, 'screenshot-8.png'));
	},
};

(async () => {
	const want = process.argv.slice(2);
	const run = want.length ? want : ['prep', '1', '2', '3', '4', '5', '6', '7', '8'];
	fs.mkdirSync(out, { recursive: true });
	const b = await launch();
	try {
		await b.cookies(cookies);
		for (const s of run) {
			if (!steps[s]) { console.log('unknown step', s); continue; }
			await steps[s](b);
			console.log('step', s, 'ok');
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
