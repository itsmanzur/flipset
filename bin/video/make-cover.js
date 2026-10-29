// node bin/video/make-cover.js -> bin/video/out/shots/custom-cover.png (900x1200)
// A fictional cover image for the "Change cover" part of the video.
const fs = require('fs');
const path = require('path');
const { launch } = require('../wporg-assets/cdp');

const font = 'file:///' + path.join(__dirname, '..', 'wporg-assets', 'fonts', 'outfit-latin.woff2').replace(/\\/g, '/');
const html = `<!doctype html><meta charset="utf-8"><style>
@font-face{font-family:Outfit;src:url("${font}") format("woff2");font-weight:100 900}
*{margin:0;box-sizing:border-box}
body{width:900px;height:1200px;overflow:hidden;font-family:Outfit,sans-serif;background:#f4ead9;color:#2b2118;position:relative}
.sun{position:absolute;width:760px;height:760px;border-radius:50%;right:-220px;top:-160px;background:radial-gradient(circle at 35% 35%,#f6b26b,#e07a3f 70%)}
.hill{position:absolute;left:-120px;bottom:-260px;width:1100px;height:720px;border-radius:50%;background:#6b8f71}
.hill2{position:absolute;right:-300px;bottom:-330px;width:1000px;height:700px;border-radius:50%;background:#3f6b4f}
.top{position:absolute;left:70px;top:80px;font-size:30px;letter-spacing:.32em;font-weight:600;text-transform:uppercase}
h1{position:absolute;left:70px;top:420px;font-size:170px;line-height:.92;font-weight:700;letter-spacing:-5px}
p{position:absolute;left:74px;top:790px;font-size:40px;font-weight:400;max-width:560px;line-height:1.25;color:#4a3a2a}
.tag{position:absolute;left:74px;bottom:70px;font-size:30px;font-weight:600;color:#fff;letter-spacing:.06em}
</style><div class="sun"></div><div class="hill"></div><div class="hill2"></div>
<div class="top">Meridian Studio</div><h1>Spring<br>Collection</h1><p>New pieces for lighter, brighter rooms.</p><div class="tag">2026 &middot; Catalogue</div>`;

(async () => {
	const b = await launch(9363);
	try {
		await b.viewport(900, 1200, 1);
		const tmp = path.join(__dirname, 'out', 'shots', 'cover.html');
		fs.mkdirSync(path.dirname(tmp), { recursive: true });
		fs.writeFileSync(tmp, html);
		await b.goto('file:///' + tmp.replace(/\\/g, '/'), { wait: 800 });
		const dest = path.join(__dirname, 'out', 'shots', 'custom-cover.png');
		fs.mkdirSync(path.dirname(dest), { recursive: true });
		await b.shot(dest, { clip: { x: 0, y: 0, width: 900, height: 1200 } });
		fs.unlinkSync(tmp);
		console.log('wrote', dest);
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
