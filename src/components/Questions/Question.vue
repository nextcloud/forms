<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		:class="{
			question: true,
			'question--editable': !readOnly,
		}"
		:aria-label="t('forms', 'Question number {index}', { index })">
		<!-- Drag handle -->
		<!-- TODO: implement arrow key mapping to reorder question -->
		<div
			v-if="!readOnly"
			:class="{
				'question__drag-handle': true,
				'question__drag-handle--shiftup': shiftDragHandle,
			}">
			<NcButton
				ref="buttonUp"
				:aria-label="t('forms', 'Move question up')"
				:disabled="!canMoveUp"
				class="question__drag-handle-button"
				type="tertiary-no-background"
				@click.stop="onMoveUp">
				<template #icon>
					<IconArrowUp :size="20" />
				</template>
			</NcButton>
			<IconDragIndicator :size="20" />
			<NcButton
				ref="buttonDown"
				:aria-label="t('forms', 'Move question down')"
				:disabled="!canMoveDown"
				class="question__drag-handle-button"
				type="tertiary-no-background"
				@click.stop="onMoveDown">
				<template #icon>
					<IconArrowDown :size="20" />
				</template>
			</NcButton>
		</div>

		<!-- Header -->
		<div class="question__header">
			<div class="question__header__title">
				<input
					v-if="!readOnly"
					:placeholder="titlePlaceholder"
					:aria-label="
						t('forms', 'Title of question number {index}', {
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
				<h3
					v-else
					:id="titleId"
					class="question__header__title__text"
					dir="auto">
					{{ computedText }}
				</h3>
				<div
					v-if="!readOnly && !questionValid"
					v-tooltip.auto="warningInvalid"
					class="question__header__title__warning"
					tabindex="0">
					<IconAlertCircleOutline :size="20" />
				</div>
				<NcActions
					v-if="!readOnly"
					:id="actionsId"
					:force-menu="true"
					placement="bottom-end"
					class="question__header__title__menu">
					<template v-if="isRequired" #icon>
						<IconOverlay>
							<template #overlay>
								<IconAsterisk :size="20" />
							</template>
							<IconDotsHorizontal :size="20" />
						</IconOverlay>
					</template>
					<NcActionCheckbox
						:checked="isRequired"
						@update:checked="onRequiredChange">
						<!-- TRANSLATORS Making this question necessary to be answered when submitting to a form -->
						{{ t('forms', 'Required') }}
					</NcActionCheckbox>
					<slot name="actions" />
					<NcActionInput
						:label="t('forms', 'Technical name of the question')"
						:label-outside="false"
						:show-trailing-button="false"
						:value="name"
						@input="onNameChange">
						<template #icon>
							<IconIdentifier :size="20" />
						</template>
						{{ t('forms', 'Technical name') }}
					</NcActionInput>
					<NcActionButton :close-after-click="true" @click="onClone">
						<template #icon>
							<IconContentCopy :size="20" />
						</template>
						{{ t('forms', 'Copy question') }}
					</NcActionButton>
					<NcActionButton @click="onDelete">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Delete question') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div
				v-if="hasDescription || !readOnly"
				class="question__header__description">
				<textarea
					v-if="!readOnly"
					ref="description"
					dir="auto"
					:value="description"
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
					class="question__header__description__output"
					v-html="computedDescription" />
				<!-- eslint-enable vue/no-v-html -->
			</div>
		</div>

		<!-- Question content -->
		<slot />
	</li>
</template>

<script>
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcButton from '@nextcloud/vue/components/NcButton'

import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconAsterisk from 'vue-material-design-icons/Asterisk.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconDotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import IconDragIndicator from '../Icons/IconDragIndicator.vue'
import IconIdentifier from 'vue-material-design-icons/Identifier.vue'
import IconOverlay from '../Icons/IconOverlay.vue'

export default {
	name: 'Question',

	components: {
		IconAlertCircleOutline,
		IconArrowDown,
		IconArrowUp,
		IconAsterisk,
		IconContentCopy,
		IconDelete,
		IconDotsHorizontal,
		IconDragIndicator,
		IconIdentifier,
		IconOverlay,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcButton,
	},

	inject: ['$markdownit'],

	props: {
		index: {
			type: Number,
			required: true,
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
			type: Object,
			required: true,
		},
		name: {
			type: String,
			default: '',
		},
		contentValid: {
			type: Boolean,
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
	},

	computed: {
		/**
		 * Extend text with asterisk if question is required
		 *
		 * @return {boolean}
		 */
		computedText() {
			if (this.isRequired) {
				return this.text + ' *'
			}
			return this.text
		},

		computedDescription() {
			return this.$markdownit.render(this.description)
		},

		/**
		 * Question valid, if text not empty and content valid
		 *
		 * @return {boolean} true if question valid
		 */
		questionValid() {
			return this.text && this.contentValid
		},

		actionsId() {
			return 'q' + this.index + '_actions'
		},

		titleId() {
			return 'q' + this.index + '_title'
		},

		hasDescription() {
			return this.description !== ''
		},
	},
	// Ensure description is sized correctly on initial render
	mounted() {
		this.$nextTick(() => this.resizeDescription())
	},
	methods: {
		onTitleChange({ target }) {
			this.$emit('update:text', target.value)
		},

		onDescriptionChange({ target }) {
			this.resizeDescription()
			this.$emit('update:description', target.value)
		},

		onNameChange({ target }) {
			this.$emit('update:name', target.value)
		},

		onRequiredChange(isRequired) {
			this.$emit('update:isRequired', isRequired)
		},

		resizeDescription() {
			// next tick ensures that the textarea is attached to DOM
			this.$nextTick(() => {
				const textarea = this.$refs.description
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		},

		/**
		 * Reorder question but keep focus on the button
		 */
		onMoveDown() {
			this.$emit('move-down')
			this.$nextTick(() => this.$refs.buttonDown.$el.focus())
		},
		onMoveUp() {
			this.$emit('move-up')
			this.$nextTick(() => this.$refs.buttonUp.$el.focus())
		},

		/**
		 * Delete this question
		 */
		onDelete() {
			this.$emit('delete')
		},

		/**
		 * Clone this question
		 */
		onClone() {
			this.$emit('clone')
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../scssmixins/markdownOutput';

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
		opacity: 0.5;
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
			opacity: 1;

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

	&__title,
	&__content {
		flex: 1 1 100%;
		max-width: 100%;
		padding: 0;
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
				color: var(--color-error);
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
	}
}
</style>
