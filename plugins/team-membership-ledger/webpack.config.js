/**
 * Custom webpack config: bundle @wordpress/dataviews (and the @wordpress
 * packages it privately couples with) into our build, because this site's
 * WordPress/Gutenberg does not register a `wp-dataviews` script handle.
 *
 * Strategy: keep the stable, must-be-shared packages external (React via
 * wp-element, plus data-only utils), and bundle everything else under
 * @wordpress/* so DataViews carries its own matching @wordpress/components,
 * @wordpress/data, and @wordpress/private-apis — no host-version mismatch and
 * no cross-boundary private-apis lock.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve && defaultConfig.resolve.alias ),
			// @wordpress/icons@15.2.0 ships a broken `exports` field (its
			// build-module/index.mjs is missing from the published tarball),
			// which breaks ESM resolution when bundling. Force the working CJS
			// entry instead.
			'@wordpress/icons$': path.resolve(
				__dirname,
				'node_modules/@wordpress/icons/build/index.cjs'
			),
		},
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				// Bundle ONLY @wordpress/dataviews; everything else it imports
				// (@wordpress/components, data, element, private-apis, …) is
				// externalized to the host's registered script handles, which
				// avoids dragging framer-motion et al. into our build.
				if ( request === '@wordpress/dataviews' ) {
					return null; // bundle
				}
				if ( request.startsWith( '@wordpress/dataviews/' ) ) {
					return null; // bundle its stylesheet import too
				}
				return undefined; // default externalization for all others
			},
		} ),
	],
};
