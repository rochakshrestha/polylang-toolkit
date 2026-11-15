import { test as teardown } from '@playwright/test';
import { restoreBlockSite } from './wp-cli';

teardown( 'restore the block editor and the block theme', () => {
	restoreBlockSite();
} );
