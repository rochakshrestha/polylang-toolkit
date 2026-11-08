// Protected file: changes need the spec-change label.
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8888';

export default defineConfig( {
	testDir: './tests/e2e',
	globalSetup: './tests/e2e/global-setup.ts',
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL,
		storageState: 'build/e2e/storage-state.json',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
