/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const browserslistConfig = require('@nextcloud/browserslist-config')
const { RsdoctorRspackPlugin } = require('@rsdoctor/rspack-plugin')
const { defineConfig } = require('@rspack/cli')
const { DefinePlugin, LightningCssMinimizerRspackPlugin, ProgressPlugin, SwcJsMinimizerRspackPlugin } = require('@rspack/core')
const browserslist = require('browserslist')
const path = require('node:path')
const { VueLoaderPlugin } = require('vue-loader')

// browserslist-rs does not support baseline queries yet
// Manually resolving the browserslist config to the list of browsers with minimal versions
// See: https://github.com/browserslist/browserslist-rs/issues/40
const browsers = browserslist(browserslistConfig)
const minBrowserVersion = browsers
	.map((str) => str.split(' '))
	.reduce((minVersion, [browser, version]) => {
		minVersion[browser] = minVersion[browser] ? Math.min(minVersion[browser], parseFloat(version)) : parseFloat(version)
		return minVersion
	}, {})
const targets = Object.entries(minBrowserVersion).map(([browser, version]) => `${browser} >=${version}`).join(',')

module.exports = defineConfig((env) => {
	const appName = process.env.npm_package_name

	const mode = (env.development && 'development') || (env.production && 'production') || process.env.NODE_ENV || 'production'
	const isDev = mode === 'development'
	process.env.NODE_ENV = mode

	console.info('Building', appName, '\n')

	return {
		target: 'web',
		mode,
		devtool: isDev ? 'cheap-source-map' : 'source-map',

		entry: {
			UserSettings: path.join(__dirname, 'src', 'UserSettings.ts'),
			AdminSettings: path.join(__dirname, 'src', 'AdminSettings.ts'),
		},

		output: {
			path: path.resolve('./js'),
			filename: `${appName}-[name].js`,
			chunkFilename: `${appName}-[name].js`,
			assetModuleFilename: `${appName}-[name][ext]`,
			clean: true,
			devtoolNamespace: appName,
			devtoolModuleFilenameTemplate(info) {
				const rootDir = process.cwd()
				const rel = path.relative(rootDir, info.absoluteResourcePath)
				return `webpack:///${appName}/${rel}`
			},
		},

		optimization: {
			minimize: !isDev,
			minimizer: [
				new SwcJsMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
				new LightningCssMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
			],
		},

		module: {
			rules: [
				{
					test: /\.vue$/,
					loader: 'vue-loader',
					options: {
						experimentalInlineMatchResource: true,
					},
				},
				{
					test: /\.css$/,
					use: [
						'style-loader',
						'css-loader',
					],
				},
				{
					test: /\.scss$/,
					use: [
						'style-loader',
						'css-loader',
						'sass-loader',
					],
				},
				{
					test: /\.ts$/,
					exclude: [/node_modules/],
					loader: 'builtin:swc-loader',
					options: {
						jsc: {
							parser: {
								syntax: 'typescript',
							},
						},
						env: {
							targets,
						},
					},
					type: 'javascript/auto',
				},
				{
					test: /\.(png|jpe?g|gif|svg|webp)$/i,
					type: 'asset',
				},
				{
					test: /\.(woff2?|eot|ttf|otf)$/i,
					type: 'asset/resource',
				},
			],
		},

		plugins: [
			new ProgressPlugin(),

			new VueLoaderPlugin(),

			new DefinePlugin({
				__VUE_OPTIONS_API__: true,
				__VUE_PROD_DEVTOOLS__: false,
				__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
			}),

			process.env.RSDOCTOR && new RsdoctorRspackPlugin(),
		],

		resolve: {
			extensions: ['*', '.ts', '.js', '.vue'],
			symlinks: false,
			fallback: {
				fs: false,
				path: require.resolve('path-browserify'),
			},
		},

		cache: true,
	}
})
