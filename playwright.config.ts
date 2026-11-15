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
	// Every spec runs twice: on the default block theme with the block editor, then on a classic
	// theme with the Classic Editor. The chain runs in order because both projects change one site:
	// block → classic-setup → classic → classic-teardown. If a block test fails, the classic run is skipped.
	projects: [
		{ name: 'block', testMatch: /.*\.spec\.ts$/, use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'classic-setup', testMatch: /classic\.setup\.ts$/, dependencies: [ 'block' ], teardown: 'classic-teardown' },
		{ name: 'classic-teardown', testMatch: /classic\.teardown\.ts$/ },
		{ name: 'classic', testMatch: /.*\.spec\.ts$/, dependencies: [ 'classic-setup' ], use: { ...devices[ 'Desktop Chrome' ] } },
	],
} );
