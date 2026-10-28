/**
 * node bin/video/build-draft.js [seconds]
 *
 * Renders bin/video/scene.html frame by frame (headless Edge/Chrome via CDP) and
 * muxes the voiceover clips from out/vo/ into out/flipset-draft.mp4.
 * Needs ffmpeg: set FFMPEG to the executable (e.g. the one from the ffmpeg-static npm package).
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { launch } = require('../wporg-assets/cdp');

const FPS = 30;
const W = 1920;
const H = 1080;
const DURATION = parseFloat(process.argv[2] || '10');
const FFMPEG = process.env.FFMPEG || 'ffmpeg';
const out = path.join(__dirname, 'out');
const frames = path.join(out, 'frames');

// Voiceover clip start times (seconds), matching the scene timings in scene.html.
const VO = [
	{ file: 'hook.wav', at: 0.4 },
	{ file: 'meet.wav', at: 3.4 },
	{ file: 'demo.wav', at: 5.5 },
];

(async () => {
	fs.rmSync(frames, { recursive: true, force: true });
	fs.mkdirSync(frames, { recursive: true });

	const b = await launch(9360);
	try {
		await b.viewport(W, H, 1);
		await b.goto('file:///' + path.join(__dirname, 'scene.html').replace(/\\/g, '/'), { wait: 300 });
		await b.eval('window.ready');
		const total = Math.round(DURATION * FPS);
		for (let i = 0; i < total; i++) {
			await b.eval('window.draw(' + (i / FPS).toFixed(4) + ')');
			await b.shot(path.join(frames, String(i).padStart(5, '0') + '.png'));
			if (i % 30 === 0) { console.log('frame', i, '/', total); }
		}
	} finally {
		await b.close();
	}

	const args = ['-y', '-framerate', String(FPS), '-i', path.join(frames, '%05d.png')];
	VO.forEach((v) => args.push('-i', path.join(out, 'vo', v.file)));
	const delays = VO.map((v, i) => '[' + (i + 1) + ':a]adelay=' + Math.round(v.at * 1000) + '|' + Math.round(v.at * 1000) + ',volume=1.6[a' + i + ']').join(';');
	const mix = VO.map((v, i) => '[a' + i + ']').join('') + 'amix=inputs=' + VO.length + ':normalize=0,afade=t=out:st=' + (DURATION - 0.4) + ':d=0.4[aout]';
	args.push('-filter_complex', delays + ';' + mix, '-map', '0:v', '-map', '[aout]', '-t', String(DURATION),
		'-c:v', 'libx264', '-preset', 'slow', '-crf', '17', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
		'-c:a', 'aac', '-b:a', '192k', '-ar', '48000', path.join(out, 'flipset-draft.mp4'));
	const r = spawnSync(FFMPEG, args, { encoding: 'utf8' });
	if (r.status !== 0) { console.error(r.stderr.slice(-2000)); process.exit(1); }
	console.log('wrote', path.join(out, 'flipset-draft.mp4'));
})().catch((e) => { console.error(e); process.exit(1); });
