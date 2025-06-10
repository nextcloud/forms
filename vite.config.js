import { defineConfig } from 'vite'
import { createVuePlugin } from 'vite-plugin-vue2'
import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default defineConfig({
	...createAppConfig(
		{
			emptyContent: resolve(join('src', 'emptyContent.js')),
			main: resolve(join('src', 'main.js')),
			submit: resolve(join('src', 'submit.js')),
			settings: resolve(join('src', 'settings.js')),
		},
		{
			config: {
				plugins: [
					createVuePlugin({
						template: {
							compilerOptions: {
								isCustomElement: (tag) => tag.includes('-'),
							},
						},
					}),
				],
				build: {
					cssCodeSplit: false,
					rollupOptions: {
						output: {
							manualChunks: {
								vendor: ['vue', 'vue-router'],
							},
						},
					},
				},
			},
		},
	),
})
