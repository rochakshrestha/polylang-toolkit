#!/usr/bin/env node
/**
 * Protected-path gate for pull requests.
 *
 * A PR that changes protected files (specs, tests, tool configs, CI, bin/) AND product code
 * fails unless it carries the `spec-change` or `bootstrap` label. This stops an agent from
 * editing a test or spec to make its own code pass.
 *
 * Usage: node bin/check-protected-paths.mjs <base-ref>
 * Env:   PR_LABELS="label1,label2"  (CI passes the PR labels)
 */
import { execFileSync } from 'node:child_process';

const PROTECTED = [
	'docs/specs/',
	'docs/polylang-api.md',
	'tests/',
	'bin/',
	'.github/',
	'phpstan.neon',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
	'playwright.config.ts',
	'AGENTS.md',
];
const PRODUCT = [ 'src/', 'toolkit-for-polylang.php', 'uninstall.php', 'assets/', 'languages/' ];
const BYPASS_LABELS = [ 'spec-change', 'bootstrap' ];

const base = process.argv[ 2 ];
if ( ! base ) {
	process.stderr.write( 'Usage: check-protected-paths.mjs <base-ref>\n' );
	process.exit( 2 );
}

const files = execFileSync( 'git', [ 'diff', '--name-only', `${ base }...HEAD` ], { encoding: 'utf8' } )
	.split( '\n' )
	.filter( Boolean );

const matches = ( file, list ) => list.some( ( p ) => ( p.endsWith( '/' ) ? file.startsWith( p ) : file === p ) );
const protectedChanged = files.filter( ( f ) => matches( f, PROTECTED ) );
const productChanged = files.filter( ( f ) => matches( f, PRODUCT ) );
const labels = ( process.env.PR_LABELS ?? '' ).split( ',' ).map( ( l ) => l.trim() ).filter( Boolean );
const bypass = labels.find( ( l ) => BYPASS_LABELS.includes( l ) );

if ( protectedChanged.length ) {
	process.stdout.write( `Protected files changed:\n  ${ protectedChanged.join( '\n  ' ) }\n` );
}

/**
 * Whether a protected file only got the changes a code PR is allowed to make:
 * - tests/: deleting ` * @group pending` lines, or turning `test.fixme(` / `test.skip(` into `test(`;
 * - docs/specs/: changing `Status: approved` to `Status: implemented`.
 *
 * @param {string} file Changed path.
 * @return {boolean} True when nothing else changed.
 */
function onlyAllowedCodePrChanges( file ) {
	const isTest = file.startsWith( 'tests/' );
	const isSpec = file.startsWith( 'docs/specs/' );
	if ( ! isTest && ! isSpec ) {
		return false;
	}
	let diff;
	try {
		diff = execFileSync( 'git', [ 'diff', '-U0', `${ base }...HEAD`, '--', file ], { encoding: 'utf8' } );
	} catch {
		return false;
	}
	if ( /^new file mode|^deleted file mode/m.test( diff ) ) {
		return false;
	}
	const lines = diff.split( '\n' );
	const removed = lines.filter( ( l ) => l.startsWith( '-' ) && ! l.startsWith( '---' ) ).map( ( l ) => l.slice( 1 ) );
	const added = lines.filter( ( l ) => l.startsWith( '+' ) && ! l.startsWith( '+++' ) ).map( ( l ) => l.slice( 1 ) );
	const expected = [];
	if ( isSpec ) {
		return removed.length === 1 && added.length === 1 && /^Status:\s*approved\s*$/.test( removed[ 0 ] ) && /^Status:\s*implemented\s*$/.test( added[ 0 ] );
	}
	for ( const line of removed ) {
		if ( /^\s*\*\s*@group\s+pending\s*$/.test( line ) ) {
			continue;
		}
		const unmarked = line.replace( /\btest\.(?:fixme|skip)\(/, 'test(' );
		if ( unmarked === line ) {
			return false;
		}
		expected.push( unmarked );
	}
	return expected.length === added.length && expected.every( ( l, i ) => l === added[ i ] );
}

const realProtected = protectedChanged.filter( ( f ) => ! onlyAllowedCodePrChanges( f ) );
if ( realProtected.length !== protectedChanged.length ) {
	process.stdout.write( 'Some protected files only had pending markers removed or a spec marked implemented; that is allowed in a code PR.\n' );
}

if ( realProtected.length && productChanged.length && ! bypass ) {
	process.stderr.write(
		'✗ This PR changes protected files and product code together.\n' +
			'  Split it: specs/tests in one PR, implementation in another.\n' +
			`  A maintainer can add one of these labels to allow it: ${ BYPASS_LABELS.join( ', ' ) }\n`
	);
	process.exit( 1 );
}

process.stdout.write( bypass ? `Protected-path check bypassed by label "${ bypass }".\n` : 'Protected-path check passed.\n' );
