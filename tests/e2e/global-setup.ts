import { request, type FullConfig } from '@playwright/test';
import { existsSync, mkdirSync } from 'node:fs';
import { DEBUG_LOG, SITE_DEBUG_LOG, restoreBlockSite, wpCli, wpCliOk } from './wp-cli';

/**
 * Checks the site logs where the fixtures read, so "no new debug.log lines" cannot pass just
 * because nothing reads the right file. The seed tests/benchmark-seeds/gate-06-php-notice.patch
 * proves a notice in a page request really lands there. TKPLL_DEBUG_LOG=off skips this for a site
 * that is not wp-env.
 */
function checkDebugLog() {
	if ( process.env.TKPLL_DEBUG_LOG === 'off' ) {
		return;
	}
	if ( ! wpCliOk( 'eval', 'exit( WP_DEBUG ? 0 : 1 );' ) ) {
		throw new Error( 'WP_DEBUG is off on the development site, so PHP notices are not logged. Check .wp-env.json.' );
	}
	const configured = wpCli( 'config', 'get', 'WP_DEBUG_LOG' );
	if ( configured !== SITE_DEBUG_LOG || ! existsSync( DEBUG_LOG ) ) {
		throw new Error(
			`The site logs to "${ configured }" and ${ DEBUG_LOG } ${ existsSync( DEBUG_LOG ) ? 'exists' : 'is missing' }; ` +
				`expected ${ SITE_DEBUG_LOG } and the host file. Restart wp-env (bin/wp-env-after-start.sh prepares the file).`
		);
	}
}

/**
 * Logs in once as the wp-env admin (admin / password) and saves the session to the path
 * playwright.config.ts uses as storageState.
 */
export default async function globalSetup( config: FullConfig ) {
	checkDebugLog();
	// A run stopped between classic-setup and classic-teardown leaves the site in classic mode.
	restoreBlockSite();
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
