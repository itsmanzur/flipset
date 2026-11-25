/**
 * Lighthouse CI budget for the sample pages the E2E seed creates.
 *
 * CI runs it against wp-env: `npx lhci autorun --config=tests/lighthouse/lighthouserc.js`.
 * The build fails when the median run of any page goes past a budget below.
 * The numbers are the Core Web Vitals "good" limits, measured with the desktop preset,
 * which keeps runs on a shared CI machine steady enough to compare.
 *
 * Set LHCI_BASE_URL to measure another site, e.g. a local one.
 */
const base = ( process.env.LHCI_BASE_URL || 'http://localhost:8888' ).replace( /\/+$/, '' );

const median = { aggregationMethod: 'median-run' };

module.exports = {
	ci: {
		collect: {
			url: [
				`${ base }/flipset-e2e-viewer/`,
				`${ base }/flipset-e2e-poster/`,
				`${ base }/flipset-e2e-library/`,
			],
			numberOfRuns: 3,
			settings: {
				preset: 'desktop',
				onlyCategories: [ 'performance' ],
				chromeFlags: '--no-sandbox --headless=new',
			},
		},
		assert: {
			assertions: {
				'largest-contentful-paint': [ 'error', { ...median, maxNumericValue: 2500 } ],
				'total-blocking-time': [ 'error', { ...median, maxNumericValue: 200 } ],
				'cumulative-layout-shift': [ 'error', { ...median, maxNumericValue: 0.1 } ],
				// Shown in the report, but not a failure: theme and WordPress files count too.
				'total-byte-weight': [ 'warn', { ...median, maxNumericValue: 1600000 } ],
			},
		},
		upload: {
			// Keep reports on the runner; CI uploads them as a build artifact.
			target: 'filesystem',
			outputDir: '.lighthouseci/reports',
		},
	},
};
