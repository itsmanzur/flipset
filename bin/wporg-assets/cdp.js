/**
 * Minimal Chrome DevTools Protocol driver (Edge/Chrome, headless) for building
 * the WordPress.org assets. No dependencies: Node 22+ has fetch and WebSocket.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BROWSERS = [
	'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
	'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
	'C:/Program Files/Google/Chrome/Application/chrome.exe',
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function launch(port = 9333) {
	const exe = BROWSERS.find((p) => fs.existsSync(p));
	if (!exe) { throw new Error('No Edge/Chrome found'); }
	const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'flipset-cdp-'));
	const proc = spawn(exe, [
		'--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
		'--force-color-profile=srgb', '--font-render-hinting=none', '--allow-file-access-from-files',
		'--autoplay-policy=no-user-gesture-required',
		'--remote-debugging-port=' + port, '--user-data-dir=' + profile, 'about:blank',
	], { stdio: 'ignore' });

	let targets = null;
	for (let i = 0; i < 60; i++) {
		try {
			targets = await (await fetch('http://127.0.0.1:' + port + '/json')).json();
			if (targets.some((t) => t.type === 'page')) { break; }
		} catch (e) { /* not up yet */ }
		await sleep(250);
	}
	if (!targets) { proc.kill(); throw new Error('Browser did not start'); }
	const target = targets.find((t) => t.type === 'page');
	const ws = new WebSocket(target.webSocketDebuggerUrl);
	await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

	let id = 0;
	const pending = new Map();
	const listeners = [];
	ws.onmessage = (ev) => {
		const msg = JSON.parse(ev.data);
		if (msg.id && pending.has(msg.id)) {
			const { res, rej } = pending.get(msg.id);
			pending.delete(msg.id);
			msg.error ? rej(new Error(msg.error.message)) : res(msg.result);
		} else if (msg.method) {
			listeners.forEach((l) => l(msg));
		}
	};
	const send = (method, params = {}) => new Promise((res, rej) => {
		const i = ++id;
		pending.set(i, { res, rej });
		ws.send(JSON.stringify({ id: i, method, params }));
	});

	await send('Page.enable');
	await send('Runtime.enable');
	await send('Network.enable');

	const api = {
		send,
		// Listen to raw protocol events, e.g. api.on((m) => m.method === 'Page.screencastFrame' && ...).
		on(fn) { listeners.push(fn); },
		async viewport(width, height, scale = 1) {
			await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: scale, mobile: false });
		},
		async cookies(list) {
			for (const c of list) { await send('Network.setCookie', c); }
		},
		async goto(url, { wait = 800, until = null, timeout = 60000 } = {}) {
			let loaded = false;
			const off = (m) => { if (m.method === 'Page.loadEventFired') { loaded = true; } };
			listeners.push(off);
			await send('Page.navigate', { url });
			const t0 = Date.now();
			while (!loaded && Date.now() - t0 < timeout) { await sleep(100); }
			listeners.splice(listeners.indexOf(off), 1);
			if (until) {
				const t1 = Date.now();
				while (Date.now() - t1 < timeout) {
					if (await api.eval(until)) { break; }
					await sleep(250);
				}
			}
			await sleep(wait);
		},
		async eval(expression) {
			const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
			if (r.exceptionDetails) { throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception && r.exceptionDetails.exception.description || '')); }
			return r.result.value;
		},
		async shot(file, { clip = null, scale = null, full = false } = {}) {
			const params = { format: 'png', fromSurface: true, captureBeyondViewport: full };
			if (clip) { params.clip = { x: clip.x, y: clip.y, width: clip.width, height: clip.height, scale: clip.scale || 1 }; }
			if (scale && clip) { params.clip.scale = scale; }
			const r = await send('Page.captureScreenshot', params);
			fs.mkdirSync(path.dirname(file), { recursive: true });
			fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
			return file;
		},
		async pdf(file, opts = {}) {
			const r = await send('Page.printToPDF', Object.assign({ printBackground: true, preferCSSPageSize: true }, opts));
			fs.mkdirSync(path.dirname(file), { recursive: true });
			fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
			return file;
		},
		async close() {
			try { ws.close(); } catch (e) { /* ignore */ }
			proc.kill();
			await sleep(300);
			try { fs.rmSync(profile, { recursive: true, force: true }); } catch (e) { /* locked, ignore */ }
		},
	};
	return api;
}

module.exports = { launch, sleep };
