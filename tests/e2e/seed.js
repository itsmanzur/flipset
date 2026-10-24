/**
 * Seed the wp-env site with a sample PDF and E2E pages.
 * Runs on the host (wp-env afterStart / Playwright globalSetup).
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '../..' );

function shQuote( value ) {
	return `'${ String( value ).replace( /'/g, `'\\''` ) }'`;
}

function wp( args ) {
	const result = execSync( `npx wp-env run cli wp ${ args }`, {
		cwd: ROOT,
		encoding: 'utf8',
		stdio: [ 'pipe', 'pipe', 'pipe' ],
	} );
	const lines = String( result )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
	return lines[ lines.length - 1 ] || '';
}

function seed() {
	const pdf = '/var/www/html/wp-content/plugins/flipset/tests/fixtures/sample.pdf';
	const attachmentId = wp( `media import ${ pdf } --porcelain` );
	if ( ! /^\d+$/.test( attachmentId ) ) {
		throw new Error( `Could not import sample PDF (got: ${ attachmentId })` );
	}
	const url = wp( `post get ${ attachmentId } --field=guid` );

	function upsertPage( slug, title, content ) {
		const existing = wp( `post list --post_type=page --name=${ slug } --field=ID --format=ids` );
		const id = ( existing.match( /\d+/ ) || [] )[ 0 ];
		if ( id ) {
			wp( `post update ${ id } --post_content=${ shQuote( content ) } --post_status=publish` );
			return;
		}
		wp(
			`post create --post_type=page --post_status=publish --post_name=${ slug } --post_title=${ shQuote( title ) } --post_content=${ shQuote( content ) }`
		);
	}

	upsertPage( 'flipset-e2e-viewer', 'Flipset E2E Viewer', `[flipset file="${ url }"]` );
	upsertPage( 'flipset-e2e-library', 'Flipset E2E Library', '[flipset_library]' );
	wp( 'rewrite structure "/%postname%/" --hard' );
}

if ( require.main === module ) {
	seed();
}

module.exports = { seed };
