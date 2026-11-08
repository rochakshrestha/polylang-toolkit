import { request, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';

/**
 * Logs in once as the wp-env admin (admin / password) and saves the session to the path
 * playwright.config.ts uses as storageState.
 */
export default async function globalSetup( config: FullConfig ) {
	const baseURL = config.projects[ 0 ].use.baseURL ?? process.env.WP_BASE_URL ?? 'http://localhost:8888';
	mkdirSync( 'build/e2e', { recursive: true } );

	const context = await request.newContext( { baseURL } );
	const response = await context.post( '/wp-login.php', {
		form: {
			log: process.env.WP_USERNAME ?? 'admin',
			pwd: process.env.WP_PASSWORD ?? 'password',
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
			testcookie: '1',
		},
		headers: { Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
	} );
	if ( ! response.ok() ) {
		throw new Error( `WordPress login failed: HTTP ${ response.status() }. Is wp-env running? (npm run env:start:coverage)` );
	}
	const state = await context.storageState( { path: 'build/e2e/storage-state.json' } );
	if ( ! state.cookies.some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ) ) {
		throw new Error( 'WordPress login failed: no logged-in cookie. Check WP_USERNAME / WP_PASSWORD.' );
	}
	await context.dispose();
}
