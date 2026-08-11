/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, Ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios, { isCancel } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import MarkdownIt from 'markdown-it'
import { computed, nextTick, provide, ref } from 'vue'
import CancelableRequest from '../utils/CancelableRequest.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'

export interface ViewFormLike {
	id: number
	hash: string
	title: string
	description: string
	lockedBy?: string | null
	lockedUntil?: number | null
	[key: string]: unknown
}

export interface UseViewFormOptions {
	form: () => ViewFormLike | Record<string, unknown>
	emit: ((event: 'open-sharing', hash: string) => void)
		& ((event: 'update:form', form: ViewFormLike) => void)
	titleRef?: { value: { focus?: () => void } | null }
}

export interface UseViewFormResult {
	isLoadingForm: Ref<boolean>
	markdownit: MarkdownIt
	formTitle: ComputedRef<string>
	formDescription: ComputedRef<string>
	isFormLocked: ComputedRef<boolean>
	onShareForm: () => void
	fetchFullForm: (id: number) => Promise<void>
	saveFormProperty: (key: string) => Promise<void>
	saveFormPropertyValue: (key: string, value: unknown) => Promise<void>
	focusTitle: () => void
}

/**
 * Shared view helpers previously provided via ViewsMixin.
 *
 * @param options View state and emit dependencies.
 */
export function useViewForm(options: UseViewFormOptions): UseViewFormResult {
	const isLoadingForm = ref(true)
	let cancelFetchFullForm: (reason?: string) => void = () => {}

	const getForm = (): ViewFormLike => options.form() as ViewFormLike

	const markdownit = new MarkdownIt({ breaks: true })
	const defaultRender =
		markdownit.renderer.rules.link_open
		|| function (tokens, idx, renderOptions, env, self) {
			return self.renderToken(tokens, idx, renderOptions)
		}

	markdownit.renderer.rules.link_open = function (
		tokens,
		idx,
		renderOptions,
		env,
		self,
	) {
		tokens[idx].attrSet('target', '_blank')
		return defaultRender(tokens, idx, renderOptions, env, self)
	}

	provide('$markdownit', markdownit)

	const formTitle = computed(() => {
		const form = getForm()
		if (form.title) {
			return form.title
		}
		return t('forms', 'New form')
	})

	const formDescription = computed(() => {
		const form = getForm()
		return markdownit.render(form.description || '') || form.description
	})

	const isFormLocked = computed(() => {
		const form = getForm()
		return (
			form.lockedUntil === 0
			|| (Number(form.lockedUntil || 0) > moment().unix()
				&& form.lockedBy !== getCurrentUser()?.uid)
		)
	})

	const focusTitle = () => {
		nextTick(() => {
			options.titleRef?.value?.focus?.()
		})
	}

	const onShareForm = () => {
		options.emit('open-sharing', getForm().hash)
	}

	const fetchFullForm = async (id: number): Promise<void> => {
		isLoadingForm.value = true
		cancelFetchFullForm('New request pending.')

		logger.debug(`Loading form ${id}`)

		const { request, cancel } = CancelableRequest(function (
			url: string,
			requestOptions?: Record<string, unknown>,
		) {
			return axios.get(url, requestOptions)
		})
		cancelFetchFullForm = cancel

		try {
			const response = await request(
				generateOcsUrl('apps/forms/api/v3/forms/{id}', { id }),
			)
			options.emit('update:form', OcsResponse2Data(response))
			isLoadingForm.value = false
		} catch (error) {
			if (isCancel(error)) {
				logger.debug(`The request for form ${id} has been canceled`, {
					error,
				})
			} else {
				logger.error(`Unexpected error fetching form ${id}`, {
					error,
				})
				isLoadingForm.value = false
			}
		} finally {
			focusTitle()
		}
	}

	const saveFormPropertyValue = async (
		key: string,
		value: unknown,
	): Promise<void> => {
		const form = getForm()
		try {
			await axios.patch(
				generateOcsUrl('apps/forms/api/v3/forms/{id}', {
					id: form.id,
				}),
				{
					keyValuePairs: {
						[key]: value,
					},
				},
			)
			emitEvent('forms:last-updated:set', form.id)
		} catch (error) {
			logger.error('Error saving form property', { error })
			showError(t('forms', 'Error while saving form'))
		}
	}

	const saveFormProperty = async (key: string): Promise<void> => {
		const form = getForm()
		await saveFormPropertyValue(key, form[key])
	}

	return {
		isLoadingForm,
		markdownit,
		formTitle,
		formDescription,
		isFormLocked,
		onShareForm,
		fetchFullForm,
		saveFormProperty,
		saveFormPropertyValue,
		focusTitle,
	}
}
