import { test as setup } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { CLASSIC_THEME, PREVIOUS_THEME_FILE, wpCli, wpCliOk } from './wp-cli';

setup( 'switch the site to the Classic Editor and a classic theme', () => {
	mkdirSync( 'build/e2e', { recursive: true } );
	writeFileSync( PREVIOUS_THEME_FILE, wpCli( 'theme', 'list', '--status=active', '--field=name' ) );

	if ( ! wpCliOk( 'plugin', 'is-installed', 'classic-editor' ) ) {
		wpCli( 'plugin', 'install', 'classic-editor' );
	}
	wpCli( 'plugin', 'activate', 'classic-editor' );

	if ( ! wpCliOk( 'theme', 'is-installed', CLASSIC_THEME ) ) {
		wpCli( 'theme', 'install', CLASSIC_THEME );
	}
	wpCli( 'theme', 'activate', CLASSIC_THEME );
} );
