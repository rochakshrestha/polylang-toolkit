import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';

/** Host path of the development site's debug log; see .wp-env.json and bin/wp-env-after-start.sh. */
export const DEBUG_LOG =
	process.env.TKPLL_DEBUG_LOG && process.env.TKPLL_DEBUG_LOG !== 'off' ? process.env.TKPLL_DEBUG_LOG : 'build/wp-debug/debug.log';

/** The same file as the site sees it (WP_DEBUG_LOG of the development environment). */
export const SITE_DEBUG_LOG = '/var/www/html/wp-content/plugins/toolkit-for-polylang/build/wp-debug/debug.log';

/** Written by classic.setup.ts so classic.teardown.ts can restore the block theme. */
export const PREVIOUS_THEME_FILE = 'build/e2e/previous-theme';

/**
 * Runs WP-CLI on the wp-env development site and returns the last non-empty line of its output
 * (wp-env adds its own status lines around the command's output).
 */
export function wpCli( ...args: string[] ): string {
	const out = execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
	return out.split( '\n' ).map( ( l ) => l.trim() ).filter( Boolean ).pop() ?? '';
}

/** Like wpCli(), but returns false instead of throwing when the command exits non-zero. */
export function wpCliOk( ...args: string[] ): boolean {
	try {
		wpCli( ...args );
		return true;
	} catch {
		return false;
	}
}

/** The classic project's theme; never recorded as the theme to restore. */
export const CLASSIC_THEME = 'twentytwentyone';
export const BLOCK_THEME = 'twentytwentyfive';

/**
 * Puts the site back in block mode: Classic Editor off, and the theme from before classic-setup
 * (the block default when none was recorded or the classic theme is still active).
 */
export function restoreBlockSite(): void {
	if ( wpCliOk( 'plugin', 'is-active', 'classic-editor' ) ) {
		wpCli( 'plugin', 'deactivate', 'classic-editor' );
	}
	const recorded = existsSync( PREVIOUS_THEME_FILE ) ? readFileSync( PREVIOUS_THEME_FILE, 'utf8' ).trim() : '';
	const active = wpCli( 'theme', 'list', '--status=active', '--field=name' );
	if ( active === CLASSIC_THEME || ( recorded && recorded !== active ) ) {
		wpCli( 'theme', 'activate', /^[a-z0-9-]+$/.test( recorded ) && recorded !== CLASSIC_THEME ? recorded : BLOCK_THEME );
	}
}
