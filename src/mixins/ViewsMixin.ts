/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import { getCurrentUser } from '@nextcloud/auth'
import axios, { isCancel } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import MarkdownIt from 'markdown-it'
import { defineComponent } from 'vue'
import CancelableRequest from '../utils/CancelableRequest.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'

/** Form share permission types */
interface FormsShare {
	shareType: number
	permissions?: string[]
	shareWith?: string
}

/** Form question structure */
interface FormsQuestion {
	id: number
	text: string
	type: string
	order: number
	options?: any[]
	[key: string]: any
}

/** Complete form structure as returned from API */
interface FormsForm {
	id: number
	hash: string
	title: string
	description: string
	ownerId: string
	created: number
	access: any
	expires: number
	fileFormat?: string | null
	fileId?: number | null
	filePath?: string | null
	isAnonymous: boolean
	isMaxSubmissionsReached: boolean
	lastUpdated: number
	submitMultiple: boolean
	allowEditSubmissions: boolean
	showExpiration: boolean
	canSubmit: boolean
	permissions: string[]
	questions: FormsQuestion[]
	state: 0 | 1 | 2
	lockedBy?: string | null
	lockedUntil?: number | null
	maxSubmissions?: number | null
	shares: FormsShare[]
	submissionCount?: number
	submissionMessage?: string | null
	confirmationEmailEnabled: boolean
	confirmationEmailSubject?: string | null
	confirmationEmailBody?: string | null
	confirmationEmailQuestionId?: number | null
	allowComments: boolean
}

/** ViewsMixin data interface */
interface ViewsMixinData {
	isLoadingForm: boolean
	cancelFetchFullForm: (reason?: string) => void
	markdownit: MarkdownIt
}

export default defineComponent({
	name: 'ViewsMixin',

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
			type: Object as () => FormsForm,
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

	data(): ViewsMixinData {
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
		 */
		formTitle(): string {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		formDescription(): string {
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

		isFormLocked(): boolean {
			return (
				this.form.lockedUntil === 0
				|| (this.form.lockedUntil! > moment().unix()
					&& this.form.lockedBy !== getCurrentUser().uid)
			)
		},
	},

	methods: {
		onShareForm(): void {
			this.$emit('open-sharing', this.form.hash)
		},

		/**
		 * Focus title after form load
		 */
		focusTitle(): void {
			this.$nextTick(() => {
				;(this.$refs.title as any)?.focus()
			})
		},

		/**
		 * Fetch the full form data and update parent
		 *
		 * @param id the unique form hash
		 */
		async fetchFullForm(id: number): Promise<void> {
			this.isLoadingForm = true

			// Cancel previous request
			this.cancelFetchFullForm('New request pending.')

			// Output after cancelling previous request for logical order.
			logger.debug(`Loading form ${id}`)

			// Create new cancelable get request
			const { request, cancel } = CancelableRequest(async function (
				url: string,
				requestOptions?: any,
			) {
				return axios.get(url, requestOptions)
			})
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
				this.focusTitle()
			}
		},

		async saveFormProperty(key: string): Promise<void> {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: this.form.id,
					}),
					{
						keyValuePairs: {
							[key]: this.form[key as keyof FormsForm],
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
})
