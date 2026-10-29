/**
 * node bin/video/build-full.js [workers]
 *
 * Renders the whole intro video: frames from full.html (several browsers in
 * parallel), then mixes the voiceover clips in out/vo/<id>.mp3 at the times
 * defined by the scene timeline and writes out/flipset-intro.mp4.
 * Needs ffmpeg: set FFMPEG to the executable (e.g. from the ffmpeg-static npm package).
 */
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');
const { launch } = require('../wporg-assets/cdp');

const FPS = 30;
const WORKERS = parseInt(process.argv[2] || '4', 10);
const FFMPEG = process.env.FFMPEG || 'ffmpeg';
const out = path.join(__dirname, 'out');
const frames = path.join(out, 'frames');

function run(args) {
	return new Promise((resolve, reject) => {
		const p = spawn(process.execPath, args, { stdio: ['ignore', 'inherit', 'inherit'] });
		p.on('exit', (code) => (code === 0 ? resolve() : reject(new Error('worker exited ' + code))));
	});
}

(async () => {
	// The flip frames are plain files; full.html reads their list from out/flip.js.
	const flip = JSON.parse(fs.readFileSync(path.join(out, 'flip.json'), 'utf8'));
	fs.writeFileSync(path.join(out, 'flip.js'), 'window.FLIP=' + JSON.stringify({ duration: flip.duration, frames: flip.frames.map((f) => ({ t: f.t, url: 'out/flip/' + f.file })) }) + ';');

	// Scene timeline, straight from the page so audio and picture always agree.
	const b = await launch(9399);
	let TL, TOTAL;
	try {
		await b.goto('file:///' + path.join(__dirname, 'full.html').replace(/\\/g, '/'), { wait: 300 });
		await b.eval('window.ready');
		TL = await b.eval('window.TL');
		TOTAL = await b.eval('window.TOTAL');
	} finally {
		await b.close();
	}
	const total = Math.ceil(TOTAL * FPS);
	console.log('duration', TOTAL.toFixed(2), 's,', total, 'frames');

	fs.rmSync(frames, { recursive: true, force: true });
	fs.mkdirSync(frames, { recursive: true });
	const per = Math.ceil(total / WORKERS);
	const jobs = [];
	for (let w = 0; w < WORKERS; w++) {
		const from = w * per;
		const to = Math.min(total, from + per);
		if (from < to) { jobs.push(run([path.join(__dirname, 'render-worker.js'), String(from), String(to), String(9400 + w)])); }
	}
	await Promise.all(jobs);

	const args = ['-y', '-framerate', String(FPS), '-i', path.join(frames, '%05d.jpg')];
	TL.forEach((s) => args.push('-i', path.join(out, 'vo', s.id + '.mp3')));
	const delays = TL.map((s, i) => `[${i + 1}:a]aformat=sample_rates=48000:channel_layouts=mono,adelay=${Math.round(s.voice * 1000)}:all=1[a${i}]`).join(';');
	const mix = TL.map((_, i) => `[a${i}]`).join('') + `amix=inputs=${TL.length}:normalize=0,loudnorm=I=-16:TP=-1.5:LRA=11,afade=t=out:st=${(TOTAL - 0.8).toFixed(2)}:d=0.8[aout]`;
	args.push('-filter_complex', delays + ';' + mix, '-map', '0:v', '-map', '[aout]', '-t', TOTAL.toFixed(2),
		'-c:v', 'libx264', '-preset', 'slow', '-crf', '17', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
		'-c:a', 'aac', '-b:a', '192k', '-ar', '48000', path.join(out, 'flipset-intro.mp4'));
	const r = spawnSync(FFMPEG, args, { encoding: 'utf8' });
	if (r.status !== 0) { console.error(r.stderr.slice(-2500)); process.exit(1); }
	console.log('wrote', path.join(out, 'flipset-intro.mp4'));
})().catch((e) => { console.error(e); process.exit(1); });
