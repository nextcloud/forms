<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<li v-click-outside="disableEdit"
		:class="{ 'question--edit': edit }"
		:aria-label="t('forms', 'Question number {index}', {index})"
		class="question"
		@click="enableEdit">
		<!-- Drag handle -->
		<!-- TODO: implement arrow key mapping to reorder question -->
		<div v-if="!readOnly"
			class="question__drag-handle"
			:class="{'question__drag-handle--shiftup': shiftDragHandle}"
			:aria-label="t('forms', 'Drag to reorder the questions')">
			<IconDragHorizontalVariant :size="20" />
		</div>
		<NcModal v-if="isModalOpen"
			size="normal"
			title="Add multiple options"
			:out-transition="true"
			:has-next="false"
			:has-previous="false"
			@close="closeModal">
			<div class="modal__content">
				<h1>Add multiple options {{ isModalOpen }}</h1>
				<textarea ref="input"
					v-model="inputedOptions"
					:aria-label="t('forms', 'An answer for the {index} option', { index: index + 1 })"
					:placeholder="t('forms', 'Answer number {index}', { index: index + 1 })"
					class="question__input"
					type="text" />
				<div class="options">
					<div v-for="(option, i) in multipleOptions" :key="i" class="single_option">
						<p>{{ option }}</p>
					</div>
				</div>
				<div>
					<NcButton @click="onMultipleOptions">
						Add options
					</NcButton>
				</div>
			</div>
		</NcModal>
		<!-- Header -->
		<div class="question__header">
			<div class="question__header__title">
				<input v-if="edit || !questionValid"
					:placeholder="titlePlaceholder"
					:aria-label="t('forms', 'Title of question number {index}', {index})"
					:value="text"
					class="question__header__title__text question__header__title__text__input"
					type="text"
					minlength="1"
					:maxlength="maxStringLengths.questionText"
					required
					@input="onTitleChange">
				<h3 v-else class="question__header__title__text" v-text="computedText" />
				<div v-if="!edit && !questionValid"
					v-tooltip.auto="warningInvalid"
					class="question__header__title__warning"
					tabindex="0">
					<IconAlertCircleOutline :size="20" />
				</div>
				<NcActions v-if="!readOnly"
					:id="actionsId"
					:container="'#' + actionsId"
					:force-menu="true"
					placement="bottom-end"
					class="question__header__title__menu">
					<NcActionCheckbox :checked="isRequired"
						@update:checked="onRequiredChange">
						<!-- TRANSLATORS Making this question necessary to be answered when submitting to a form -->
						{{ t('forms', 'Required') }}
					</NcActionCheckbox>
					<slot name="actions" />
					<NcActionButton
						@click="openModal">
						<template #icon>
							<IconContentPaste :size="20" />
						</template>
						{{ t('forms', 'Multiple Options') }}
					</NcActionButton>
					<NcActionButton @click="onDelete">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Delete question') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="hasDescription || edit || !questionValid" class="question__header__description">
				<textarea v-if="edit || !questionValid"
					ref="description"
					:value="description"
					:placeholder="t('forms', 'Description (formatting using Markdown is supported)')"
					:maxlength="maxStringLengths.questionDescription"
					class="question__header__description__input"
					@input="onDescriptionChange" />
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div v-else class="question__header__description__output" v-html="computedDescription" />
			</div>
		</div>

		<!-- Question content -->
		<slot />
	</li>
</template>

<script>
import { directive as ClickOutside } from 'v-click-outside'

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { showError } from '@nextcloud/dialogs'

import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconDragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'

export default {
	name: 'Question',

	directives: {
		ClickOutside,
	},

	components: {
		IconAlertCircleOutline,
		IconDelete,
		IconDragHorizontalVariant,
		IconContentPaste,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcModal,
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
		edit: {
			type: Boolean,
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
		contentValid: {
			type: Boolean,
			default: true,
		},
		warningInvalid: {
			type: String,
			default: t('forms', 'This question needs a title!'),
		},
	},

	data() {
		return {
			isModalOpen: false,
			inputedOptions: '',
		}
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

		hasDescription() {
			return this.description !== ''
		},
		multipleOptions() {
			const allOptions = this.inputedOptions.split(/\r?\n/g)
			return allOptions.filter(answer => { return answer.trim().length > 0 })
		},
	},
	watch: {
		edit(newEdit) {
			if (newEdit || !this.questionValid) {
				this.resizeDescription()
			}
		},
	},
	// Ensure description is sized correctly on initial render
	mounted() {
		this.$nextTick(() => this.resizeDescription())
	},
	methods: {
		closeModal() {
			this.isModalOpen = false
		},

		openModal() {
			this.isModalOpen = true
		},

		onMultipleOptions() {
			this.isModalOpen = false
			this.$nextTick(() => {
				this.$emit('update:edit', true)
				if (this.multipleOptions.length > 1) {
				// extract all options entries to parent
					this.$emit('multiple-answers', this.multipleOptions)
					this.inputedOptions = ''
					return
				}
				// in case of only one option, just show an error message because it is probably missuse of the feature
				showError(t('forms', 'Options should seperated by new line!'))
			})

		},

		onTitleChange({ target }) {
			this.$emit('update:text', target.value)
		},

		onDescriptionChange({ target }) {
			this.resizeDescription()
			this.$emit('update:description', target.value)
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
		 * Enable the edit mode
		 */
		enableEdit() {
			if (!this.readOnly) {
				this.$emit('update:edit', true)
			}
		},

		/**
		 * Disable the edit mode
		 */
		disableEdit() {
			if (!this.readOnly) {
				this.$emit('update:edit', false)
			}
		},

		/**
		 * Delete this question
		 */
		onDelete() {
			this.$emit('delete')
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
	margin-bottom: 80px;
	padding-left: 44px;
	user-select: none;
	background-color: var(--color-main-background);

	> * {
		cursor: pointer;
	}

	&__drag-handle {
		position: absolute;
		display: flex;
		left: 0;
		width: 44px;
		height: 100%;
		opacity: .5;
		cursor: grab;

		// Avoid moving drag-handle due to newAnswer-input on multiple-Questions
		&--shiftup {
			height: calc(100% - 44px);
		}

		&:hover,
		&:focus {
			opacity: 1;
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
		padding-bottom: 8px;
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
				padding: 10px 0px;
				font-weight: bold;
				margin: auto !important;

				&__input {
					position: relative;
					left: -12px;
					margin-right: -12px !important;
					padding-left: 10px !important;
				}
			}

			&__warning {
				margin: auto 4px auto 12px;
				color: var(--color-error);
			}

			&__menu.action-item {
				position: sticky;
				top: var(--header-height);
				// above other actions
				z-index: 50;
			}
		}

		&__description {
			display: flex;

			&__input {
				margin: 0px;
				min-height: 1.5em;
				border-width: 2px;
				position: relative;
				left: -12px;
				padding: 4px 10px;
				resize: none;
			}

			&__input,
			&__output {
				font-size: 14px;
				color: var(--color-text-maxcontrast) !important;
				line-height: 1.5em;
				z-index: inherit;
				overflow-wrap: break-word;
				width: calc(100% - 32px); // match with other inputs
			}
			&__output {
				padding: 6px 0; //compensate border
				@include markdown-output; // Styling for rendered Output
			}
		}
	}
}
.modal__content {
		padding: 20px;
		display: flex;
		flex-direction: column;
	}
.options{
  display: flex !important;
  flex-wrap: wrap;
  width: 100%;
  padding: 10px;
  justify-content: flex-start;
  max-height: 300px;
  overflow-y: auto;
  margin: 5px 0px;
}
.single_option{
 width: max-content;
 padding: 5px;
 margin: 5px;
 border: 1px solid var(--color-primary);
}

</style>
