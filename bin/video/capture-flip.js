/**
 * node bin/video/capture-flip.js
 *
 * Records the real 3D page turn of the Flipset viewer on the demo site with the
 * browser's screencast (2x), and writes bin/video/out/flip/NNNN.jpg plus
 * flip.json: [{ t, file }] with t in seconds from the first frame.
 * The scene player picks the frame whose t is closest to the scene time.
 */
const fs = require('fs');
const path = require('path');
const { launch, sleep } = require('../wporg-assets/cdp');

const SITE = process.env.SITE || 'http://127.0.0.1:8101';
const out = path.join(__dirname, 'out');
const dir = path.join(out, 'flip');
const cookies = JSON.parse(fs.readFileSync(path.join(out, 'cookies.json'), 'utf8'));
const pages = JSON.parse(fs.readFileSync(path.join(out, 'pages.json'), 'utf8'));
const ready = `(function(){var v=document.querySelector('.flipset-viewer');return !!v&&v.querySelectorAll('canvas').length>0&&!/Loading document/i.test(v.innerText);})()`;

(async () => {
	fs.rmSync(dir, { recursive: true, force: true });
	fs.mkdirSync(dir, { recursive: true });
	const b = await launch(9366);
	const frames = [];
	try {
		await b.cookies(cookies);
		await b.send('Page.addScriptToEvaluateOnNewDocument', { source: `try{localStorage.clear()}catch(e){}` });
		await b.viewport(1440, 900, 2);
		await b.goto(SITE + '/?page_id=' + pages.flip, { until: ready, wait: 2500 });
		await b.eval(`(function(){var s=document.createElement('style');s.textContent='#wpadminbar{display:none!important}html{margin-top:0!important}';document.head.appendChild(s);var t=document.querySelector('.flipset-wrap');window.scrollTo(0,t.getBoundingClientRect().top+scrollY-150);})()`);
		await sleep(800);

		let n = 0;
		let t0 = null;
		b.on((m) => {
			if (m.method !== 'Page.screencastFrame') { return; }
			const ts = m.params.metadata.timestamp;
			if (t0 === null) { t0 = ts; }
			const name = String(n++).padStart(4, '0') + '.jpg';
			fs.writeFileSync(path.join(dir, name), Buffer.from(m.params.data, 'base64'));
			frames.push({ t: +(ts - t0).toFixed(3), file: name });
			b.send('Page.screencastFrameAck', { sessionId: m.params.sessionId }).catch(() => {});
		});
		await b.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 2880, maxHeight: 1800, everyNthFrame: 1 });
		await sleep(700);
		// Two page turns, a beat apart.
		for (let i = 0; i < 2; i++) {
			await b.eval(`document.querySelector('.flipset-next').click()`);
			await sleep(2200);
		}
		await sleep(500);
		await b.send('Page.stopScreencast');
		fs.writeFileSync(path.join(out, 'flip.json'), JSON.stringify({ frames, duration: frames.length ? frames[frames.length - 1].t : 0 }));
		console.log('frames', frames.length, 'duration', frames.length ? frames[frames.length - 1].t : 0);
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
