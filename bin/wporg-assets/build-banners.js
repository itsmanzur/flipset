// node bin/wporg-assets/build-banners.js -> .wordpress-org/banner-1544x500.png, banner-772x250.png (+ -rtl)
const fs = require('fs');
const path = require('path');
const { launch } = require('./cdp');

const root = path.resolve(__dirname, '..', '..');
const outDir = path.join(root, '.wordpress-org');
const page = 'file:///' + path.join(__dirname, 'banner.html').replace(/\\/g, '/');

(async () => {
	fs.mkdirSync(outDir, { recursive: true });
	const b = await launch();
	try {
		for (const rtl of [false, true]) {
			await b.viewport(1544, 500, 1);
			await b.goto(page + (rtl ? '?rtl=1' : ''), { wait: 700 });
			const suffix = rtl ? '-rtl' : '';
			await b.shot(path.join(outDir, 'banner-1544x500' + suffix + '.png'), { clip: { x: 0, y: 0, width: 1544, height: 500 } });
			await b.shot(path.join(outDir, 'banner-772x250' + suffix + '.png'), { clip: { x: 0, y: 0, width: 1544, height: 500, scale: 0.5 } });
			console.log('banners', rtl ? 'rtl' : 'ltr');
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
