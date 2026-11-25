/**
 * Write the eight-page demo booklet the WordPress Playground blueprint embeds:
 * docs/playground/flipset-demo.pdf. Plain vector shapes and the standard Helvetica
 * fonts, so the file is small, the text is searchable and nothing is borrowed.
 *
 * Usage: node bin/make-demo-pdf.js
 */
const fs = require( 'fs' );
const path = require( 'path' );

const OUT = path.resolve( __dirname, '../docs/playground/flipset-demo.pdf' );
const W = 595;
const H = 842;

const C = {
	ink: [ 0.11, 0.13, 0.16 ],
	soft: [ 0.38, 0.42, 0.47 ],
	teal: [ 0.05, 0.37, 0.4 ],
	tealDark: [ 0.03, 0.22, 0.25 ],
	mint: [ 0.62, 0.86, 0.8 ],
	sand: [ 0.98, 0.95, 0.89 ],
	coral: [ 0.94, 0.45, 0.36 ],
	gold: [ 0.96, 0.75, 0.3 ],
	white: [ 1, 1, 1 ],
	line: [ 0.86, 0.88, 0.9 ],
};

/** One page's drawing commands. */
class Page {
	constructor() {
		this.ops = [];
	}
	rgb( c, stroke ) {
		return `${ c.map( ( v ) => v.toFixed( 3 ) ).join( ' ' ) } ${ stroke ? 'RG' : 'rg' }`;
	}
	rect( x, y, w, h, color ) {
		this.ops.push( `${ this.rgb( color ) } ${ x } ${ y } ${ w } ${ h } re f` );
		return this;
	}
	round( x, y, w, h, r, color ) {
		const k = 0.5523 * r;
		this.ops.push(
			[
				this.rgb( color ),
				`${ x + r } ${ y } m`,
				`${ x + w - r } ${ y } l`,
				`${ x + w - r + k } ${ y } ${ x + w } ${ y + r - k } ${ x + w } ${ y + r } c`,
				`${ x + w } ${ y + h - r } l`,
				`${ x + w } ${ y + h - r + k } ${ x + w - r + k } ${ y + h } ${ x + w - r } ${ y + h } c`,
				`${ x + r } ${ y + h } l`,
				`${ x + r - k } ${ y + h } ${ x } ${ y + h - r + k } ${ x } ${ y + h - r } c`,
				`${ x } ${ y + r } l`,
				`${ x } ${ y + r - k } ${ x + r - k } ${ y } ${ x + r } ${ y } c`,
				'f',
			].join( '\n' )
		);
		return this;
	}
	circle( cx, cy, r, color ) {
		const k = 0.5523 * r;
		this.ops.push(
			[
				this.rgb( color ),
				`${ cx + r } ${ cy } m`,
				`${ cx + r } ${ cy + k } ${ cx + k } ${ cy + r } ${ cx } ${ cy + r } c`,
				`${ cx - k } ${ cy + r } ${ cx - r } ${ cy + k } ${ cx - r } ${ cy } c`,
				`${ cx - r } ${ cy - k } ${ cx - k } ${ cy - r } ${ cx } ${ cy - r } c`,
				`${ cx + k } ${ cy - r } ${ cx + r } ${ cy - k } ${ cx + r } ${ cy } c`,
				'f',
			].join( '\n' )
		);
		return this;
	}
	line( x1, y1, x2, y2, color, width ) {
		this.ops.push( `${ this.rgb( color, true ) } ${ width } w ${ x1 } ${ y1 } m ${ x2 } ${ y2 } l S` );
		return this;
	}
	text( x, y, str, size, color, bold ) {
		const safe = String( str ).replace( /[\\()]/g, ( ch ) => '\\' + ch );
		this.ops.push( `BT /${ bold ? 'F2' : 'F1' } ${ size } Tf ${ this.rgb( color ) } ${ x } ${ y } Td (${ safe }) Tj ET` );
		return this;
	}
	/** Left-aligned lines, top to bottom. */
	para( x, y, lines, size, color, lead ) {
		lines.forEach( ( l, i ) => this.text( x, y - i * ( lead || size * 1.45 ), l, size, color ) );
		return this;
	}
}

/** Page chrome shared by the inside pages: number, running title, accent bar. */
function inside( n, kicker, title, accent ) {
	const p = new Page();
	p.rect( 0, 0, W, H, C.sand );
	p.rect( 0, H - 12, W, 12, accent );
	p.text( 56, H - 92, kicker.toUpperCase(), 11, accent, true );
	p.text( 56, H - 140, title, 34, C.ink, true );
	p.line( 56, 70, W - 56, 70, C.line, 1 );
	p.text( 56, 48, 'Flipset demo booklet', 9, C.soft );
	p.text( W - 70, 48, String( n ), 9, C.soft, true );
	return p;
}

function miniBook( p, x, y, w, h, cover, accent ) {
	p.round( x + 4, y - 4, w, h, 6, [ 0.85, 0.83, 0.78 ] );
	p.round( x, y, w, h, 6, cover );
	p.rect( x + 12, y + h - 30, w - 24, 6, accent );
	p.rect( x + 12, y + h - 44, ( w - 24 ) * 0.6, 4, C.white );
	p.circle( x + w / 2, y + h * 0.38, w * 0.2, accent );
}

const pages = [];

// 1. Cover.
{
	const p = new Page();
	p.rect( 0, 0, W, H, C.tealDark );
	p.circle( 470, 690, 190, C.teal );
	p.circle( 120, 140, 120, C.teal );
	p.circle( 500, 250, 46, C.coral );
	p.circle( 430, 330, 18, C.gold );
	p.rect( 56, 560, 70, 8, C.mint );
	p.text( 56, 480, 'Flipset', 76, C.white, true );
	p.text( 56, 430, 'Turn any PDF into a flipbook', 22, C.mint );
	p.text( 56, 400, 'right inside WordPress.', 22, C.mint );
	p.text( 56, 90, 'DEMO BOOKLET', 11, C.white, true );
	p.text( 56, 72, 'Use the arrows, swipe or press the right arrow key to turn the page.', 10, C.mint );
	pages.push( p );
}

// 2. Turning pages.
{
	const p = inside( 2, 'Reading', 'Turn the page', C.coral );
	p.para( 56, H - 190, [
		'This booklet is a normal PDF file. Flipset shows it as a book:',
		'click the arrows, swipe on a phone or tablet, or use the arrow',
		'keys and Page Up / Page Down on a keyboard.',
	], 13, C.ink );
	// An open book with a page lifting.
	const bx = 110, by = 300, bw = 170, bh = 230;
	p.round( bx - 6, by - 8, bw * 2 + 12, bh + 16, 10, [ 0.88, 0.85, 0.79 ] );
	p.rect( bx, by, bw, bh, C.white ).rect( bx + bw, by, bw, bh, C.white );
	p.line( bx + bw, by, bx + bw, by + bh, C.line, 1.5 );
	for ( let i = 0; i < 7; i++ ) {
		p.rect( bx + 20, by + bh - 40 - i * 22, bw - 40 - ( i % 3 ) * 18, 6, C.line );
	}
	p.ops.push( `${ p.rgb( C.mint ) } ${ bx + bw } ${ by } m ${ bx + bw + 120 } ${ by + 40 } ${ bx + bw + 150 } ${ by + 120 } ${ bx + bw + 130 } ${ by + bh } c ${ bx + bw } ${ by + bh } l f` );
	p.circle( bx + bw * 2 - 30, by + 40, 14, C.coral );
	p.para( 56, 230, [
		'Pages are drawn only when they are needed, and the animation',
		'switches to a lighter style by itself on a slow device.',
	], 13, C.soft );
	pages.push( p );
}

// 3. Views.
{
	const p = inside( 3, 'Views', 'Four ways to read', C.teal );
	const views = [
		[ 'Flip', 'A book with page turns.' ],
		[ 'Spread', 'Two pages side by side.' ],
		[ 'Page', 'One page at a time.' ],
		[ 'Scroll', 'Every page in a column.' ],
	];
	views.forEach( ( [ name, note ], i ) => {
		const x = 56 + ( i % 2 ) * 250;
		const y = 470 - Math.floor( i / 2 ) * 230;
		p.round( x, y, 230, 190, 12, C.white );
		if ( 0 === i || 1 === i ) {
			p.rect( x + 40, y + 60, 70, 95, C.mint ).rect( x + 120, y + 60, 70, 95, C.mint );
		} else if ( 2 === i ) {
			p.rect( x + 80, y + 60, 70, 95, C.mint );
		} else {
			p.rect( x + 80, y + 120, 70, 50, C.mint ).rect( x + 80, y + 62, 70, 50, C.mint );
		}
		if ( 0 === i ) {
			p.ops.push( `${ p.rgb( C.teal ) } ${ x + 120 } ${ y + 60 } m ${ x + 170 } ${ y + 80 } ${ x + 180 } ${ y + 120 } ${ x + 160 } ${ y + 155 } c ${ x + 120 } ${ y + 155 } l f` );
		}
		p.text( x + 20, y + 32, name, 16, C.ink, true );
		p.text( x + 20, y + 14, note, 10, C.soft );
	} );
	p.para( 56, 190, [ 'Pick the view in the toolbar. Readers can switch at any time.' ], 13, C.ink );
	pages.push( p );
}

// 4. Search.
{
	const p = inside( 4, 'Search', 'Find any word', C.gold );
	p.para( 56, H - 190, [
		'The text of a PDF stays real text, so readers can search it and',
		'copy from it. Try it now: open search in the toolbar and look',
		'for the word lighthouse. It is hidden on the next page.',
	], 13, C.ink );
	p.round( 56, 470, W - 112, 54, 27, C.white );
	p.circle( 92, 497, 11, C.gold );
	p.circle( 92, 497, 6, C.white );
	p.line( 99, 489, 108, 480, C.gold, 3 );
	p.text( 124, 491, 'lighthouse', 16, C.ink );
	p.text( W - 150, 491, '1 of 3', 12, C.soft );
	p.para( 56, 400, [
		'Search engines can read it too: the text of each PDF can be',
		'added to the page for SEO, and the document gets its own',
		'structured data.',
	], 13, C.soft );
	pages.push( p );
}

// 5. Zoom.
{
	const p = inside( 5, 'Detail', 'Zoom in, go fullscreen', C.coral );
	p.para( 56, H - 190, [
		'Pinch on a phone, use the zoom buttons, or fit the page to the',
		'width of the screen. Fullscreen gives the document the whole',
		'display, and presentation mode hides everything else.',
	], 13, C.ink );
	p.circle( 300, 420, 120, C.white );
	p.circle( 300, 420, 104, C.mint );
	p.text( 236, 404, 'Aa', 56, C.tealDark, true );
	p.line( 385, 335, 450, 270, C.coral, 14 );
	p.text( 56, 200, 'A quiet line on page five: the lighthouse keeps its light on all night.', 11, C.soft );
	pages.push( p );
}

// 6. Library.
{
	const p = inside( 6, 'Library', 'All your PDFs on one page', C.teal );
	p.para( 56, H - 190, [
		'The library shows the PDFs from your Media Library as a grid of',
		'covers, with search and sorting. A click opens the flipbook.',
	], 13, C.ink );
	const covers = [ C.teal, C.coral, C.gold, C.tealDark, C.mint, C.coral ];
	covers.forEach( ( c, i ) => {
		const x = 70 + ( i % 3 ) * 160;
		const y = 390 - Math.floor( i / 3 ) * 200;
		miniBook( p, x, y, 120, 160, c, i % 2 ? C.white : C.sand );
	} );
	pages.push( p );
}

// 7. Speed.
{
	const p = inside( 7, 'Speed', 'Fast on every device', C.gold );
	const rows = [
		[ 'Page one first', 'Only the parts of the file the reader needs are downloaded.' ],
		[ 'Poster mode', 'Show a cover and an Open button; load the PDF on click.' ],
		[ 'Small files', 'Minified scripts, loaded only on pages that show a PDF.' ],
		[ 'Gentle on phones', 'Canvas size is capped so zooming never runs out of memory.' ],
	];
	rows.forEach( ( [ head, body ], i ) => {
		const y = 560 - i * 110;
		p.circle( 80, y + 14, 18, i % 2 ? C.teal : C.gold );
		p.text( 74, y + 9, String( i + 1 ), 14, C.white, true );
		p.text( 116, y + 18, head, 17, C.ink, true );
		p.text( 116, y - 4, body, 11.5, C.soft );
	} );
	pages.push( p );
}

// 8. Back cover.
{
	const p = new Page();
	p.rect( 0, 0, W, H, C.teal );
	p.circle( 300, 470, 150, C.tealDark );
	p.circle( 300, 470, 70, C.mint );
	p.circle( 300, 470, 30, C.tealDark );
	p.text( 190, 230, 'Made with Flipset', 26, C.white, true );
	p.text( 188, 200, 'wordpress.org/plugins/flipset', 14, C.mint );
	p.text( 56, 60, 'This booklet is part of the Flipset demo and may be shared freely.', 9, C.mint );
	pages.push( p );
}

// Assemble the file.
const objects = [];
const add = ( body ) => objects.push( body ) && objects.length;

const catalog = add( null );
const pagesObj = add( null );
const f1 = add( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>' );
const f2 = add( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>' );
const kids = pages.map( ( p ) => {
	const stream = p.ops.join( '\n' );
	const content = add( `<< /Length ${ Buffer.byteLength( stream, 'latin1' ) } >>\nstream\n${ stream }\nendstream` );
	return add(
		`<< /Type /Page /Parent ${ pagesObj } 0 R /MediaBox [0 0 ${ W } ${ H }] /Contents ${ content } 0 R /Resources << /Font << /F1 ${ f1 } 0 R /F2 ${ f2 } 0 R >> >> >>`
	);
} );
objects[ catalog - 1 ] = `<< /Type /Catalog /Pages ${ pagesObj } 0 R /PageLayout /TwoPageRight >>`;
objects[ pagesObj - 1 ] = `<< /Type /Pages /Kids [${ kids.map( ( k ) => `${ k } 0 R` ).join( ' ' ) }] /Count ${ kids.length } >>`;
const info = add( '<< /Title (Flipset demo booklet) /Author (Flipset) /Subject (A sample PDF for the Flipset WordPress plugin) /Producer (bin/make-demo-pdf.js) >>' );

let out = '%PDF-1.4\n%\xe2\xe3\xcf\xd3\n';
const offsets = [];
objects.forEach( ( body, i ) => {
	offsets.push( Buffer.byteLength( out, 'latin1' ) );
	out += `${ i + 1 } 0 obj\n${ body }\nendobj\n`;
} );
const xref = Buffer.byteLength( out, 'latin1' );
out += `xref\n0 ${ objects.length + 1 }\n0000000000 65535 f \n`;
out += offsets.map( ( o ) => `${ String( o ).padStart( 10, '0' ) } 00000 n \n` ).join( '' );
out += `trailer\n<< /Size ${ objects.length + 1 } /Root ${ catalog } 0 R /Info ${ info } 0 R >>\nstartxref\n${ xref }\n%%EOF\n`;

fs.mkdirSync( path.dirname( OUT ), { recursive: true } );
fs.writeFileSync( OUT, Buffer.from( out, 'latin1' ) );
console.log( `Wrote ${ path.relative( process.cwd(), OUT ) } (${ pages.length } pages, ${ Buffer.byteLength( out, 'latin1' ) } bytes)` );
