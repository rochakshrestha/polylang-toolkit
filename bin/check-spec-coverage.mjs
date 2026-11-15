#!/usr/bin/env node
/**
 * Spec traceability gate (benchmark gate 5).
 *
 * - `approved` spec: every acceptance ID is cited by at least one test (a pending test is enough).
 * - `implemented` spec: every acceptance ID is cited by at least one non-pending test, and no
 *   pending test may cite it.
 * - `draft` spec: nothing required.
 * - Every ID cited by a test must exist in some spec.
 * - An ID may be defined only once.
 * - Gate tags: a criterion may name benchmark gates in square brackets right after its ID
 *   (`[seo]` gate 7, `[coexistence]` gate 8, `[performance]` gate 11, `[security]` gate 12).
 *   For each tag, at least one test citing the ID must be in that group, so the gate can never
 *   pass with nothing in it. The same pending rules apply as above.
 *
 * Spec format (docs/specs/*.md; files starting with `_` are templates and are skipped):
 *   Status: draft | approved | implemented
 *   - **SLUG-03** [seo] Criterion text …
 *
 * Test citations:
 *   PHPUnit:    `@spec SLUG-03` in a test's docblock. The test is pending when the same docblock
 *               has `@group pending` (excluded from the PHPUnit run by phpunit.xml.dist). Its
 *               groups are the other `@group` lines of that docblock (`@group seo`).
 *   Playwright: a title starting with the ID: test( 'SLUG-03 …' ). `test.fixme(` / `test.skip(`
 *               mark it pending. Its groups are the `@tags` in the title
 *               (`test( 'SLUG-03 @seo …' )`). Comments do not count as citations.
 *
 * Usage: node bin/check-spec-coverage.mjs [--json] [--root <dir>]
 */
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';

const args = process.argv.slice( 2 );
const json = args.includes( '--json' );
const rootIndex = args.indexOf( '--root' );
const root = rootIndex >= 0 ? args[ rootIndex + 1 ] : process.cwd();

const ID = '[A-Z]{2,8}-\\d{2,3}';
const DEFINE_RE = new RegExp( `^\\s*[-*]\\s+\\*\\*(${ ID })\\*\\*((?:[ \\t]*\\[[^\\]\\n]*\\])*)`, 'gm' );
const GATE_TAGS = new Set( [ 'seo', 'coexistence', 'performance', 'security' ] );
const STATUS_RE = /^Status:\s*(draft|approved|implemented)\s*$/im;
const DOCBLOCK_RE = /\/\*\*([\s\S]*?)\*\//g;
const CITE_RE = new RegExp( `@spec\\s+((?:${ ID })(?:[\\s,]+${ ID })*)`, 'g' );
const PENDING_RE = /@group\s+pending\b/;
const GROUP_RE = /@group\s+([a-z0-9_-]+)/g;
const TITLE_RE = new RegExp( `\\btest(?:\\.(only|skip|fixme))?\\(\\s*(['"\`])(${ ID })\\b((?:(?!\\2).)*)\\2`, 'g' );
const TITLE_TAG_RE = /@([a-z0-9_-]+)/g;
const REQUIRED = new Set( [ 'approved', 'implemented' ] );

function walk( dir, exts ) {
	if ( ! existsSync( dir ) ) {
		return [];
	}
	const out = [];
	for ( const name of readdirSync( dir ) ) {
		if ( name === 'node_modules' || name === 'vendor' ) {
			continue;
		}
		const full = join( dir, name );
		if ( statSync( full ).isDirectory() ) {
			out.push( ...walk( full, exts ) );
		} else if ( exts.some( ( e ) => name.endsWith( e ) ) ) {
			out.push( full );
		}
	}
	return out;
}

const errors = [];
const defined = new Map(); // id -> { file, status, tags }

for ( const file of walk( join( root, 'docs/specs' ), [ '.md' ] ) ) {
	if ( file.split( /[\\/]/ ).pop().startsWith( '_' ) ) {
		continue; // Templates such as _template.md define nothing.
	}
	const text = readFileSync( file, 'utf8' );
	const statusMatch = text.match( STATUS_RE );
	const rel = relative( root, file );
	if ( ! statusMatch ) {
		errors.push( `${ rel }: missing "Status: draft|approved|implemented" line` );
		continue;
	}
	const status = statusMatch[ 1 ].toLowerCase();
	for ( const m of text.matchAll( DEFINE_RE ) ) {
		const id = m[ 1 ];
		if ( defined.has( id ) ) {
			errors.push( `${ id } is defined twice (${ defined.get( id ).file } and ${ rel })` );
			continue;
		}
		const tags = [ ...m[ 2 ].matchAll( /\[([^\]]*)\]/g ) ].map( ( t ) => t[ 1 ].trim() );
		for ( const tag of tags ) {
			if ( ! GATE_TAGS.has( tag ) ) {
				errors.push( `${ id } (${ rel }) has unknown gate tag [${ tag }]; allowed: ${ [ ...GATE_TAGS ].join( ', ' ) }` );
			}
		}
		defined.set( id, { file: rel, status, tags: tags.filter( ( t ) => GATE_TAGS.has( t ) ) } );
	}
}

const cited = new Map(); // id -> { real: Set(files), pending: Set(files), realGroups: Set, pendingGroups: Set }
const cite = ( id, rel, pending, groups ) => {
	if ( ! cited.has( id ) ) {
		cited.set( id, { real: new Set(), pending: new Set(), realGroups: new Set(), pendingGroups: new Set() } );
	}
	const entry = cited.get( id );
	entry[ pending ? 'pending' : 'real' ].add( rel );
	for ( const group of groups ) {
		entry[ pending ? 'pendingGroups' : 'realGroups' ].add( group );
	}
};

for ( const file of walk( join( root, 'tests/phpunit' ), [ '.php' ] ) ) {
	const text = readFileSync( file, 'utf8' );
	const rel = relative( root, file );
	for ( const block of text.matchAll( DOCBLOCK_RE ) ) {
		const pending = PENDING_RE.test( block[ 1 ] );
		const groups = [ ...block[ 1 ].matchAll( GROUP_RE ) ].map( ( g ) => g[ 1 ] );
		for ( const m of block[ 1 ].matchAll( CITE_RE ) ) {
			for ( const id of m[ 1 ].split( /[\s,]+/ ).filter( Boolean ) ) {
				cite( id, rel, pending, groups );
			}
		}
	}
}
for ( const file of walk( join( root, 'tests/e2e' ), [ '.ts', '.js' ] ) ) {
	const text = readFileSync( file, 'utf8' );
	const rel = relative( root, file );
	for ( const m of text.matchAll( TITLE_RE ) ) {
		const groups = [ ...m[ 4 ].matchAll( TITLE_TAG_RE ) ].map( ( g ) => g[ 1 ] );
		cite( m[ 3 ], rel, m[ 1 ] === 'skip' || m[ 1 ] === 'fixme', groups );
	}
}

const missing = [];
const untagged = [];
for ( const [ id, info ] of defined ) {
	const c = cited.get( id );
	if ( info.status === 'approved' && ! c ) {
		missing.push( id );
		errors.push( `${ id } (${ info.file }, approved) has no test citing it (a pending test is enough)` );
	}
	if ( info.status === 'implemented' ) {
		if ( ! c || c.real.size === 0 ) {
			missing.push( id );
			errors.push( `${ id } (${ info.file }, implemented) has no passing (non-pending) test citing it` );
		}
		if ( c && c.pending.size ) {
			errors.push( `${ id } is implemented but still cited by pending tests in ${ [ ...c.pending ].join( ', ' ) }` );
		}
	}
	if ( ! REQUIRED.has( info.status ) || ! c ) {
		continue;
	}
	for ( const tag of info.tags ) {
		const inGroup = c.realGroups.has( tag ) || ( info.status === 'approved' && c.pendingGroups.has( tag ) );
		if ( ! inGroup ) {
			untagged.push( `${ id }[${ tag }]` );
			errors.push(
				`${ id } (${ info.file }) is tagged [${ tag }] but no ${ info.status === 'implemented' ? 'non-pending ' : '' }test citing it is in that group (PHPUnit: "@group ${ tag }", Playwright: "@${ tag }" in the title)`
			);
		}
	}
}

const unknown = [];
for ( const [ id, files ] of cited ) {
	if ( ! defined.has( id ) ) {
		unknown.push( id );
		errors.push( `${ id } is cited in ${ [ ...files.real, ...files.pending ].join( ', ' ) } but not defined in docs/specs` );
	}
}

const requiredCount = [ ...defined.values() ].filter( ( i ) => REQUIRED.has( i.status ) ).length;
const summary = {
	defined: defined.size,
	required: requiredCount,
	covered: requiredCount - missing.length,
	missing,
	untagged,
	unknown,
	errors,
};

if ( json ) {
	process.stdout.write( JSON.stringify( summary, null, 2 ) + '\n' );
} else {
	for ( const e of errors ) {
		process.stderr.write( `✗ ${ e }\n` );
	}
	process.stdout.write(
		`Spec traceability: ${ summary.covered }/${ summary.required } required criteria covered, ${ defined.size } defined, ${ errors.length } error(s).\n`
	);
}

process.exit( errors.length ? 1 : 0 );
