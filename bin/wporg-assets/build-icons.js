// node bin/wporg-assets/build-icons.js  -> .wordpress-org/icon-128x128.png, icon-256x256.png, icon.svg
const fs = require('fs');
const path = require('path');
const { launch } = require('./cdp');

const root = path.resolve(__dirname, '..', '..');
const outDir = path.join(root, '.wordpress-org');
const svg = fs.readFileSync(path.join(__dirname, 'icon.svg'), 'utf8');

(async () => {
	fs.mkdirSync(outDir, { recursive: true });
	fs.copyFileSync(path.join(__dirname, 'icon.svg'), path.join(outDir, 'icon.svg'));
	const b = await launch();
	try {
		for (const size of [128, 256]) {
			await b.viewport(size, size, 1);
			const html = '<!doctype html><meta charset="utf-8"><style>html,body{margin:0;background:transparent}svg{display:block;width:' + size + 'px;height:' + size + 'px}</style>' + svg;
			await b.goto('data:text/html;charset=utf-8,' + encodeURIComponent(html), { wait: 300 });
			await b.send('Emulation.setDefaultBackgroundColorOverride', { color: { r: 0, g: 0, b: 0, a: 0 } });
			await b.shot(path.join(outDir, 'icon-' + size + 'x' + size + '.png'), { clip: { x: 0, y: 0, width: size, height: size } });
			console.log('icon', size);
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
