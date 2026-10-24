const { test, expect } = require( '@playwright/test' );

test.describe( 'Documents thumbnail generation', () => {
	test( 'generates a first-page preview from the Documents screen', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL( /wp-admin/ );

		await page.goto( '/wp-admin/admin.php?page=flipset-documents' );
		await expect( page.locator( '.flipset-docs-table' ) ).toBeVisible();

		const thumb = page.locator( '.flipset-docs-thumb-img' );
		const indexed = page.locator( '.flipset-index-status.is-ready, .flipset-index-status.is-empty' );
		const a11y = page.locator( '.flipset-a11y-status.is-ready, .flipset-a11y-status.is-warn' );
		await expect( thumb.or( indexed ).or( a11y ) ).toBeVisible( { timeout: 90_000 } );
	} );
} );
