const { test, expect } = require( '@playwright/test' );

test.describe( 'Flipset viewer', () => {
	test( 'renders toolbar controls for an embedded PDF', async ( { page } ) => {
		await page.goto( '/flipset-e2e-viewer/' );
		await expect( page.locator( '.flipset-viewer' ) ).toBeVisible();
		await expect( page.locator( '.flipset-toolbar' ) ).toBeVisible();
		await expect( page.locator( '.flipset-rotate' ) ).toBeVisible();
		await expect( page.locator( '.flipset-fit' ) ).toBeVisible();
		await expect( page.locator( '.flipset-page-input' ) ).toBeVisible();
	} );
} );
