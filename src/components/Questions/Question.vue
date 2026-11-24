<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		class="question"
		:class="{
			'question--editable': !readOnly,
			'question--section': readOnly && isSection,
		}"
		:aria-label="
			isSection
				? readOnly
					? undefined
					: t('forms', 'Section number {index}', { index })
				: t('forms', 'Question number {index}', { index })
		"
		:aria-labelledby="isSection && readOnly && titled ? titleId : undefined">
		<!-- Drag handle -->
		<!-- TODO: implement arrow key mapping to reorder question -->
		<div
			v-if="!readOnly && !isSection"
			class="question__drag-handle"
			:class="{
				'question__drag-handle--shiftup': shiftDragHandle,
			}">
			<NcButton
				ref="buttonUp"
				:aria-label="t('forms', 'Move question up')"
				:disabled="!canMoveUp"
				class="question__drag-handle-button"
				variant="tertiary-no-background"
				@click.stop="onMoveUp">
				<template #icon>
					<NcIconSvgWrapper :svg="IconArrowUp" />
				</template>
			</NcButton>
			<NcIconSvgWrapper :svg="IconDragIndicator" />
			<NcButton
				ref="buttonDown"
				:aria-label="t('forms', 'Move question down')"
				:disabled="!canMoveDown"
				class="question__drag-handle-button"
				variant="tertiary-no-background"
				@click.stop="onMoveDown">
				<template #icon>
					<NcIconSvgWrapper :svg="IconArrowDown" />
				</template>
			</NcButton>
		</div>

		<div v-if="hasSectionBar" class="question__section-bar">
			<span class="question__section-bar__label">
				{{
					t('forms', 'Section {index} of {total}', {
						index,
						total: sectionCount,
					})
				}}
			</span>
			<div :id="sectionBarMenuId" class="question__section-bar__menu" />
		</div>

		<!-- Header -->
		<div class="question__header">
			<div
				class="question__header__title"
				:class="{ 'question__header__title--menu-in-bar': hasSectionBar }">
				<input
					v-if="!readOnly"
					:placeholder="titlePlaceholder"
					:aria-label="
						isSection
							? t('forms', 'Title of section number {index}', {
									index,
								})
							: t('forms', 'Title of question number {index}', {
									index,
								})
					"
					:value="text"
					class="question__header__title__text question__header__title__text__input"
					type="text"
					dir="auto"
					minlength="1"
					:maxlength="maxStringLengths.questionText"
					required
					@input="onTitleChange" />
				<component
					:is="`h${headingLevel}`"
					v-else-if="titled"
					:id="titleId"
					class="question__header__title__text"
					dir="auto">
					{{ computedText }}
				</component>
				<div
					v-if="!readOnly && !questionValid"
					:title="warningInvalid"
					class="question__header__title__warning"
					tabindex="0">
					<NcIconSvgWrapper :svg="IconAlertCircleOutline" />
				</div>
				<Teleport
					v-if="!readOnly"
					:to="sectionMenuTarget"
					:disabled="!hasSectionBar"
					defer>
					<NcActions
						:id="actionsId"
						v-model:open="isActionsOpen"
						forceMenu
						placement="bottom-end"
						class="question__header__title__menu">
						<template v-if="isRequired && !isSection" #icon>
							<IconOverlay>
								<template #overlay>
									<NcIconSvgWrapper :svg="IconAsterisk" inline />
								</template>
								<NcIconSvgWrapper :svg="IconDotsHorizontal" />
							</IconOverlay>
						</template>
						<NcActionCheckbox
							v-if="!isSection"
							:modelValue="isRequired"
							@update:modelValue="onRequiredChange">
							<!-- TRANSLATORS Making this question necessary to be answered when submitting to a form -->
							{{ t('forms', 'Required') }}
						</NcActionCheckbox>
						<NcActionCheckbox
							v-if="
								jumps
								&& !isSection
								&& (jumps.canGoTo || jumps.canNext)
							"
							:modelValue="jumps.enabled"
							@update:modelValue="onJumpsEnabledChange">
							{{
								jumps.canGoTo
									? t('forms', 'Go to based on answer')
									: t('forms', 'Go to after this question')
							}}
						</NcActionCheckbox>
						<slot name="actions" />
						<NcActionButton
							v-if="isSection && (canMoveUp || canMoveDown)"
							closeAfterClick
							@click="$emit('moveSection')">
							<template #icon>
								<NcIconSvgWrapper :svg="IconSwapVert" />
							</template>
							{{ t('forms', 'Move section') }}
						</NcActionButton>
						<NcActionInput
							v-if="!isSection"
							:label="t('forms', 'Technical name of the question')"
							:labelOutside="false"
							:showTrailingButton="false"
							:modelValue="name"
							@update:modelValue="onNameChange">
							<template #icon>
								<NcIconSvgWrapper :svg="IconIdentifier" />
							</template>
							{{ t('forms', 'Technical name') }}
						</NcActionInput>
						<NcActionButton
							v-if="!isSection"
							closeAfterClick
							@click="onClone">
							<template #icon>
								<NcIconSvgWrapper :svg="IconContentCopy" />
							</template>
							{{ t('forms', 'Copy question') }}
						</NcActionButton>
						<NcActionButton closeAfterClick @click="onDelete">
							<template #icon>
								<NcIconSvgWrapper :svg="IconDelete" />
							</template>
							{{
								isSection
									? t('forms', 'Delete section')
									: t('forms', 'Delete question')
							}}
						</NcActionButton>
					</NcActions>
				</Teleport>
			</div>
			<div
				v-if="hasDescription || !readOnly"
				class="question__header__description">
				<textarea
					v-if="!readOnly"
					ref="descriptionField"
					dir="auto"
					:value="description"
					:aria-label="
						isSection
							? t('forms', 'Description of section number {index}', {
									index,
								})
							: t('forms', 'Description of question number {index}', {
									index,
								})
					"
					:placeholder="
						t(
							'forms',
							'Description (formatting using Markdown is supported)',
						)
					"
					:maxlength="maxStringLengths.questionDescription"
					class="question__header__description__input"
					@input="onDescriptionChange" />
				<!-- eslint-disable vue/no-v-html -->
				<div
					v-else
					:id="descriptionId"
					class="question__header__description__output"
					v-html="computedDescription" />
				<!-- eslint-enable vue/no-v-html -->
			</div>
			<NcNoteCard v-if="hasInfo" :id="infoId" type="info">
				{{ infoMessage }}
			</NcNoteCard>
			<NcNoteCard v-if="hasError" :id="errorId" type="error">
				{{ errorMessage }}
			</NcNoteCard>
			<div v-if="!readOnly && jumps" class="question__header__warnings">
				<NcNoteCard
					v-for="warning in jumps.warnings"
					:key="warning"
					type="warning">
					{{ warning }}
				</NcNoteCard>
				<NcNoteCard v-if="jumps.hint" type="warning">
					{{ jumps.hint }}
				</NcNoteCard>
				<div class="hidden-visually" aria-live="polite">
					{{ hintAnnouncement }}
				</div>
			</div>
		</div>

		<!-- Question content -->
		<slot />
		<div v-if="jumps?.showNext" class="question__jump">
			<JumpTargetSelect
				:modelValue="jumps.next"
				:options="jumps.targetOptions"
				:inputLabel="jumps.nextLabel"
				:fallbackLabel="jumps.fallbackLabel"
				@update:modelValue="jumps.setNext" />
		</div>
		<!-- Insert question menu -->
		<slot name="insert" />
	</li>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { Jumps } from '../../composables/useJumps.ts'
import type { MaxStringLengths } from '../../types/Entities.ts'

import IconAsterisk from '@material-symbols/svg-400/outlined/asterisk.svg?raw'
import IconIdentifier from '@material-symbols/svg-400/outlined/badge.svg?raw'
import IconContentCopy from '@material-symbols/svg-400/outlined/content_copy.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconDragIndicator from '@material-symbols/svg-400/outlined/drag_indicator.svg?raw'
import IconAlertCircleOutline from '@material-symbols/svg-400/outlined/error.svg?raw'
import IconArrowDown from '@material-symbols/svg-400/outlined/keyboard_arrow_down.svg?raw'
import IconArrowUp from '@material-symbols/svg-400/outlined/keyboard_arrow_up.svg?raw'
import IconDotsHorizontal from '@material-symbols/svg-400/outlined/more_horiz.svg?raw'
import IconSwapVert from '@material-symbols/svg-400/outlined/swap_vert.svg?raw'
import { t } from '@nextcloud/l10n'
import {
	computed,
	defineComponent,
	inject,
	nextTick,
	onMounted,
	ref,
	watch,
} from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconOverlay from '../Icons/IconOverlay.vue'
import JumpTargetSelect from './JumpTargetSelect.vue'
import { FORM_QUESTIONS_KEY } from '../../composables/useJumps.ts'
import {
	getIdPrefix,
	hasTitle,
	QUESTION_TYPE_SECTION,
	STUCK_SECTION_KEY,
} from '../../utils/Sections.ts'

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Question',

	components: {
		NcIconSvgWrapper,
		IconOverlay,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcButton,
		NcNoteCard,
		JumpTargetSelect,
	},

	props: {
		index: {
			type: Number,
			required: true,
		},

		headingLevel: {
			type: Number,
			default: 3,
		},

		text: {
			type: String,
			required: true,
		},

		titlePlaceholder: {
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

		shiftDragHandle: {
			type: Boolean,
			default: false,
		},

		readOnly: {
			type: Boolean,
			default: false,
		},

		maxStringLengths: {
			type: Object as PropType<MaxStringLengths>,
			required: true,
		},

		name: {
			type: String,
			default: '',
		},

		type: {
			type: String,
			default: '',
		},

		contentValid: {
			type: Boolean,
			// eslint-disable-next-line vue/no-boolean-default
			default: true,
		},

		warningInvalid: {
			type: String,
			default: t('forms', 'This question needs a title!'),
		},

		canMoveDown: {
			type: Boolean,
			default: false,
		},

		canMoveUp: {
			type: Boolean,
			default: false,
		},

		errorMessage: {
			type: String,
			default: null,
		},

		infoMessage: {
			type: String,
			default: null,
		},

		/**
		 * The jumps of the question, see `useJumps()`
		 */
		jumps: {
			type: Object as PropType<Jumps | null>,
			default: null,
		},
	},

	emits: [
		'update:text',
		'update:description',
		'update:name',
		'update:isRequired',
		'moveDown',
		'moveUp',
		'moveSection',
		'delete',
		'clone',
	],

	setup(props, { emit }) {
		const markdownit = inject<{ render: (value: string) => string } | null>(
			'$markdownit',
			null,
		)
		const descriptionField = ref<HTMLTextAreaElement | null>(null)
		const buttonUp = ref<{ $el?: HTMLElement } | undefined>(undefined)
		const buttonDown = ref<{ $el?: HTMLElement } | undefined>(undefined)

		const isSection = computed(() => props.type === QUESTION_TYPE_SECTION)
		const formQuestions = inject(FORM_QUESTIONS_KEY, null)
		const hasSectionBar = computed(() => isSection.value && !props.readOnly)
		const sectionCount = computed(
			() =>
				formQuestions?.value.filter(
					(question) => question.type === QUESTION_TYPE_SECTION,
				).length ?? 0,
		)
		const titled = computed(() => !isSection.value || hasTitle(props))

		/**
		 * Extend text with asterisk if question is required
		 */
		const computedText = computed(() => {
			if (props.isRequired && !isSection.value) {
				return props.text + ' *'
			}
			return props.text
		})

		const computedDescription = computed(() => {
			if (!markdownit) {
				return props.description
			}
			return markdownit.render(props.description)
		})

		/**
		 * Question valid, if text not empty and content valid
		 *
		 * @return true if question valid
		 */
		const questionValid = computed(() => !!props.text && props.contentValid)
		const idPrefix = computed(() => getIdPrefix(props.type, props.index))
		const actionsId = computed(() => idPrefix.value + '_actions')
		const sectionBarMenuId = computed(() => idPrefix.value + '_bar_menu')
		const stuckSection = inject(STUCK_SECTION_KEY, null)
		const sectionMenuTarget = computed(() =>
			hasSectionBar.value && stuckSection?.value === props.index
				? '#section-sticky-menu'
				: '#' + sectionBarMenuId.value,
		)
		const titleId = computed(() => idPrefix.value + '_title')
		const descriptionId = computed(() => idPrefix.value + '_desc')
		const hasDescription = computed(() => props.description !== '')
		const hasError = computed(() => !!props.errorMessage)
		const hasInfo = computed(() => !!props.infoMessage)
		const errorId = computed(() => `${idPrefix.value}_error`)
		const infoId = computed(() => `${idPrefix.value}_info`)

		const resizeDescription = (): void => {
			// next tick ensures that the textarea is attached to DOM
			nextTick(() => {
				const textarea = descriptionField.value
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		}

		// Ensure description is sized correctly on initial render
		onMounted(() => {
			nextTick(() => resizeDescription())
		})

		const onTitleChange = (event: Event): void => {
			const target = event.target as HTMLInputElement | null
			if (!target) {
				return
			}
			emit('update:text', target.value)
		}

		const onDescriptionChange = (event: Event): void => {
			const target = event.target as HTMLTextAreaElement | null
			if (!target) {
				return
			}
			resizeDescription()
			emit('update:description', target.value)
		}

		const onNameChange = (name: string): void => {
			emit('update:name', name)
		}

		const onRequiredChange = (isRequired: boolean): void => {
			emit('update:isRequired', isRequired)
		}

		const isActionsOpen = ref(false)

		const hintAnnouncement = ref('')
		watch(
			() => props.jumps?.hint,
			(hint) => {
				hintAnnouncement.value = hint
					? t(
							'forms',
							'Question {number}: {title}',
							{ number: props.index, title: hint },
							{ escape: false, sanitize: false },
						)
					: ''
			},
		)

		/**
		 * Switch the jumps of the question on or off; switching off closes the menu first
		 *
		 * @param enabled whether jumps are switched on
		 */
		const onJumpsEnabledChange = (
			enabled: boolean,
		): Promise<void> | undefined => {
			if (!enabled) {
				isActionsOpen.value = false
			}
			return props.jumps?.setEnabled(enabled)
		}

		/**
		 * Reorder question but keep focus on the button, or on the other move button once the question is the last one
		 */
		const onMoveDown = (): void => {
			emit('moveDown')
			nextTick(() => {
				;(props.canMoveDown ? buttonDown : buttonUp).value?.$el?.focus()
			})
		}

		/**
		 * Reorder question but keep focus on the button, or on the other move button once the question is the first one
		 */
		const onMoveUp = (): void => {
			emit('moveUp')
			nextTick(() => {
				;(props.canMoveUp ? buttonUp : buttonDown).value?.$el?.focus()
			})
		}

		/**
		 * Delete this question
		 */
		const onDelete = (): void => {
			emit('delete')
		}

		/**
		 * Clone this question
		 */
		const onClone = (): void => {
			emit('clone')
		}

		return {
			buttonUp,
			buttonDown,
			descriptionField,
			IconAsterisk,
			IconAlertCircleOutline,
			IconArrowDown,
			IconArrowUp,
			IconContentCopy,
			IconSwapVert,
			IconDelete,
			IconDotsHorizontal,
			IconDragIndicator,
			IconIdentifier,
			t,
			computedText,
			computedDescription,
			questionValid,
			actionsId,
			sectionBarMenuId,
			sectionMenuTarget,
			hasSectionBar,
			sectionCount,
			titleId,
			descriptionId,
			hasDescription,
			isSection,
			titled,
			hasError,
			hasInfo,
			errorId,
			infoId,
			resizeDescription,
			onTitleChange,
			onDescriptionChange,
			onNameChange,
			onRequiredChange,
			isActionsOpen,
			hintAnnouncement,
			onJumpsEnabledChange,
			onMoveDown,
			onMoveUp,
			onDelete,
			onClone,
		}
	},
})
</script>

<style lang="scss" scoped>
@use '../../scssmixins/markdownOutput.scss' as *;

.question {
	position: relative;
	display: flex;
	align-items: stretch;
	flex-direction: column;
	justify-content: stretch;
	margin-block-end: 64px;
	padding-inline-start: var(--default-clickable-area);
	user-select: none;
	background-color: var(--color-main-background);

	&--editable {
		padding-inline-start: 56px; // add 12px for the title input box
	}

	> * {
		cursor: pointer;
	}

	&__drag-handle {
		position: absolute;
		display: flex;
		inset-inline-start: var(--default-grid-baseline);
		flex-direction: column;
		justify-content: center;
		gap: 12px;
		width: var(--default-clickable-area);
		height: 100%;
		color: var(--color-text-maxcontrast);
		cursor: grab;

		&-button {
			position: absolute;
			inset-block-start: -9999px;
		}

		// Avoid moving drag-handle due to newAnswer-input on multiple-Questions
		&--shiftup {
			height: calc(100% - var(--default-clickable-area));
		}

		&:hover,
		&:focus,
		&:focus-within {
			color: var(--color-main-text);

			.question__drag-handle-button {
				position: initial;
				inset-block-start: initial;
			}
		}

		&:active {
			cursor: grabbing;
		}

		> * {
			cursor: grab;
		}
	}

	&__section-bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: var(--default-grid-baseline);
		min-height: var(--default-clickable-area);
		margin-block: 2px calc(3 * var(--default-grid-baseline));
		margin-inline: calc(2px - 56px + var(--section-frame-inset-start, 0px))
			calc(
				2px +
					var(
						--section-frame-inset-end,
						calc(-2 * var(--default-grid-baseline))
					)
			);
		padding-inline: calc(56px - 2px - var(--section-frame-inset-start, 0px))
			var(--default-grid-baseline);
		border-start-start-radius: calc(var(--border-radius-large) - 2px);
		border-start-end-radius: calc(var(--border-radius-large) - 2px);
		background-color: var(--color-primary-element-light);
		color: var(--color-main-text);
	}

	&__title,
	&__content {
		flex: 1 1 100%;
		max-width: 100%;
		padding: 0;
	}

	&__jump {
		display: flex;
		container-type: inline-size;
		margin-block-start: calc(2 * var(--default-grid-baseline));
		max-width: 440px;
		padding-inline-end: calc(var(--default-clickable-area) + 12px);
	}

	&__header {
		display: block;
		padding-block-end: 8px;
		align-items: center;
		flex: 1 1 100%;
		justify-content: space-between;
		width: auto;

		&__title {
			display: flex;
			min-height: 44px;

			&--menu-in-bar {
				padding-inline-end: calc(var(--default-clickable-area) + 12px);
			}

			&__text {
				flex: 1 1 100%;
				font-size: 16px !important;
				padding-block: 10px;
				padding-inline: 0px;
				font-weight: bold;
				margin: auto !important;

				&__input {
					position: relative;
					inset-inline-start: -12px;
					margin-inline-end: -12px !important;
					padding-inline-start: 10px !important;
				}
			}

			&__warning {
				margin-block: auto;
				margin-inline: 8px;
				color: var(--color-element-error);
			}

			&__menu {
				margin-block: auto;
				margin-inline-end: 12px;
			}
		}

		&__description {
			display: flex;

			&__input {
				margin: 0px;
				min-height: 1.5em;
				border-width: 2px;
				position: relative;
				inset-inline-start: -12px;
				padding-block: 4px;
				padding-inline: 10px;
				resize: none;
			}

			&__input,
			&__output {
				color: var(--color-text-maxcontrast) !important;
				line-height: 1.5em;
				z-index: inherit;
				overflow-wrap: break-word;
				// match with other inputs
				width: calc(100% - var(--default-clickable-area));
			}
			&__output {
				//compensate border
				padding-block: 6px;
				padding-inline: 0;
				// Styling for rendered Output
				@include markdown-output;
			}
		}

		&__warnings :deep(.notecard) {
			position: relative;
			inset-inline-start: -12px;
			width: calc(100% - var(--default-clickable-area));
			box-sizing: border-box;
		}
	}

	&--section {
		margin-block-end: 16px;

		h3 {
			font-size: 24px !important;
			border-block-end: 1px solid var(--color-border);
		}
	}
}
</style>
