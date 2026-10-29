/**
 * node bin/video/make-srt.js  ->  bin/video/out/flipset-intro.en.srt
 *
 * English captions for the intro video. Each voiceover clip is split into its
 * phrases at the pauses (ffmpeg silencedetect), and every phrase is placed at
 * the clip's start time in the video. Scene timings are the same constants as
 * in full.html (CLIPS), so captions and picture agree.
 * Needs ffmpeg: set FFMPEG to the executable.
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const FFMPEG = process.env.FFMPEG || 'ffmpeg';
const out = path.join(__dirname, 'out');

// [id, clip seconds, lead-in, tail] exactly as in full.html.
const CLIPS = [['hook', 3.32, .35, .5], ['meet', 6.11, .2, .5], ['embed', 8.99, .2, .4], ['views', 11.47, .2, .4],
	['builder', 8.28, .2, .4], ['library', 8.99, .2, .4], ['search', 8.28, .2, .4], ['close', 9.48, .2, 1.3]];

// The spoken phrases of each clip, in order, split where the voice pauses.
const PHRASES = {
	hook: ["Sharing a PDF shouldn't mean sending a download."],
	meet: ['Meet Flipset:', 'a PDF viewer, 3D flipbook and document library for WordPress.'],
	embed: ['Upload a PDF, add one shortcode, or drop in the block.', 'Your document opens right on the page, in a fast viewer that runs on your own site.'],
	views: ['Read page by page, as a spread, or as a 3D flipbook.', 'Switch between light, dark and sepia,', 'zoom, search and print,', 'all from one clean toolbar.'],
	builder: ['The Shortcode Builder shows a live preview as you change settings.', 'Pick a preset, save it, and copy a short, clean shortcode.'],
	library: ['Show every document in a searchable grid.', 'Pick your own cover image, or use the first page.', 'Visitors click a cover and read it right there.'],
	search: ['Flipset reads the text inside your PDFs, so your site search can find it,', 'and it checks every file for accessibility tags.'],
	close: ['Everything is bundled:', 'no CDN, no account, no tracking.', 'Flipset is free on WordPress.org.', 'Search for Flipset and try it today.'],
};

function speechSegments(file, dur, d) {
	const r = spawnSync(FFMPEG, ['-i', file, '-af', 'silencedetect=noise=-35dB:d=' + d, '-f', 'null', '-'], { encoding: 'utf8' });
	const starts = [...r.stderr.matchAll(/silence_start: ([0-9.]+)/g)].map((m) => parseFloat(m[1]));
	const ends = [...r.stderr.matchAll(/silence_end: ([0-9.]+)/g)].map((m) => parseFloat(m[1]));
	const segs = [];
	let cur = 0;
	starts.forEach((s, i) => {
		if (s - cur > 0.05) { segs.push([cur, s]); }
		cur = ends[i] !== undefined ? ends[i] : dur;
	});
	if (dur - cur > 0.05) { segs.push([cur, dur]); }
	return segs;
}

function wrap(text, max = 42) {
	if (text.length <= max) { return text; }
	const words = text.split(' ');
	let best = null;
	for (let i = 1; i < words.length; i++) {
		const a = words.slice(0, i).join(' '), b = words.slice(i).join(' ');
		const score = Math.max(a.length, b.length);
		if (!best || score < best.score) { best = { a, b, score }; }
	}
	return best.a + '\n' + best.b;
}

const stamp = (t) => {
	const ms = Math.round(t * 1000);
	const p = (n, w = 2) => String(n).padStart(w, '0');
	return `${p(Math.floor(ms / 3600000))}:${p(Math.floor(ms / 60000) % 60)}:${p(Math.floor(ms / 1000) % 60)},${p(ms % 1000, 3)}`;
};

let acc = 0;
const cues = [];
for (const [id, dur, lead, tail] of CLIPS) {
	const file = path.join(out, 'vo', id + '.mp3');
	const want = PHRASES[id].length;
	let segs = null;
	for (const d of [0.25, 0.3, 0.35, 0.4, 0.2, 0.45, 0.5]) {
		const s = speechSegments(file, dur, d);
		if (s.length === want) { segs = s; break; }
	}
	if (!segs) { throw new Error(id + ': could not split into ' + want + ' phrases'); }
	const base = acc + lead;
	segs.forEach((s, i) => cues.push({ a: base + s[0], b: base + Math.min(dur, s[1] + 0.15), text: PHRASES[id][i] }));
	acc += lead + dur + tail;
}

// A cue never overlaps the next one.
cues.forEach((c, i) => { if (cues[i + 1] && c.b > cues[i + 1].a - 0.04) { c.b = cues[i + 1].a - 0.04; } });

const srt = cues.map((c, i) => `${i + 1}\n${stamp(c.a)} --> ${stamp(c.b)}\n${wrap(c.text)}\n`).join('\n');
const dest = path.join(out, 'flipset-intro.en.srt');
fs.writeFileSync(dest, srt);
console.log('wrote', dest, cues.length, 'cues');
cues.forEach((c) => console.log(stamp(c.a), '-->', stamp(c.b), c.text));
