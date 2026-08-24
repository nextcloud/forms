/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable jsdoc/require-jsdoc */

import type { Ref } from 'vue'
import type { FormsOption } from '../models/Entities.d.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, nextTick, ref, watch } from 'vue'
import { INPUT_DEBOUNCE_MS, OptionType } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'

export const QUESTION_MULTIPLE_EMITS = ['update:options']

interface UseQuestionMultipleOptions {
	emit: (event: string, ...args: unknown[]) => void
	input?: Ref<
		Array<{
			focus?: () => void
			$props?: { optionType?: string; index?: number }
		} | null>
	>
	isLoading?: Ref<boolean>
}

interface AnswerTypeLike {
	validate: (ctx: unknown) => boolean
}

export interface QuestionMultiplePropsLike {
	options: FormsOption[]
	values: unknown[]
	answerType: AnswerTypeLike
	readOnly: boolean
	extraSettings?: Record<string, unknown>
	formId: number
	id: number
}

/**
 * Shared helpers previously provided via QuestionMultipleMixin.
 *
 * @param props Question component props used by multi-option helpers.
 * @param options Emits bridge for forwarding option updates.
 */
export function useQuestionMultiple(
	props: unknown,
	options: UseQuestionMultipleOptions,
) {
	const typedProps = props as QuestionMultiplePropsLike
	const dirtyOptionsType = ref<string | null>(null)

	const areNoneChecked = computed(() => {
		return typedProps.values.length === 0
	})

	const contentValid = computed(() => {
		return typedProps.answerType.validate(typedProps)
	})

	const isLastEmpty = computed(() => {
		const value = typedProps.options[typedProps.options.length - 1] as
			FormsOption | undefined
		return value?.text?.trim?.().length === 0
	})

	const expectedOptionTypes = computed(() => {
		return [OptionType.Choice, OptionType.Row, OptionType.Column]
	})

	const sortedOptionsPerType = computed(() => {
		const optionsPerType: { [key: string]: FormsOption[] } = Object.fromEntries(
			expectedOptionTypes.value.map((optionType) => [optionType, []]),
		)

		typedProps.options.forEach((option) => {
			if (optionsPerType[option.optionType]) {
				optionsPerType[option.optionType].push(option)
			}
		})

		for (const optionType of Object.keys(optionsPerType)) {
			if (typedProps.readOnly && typedProps.extraSettings?.shuffleOptions) {
				optionsPerType[optionType] = shuffleArray(optionsPerType[optionType])
			} else {
				optionsPerType[optionType] = [...optionsPerType[optionType]].sort(
					(a, b) => {
						if (a.order === b.order) {
							return a.id - b.id
						}
						return (a.order ?? 0) - (b.order ?? 0)
					},
				)

				if (!typedProps.readOnly) {
					optionsPerType[optionType].push({
						id: 0,
						local: true,
						questionId: typedProps.id,
						text: '',
						optionType,
						order: optionsPerType[optionType].length,
					} as FormsOption)
				}
			}
		}

		return optionsPerType
	})

	const handleMultipleOptions = async (answers: string[]): Promise<void> => {
		if (options.isLoading) {
			options.isLoading.value = true
		}

		try {
			const response = await axios.post(
				generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
					{
						id: typedProps.formId,
						questionId: typedProps.id,
					},
				),
				{
					optionTexts: answers,
					optionType: OptionType.Choice,
				},
			)

			const newServerOptions = OcsResponse2Data(response) as FormsOption[]
			const newOptions = typedProps.options.slice()
			newServerOptions.forEach((option: FormsOption, index: number) => {
				const order =
					typeof option.order === 'number'
						? option.order
						: newOptions.length + index

				newOptions.push({
					...option,
					id: option.id,
					questionId: typedProps.id,
					text: option.text,
					optionType: option.optionType ?? OptionType.Choice,
					order,
					local: false,
				})
			})

			updateOptions(newOptions)

			nextTick(() => {
				focusIndex(newOptions.length - 1, OptionType.Choice)
			})
		} catch (error) {
			logger.error('Error while saving question options', { error })
			showError(t('forms', 'Error while saving question options'))
		} finally {
			if (options.isLoading) {
				options.isLoading.value = false
			}
		}
	}

	function shuffleArray(input: FormsOption[]): FormsOption[] {
		const shuffled = [...input]
		let idx = shuffled.length
		while (--idx > 0) {
			const rndIdx = Math.floor(Math.random() * (idx + 1))
			;[shuffled[rndIdx], shuffled[idx]] = [shuffled[idx], shuffled[rndIdx]]
		}
		return shuffled
	}

	function focusNextInput(index: number, optionType: string) {
		focusIndex(index + 1, optionType)
	}

	function focusIndex(index: number, optionType: string) {
		const refsArray = Array.isArray(options.input?.value)
			? options.input.value
			: options.input?.value
				? [options.input.value]
				: []

		const item = refsArray.find((component) => {
			const props = (
				component as {
					$props?: { optionType?: string; index?: number }
				}
			)?.$props

			return props?.optionType === optionType && props?.index === index
		})

		if (item?.focus) {
			item.focus()
		} else {
			logger.warn('Could not find option to focus', {
				index,
				optionType,
				options: sortedOptionsPerType.value[optionType],
			})
		}
	}

	function sortOptionsOfType(optionsList: FormsOption[], optionType: string) {
		let filtered = optionsList.filter(
			(option) => option.optionType === optionType,
		)
		if (typedProps.readOnly && typedProps.extraSettings?.shuffleOptions) {
			return shuffleArray(filtered)
		}

		filtered = [...filtered].sort((a, b) => {
			if (a.order === b.order) {
				return a.id - b.id
			}
			return (a.order ?? 0) - (b.order ?? 0)
		})

		if (!typedProps.readOnly) {
			return [
				...filtered,
				{
					id: 0,
					local: true,
					questionId: typedProps.id,
					text: '',
					optionType,
					order: filtered.length,
				} as FormsOption,
			]
		}

		return filtered
	}

	function updateOptionsOrder(newOptions: FormsOption[], optionType: string) {
		replaceOptionsOfType(
			newOptions
				.filter((option) => !option.local)
				.map((option, index) => {
					return {
						...option,
						order: index,
					}
				}),
			optionType,
		)
	}

	function onCreateAnswer(index: number, answer: FormsOption): void {
		nextTick(() => {
			nextTick(() => focusIndex(index + 1, answer.optionType))
		})
		updateOptions([...typedProps.options, answer])
	}

	function replaceOptionsOfType(optionsList: FormsOption[], optionType: string) {
		const updatedOptions = [
			...typedProps.options.filter(
				(option) => option.optionType !== optionType,
			),
			...optionsList,
		]

		updateOptions(updatedOptions)
	}

	function updateOptions(optionsList: FormsOption[]) {
		options.emit('update:options', optionsList)
		emitEvent('forms:last-updated:set', typedProps.formId)
	}

	function updateAnswer(index: number, answer: FormsOption) {
		const optionsList = [...sortedOptionsPerType.value[answer.optionType]]
		const [oldValue] = optionsList.splice(index, 1, answer)

		if (oldValue.local && !answer.local) {
			nextTick(() => {
				nextTick(() => focusIndex(index, answer.optionType))
			})
		}

		replaceOptionsOfType(
			optionsList.filter(({ local }) => !local),
			answer.optionType,
		)
	}

	function checkValidOption(optionType: string) {
		sortedOptionsPerType.value[optionType].forEach((option) => {
			if (!option.text && !option.local) {
				deleteOption(option)
			}
		})
	}

	function deleteOption(optionToDelete: FormsOption) {
		const optionType = optionToDelete.optionType
		const sortedOptions = sortedOptionsPerType.value[optionType]
		const index = sortedOptions.findIndex(
			(option) => option.id === optionToDelete.id,
		)
		const optionsList = [...sortedOptions]
		const [option] = optionsList.splice(index, 1)

		deleteOptionFromDatabase(option)

		replaceOptionsOfType(
			optionsList
				.filter(({ local }) => !local)
				.map((opt, order) => ({ ...opt, order })),
			optionType,
		)

		nextTick(() => focusIndex(Math.max(index - 1, 0), optionType))
	}

	function deleteOptionFromDatabase(option: FormsOption & { local?: boolean }) {
		const optionIndex = typedProps.options.findIndex(
			(opt) => opt.id === option.id,
		)

		if (!option.local) {
			axios
				.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
						{
							id: typedProps.formId,
							questionId: typedProps.id,
							optionId: option.id,
						},
					),
				)
				.catch((error) => {
					logger.error('Error while deleting an option', {
						error,
						option,
					})
					showError(t('forms', 'There was an issue deleting this option'))
					restoreOption(option, optionIndex)
				})
		}
	}

	function restoreOption(option: FormsOption, index: number) {
		const optionsList = typedProps.options.slice()
		optionsList.splice(index, 0, option)

		updateOptions(optionsList)
		focusIndex(index, option.optionType)
	}

	async function saveOptionsOrder(optionType: string) {
		try {
			const newOrder = sortedOptionsPerType.value[optionType]
				.filter((option) => !option.local)
				.map((option) => option.id)

			await axios.patch(
				generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
					{
						id: typedProps.formId,
						questionId: typedProps.id,
					},
				),
				{
					newOrder,
					optionType,
				},
			)
			emitEvent('forms:last-updated:set', typedProps.formId)
			dirtyOptionsType.value = null
		} catch (error) {
			logger.error('Could not reorder options', { error })
			showError(t('forms', 'Error while saving options order'))
		}
	}

	function onOptionMoveUp(index: number, optionType: string) {
		if (index > 0) {
			onOptionMoveDown(index - 1, optionType)
		}
	}

	function onOptionMoveDown(index: number, optionType: string) {
		if (index === sortedOptionsPerType.value[optionType].length - 1) {
			return
		}

		const first = sortedOptionsPerType.value[optionType][index]
		const second = sortedOptionsPerType.value[optionType][index + 1]
		second.order = index
		first.order = index + 1

		dirtyOptionsType.value = optionType
	}

	watch(
		dirtyOptionsType,
		debounce((optionType: string | null) => {
			if (!optionType) {
				return
			}
			saveOptionsOrder(optionType)
		}, INPUT_DEBOUNCE_MS),
	)

	return {
		dirtyOptionsType,
		areNoneChecked,
		contentValid,
		isLastEmpty,
		expectedOptionTypes,
		sortedOptionsPerType,
		handleMultipleOptions,
		focusNextInput,
		focusIndex,
		sortOptionsOfType,
		updateOptionsOrder,
		onCreateAnswer,
		replaceOptionsOfType,
		updateOptions,
		updateAnswer,
		checkValidOption,
		deleteOption,
		deleteOptionFromDatabase,
		restoreOption,
		saveOptionsOrder,
		onOptionMoveUp,
		onOptionMoveDown,
	}
}
