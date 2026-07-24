/**
 * Custom webpack config: bundle @wordpress/dataviews into our build, because
 * this site's WordPress/Gutenberg does not register a `wp-dataviews` script
 * handle.
 *
 * Strategy: bundle ONLY @wordpress/dataviews; everything it imports
 * (@wordpress/components, @wordpress/data, @wordpress/private-apis,
 * @wordpress/element, React, …) is externalized to the host's registered
 * script handles. Because components/data/private-apis resolve to the HOST's
 * instances, this build must run against a Gutenberg whose @wordpress/*
 * versions are compatible with the bundled dataviews (pinned to 17.2.0, which
 * matches the host's Gutenberg 23.6). This keeps the bundle small and avoids
 * pulling framer-motion and the broken @wordpress/ui/@wordpress/icons ESM into
 * the build.
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
