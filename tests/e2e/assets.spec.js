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

	test( 'only a page with a viewer that loads straight away preloads PDF.js', async ( { page } ) => {
		const preload = 'head link[rel="preload"][as="script"][href*="pdf.min.js"]';
		const prefetch = 'head link[rel="prefetch"][href*="pdf.worker.min.js"]';

		await page.goto( '/flipset-e2e-viewer/' );
		await expect( page.locator( preload ) ).toHaveCount( 1 );
		await expect( page.locator( prefetch ) ).toHaveCount( 1 );

		await page.goto( '/flipset-e2e-poster/' );
		await expect( page.locator( preload ) ).toHaveCount( 0 );

		await page.goto( '/flipset-e2e-plain/' );
		await expect( page.locator( preload ) ).toHaveCount( 0 );
	} );
} );
