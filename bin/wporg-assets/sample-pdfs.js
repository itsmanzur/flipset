// node bin/wporg-assets/sample-pdfs.js -> bin/wporg-assets/out/pdf/*.pdf
// Original, fictional sample documents used only to take the WordPress.org screenshots.
const fs = require('fs');
const path = require('path');
const { launch } = require('./cdp');

const outDir = path.join(__dirname, 'out', 'pdf');
const font = 'file:///' + path.join(__dirname, 'fonts', 'outfit-latin.woff2').replace(/\\/g, '/');

const DOCS = [
	{ file: 'meridian-product-catalogue-2026', title: 'Product Catalogue 2026', org: 'Meridian Studio', sub: 'Furniture and lighting for calm, well-made homes.', c1: '#4f46e5', c2: '#7c3aed', pages: 8, tagged: true,
		kinds: ['Seating', 'Tables', 'Lighting', 'Storage', 'Textiles', 'Outdoor'] },
	{ file: 'northlight-annual-report-2025', title: 'Annual Report 2025', org: 'Northlight Energy', sub: 'A year of steady growth and cleaner power.', c1: '#0f766e', c2: '#14b8a6', pages: 6, tagged: true,
		kinds: ['Highlights', 'Revenue', 'Operations', 'People', 'Outlook'] },
	{ file: 'employee-handbook', title: 'Employee Handbook', org: 'Brightwave Labs', sub: 'How we work, learn and look after each other.', c1: '#c2410c', c2: '#f59e0b', pages: 6, tagged: false,
		kinds: ['Welcome', 'Working hours', 'Benefits', 'Time off', 'Growth'] },
	{ file: 'harbor-cafe-seasonal-menu', title: 'Seasonal Menu', org: 'Harbor Cafe', sub: 'Autumn plates, slow coffee and baked-today bread.', c1: '#9a3412', c2: '#fb923c', pages: 3, tagged: false,
		kinds: ['Breakfast', 'Lunch', 'Sweet things'] },
	{ file: 'summit-conference-programme', title: 'Conference Programme', org: 'Summit Design Week', sub: 'Three days of talks, workshops and good company.', c1: '#7c3aed', c2: '#ec4899', pages: 5, tagged: true,
		kinds: ['Keynotes', 'Workshops', 'Panels', 'Evening events'] },
	{ file: 'aurora-lamp-user-guide', title: 'User Guide', org: 'Aurora Lamp', sub: 'Setup, care and troubleshooting for your lamp.', c1: '#1e293b', c2: '#f59e0b', pages: 5, tagged: false,
		kinds: ['In the box', 'Setup', 'Dimming', 'Care'] },
	{ file: 'riverbank-newsletter-autumn', title: 'Autumn Newsletter', org: 'Riverbank Community', sub: 'News, events and volunteer stories from the neighbourhood.', c1: '#15803d', c2: '#84cc16', pages: 4, tagged: true,
		kinds: ['This season', 'Volunteers', 'Events'] },
	{ file: 'foundations-of-design-notes', title: 'Course Notes', org: 'Foundations of Design', sub: 'Layout, colour and type, one lesson at a time.', c1: '#1d4ed8', c2: '#38bdf8', pages: 5, tagged: false,
		kinds: ['Layout', 'Colour', 'Type', 'Practice'] },
];

const PARA = [
	'Every piece in this collection is made in small batches by people we know by name. We keep our ranges short on purpose, so that each item can be tested, repaired and, when the time comes, recycled.',
	'Customers tell us that the details matter most: the way a drawer closes, how a fabric feels after a year, whether a spare part can still be ordered. We designed our process around those details.',
	'Throughout the year we publish clear, plain-language information about materials, delivery and care, so that you can make a confident choice and enjoy the result for many years to come.',
	'If something is not right, tell us. Our support team reads every message and replies within one working day, with a real answer and, where needed, a replacement on its way.',
	'We measure ourselves against three simple questions: is it useful, is it honest, and will it last? Anything that cannot answer yes to all three does not leave the workshop.',
	'Planning is shared openly. Each quarter we review what worked, what did not, and what we would change, and we write it down so that anyone joining the team can read the history.',
];

function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

function cover(d) {
	return `<section class="pg cover" style="background:linear-gradient(145deg,${d.c1},${d.c2})">
		<div class="orb o1"></div><div class="orb o2"></div>
		<div class="cv-top">${esc(d.org)}</div>
		<div class="cv-mid"><h1>${esc(d.title)}</h1><p>${esc(d.sub)}</p></div>
		<div class="cv-bot"><span>2026 edition</span><span>${d.pages} pages</span></div>
	</section>`;
}

function chart(d, i) {
	const bars = [42, 66, 54, 88, 72, 96].map((h, k) => `<i style="height:${Math.round(h * (0.7 + ((i + k) % 3) * 0.12))}%"></i>`).join('');
	return `<div class="fig" style="background:linear-gradient(135deg,${d.c1},${d.c2})"><div class="bars">${bars}</div><span>Figure ${i}: quarter by quarter</span></div>`;
}
function stats(d, i) {
	const v = [['94%', 'on-time delivery'], ['4.8', 'average rating'], ['12k', 'happy customers']];
	return `<div class="stats">${v.map((x) => `<div><b style="color:${d.c1}">${x[0]}</b><small>${x[1]}</small></div>`).join('')}</div>`;
}
function table(d) {
	const rows = [['Standard', '3 – 5 days', 'Free over 60'], ['Express', '1 – 2 days', '9.00'], ['Collection', 'Same day', 'Free']];
	return `<table><thead><tr><th>Option</th><th>Time</th><th>Cost</th></tr></thead><tbody>${rows.map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function inner(d, n) {
	const kind = d.kinds[(n - 2) % d.kinds.length];
	const p1 = PARA[(n * 2) % PARA.length], p2 = PARA[(n * 2 + 1) % PARA.length], p3 = PARA[(n * 2 + 3) % PARA.length];
	const fig = n % 3 === 0 ? stats(d, n) : n % 3 === 1 ? chart(d, n) : table(d);
	return `<section class="pg inner">
		<header><span style="color:${d.c1}">${esc(d.org)}</span><span>${esc(d.title)}</span></header>
		<div class="body">
			<div class="kicker" style="color:${d.c1}">Section ${n - 1}</div>
			<h2>${esc(kind)}</h2>
			<p class="lead">${esc(p1)}</p>
			${fig}
			<p>${esc(p2)}</p>
			<h3>What to expect</h3>
			<ul><li>Clear information before you decide.</li><li>Materials and methods explained in plain words.</li><li>Support that answers within one working day.</li></ul>
			<p>${esc(p3)}</p>
		</div>
		<footer><span>${esc(d.org)}</span><b>${n}</b></footer>
	</section>`;
}

function html(d) {
	const pages = [cover(d)];
	for (let n = 2; n <= d.pages; n++) { pages.push(inner(d, n)); }
	return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${esc(d.org)} — ${esc(d.title)}</title><style>
	@font-face{font-family:Outfit;src:url("${font}") format("woff2");font-weight:100 900}
	@page{size:210mm 297mm;margin:0}
	*{box-sizing:border-box;margin:0;padding:0}
	body{font-family:Outfit,"Segoe UI",sans-serif;color:#1f2347;-webkit-print-color-adjust:exact;print-color-adjust:exact}
	.pg{width:210mm;height:297mm;position:relative;overflow:hidden;page-break-after:always;break-after:page}
	.cover{color:#fff;padding:22mm 20mm;display:flex;flex-direction:column;justify-content:space-between}
	.orb{position:absolute;border-radius:50%;background:rgba(255,255,255,.14)}
	.o1{width:150mm;height:150mm;right:-50mm;top:-40mm}.o2{width:110mm;height:110mm;left:-40mm;bottom:-30mm;background:rgba(0,0,0,.12)}
	.cv-top{position:relative;font-size:16pt;letter-spacing:.18em;text-transform:uppercase;font-weight:600}
	.cv-mid{position:relative}.cv-mid h1{font-size:50pt;line-height:1.02;font-weight:700;letter-spacing:-.02em;margin-bottom:8mm}
	.cv-mid p{color:#fff;font-size:17pt;max-width:130mm;opacity:.92;line-height:1.35}
	.cv-bot{position:relative;display:flex;justify-content:space-between;font-size:13pt;opacity:.85}
	.inner{background:#fff;padding:16mm 20mm 14mm;display:flex;flex-direction:column}
	header{display:flex;justify-content:space-between;font-size:10pt;color:#8a8fb0;border-bottom:1px solid #e6e8f2;padding-bottom:4mm;font-weight:500}
	header span:first-child{font-weight:700;letter-spacing:.06em;text-transform:uppercase}
	.body{flex:1;padding-top:12mm}.kicker{font-size:11pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:3mm}
	h2{font-size:30pt;line-height:1.1;margin-bottom:6mm;font-weight:700;letter-spacing:-.01em}
	h3{font-size:15pt;margin:7mm 0 3mm}
	p{font-size:11.5pt;line-height:1.55;color:#3b4064;margin-bottom:5mm}.lead{font-size:13.5pt;color:#1f2347}
	ul{margin:0 0 5mm 5mm;font-size:11.5pt;line-height:1.7;color:#3b4064}
	.fig{height:62mm;border-radius:4mm;position:relative;color:#fff;margin:6mm 0;overflow:hidden}
	.bars{position:absolute;left:8mm;right:8mm;bottom:12mm;top:8mm;display:flex;align-items:flex-end;gap:5mm}
	.bars i{flex:1;background:rgba(255,255,255,.88);border-radius:2mm 2mm 0 0}.fig span{position:absolute;left:8mm;bottom:3.5mm;font-size:9.5pt;opacity:.9}
	.stats{display:flex;gap:6mm;margin:6mm 0}.stats div{flex:1;border:1px solid #e6e8f2;border-radius:3mm;padding:6mm;background:#f8f9fd}
	.stats b{display:block;font-size:26pt}.stats small{font-size:10pt;color:#6b7094}
	table{width:100%;border-collapse:collapse;margin:6mm 0;font-size:11pt}th,td{text-align:left;padding:3.2mm 4mm;border-bottom:1px solid #e6e8f2}th{background:#f1f2fb;font-size:9.5pt;letter-spacing:.08em;text-transform:uppercase;color:#5b6090}
	footer{display:flex;justify-content:space-between;font-size:9.5pt;color:#8a8fb0;border-top:1px solid #e6e8f2;padding-top:4mm}
	</style></head><body>${pages.join('')}</body></html>`;
}

(async () => {
	fs.mkdirSync(outDir, { recursive: true });
	const b = await launch();
	try {
		for (const d of DOCS) {
			const tmp = path.join(outDir, d.file + '.html');
			fs.writeFileSync(tmp, html(d));
			await b.goto('file:///' + tmp.replace(/\\/g, '/'), { wait: 500 });
			await b.pdf(path.join(outDir, d.file + '.pdf'), { generateTaggedPDF: !!d.tagged, paperWidth: 8.2677, paperHeight: 11.6929, marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0 });
			fs.unlinkSync(tmp);
			console.log('pdf', d.file, d.tagged ? '(tagged)' : '');
		}
	} finally {
		await b.close();
	}
})().catch((e) => { console.error(e); process.exit(1); });
