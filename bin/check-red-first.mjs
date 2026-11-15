#!/usr/bin/env node
/**
 * Red-first gate for pull requests.
 *
 * A tests PR adds tests marked pending, ahead of the code. Each of them must FAIL against the
 * product code of the base branch; a pending test that already passes there proves nothing and is
 * rejected. CI runs the pending group against the base branch's src/ and gives this script the
 * JUnit report.
 *
 * "New pending test" = a PHPUnit test method whose docblock has `@group pending` in HEAD but not at
 * the merge base with <base-ref>. Every data-provider case of such a test must fail or error.
 * Playwright `test.fixme(` titles are listed as not checked yet (they are not run by this gate).
 *
 * Usage:
 *   node bin/check-red-first.mjs list <base-ref> [--count] [--root <dir>]
 *   node bin/check-red-first.mjs verify <base-ref> <junit.xml> [--log <phpunit-output>] [--root <dir>]
 *
 * Writes a short report to $GITHUB_STEP_SUMMARY when that variable is set.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync, statSync, existsSync, appendFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const args = process.argv.slice( 2 );
const option = ( name ) => {
	const i = args.indexOf( name );
	return i >= 0 ? args[ i + 1 ] : undefined;
};
const root = option( '--root' ) ?? process.cwd();
const VALUE_OPTIONS = new Set( [ '--root', '--log' ] );
const positional = args.filter( ( a, i ) => ! a.startsWith( '--' ) && ! VALUE_OPTIONS.has( args[ i - 1 ] ) );
const [ command, baseRef, junitPath ] = positional;

const DOCBLOCK_METHOD_RE = /\/\*\*([\s\S]*?)\*\/\s*(?:#\[[^\]]*\]\s*)*(?:(?:public|protected|private|static|final)\s+)*function\s+(test\w*)\s*\(/g;
const PENDING_RE = /@group\s+pending\b/;
const FIXME_RE = /\btest\.fixme\(\s*(['"`])((?:(?!\1).)*)\1/g;

const CONTROL_HINT =
	'A test that passes without the feature proves nothing. If it checks that something does NOT happen, ' +
	'add a control assertion that only holds once the feature exists: assert the module is registered and booted ' +
	'(e.g. `Registry::builtin()` lists it, or `Plugin::instance()->registry()->booted()` includes it), or exercise the positive path ' +
	'in the same test first (an authorized user gets the copy, then the unauthorized one gets nothing). See AGENTS.md, "Red first".';

function git( ...gitArgs ) {
	return execFileSync( 'git', gitArgs, { cwd: root, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
}

function walk( dir, exts ) {
	if ( ! existsSync( dir ) ) {
		return [];
	}
	const out = [];
	for ( const name of readdirSync( dir ) ) {
		const full = join( dir, name );
		if ( statSync( full ).isDirectory() ) {
			out.push( ...walk( full, exts ) );
		} else if ( exts.some( ( e ) => name.endsWith( e ) ) ) {
			out.push( full );
		}
	}
	return out;
}

const toPosix = ( p ) => p.split( sep ).join( '/' );

function baseContent( mergeBase, rel ) {
	try {
		return git( 'show', `${ mergeBase }:${ rel }` );
	} catch {
		return '';
	}
}

function pendingMethods( text ) {
	const out = new Set();
	for ( const m of text.matchAll( DOCBLOCK_METHOD_RE ) ) {
		if ( PENDING_RE.test( m[ 1 ] ) ) {
			out.add( m[ 2 ] );
		}
	}
	return out;
}

function fixmeTitles( text ) {
	return new Set( [ ...text.matchAll( FIXME_RE ) ].map( ( m ) => m[ 2 ] ) );
}

/**
 * @return {{ php: {file: string, method: string}[], e2e: {file: string, title: string}[] }}
 */
function newPending( ref ) {
	const mergeBase = git( 'merge-base', ref, 'HEAD' ).trim();
	const php = [];
	for ( const file of walk( join( root, 'tests/phpunit' ), [ '.php' ] ) ) {
		const rel = toPosix( relative( root, file ) );
		const before = pendingMethods( baseContent( mergeBase, rel ) );
		for ( const method of pendingMethods( readFileSync( file, 'utf8' ) ) ) {
			if ( ! before.has( method ) ) {
				php.push( { file: rel, method } );
			}
		}
	}
	const e2e = [];
	for ( const file of walk( join( root, 'tests/e2e' ), [ '.ts', '.js' ] ) ) {
		const rel = toPosix( relative( root, file ) );
		const before = fixmeTitles( baseContent( mergeBase, rel ) );
		for ( const title of fixmeTitles( readFileSync( file, 'utf8' ) ) ) {
			if ( ! before.has( title ) ) {
				e2e.push( { file: rel, title } );
			}
		}
	}
	return { php, e2e };
}

const ENTITIES = { '&quot;': '"', '&apos;': "'", '&lt;': '<', '&gt;': '>', '&amp;': '&' };
const decode = ( v ) => v.replace( /&(quot|apos|lt|gt|amp);/g, ( e ) => ENTITIES[ e ] );
const attr = ( tag, name ) => decode( tag.match( new RegExp( `\\s${ name }="([^"]*)"` ) )?.[ 1 ] ?? '' );

/**
 * Parses PHPUnit's JUnit report into { file, name, outcome } where outcome is failed|passed|skipped.
 */
function parseJunit( xml ) {
	const cases = [];
	const re = /<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g;
	for ( const m of xml.matchAll( re ) ) {
		const body = m[ 3 ] ?? '';
		let outcome = 'passed';
		if ( /<(failure|error)\b/.test( body ) ) {
			outcome = 'failed';
		} else if ( /<skipped\b/.test( body ) ) {
			outcome = 'skipped';
		}
		cases.push( { file: toPosix( attr( m[ 1 ], 'file' ) ), name: attr( m[ 1 ], 'name' ), outcome } );
	}
	return cases;
}

function verify( ref, junitFile, logFile ) {
	const { php, e2e } = newPending( ref );
	const errors = [];
	let missing = false;
	const cases = existsSync( junitFile ) ? parseJunit( readFileSync( junitFile, 'utf8' ) ) : [];
	if ( php.length && ! existsSync( junitFile ) ) {
		errors.push( `JUnit report ${ junitFile } not found: the pending tests did not run.` );
		missing = true;
	}
	for ( const { file, method } of php ) {
		const mine = cases.filter(
			( c ) =>
				( c.file === file || c.file.endsWith( `/${ file }` ) ) &&
				( c.name === method || c.name.startsWith( `${ method } with data set` ) )
		);
		if ( ! mine.length ) {
			if ( existsSync( junitFile ) ) {
				errors.push( `${ file }::${ method } is missing from the report: it did not run (see the PHPUnit output below).` );
			}
			missing = true;
			continue;
		}
		for ( const c of mine ) {
			if ( c.outcome === 'passed' ) {
				errors.push( `${ file }::${ c.name } passes against the base branch's code.` );
			} else if ( c.outcome === 'skipped' ) {
				errors.push( `${ file }::${ c.name } was skipped or incomplete; a pending test must fail, not skip.` );
			}
		}
	}

	const lines = [ '### Red-first check', '' ];
	if ( ! php.length ) {
		lines.push( 'No new pending PHPUnit tests.' );
	} else if ( ! errors.length ) {
		lines.push( `All ${ php.length } new pending PHPUnit test(s) fail against the base branch, as they should.` );
	} else {
		lines.push( ...errors.map( ( e ) => `- ✗ ${ e }` ) );
		if ( errors.some( ( e ) => e.includes( 'passes against' ) ) ) {
			lines.push( '', CONTROL_HINT );
		}
	}
	if ( e2e.length ) {
		lines.push(
			'',
			`**Not checked yet:** ${ e2e.length } new pending Playwright test(s); reviewers must confirm they fail without the code:`,
			...e2e.map( ( t ) => `- \`${ t.file }\`: ${ t.title }` )
		);
	}
	const report = lines.join( '\n' ) + '\n';
	process.stdout.write( report );
	if ( missing && logFile && existsSync( logFile ) ) {
		const tail = readFileSync( logFile, 'utf8' ).split( '\n' ).slice( -60 ).join( '\n' );
		process.stdout.write( `\n--- Last lines of the PHPUnit output (${ logFile }) ---\n${ tail }\n` );
	}
	if ( process.env.GITHUB_STEP_SUMMARY ) {
		appendFileSync( process.env.GITHUB_STEP_SUMMARY, report );
	}
	return errors.length ? 1 : 0;
}

if ( ! baseRef || ! [ 'list', 'verify' ].includes( command ) || ( command === 'verify' && ! junitPath ) ) {
	process.stderr.write(
		'Usage: check-red-first.mjs list <base-ref> [--count] | verify <base-ref> <junit.xml> [--log <file>]\n'
	);
	process.exit( 2 );
}

if ( command === 'list' ) {
	const found = newPending( baseRef );
	process.stdout.write( args.includes( '--count' ) ? `${ found.php.length }\n` : JSON.stringify( found, null, 2 ) + '\n' );
	process.exit( 0 );
}
process.exit( verify( baseRef, junitPath, option( '--log' ) ) );
