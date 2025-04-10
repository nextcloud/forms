/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import axios, { isCancel } from '@nextcloud/axios'
import MarkdownIt from 'markdown-it'

import CancelableRequest from '../utils/CancelableRequest.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import logger from '../utils/Logger.js'

export default {
	provide() {
		return {
			$markdownit: this.markdownit,
		}
	},

	props: {
		hash: {
			type: String,
			default: '',
		},
		form: {
			type: Object,
			required: true,
		},
		publicView: {
			type: Boolean,
			default: false,
		},
		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			// State-Variable
			isLoadingForm: true,

			// storage for axios cancel function
			cancelFetchFullForm: () => {},

			// markdown renderer for descriptions
			markdownit: new MarkdownIt({ breaks: true }),
		}
	},

	computed: {
		/**
		 * Return form title, or placeholder if not set
		 *
		 * @return {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		formDescription() {
			// Remember the old renderer if overridden, or proxy to the default renderer.
			const defaultRender =
				this.markdownit.renderer.rules.link_open
				|| function (tokens, idx, options, env, self) {
					return self.renderToken(tokens, idx, options)
				}

			this.markdownit.renderer.rules.link_open = function (
				tokens,
				idx,
				options,
				env,
				self,
			) {
				// Add a new `target` attribute, or replace the value of the existing one.
				tokens[idx].attrSet('target', '_blank')

				// Pass the token to the default renderer.
				return defaultRender(tokens, idx, options, env, self)
			}

			return (
				this.markdownit.render(this.form.description)
				|| this.form.description
			)
		},
	},

	methods: {
		onShareForm() {
			this.$emit('open-sharing', this.form.hash)
		},

		/**
		 * Focus title after form load
		 */
		focusTitle() {
			this.$nextTick(() => {
				this.$refs.title.focus()
			})
		},

		/**
		 * Fetch the full form data and update parent
		 *
		 * @param {number} id the unique form hash
		 */
		async fetchFullForm(id) {
			this.isLoadingForm = true

			// Cancel previous request
			this.cancelFetchFullForm('New request pending.')

			// Output after cancelling previous request for logical order.
			logger.debug(`Loading form ${id}`)

			// Create new cancelable get request
			const { request, cancel } = CancelableRequest(
				async function (url, requestOptions) {
					return axios.get(url, requestOptions)
				},
			)
			// Store cancel-function
			this.cancelFetchFullForm = cancel

			try {
				const response = await request(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', { id }),
				)
				this.$emit('update:form', OcsResponse2Data(response))
				this.isLoadingForm = false
			} catch (error) {
				if (isCancel(error)) {
					logger.debug(`The request for form ${id} has been canceled`, {
						error,
					})
				} else {
					logger.error(`Unexpected error fetching form ${id}`, {
						error,
					})
					this.isLoadingForm = false
				}
			} finally {
				if (this.form.title === '') {
					this.focusTitle()
				}
			}
		},

		async saveFormProperty(key) {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: this.form.id,
					}),
					{
						keyValuePairs: {
							[key]: this.form[key],
						},
					},
				)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error('Error saving form property', { error })
				showError(t('forms', 'Error while saving form'))
			}
		},
	},
}
