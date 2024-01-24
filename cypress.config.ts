/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { startNextcloud, waitOnNextcloud, configureNextcloud } from '@nextcloud/cypress/docker'
import { defineConfig } from 'cypress'
import { readFileSync } from 'node:fs'
import cypressSplit from 'cypress-split'
import vitePreprocessor from 'cypress-vite'

export default defineConfig({
	// 16:9 display
	viewportHeight: 720,
	viewportWidth: 1280,

	// Retry on CI but not locally
	retries: {
		runMode: 2,
		openMode: 0,
	},

	// faster video processing
	videoCompression: false,

	e2e: {
		async setupNodeEvents(on, config) {
			on('file:preprocessor', vitePreprocessor({ configFile: false }))
			cypressSplit(on, config)

			const appinfo = readFileSync('appinfo/info.xml').toString()
			const maxVersion = appinfo.match(/<nextcloud min-version="\d+" max-version="(\d\d+)" \/>/)?.[1]

			const IP = await startNextcloud(maxVersion ? `stable${maxVersion}` : undefined)
			await waitOnNextcloud(IP)
			await configureNextcloud(['forms', 'viewer'])

			config.baseUrl = `http://${IP}/index.php`
			return config
		},
	},
})
