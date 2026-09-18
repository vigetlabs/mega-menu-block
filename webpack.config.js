/**
 * Webpack Configuration
 *
 * Customizes @wordpress/scripts webpack config to output separate CSS files.
 *
 * @package
 */

const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,

	entry: {
		index: path.resolve( process.cwd(), 'src/js/index.js' ),
		editor: path.resolve( process.cwd(), 'src/css/editor.css' ),
		style: path.resolve( process.cwd(), 'src/css/style.css' ),
	},

	module: {
		...defaultConfig.module,
		rules: [
			// Add our raw SVG loader BEFORE the default rules so it takes precedence
			// This must come first to override WordPress scripts' default SVG handling
			{
				test: /\.svg$/,
				resourceQuery: /raw/,
				use: {
					loader: 'raw-loader',
					options: {
						esModule: false, // Use CommonJS format for better compatibility
					},
				},
				type: 'javascript/auto', // Override any default type
			},
			...defaultConfig.module.rules,
		],
	},

	// wp-scripts renames extracted CSS to "{cacheGroupKey}-{entry}.css", which turns the
	// `style` entry into `style-style.css` (and, since wp-scripts 30, `style-style-rtl.css`).
	// Keep the entry name so block.json's `file:./build/style.css` resolves and WordPress
	// finds the matching `-rtl.css` on its own.
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			...defaultConfig.optimization.splitChunks,
			cacheGroups: {
				...defaultConfig.optimization.splitChunks.cacheGroups,
				style: {
					...defaultConfig.optimization.splitChunks.cacheGroups.style,
					name: ( _, chunks ) => chunks[ 0 ].name,
				},
			},
		},
	},

	plugins: [
		...defaultConfig.plugins,
		new RemoveEmptyScriptsPlugin( {
			enabled: true,
			stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
		} ),
	],
};
