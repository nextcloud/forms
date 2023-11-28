const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

webpackConfig.entry.emptyContent = path.resolve(path.join('src', 'emptyContent.js'))
webpackConfig.entry.submit = path.resolve(path.join('src', 'submit.js'))
webpackConfig.entry.settings = path.resolve(path.join('src', 'settings.js'))

delete webpackRules.RULE_ASSETS

webpackConfig.module.rules = [
	{
		test: /\.svg$/i,
		use: 'raw-loader',
		resourceQuery: /raw/,
	},
	...Object.values(webpackRules),
]

module.exports = webpackConfig
