/**
 * node bin/video/stills.js <outDir> <t1> <t2> ...
 * Saves single frames of scene.html at the given times (seconds) for a quick look.
 */
const path = require('path');
const { launch } = require('../wporg-assets/cdp');

const [outDir, ...times] = process.argv.slice(2);

(async () => {
	const b = await launch(9361);
	try {
		await b.viewport(1920, 1080, 1);
		await b.goto('file:///' + path.join(__dirname, process.env.PAGE || 'scene.html').replace(/\\/g, '/'), { wait: 300 });
		await b.eval('window.ready');
		for (const t of times) {
			await b.eval('window.draw(' + parseFloat(t) + ')');
			await b.shot(path.join(outDir, 't' + t + '.png'));
			console.log('still', t);
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
