/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'
import type { FormsOption } from '../types/Entities.d.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, nextTick, ref } from 'vue'
import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'

export const QUESTION_EMITS = [
	'update:text',
	'update:description',
	'update:isRequired',
	'update:extraSettings',
	'update:name',
	'update:values',
	'delete',
	'clone',
	'keydown',
	'moveDown',
	'moveUp',
]

export const QUESTION_PROPS = {
	id: {
		type: Number,
		required: true,
	},
	formId: {
		type: Number,
		default: null,
	},
	text: {
		type: String,
		required: true,
	},
	description: {
		type: String,
		required: true,
	},
	isRequired: {
		type: Boolean,
		required: true,
	},
	index: {
		type: Number,
		required: true,
	},
	name: {
		type: String,
		default: '',
	},
	values: {
		type: [Array, Object],
		default() {
			return []
		},
	},
	options: {
		type: Array as () => FormsOption[],
		required: true,
	},
	order: {
		type: Number,
		default: -1,
	},
	type: {
		type: String,
		default: null,
	},
	answerType: {
		type: Object,
		required: true,
	},
	readOnly: {
		type: Boolean,
		default: false,
	},
	maxStringLengths: {
		type: Object,
		required: true,
	},
	extraSettings: {
		default: () => {
			return {}
		},
	},
	accept: {
		type: Array,
		default() {
			return []
		},
	},
	canMoveUp: {
		type: Boolean,
		default: false,
	},
	canMoveDown: {
		type: Boolean,
		default: false,
	},
}

interface RootElementLike {
	$el?: HTMLElement
}

interface UseQuestionOptions {
	emit: (event: string, ...args: unknown[]) => void
	infoMessage?: Ref<string | null>
	rootElement?: Ref<RootElementLike | HTMLElement | null>
}

interface QuestionPropsLike {
	id: number
	formId: number | null
	index: number
	text: string
	description: string
	isRequired: boolean
	readOnly: boolean
	name: string
	maxStringLengths: Record<string, number>
	canMoveUp: boolean
	canMoveDown: boolean
	extraSettings: Record<string, unknown>
	options: FormsOption[]
	[key: string]: unknown
}

interface QuestionForwardedProps {
	index: number
	text: string
	description: string
	isRequired: boolean
	readOnly: boolean
	maxStringLengths: Record<string, number>
	name: string
	canMoveUp: boolean
	canMoveDown: boolean
}

/**
 * Shared question helpers previously provided via QuestionMixin.
 *
 * @param props Question component props used by shared question logic.
 * @param options Emits bridge for forwarding events to parent components.
 */
export function useQuestion(props: QuestionPropsLike, options: UseQuestionOptions) {
	const errorMessage = ref<string | null>(null)

	const questionProps = computed<QuestionForwardedProps>(() => ({
		index: props.index,
		text: props.text,
		description: props.description,
		isRequired: props.isRequired,
		readOnly: props.readOnly,
		maxStringLengths: props.maxStringLengths,
		name: props.name,
		canMoveUp: props.canMoveUp,
		canMoveDown: props.canMoveDown,
	}))

	const titleId = computed(() => {
		return 'q' + props.index + '_title'
	})

	const descriptionId = computed(() => {
		return 'q' + props.index + '_desc'
	})

	const hasError = computed(() => {
		return !!errorMessage.value
	})

	const hasInfo = computed(() => {
		return !!options.infoMessage?.value
	})

	const errorId = computed(() => {
		return `q${props.index}_error`
	})

	const infoId = computed(() => {
		return `q${props.index}_info`
	})

	const onTitleChange = debounce(function (text: string) {
		options.emit('update:text', text)
		saveQuestionProperty('text', text)
	}, INPUT_DEBOUNCE_MS)

	const onDescriptionChange = debounce(function (description: string) {
		options.emit('update:description', description)
		saveQuestionProperty('description', description)
	}, INPUT_DEBOUNCE_MS)

	const onRequiredChange = debounce(function (isRequiredValue: boolean) {
		options.emit('update:isRequired', isRequiredValue)
		saveQuestionProperty('isRequired', isRequiredValue)
	}, INPUT_DEBOUNCE_MS)

	const onExtraSettingsChange = debounce(function (
		newSettings: Record<string, unknown>,
	) {
		const newExtraSettings = { ...props.extraSettings, ...newSettings }
		options.emit('update:extraSettings', newExtraSettings)
		saveQuestionProperty('extraSettings', newExtraSettings)
	}, INPUT_DEBOUNCE_MS)

	const onNameChange = debounce(function (name: string) {
		options.emit('update:name', name)
		saveQuestionProperty('name', name)
	}, INPUT_DEBOUNCE_MS)

	const onShuffleOptionsChange = (shuffle: boolean): void => {
		onExtraSettingsChange({ shuffleOptions: shuffle })
	}

	const onValuesChange = (
		values:
			| string
			| number
			| boolean
			| Array<string | number | boolean | Record<string, unknown>>
			| Record<string, unknown>
			| null
			| unknown,
	): void => {
		options.emit('update:values', values)
	}

	const onDelete = (): void => {
		options.emit('delete')
	}

	const onClone = (): void => {
		options.emit('clone')
	}

	const onKeydownEnter = (event: KeyboardEvent): void => {
		options.emit('keydown', event)
	}

	const focus = (): void => {
		const raw = options.rootElement?.value
		const element = (raw && '$el' in raw ? raw.$el : raw) as
			HTMLElement | null | undefined
		element?.scrollIntoView({ behavior: 'smooth' })
		nextTick(() => {
			const title = element?.querySelector(
				'.question__header__title__text__input',
			) as HTMLInputElement | null
			if (title) {
				title.focus()
			}
		})
	}

	const shuffleArray = <T>(input: T[]): T[] => {
		const shuffled = [...input]
		let idx = shuffled.length
		while (--idx > 0) {
			const rndIdx = Math.floor(Math.random() * (idx + 1))
			;[shuffled[rndIdx], shuffled[idx]] = [shuffled[idx], shuffled[rndIdx]]
		}
		return shuffled
	}

	/**
	 * Persist a single question field to the backend.
	 *
	 * @param key Question property name.
	 * @param value Question property value.
	 */
	async function saveQuestionProperty(
		key: string,
		value:
			| string
			| number
			| boolean
			| Array<unknown>
			| Record<string, unknown>
			| null
			| unknown,
	): Promise<void> {
		try {
			await axios.patch(
				generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions/{questionId}',
					{
						id: props.formId,
						questionId: props.id,
					},
				),
				{
					keyValuePairs: {
						[key]: value,
					},
				},
			)
			emitEvent('forms:last-updated:set', props.formId)
		} catch (error) {
			logger.error('Error while saving question', { error })
			showError(t('forms', 'Error while saving question'))
		}
	}

	const commonListeners = computed(() => {
		return {
			clone: onClone,
			delete: onDelete,
			'update:text': onTitleChange,
			'update:description': onDescriptionChange,
			'update:isRequired': onRequiredChange,
			'update:name': onNameChange,
			moveDown: (...args: unknown[]) => options.emit('moveDown', ...args),
			moveUp: (...args: unknown[]) => options.emit('moveUp', ...args),
		}
	})

	return {
		errorMessage,
		questionProps,
		titleId,
		descriptionId,
		hasError,
		hasInfo,
		errorId,
		infoId,
		commonListeners,
		onTitleChange,
		onDescriptionChange,
		onRequiredChange,
		onExtraSettingsChange,
		onNameChange,
		onShuffleOptionsChange,
		onValuesChange,
		onDelete,
		onClone,
		onKeydownEnter,
		focus,
		shuffleArray,
		saveQuestionProperty,
	}
}
