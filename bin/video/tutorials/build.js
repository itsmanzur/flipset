/**
 * node bin/video/tutorials/build.js <id> [--shots t1,t2,...] [--workers N] [--no-render]
 *
 * <id> is f01, f02, ... with a spec in specs/<id>.js. Builds out/tutorials/<id>/data.js (scene timeline from the real
 * voiceover clips in tutorials/<id>/vo, image sizes and element boxes from the capture step), then
 *   --shots   writes preview stills at those seconds and stops
 *   default   renders every frame (several browsers in parallel), mixes the voiceover, writes the mp4 and an English .srt
 * Needs ffmpeg and ffprobe: set FFMPEG to the ffmpeg executable (ffprobe is taken from the same folder).
 */
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');
const { launch } = require('../../wporg-assets/cdp');

const FPS = 30;
const args = process.argv.slice(2);
const id = args[0];
const opt = (n) => { const i = args.indexOf('--' + n); return i < 0 ? null : args[i + 1]; };
const FFMPEG = process.env.FFMPEG || 'ffmpeg';
const FFPROBE = process.env.FFPROBE || FFMPEG.replace(/ffmpeg(\.exe)?$/i, 'ffprobe$1');
const root = path.join(__dirname, '..');
const base = path.join(root, 'out', 'tutorials');
const out = path.join(base, id);
const frames = path.join(out, 'frames');
const spec = require('./specs/' + id + '.js');

function dur(file) {
	const r = spawnSync(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file], { encoding: 'utf8' });
	return parseFloat(r.stdout);
}
function pngSize(file) {
	const b = fs.readFileSync(file);
	return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) };
}
function run(a) {
	return new Promise((resolve, reject) => {
		const p = spawn(process.execPath, a, { stdio: ['ignore', 'inherit', 'inherit'] });
		p.on('exit', (code) => (code === 0 ? resolve() : reject(new Error('worker exited ' + code))));
	});
}

// Voiceover lines of this video from wave1.md: { id: text }.
function lines() {
	const md = fs.readFileSync(path.join(__dirname, spec.script || 'wave1.md'), 'utf8');
	const sec = md.split(/^## /m).find((s) => s.startsWith(spec.code + ' -'));
	const res = {};
	(sec || '').split('\n').forEach((l) => {
		const c = l.split('|').map((x) => x.trim());
		const m = c[2] && c[2].match(/^`(\w+)`$/);
		if (m) { res[m[1]] = c[4]; }
	});
	return res;
}

function buildData() {
	fs.mkdirSync(out, { recursive: true });
	const lead = Object.assign({ default: .2, hook: .35 }, spec.lead || {});
	const tail = Object.assign({ default: .4, hook: .5, close: 1.4 }, spec.tail || {});
	const TL = []; let acc = 0;
	spec.order.forEach((sid) => {
		const f = path.join(__dirname, id, 'vo', sid + '.mp3');
		if (!fs.existsSync(f)) { throw new Error('missing voiceover ' + f); }
		const clip = dur(f);
		const l = lead[sid] != null ? lead[sid] : lead.default, t = tail[sid] != null ? tail[sid] : tail.default;
		const len = l + clip + t;
		TL.push({ id: sid, s: acc, len, voice: acc + l, clipLen: clip, file: f });
		acc += len;
	});
	const shots = path.join(base, 'shots');
	const IMG = {};
	fs.readdirSync(shots).filter((f) => /\.png$/.test(f)).forEach((f) => {
		const d = pngSize(path.join(shots, f));
		IMG[f.replace(/\.png$/, '')] = { url: '../out/tutorials/shots/' + f, w: d.w / 2, h: d.h / 2 };
	});
	const RECTS = JSON.parse(fs.readFileSync(path.join(shots, 'rects.json'), 'utf8'));
	Object.keys(spec.rects || {}).forEach((k) => { RECTS[k] = Object.assign(RECTS[k] || {}, spec.rects[k]); });
	const flip = JSON.parse(fs.readFileSync(path.join(root, 'out', 'flip.json'), 'utf8'));
	const FLIP = { duration: flip.duration, frames: flip.frames.map((f) => ({ t: f.t, url: '../out/flip/' + f.file })) };
	const SPEC = { id, TL: TL.map(({ id, s, len, voice, clipLen }) => ({ id, s, len, voice, clipLen })), TOTAL: acc, IMG, RECTS, FLIP, scenes: spec.scenes };
	fs.writeFileSync(path.join(out, 'data.js'), 'window.SPEC=' + JSON.stringify(SPEC) + ';');
	return { TL, TOTAL: acc };
}

function srtTime(t) {
	const ms = Math.round(t * 1000), h = Math.floor(ms / 3600000), m = Math.floor(ms / 60000) % 60, s = Math.floor(ms / 1000) % 60;
	return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0') + ',' + String(ms % 1000).padStart(3, '0');
}
// Captions: each clip's sentences, spread over the clip by length.
function writeSrt(TL, name) {
	const text = lines();
	let n = 1, srt = '';
	TL.forEach((sc) => {
		const t = text[sc.id];
		if (!t) { return; }
		let parts = t.split(/(?<=[.!?])\s+/).filter(Boolean);
		parts = parts.flatMap((p) => (p.length > 84 ? p.split(/(?<=[,:;])\s+/).reduce((a, c) => { if (a.length && (a[a.length - 1].length + c.length) < 70) { a[a.length - 1] += ' ' + c; } else { a.push(c); } return a; }, []) : [p]));
		const total = parts.reduce((a, p) => a + p.length, 0);
		let at = sc.voice;
		parts.forEach((p) => {
			const d = sc.clipLen * p.length / total;
			srt += n++ + '\n' + srtTime(at) + ' --> ' + srtTime(at + d - .05) + '\n' + p + '\n\n';
			at += d;
		});
	});
	fs.writeFileSync(name, srt);
}

(async () => {
	const { TL, TOTAL } = buildData();
	console.log(id, 'duration', TOTAL.toFixed(2), 's,', Math.ceil(TOTAL * FPS), 'frames');
	const shotsAt = opt('shots');
	if (shotsAt) {
		const b = await launch(9395);
		try {
			await b.viewport(1920, 1080, 1);
			await b.goto('file:///' + path.join(__dirname, 'engine.html').replace(/\\/g, '/') + '?id=' + id, { wait: 300 });
			await b.eval('window.ready');
			fs.mkdirSync(path.join(out, 'preview'), { recursive: true });
			for (const t of shotsAt.split(',').map(Number)) {
				await b.eval('window.draw(' + t + ')');
				const r = await b.send('Page.captureScreenshot', { format: 'jpeg', quality: 85, fromSurface: true });
				const f = path.join(out, 'preview', 't' + String(t).replace('.', '_') + '.jpg');
				fs.writeFileSync(f, Buffer.from(r.data, 'base64'));
				console.log('wrote', f);
			}
		} finally { await b.close(); }
		return;
	}
	const total = Math.ceil(TOTAL * FPS);
	if (!args.includes('--no-render')) {
		fs.rmSync(frames, { recursive: true, force: true });
		fs.mkdirSync(frames, { recursive: true });
		const W = parseInt(opt('workers') || '4', 10), per = Math.ceil(total / W), jobs = [];
		for (let w = 0; w < W; w++) {
			const from = w * per, to = Math.min(total, from + per);
			if (from < to) { jobs.push(run([path.join(__dirname, 'render-worker.js'), id, String(from), String(to), String(9400 + w)])); }
		}
		await Promise.all(jobs);
	}
	const mp4 = path.join(out, spec.file + '.mp4');
	const a = ['-y', '-framerate', String(FPS), '-i', path.join(frames, '%05d.jpg')];
	TL.forEach((s) => a.push('-i', s.file));
	const delays = TL.map((s, i) => `[${i + 1}:a]aformat=sample_rates=48000:channel_layouts=mono,adelay=${Math.round(s.voice * 1000)}:all=1[a${i}]`).join(';');
	const mix = TL.map((_, i) => `[a${i}]`).join('') + `amix=inputs=${TL.length}:normalize=0,loudnorm=I=-16:TP=-1.5:LRA=11,afade=t=out:st=${(TOTAL - 0.8).toFixed(2)}:d=0.8[aout]`;
	a.push('-filter_complex', delays + ';' + mix, '-map', '0:v', '-map', '[aout]', '-t', TOTAL.toFixed(2),
		'-c:v', 'libx264', '-preset', 'slow', '-crf', '17', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-c:a', 'aac', '-b:a', '192k', '-ar', '48000', mp4);
	const r = spawnSync(FFMPEG, a, { encoding: 'utf8' });
	if (r.status !== 0) { console.error(r.stderr.slice(-2500)); process.exit(1); }
	console.log('wrote', mp4);
	writeSrt(TL, path.join(out, spec.file + '.en.srt'));
	console.log('wrote srt');
})().catch((e) => { console.error(e); process.exit(1); });
