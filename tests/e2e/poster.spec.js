const { test, expect } = require( '@playwright/test' );

test.describe( 'Flipset poster mode', () => {
	test( 'loads PDF.js only after the poster is opened', async ( { page } ) => {
		const pdfjsRequests = [];
		page.on( 'request', ( request ) => {
			if ( /pdf(.worker)?.min.js/.test( request.url() ) ) {
				pdfjsRequests.push( request.url() );
			}
		} );

		await page.goto( '/flipset-e2e-poster/' );
		const open = page.locator( '.flipset-poster-open' );
		await expect( open ).toBeVisible();
		await expect( open ).toHaveAttribute( 'role', 'button' );
		await expect( open ).toHaveAccessibleName( 'Open document: Poster sample' );
		await expect( page.locator( '.flipset-toolbar' ) ).toBeHidden();
		expect( pdfjsRequests ).toHaveLength( 0 );

		await open.click();

		await expect( page.locator( '.flipset-poster' ) ).toHaveCount( 0 );
		await expect( page.locator( '.flipset-toolbar' ) ).toBeVisible();
		await expect( page.locator( '.flipset-viewer canvas' ).first() ).toBeVisible( { timeout: 20000 } );
		expect( pdfjsRequests.some( ( url ) => /pdf.min.js/.test( url ) ) ).toBe( true );
	} );
} );
