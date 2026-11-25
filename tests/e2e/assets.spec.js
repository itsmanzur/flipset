const { test, expect } = require( '@playwright/test' );

/**
 * Collect every request for a file inside the Flipset plugin folder.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {string[]} Requested URLs, filled while the page loads.
 */
function watchFlipsetRequests( page ) {
	const urls = [];
	page.on( 'request', ( request ) => {
		if ( request.url().includes( '/wp-content/plugins/flipset/' ) ) {
			urls.push( request.url() );
		}
	} );
	return urls;
}

test.describe( 'Flipset assets', () => {
	test( 'a page without a viewer loads no Flipset files', async ( { page } ) => {
		const urls = watchFlipsetRequests( page );
		await page.goto( '/flipset-e2e-plain/' );
		await page.waitForLoadState( 'networkidle' );
		expect( urls ).toEqual( [] );
	} );

	test( 'a page with a viewer loads the viewer files', async ( { page } ) => {
		const urls = watchFlipsetRequests( page );
		await page.goto( '/flipset-e2e-viewer/' );
		await expect( page.locator( '.flipset-viewer' ) ).toBeVisible();
		expect( urls.some( ( url ) => /flipset-viewer\.js/.test( url ) ) ).toBe( true );
		expect( urls.some( ( url ) => /flipset-library\.(css|js)/.test( url ) ) ).toBe( false );
	} );
} );
