// Tests for bin/check-spec-coverage.mjs (benchmark gate 5). Run: npm run test:tools
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const script = join( dirname( fileURLToPath( import.meta.url ) ), '../../bin/check-spec-coverage.mjs' );

function run( files ) {
	const root = mkdtempSync( join( tmpdir(), 'tkpll-spec-' ) );
	for ( const [ path, content ] of Object.entries( files ) ) {
		mkdirSync( dirname( join( root, path ) ), { recursive: true } );
		writeFileSync( join( root, path ), content );
	}
	const result = spawnSync( process.execPath, [ script, '--json', '--root', root ], { encoding: 'utf8' } );
	return { code: result.status, ...JSON.parse( result.stdout ) };
}

const spec = ( status, lines ) => `# X\n\nStatus: ${ status }\n\n## Acceptance criteria\n${ lines.join( '\n' ) }\n`;
const phpTest = ( doc ) => `<?php\nclass T {\n\t/**\n${ doc.map( ( l ) => `\t * ${ l }` ).join( '\n' ) }\n\t */\n\tpublic function test_x(): void {}\n}\n`;

test( 'draft specs require nothing', () => {
	const r = run( { 'docs/specs/x.md': spec( 'draft', [ '- **XX-01** [seo] Something.' ] ) } );
	assert.equal( r.code, 0 );
} );

test( 'approved spec needs a citation; a pending one is enough', () => {
	const files = { 'docs/specs/x.md': spec( 'approved', [ '- **XX-01** Something.' ] ) };
	assert.equal( run( files ).code, 1 );
	files[ 'tests/phpunit/T.php' ] = phpTest( [ '@spec XX-01', '@group pending' ] );
	assert.equal( run( files ).code, 0 );
} );

test( 'implemented spec rejects pending tests', () => {
	const r = run( {
		'docs/specs/x.md': spec( 'implemented', [ '- **XX-01** Something.' ] ),
		'tests/phpunit/T.php': phpTest( [ '@spec XX-01', '@group pending' ] ),
	} );
	assert.equal( r.code, 1 );
	assert.deepEqual( r.missing, [ 'XX-01' ] );
} );

test( 'gate tag needs a citing test in that group', () => {
	const files = {
		'docs/specs/x.md': spec( 'implemented', [ '- **XX-01** [security] Something.' ] ),
		'tests/phpunit/T.php': phpTest( [ '@spec XX-01' ] ),
	};
	const bad = run( files );
	assert.equal( bad.code, 1 );
	assert.deepEqual( bad.untagged, [ 'XX-01[security]' ] );

	files[ 'tests/phpunit/T.php' ] = phpTest( [ '@spec XX-01', '@group security' ] );
	assert.equal( run( files ).code, 0 );
} );

test( 'a group on some other test does not satisfy the tag', () => {
	const r = run( {
		'docs/specs/x.md': spec( 'implemented', [ '- **XX-01** [seo] One.', '- **XX-02** Two.' ] ),
		'tests/phpunit/A.php': phpTest( [ '@spec XX-01' ] ),
		'tests/phpunit/B.php': phpTest( [ '@spec XX-02', '@group seo' ] ),
	} );
	assert.deepEqual( r.untagged, [ 'XX-01[seo]' ] );
} );

test( 'pending test in the group satisfies an approved spec but not an implemented one', () => {
	const files = {
		'docs/specs/x.md': spec( 'approved', [ '- **XX-01** [seo] One.' ] ),
		'tests/phpunit/A.php': phpTest( [ '@spec XX-01', '@group seo', '@group pending' ] ),
	};
	assert.equal( run( files ).code, 0 );
	files[ 'docs/specs/x.md' ] = spec( 'implemented', [ '- **XX-01** [seo] One.' ] );
	assert.equal( run( files ).code, 1 );
} );

test( 'Playwright title tags count as groups', () => {
	const files = {
		'docs/specs/x.md': spec( 'implemented', [ '- **XX-01** [seo] [performance] One.' ] ),
		'tests/e2e/a.spec.ts': "test( 'XX-01 @seo page has one hreflang set', async () => {} );\n",
	};
	assert.deepEqual( run( files ).untagged, [ 'XX-01[performance]' ] );
	files[ 'tests/e2e/a.spec.ts' ] = "test( 'XX-01 @seo @performance one set', async () => {} );\n";
	assert.equal( run( files ).code, 0 );
} );

test( 'unknown gate tag and unknown cited ID are errors', () => {
	const r = run( {
		'docs/specs/x.md': spec( 'draft', [ '- **XX-01** [speed] One.' ] ),
		'tests/phpunit/A.php': phpTest( [ '@spec YY-09' ] ),
	} );
	assert.equal( r.code, 1 );
	assert.deepEqual( r.unknown, [ 'YY-09' ] );
	assert.ok( r.errors.some( ( e ) => e.includes( 'unknown gate tag [speed]' ) ) );
} );
