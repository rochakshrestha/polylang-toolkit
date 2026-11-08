#!/usr/bin/env node
/**
 * Coverage gate (benchmark gate 4). Reads a Clover report and checks line coverage of src/.
 *
 * Usage: node bin/check-coverage.mjs [clover.xml] [--min 80] [--baseline <percent>]
 *   --baseline: fail if coverage drops more than 0.1 point below this value (CI passes main's value).
 */
import { readFileSync, existsSync } from 'node:fs';

const args = process.argv.slice( 2 );
const opt = ( name, fallback ) => {
	const i = args.indexOf( name );
	return i >= 0 ? args[ i + 1 ] : fallback;
};
const file = args[ 0 ] && ! args[ 0 ].startsWith( '--' ) ? args[ 0 ] : 'build/coverage/clover.xml';
const min = Number( opt( '--min', process.env.MIN_COVERAGE ?? '80' ) );
const baseline = opt( '--baseline', process.env.COVERAGE_BASELINE );

if ( ! existsSync( file ) ) {
	process.stderr.write( `✗ Coverage report not found: ${ file }\n` );
	process.exit( 1 );
}

const xml = readFileSync( file, 'utf8' );
let statements = 0;
let covered = 0;
// <file name="/var/www/html/wp-content/plugins/toolkit-for-polylang/src/Plugin.php"> … <metrics … statements="N" coveredstatements="M" …/>
const fileRe = /<file name="([^"]+)">([\s\S]*?)<\/file>/g;
for ( const m of xml.matchAll( fileRe ) ) {
	if ( ! /\/src\//.test( m[ 1 ] ) || /\/src\/autoload\.php$/.test( m[ 1 ] ) ) {
		continue;
	}
	const metrics = m[ 2 ].match( /<metrics[^>]*\sstatements="(\d+)"[^>]*\scoveredstatements="(\d+)"/ );
	if ( metrics ) {
		statements += Number( metrics[ 1 ] );
		covered += Number( metrics[ 2 ] );
	}
}

if ( statements === 0 ) {
	process.stderr.write( '✗ No src/ statements found in the coverage report.\n' );
	process.exit( 1 );
}

const pct = ( covered / statements ) * 100;
process.stdout.write( `Line coverage of src/: ${ pct.toFixed( 2 ) }% (${ covered }/${ statements }), minimum ${ min }%\n` );

let failed = false;
if ( pct < min ) {
	process.stderr.write( `✗ Coverage is below ${ min }%.\n` );
	failed = true;
}
if ( baseline !== undefined && pct < Number( baseline ) - 0.1 ) {
	process.stderr.write( `✗ Coverage dropped from ${ Number( baseline ).toFixed( 2 ) }% on main.\n` );
	failed = true;
}
process.exit( failed ? 1 : 0 );
