/**
 * Build minified copies of Flipset's scripts and stylesheets.
 *
 * For every readable file in assets/js and assets/css, writes NAME.min.js (or .min.css)
 * and a source map NAME.min.js.map next to it. The readable files stay the source of
 * truth and ship in the release zip too, so the code on WordPress.org stays readable.
 * PHP picks the .min file when it exists (Flipset::asset_url()), or the readable one
 * when SCRIPT_DEBUG is on. The bundled PDF.js in assets/vendor is left alone.
 *
 * Usage: npm run build          Build every file.
 *        npm run build -- --clean   Remove the built files instead.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const esbuild = require( 'esbuild' );

const root = path.resolve( __dirname, '..' );
const dirs = [
	{ dir: path.join( root, 'assets', 'js' ), ext: '.js' },
	{ dir: path.join( root, 'assets', 'css' ), ext: '.css' },
];

// Browsers WordPress supports; esbuild never adds newer syntax than this.
const target = [ 'es2017', 'chrome80', 'firefox78', 'safari13', 'edge80' ];

function isBuilt( name ) {
	return /\.min\.(js|css)(\.map)?$/.test( name );
}

function sources() {
	const list = [];
	dirs.forEach( ( { dir, ext } ) => {
		fs.readdirSync( dir )
			.filter( ( name ) => name.endsWith( ext ) && ! isBuilt( name ) )
			.sort()
			.forEach( ( name ) => list.push( path.join( dir, name ) ) );
	} );
	return list;
}

function clean() {
	let removed = 0;
	dirs.forEach( ( { dir } ) => {
		fs.readdirSync( dir )
			.filter( isBuilt )
			.forEach( ( name ) => {
				fs.rmSync( path.join( dir, name ) );
				removed++;
			} );
	} );
	console.log( `Removed ${ removed } built files.` );
}

function build() {
	let before = 0;
	let after = 0;
	sources().forEach( ( src ) => {
		const ext = path.extname( src );
		const out = src.slice( 0, -ext.length ) + '.min' + ext;
		esbuild.buildSync( {
			entryPoints: [ src ],
			outfile: out,
			bundle: false,
			minify: true,
			sourcemap: 'linked',
			// The readable file sits next to the map, so the map only points at it.
			sourcesContent: false,
			legalComments: 'none',
			charset: 'utf8',
			target,
			logLevel: 'warning',
		} );
		const a = fs.statSync( src ).size;
		const b = fs.statSync( out ).size;
		before += a;
		after += b;
		console.log(
			`${ path.relative( root, out ).replace( /\\/g, '/' ) }  ${ ( a / 1024 ).toFixed( 1 ) } KB -> ${ ( b / 1024 ).toFixed( 1 ) } KB`
		);
	} );
	console.log( `Total ${ ( before / 1024 ).toFixed( 1 ) } KB -> ${ ( after / 1024 ).toFixed( 1 ) } KB` );
}

if ( process.argv.includes( '--clean' ) ) {
	clean();
} else {
	build();
}
