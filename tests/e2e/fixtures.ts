import { test as base, expect } from '@playwright/test';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { DEBUG_LOG } from './wp-cli';

/**
 * Every spec imports `test` and `expect` from here, not from '@playwright/test' (enforced by
 * tests/tools/e2e-fixtures.test.mjs). An automatic fixture fails any test during which
 *   - the browser logged a console error or threw an uncaught page error, or
 *   - the site wrote a new line to its PHP debug log (a notice, warning, deprecation or fatal).
 *
 * A test that expects a console error opts out per pattern:
 *   test.use( { allowedConsoleErrors: [ /Failed to load resource/ ] } );
 * There is no opt-out for the debug log: fix the notice.
 */

function size( file: string ): number {
	return existsSync( file ) ? statSync( file ).size : 0;
}

/** Lines appended to the log since `offset` (from the start if the log was truncated). */
function newLines( file: string, offset: number ): string[] {
	if ( ! existsSync( file ) ) {
		return [];
	}
	const buffer = readFileSync( file );
	const from = buffer.length < offset ? 0 : offset;
	return buffer
		.subarray( from )
		.toString( 'utf8' )
		.split( '\n' )
		.map( ( l ) => l.trim() )
		.filter( Boolean );
}

type Options = { allowedConsoleErrors: RegExp[] };

export const test = base.extend< Options & { tkpllGuards: void } >( {
	allowedConsoleErrors: [ [], { option: true } ],

	tkpllGuards: [
		async ( { page, allowedConsoleErrors }, use ) => {
			const errors: string[] = [];
			page.on( 'console', ( message ) => {
				if ( message.type() === 'error' ) {
					errors.push( message.text() );
				}
			} );
			page.on( 'pageerror', ( error ) => errors.push( error.message ) );
			const offset = size( DEBUG_LOG );

			await use();

			const problems: string[] = [];
			const unexpected = errors.filter( ( e ) => ! allowedConsoleErrors.some( ( re ) => re.test( e ) ) );
			if ( unexpected.length ) {
				problems.push( `tkpll-fixture: browser console errors: ${ unexpected.join( ' | ' ) }` );
			}
			const logged = newLines( DEBUG_LOG, offset );
			if ( logged.length ) {
				problems.push( `tkpll-fixture: new debug.log lines (${ DEBUG_LOG }): ${ logged.join( ' | ' ) }` );
			}
			expect( problems, problems.join( '\n' ) ).toEqual( [] );
		},
		{ auto: true },
	],
} );

export { expect };
