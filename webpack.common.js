const path = require('path')
const webpack = require('webpack')

const StyleLintPlugin = require('stylelint-webpack-plugin')
const VueLoaderPlugin = require('vue-loader/lib/plugin')

const appName = process.env.npm_package_name.toString()
const appVersion = process.env.npm_package_version.toString()
console.info('Building', appName, appVersion, '\n')

module.exports = {
	entry: {
		forms: path.resolve(path.join('src', 'main.js')),
		submit: path.resolve(path.join('src', 'submit.js')),
	},
	output: {
		path: path.resolve('./js'),
		publicPath: '/js/',
		filename: `[name].js`,
		chunkFilename: `${appName}.[name].js?v=[contenthash]`,
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader'],
			},
			{
				test: /\.(js|vue)$/,
				use: 'eslint-loader',
				exclude: /node_modules/,
				enforce: 'pre',
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
				exclude: /node_modules/,
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/,
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new StyleLintPlugin(),
		// Make appName & appVersion available as a constant
		new webpack.DefinePlugin({ appName }),
		new webpack.DefinePlugin({ appVersion }),
	],
	resolve: {
		extensions: ['*', '.js', '.vue'],
		symlinks: false,
	},
}
