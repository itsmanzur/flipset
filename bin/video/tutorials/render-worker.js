/**
 * node bin/video/tutorials/render-worker.js <id> <fromFrame> <toFrame> <port>
 * Renders frames [from, to) of engine.html?id=<id> to out/tutorials/<id>/frames/NNNNN.jpg (1920x1080, 30 fps).
 * Started by build.js, which runs several of these in parallel.
 */
const fs = require('fs');
const path = require('path');
const { launch } = require('../../wporg-assets/cdp');

const FPS = 30;
const id = process.argv[2];
const [from, to, port] = process.argv.slice(3).map(Number);
const frames = path.join(__dirname, '..', 'out', 'tutorials', id, 'frames');

(async () => {
	fs.mkdirSync(frames, { recursive: true });
	const b = await launch(port);
	try {
		await b.viewport(1920, 1080, 1);
		await b.goto('file:///' + path.join(__dirname, 'engine.html').replace(/\\/g, '/') + '?id=' + id, { wait: 300 });
		await b.eval('window.ready');
		for (let i = from; i < to; i++) {
			await b.eval('window.draw(' + (i / FPS).toFixed(4) + ')');
			const r = await b.send('Page.captureScreenshot', { format: 'jpeg', quality: 93, fromSurface: true });
			fs.writeFileSync(path.join(frames, String(i).padStart(5, '0') + '.jpg'), Buffer.from(r.data, 'base64'));
			if ((i - from) % 90 === 0) { console.log('worker', port, 'frame', i, '/', to); }
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
