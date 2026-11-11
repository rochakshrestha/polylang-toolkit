// Tests for bin/check-red-first.mjs (red-first gate). Run: npm run test:tools
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const script = join( dirname( fileURLToPath( import.meta.url ) ), '../../bin/check-red-first.mjs' );

const method = ( name, pending ) =>
	`\t/**\n\t * @spec XX-01\n${ pending ? '\t * @group pending\n' : '' }\t */\n\tpublic function ${ name }(): void {}\n`;
const phpClass = ( ...methods ) => `<?php\nclass DupTest {\n${ methods.join( '\n' ) }}\n`;

/** Creates a repo whose `main` has `base` files and whose HEAD adds `head` files. */
function repo( base, head ) {
	const root = mkdtempSync( join( tmpdir(), 'tkpll-red-' ) );
	const g = ( ...a ) => execFileSync( 'git', a, { cwd: root, stdio: 'ignore' } );
	const write = ( files ) => {
		for ( const [ path, content ] of Object.entries( files ) ) {
			mkdirSync( dirname( join( root, path ) ), { recursive: true } );
			writeFileSync( join( root, path ), content );
		}
	};
	g( 'init', '-q', '-b', 'main' );
	g( 'config', 'user.email', 't@example.com' );
	g( 'config', 'user.name', 't' );
	write( { 'README': 'x', ...base } );
	g( 'add', '-A' );
	g( 'commit', '-qm', 'base' );
	g( 'checkout', '-qb', 'pr' );
	write( head );
	g( 'add', '-A' );
	g( 'commit', '-qm', 'head' );
	return root;
}

const run = ( root, ...a ) => {
	const r = spawnSync( process.execPath, [ script, ...a, '--root', root ], {
		encoding: 'utf8',
		env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
	} );
	return { code: r.status, out: r.stdout + r.stderr };
};

const FILE = 'tests/phpunit/Integration/DupTest.php';
const junit = ( ...cases ) =>
	`<?xml version="1.0"?>\n<testsuites><testsuite name="all">${ cases.join( '' ) }</testsuite></testsuites>\n`;
const tc = ( name, child = '' ) =>
	child
		? `<testcase name="${ name }" class="DupTest" file="/var/www/html/wp-content/plugins/toolkit-for-polylang/${ FILE }">${ child }</testcase>`
		: `<testcase name="${ name }" class="DupTest" file="/var/www/html/wp-content/plugins/toolkit-for-polylang/${ FILE }"/>`;
const FAIL = '<failure type="x">Failed asserting</failure>';

function prRepo( extraHead = {} ) {
	return repo(
		{ [ FILE ]: phpClass( method( 'test_old_pending', true ), method( 'test_real', false ) ) },
		{
			[ FILE ]: phpClass(
				method( 'test_old_pending', true ),
				method( 'test_real', false ),
				method( 'test_new_a', true ),
				method( 'test_new_b', true )
			),
			...extraHead,
		}
	);
}

test( 'list finds only pending tests added since the merge base', () => {
	const root = prRepo( { 'tests/e2e/dup.spec.ts': "test.fixme( 'DUP-01 block editor', async () => {} );\n" } );
	const found = JSON.parse( run( root, 'list', 'main' ).out );
	assert.deepEqual( found.php.map( ( t ) => t.method ), [ 'test_new_a', 'test_new_b' ] );
	assert.deepEqual( found.e2e.map( ( t ) => t.title ), [ 'DUP-01 block editor' ] );
	assert.equal( run( root, 'list', 'main', '--count' ).out.trim(), '2' );
} );

test( 'verify passes when every new pending test fails or errors', () => {
	const root = prRepo( {
		'build/j.xml': junit( tc( 'test_old_pending' ), tc( 'test_new_a', FAIL ), tc( 'test_new_b', '<error type="Error">Class not found</error>' ) ),
	} );
	const r = run( root, 'verify', 'main', join( root, 'build/j.xml' ) );
	assert.equal( r.code, 0, r.out );
} );

test( 'verify rejects a new pending test that passes, and explains control assertions', () => {
	const root = prRepo( { 'build/j.xml': junit( tc( 'test_new_a', FAIL ), tc( 'test_new_b' ) ) } );
	const r = run( root, 'verify', 'main', join( root, 'build/j.xml' ) );
	assert.equal( r.code, 1 );
	assert.match( r.out, /test_new_b passes against the base branch/ );
	assert.match( r.out, /control assertion/ );
} );

test( 'verify rejects a skipped test and a missing test, and prints the PHPUnit output tail', () => {
	const root = prRepo( {
		'build/j.xml': junit( tc( 'test_new_a', '<skipped/>' ) ),
		'build/out.log': 'PHP Fatal error: Class "Dup\\Module" not found in DupProvider.php\n',
	} );
	const r = run( root, 'verify', 'main', join( root, 'build/j.xml' ), '--log', join( root, 'build/out.log' ) );
	assert.equal( r.code, 1 );
	assert.match( r.out, /test_new_a was skipped/ );
	assert.match( r.out, /test_new_b is missing from the report/ );
	assert.match( r.out, /Class "Dup\\Module" not found/ );
} );

test( 'an empty report or no report fails when there are new pending tests', () => {
	const root = prRepo( { 'build/empty.xml': junit() } );
	assert.equal( run( root, 'verify', 'main', join( root, 'build/empty.xml' ) ).code, 1 );
	const r = run( root, 'verify', 'main', join( root, 'build/none.xml' ) );
	assert.equal( r.code, 1 );
	assert.match( r.out, /not found/ );
} );

test( 'every data-provider case must fail', () => {
	const root = prRepo( {
		'build/j.xml': junit(
			tc( 'test_new_a with data set &quot;image&quot;', FAIL ),
			tc( 'test_new_a with data set &quot;untouched&quot;' ),
			tc( 'test_new_b', FAIL )
		),
	} );
	const r = run( root, 'verify', 'main', join( root, 'build/j.xml' ) );
	assert.equal( r.code, 1 );
	assert.match( r.out, /data set "untouched" passes/ );
} );

test( 'no new pending tests: verify passes without a report and lists Playwright as not checked', () => {
	const root = repo( { [ FILE ]: phpClass( method( 'test_real', false ) ) }, { 'tests/e2e/a.spec.ts': "test.fixme( 'DUP-01 x', async () => {} );\n" } );
	const r = run( root, 'verify', 'main', join( root, 'build/none.xml' ) );
	assert.equal( r.code, 0, r.out );
	assert.match( r.out, /Not checked yet:\*\* 1 new pending Playwright/ );
} );
