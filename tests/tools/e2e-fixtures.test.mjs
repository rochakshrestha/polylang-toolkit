// Every Playwright spec must use the guarded `test` from tests/e2e/fixtures.ts, which fails a test on
// browser console errors and new PHP debug.log lines. A spec importing '@playwright/test' directly
// would silently skip both checks.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const dir = new URL( '../e2e/', import.meta.url ).pathname;

test( 'every e2e spec imports test from ./fixtures, not @playwright/test', () => {
	const specs = readdirSync( dir ).filter( ( f ) => f.endsWith( '.spec.ts' ) );
	assert.ok( specs.length > 0, 'no e2e specs found' );
	const offenders = specs.filter( ( f ) => {
		const src = readFileSync( join( dir, f ), 'utf8' );
		return /from\s+['"]@playwright\/test['"]/.test( src ) || ! /from\s+['"]\.\/fixtures['"]/.test( src );
	} );
	assert.deepEqual( offenders, [] );
} );
