const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

// Add entry points
// Note: @nextcloud/webpack-vue-config automatically prefixes with app name
// So 'main' becomes 'files_archive-main.js'
webpackConfig.entry = {
	'main': './src/main.js',
	'navigation': './src/filesNavigation.js',
	'archive': './src/archive.js',
	'archiveLink': './src/filesArchiveLink.js',
}

// Ensure output directory is explicitly set to js/
webpackConfig.output = {
	...webpackConfig.output,
	path: path.resolve(__dirname, 'js'),
	publicPath: '/apps/files_archive/js/',
}

module.exports = webpackConfig
