// node bin/video/build-thumbnail.js -> bin/video/out/thumbnail.jpg (1280x720, under YouTube's 2 MB limit)
const fs = require('fs');
const path = require('path');
const { launch } = require('../wporg-assets/cdp');

(async () => {
	const b = await launch(9368);
	try {
		await b.viewport(1280, 720, 1);
		await b.goto('file:///' + path.join(__dirname, 'thumbnail.html').replace(/\\/g, '/'), { wait: 800 });
		const r = await b.send('Page.captureScreenshot', { format: 'jpeg', quality: 92, fromSurface: true });
		const dest = path.join(__dirname, 'out', 'thumbnail.jpg');
		fs.mkdirSync(path.dirname(dest), { recursive: true });
		fs.writeFileSync(dest, Buffer.from(r.data, 'base64'));
		console.log('wrote', dest, (fs.statSync(dest).size / 1024).toFixed(0) + ' KB');
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
