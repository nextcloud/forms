const { merge } = require('webpack-merge')
const path = require('path')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	entry: {
		submit: path.resolve(path.join('src', 'submit.js')),
	},
	plugins: [
		new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/),
	],
}

if (process.env.NODE_ENV === 'production') {
	module.exports = merge(config, webpackConfig.prod)
}
module.exports = merge(config, webpackConfig.dev)
