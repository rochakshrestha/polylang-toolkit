import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const SETTINGS_URL = '/wp-admin/admin.php?page=tkpll';

test.describe( 'Toolkit settings page', () => {
	test( 'CORE-08 settings page lives under Languages and shows the disclaimer', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Toolkit for Polylang' } ) ).toBeVisible();
		await expect( page.getByText( 'not affiliated with or endorsed by WP SYNTEX' ) ).toBeVisible();
		await expect( page.getByRole( 'link', { name: 'Official Polylang documentation' } ) ).toHaveAttribute(
			'href',
			'https://polylang.pro/documentation/'
		);
		await expect( page.locator( '#adminmenu a[href$="page=tkpll"]' ) ).toHaveCount( 1 );
	} );

	test( 'CORE-09 settings page has no serious axe violations or console errors', async ( { page } ) => {
		const errors: string[] = [];
		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				errors.push( message.text() );
			}
		} );
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await page.goto( SETTINGS_URL );
		await expect( page.locator( '.tkpll-settings' ) ).toBeVisible();

		const results = await new AxeBuilder( { page } ).include( '.tkpll-settings' ).analyze();
		const serious = results.violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
		expect( serious, JSON.stringify( serious, null, 2 ) ).toEqual( [] );
		expect( errors ).toEqual( [] );
	} );
} );
