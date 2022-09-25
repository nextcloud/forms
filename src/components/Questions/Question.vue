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

		<!-- Header -->
		<div class="question__header">
			<div class="question__header__title">
				<input v-if="edit || !questionValid"
					:placeholder="titlePlaceholder"
					:aria-label="t('forms', 'Title of question number {index}', {index})"
					:value="text"
					class="question__header__title__text"
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
					class="question__header__title__menu">
					<NcActionCheckbox :checked="isRequired"
						@update:checked="onRequiredChange">
						<!-- TRANSLATORS Making this question necessary to be answered when submitting to a form -->
						{{ t('forms', 'Required') }}
					</NcActionCheckbox>
					<NcActionCheckbox v-if="shuffleOptions !== undefined"
						:checked="shuffleOptions"
						@update:checked="onShuffleOptionsChange">
						{{ t('forms', 'Shuffle options') }}
					</NcActionCheckbox>
					<NcActionButton @click="onDelete">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Delete question') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div class="question__header__description">
				<textarea v-if="edit || !questionValid"
					ref="description"
					rows="1"
					:value="description"
					:maxlength="maxStringLengths.questionDescription"
					:placeholder="t('forms', 'Description')"
					class="question__header__description__input"
					@input="onDescriptionChange" />

				<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
				<!-- eslint-disable-next-line -->
				<p v-else class="question__header__description__output">{{ description }}</p>
			</div>
		</div>

		<!-- Question content -->
		<slot />
	</li>
</template>

<script>
import { directive as ClickOutside } from 'v-click-outside'
import NcActions from '@nextcloud/vue/dist/Components/NcActions'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox'

import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline'
import IconDelete from 'vue-material-design-icons/Delete'
import IconDragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant'

export default {
	name: 'Question',

	directives: {
		ClickOutside,
	},

	components: {
		IconAlertCircleOutline,
		IconDelete,
		IconDragHorizontalVariant,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
	},

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
		shuffleOptions: {
			type: Boolean,
			default: undefined,
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
	},

	methods: {
		onTitleChange({ target }) {
			this.$emit('update:text', target.value)
		},

		onDescriptionChange({ target }) {
			this.autoSizeDescription()
			this.$emit('update:description', target.value)
		},

		onRequiredChange(isRequired) {
			this.$emit('update:isRequired', isRequired)
		},

		onShuffleOptionsChange(shuffleOptions) {
			this.$emit('update:shuffleOptions', shuffleOptions)
		},

		/**
		 * Enable the edit mode
		 */
		enableEdit() {
			if (!this.readOnly) {
				this.$emit('update:edit', true)
			}
			this.autoSizeDescription()
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

		/**
		 * Auto adjust the description height based on lines number
		 */
		async autoSizeDescription() {
			this.$nextTick(() => {
				const textarea = this.$refs.description
				if (textarea) {
					textarea.style.cssText = 'height:auto'
					textarea.style.cssText = `height: ${textarea.scrollHeight + 5}px`
				}
			})
		},
	},
}
</script>

<style lang="scss" scoped>
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
		padding-bottom: 10px;
		align-items: center;
		flex: 1 1 100%;
		justify-content: space-between;
		width: auto;

		// Using type to have a higher order than the input styling of server
		&__title {
			display: flex;
			height: 44px;

			&__text,
			&__text[type=text] {
				flex: 1 1 100%;
				min-height: 22px;
				margin: 0;
				padding: 0;
				padding-top: 14px;
				color: var(--color-text-light);
				border: 0;
				border-bottom: 1px dotted transparent;
				border-radius: 0;
				font-size: 16px;
				font-weight: bold;
				line-height: 22px;
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
			white-space: pre-wrap;

			&__input {
				margin: 0px;
				margin-bottom: -5px;
				min-height: 1.3em;
				border: none;
				resize: none;
			}

			&__input,
			&__output {
				width: 100%;
				font-size: 14px;
				color: var(--color-text-maxcontrast) !important;
				line-height: 1.3em;
				padding: 0px;
				z-index: inherit;
				overflow-wrap: break-word;
			}
		}
	}
}

</style>
