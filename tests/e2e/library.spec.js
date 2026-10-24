const { test, expect } = require( '@playwright/test' );

test.describe( 'Flipset library', () => {
	test( 'shows a grid of Media Library PDFs', async ( { page } ) => {
		await page.goto( '/flipset-e2e-library/' );
		await expect( page.locator( '.flipset-library' ) ).toBeVisible();
		await expect( page.locator( '.flipset-library-item' ).first() ).toBeVisible();
		await expect( page.locator( '.flipset-library-search' ) ).toBeVisible();
	} );
} );
