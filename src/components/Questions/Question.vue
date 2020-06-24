<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
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
			class="question__drag-handle icon-drag-handle"
			:class="{'question__drag-handle--shiftup': shiftDragHandle}"
			:aria-label="t('forms', 'Drag to reorder the questions')" />

		<!-- Header -->
		<div class="question__header">
			<input v-if="edit || !text"
				ref="titleInput"
				:placeholder="titlePlaceholder"
				:aria-label="t('forms', 'Title of question number {index}', {index})"
				:value="text"
				class="question__header-title"
				type="text"
				minlength="1"
				:maxlength="maxQuestionLength"
				required
				@input="onTitleChange"
				@keydown.shift.tab.capture="nextDisableEdit">
			<h3 v-else
				class="question__header-title"
				:tabindex="computedTitleTabIndex"
				@focus="onTitleFocus"
				v-text="computedText" />
			<div v-if="!edit && !questionValid"
				v-tooltip.auto="warningInvalid"
				class="question__header-warning icon-error-color"
				tabindex="0" />
			<Actions v-if="!readOnly" class="question__header-menu" :force-menu="true">
				<ActionCheckbox :checked="mandatory"
					@update:checked="onMandatoryChange">
					{{ t('forms', 'Required') }}
				</ActionCheckbox>
				<ActionButton icon="icon-delete" @click="onDelete">
					{{ t('forms', 'Delete question') }}
				</ActionButton>
			</Actions>
		</div>

		<!-- Question content -->
		<slot />
	</li>
</template>

<script>
import { directive as ClickOutside } from 'v-click-outside'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'

export default {
	name: 'Question',

	directives: {
		ClickOutside,
	},

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
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
		mandatory: {
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
		maxQuestionLength: {
			type: Number,
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
		 * Extend text with asterisk if question is mandatory
		 * @returns {Boolean}
		 */
		computedText() {
			if (this.mandatory) {
				return this.text + ' *'
			}
			return this.text
		},

		/**
		 * Question valid, if text not empty and content valid
		 * @returns {Boolean} true if question valid
		 */
		questionValid() {
			return this.text && this.contentValid
		},

		/**
		 * Only allow tabbing the title when necessary for edit.
		 * @returns {Number}
		 */
		computedTitleTabIndex() {
			if (!this.readOnly) {
				return 0
			}
			return -1
		},
	},

	methods: {
		onTitleChange({ target }) {
			this.$emit('update:text', target.value)
		},

		onMandatoryChange(mandatory) {
			this.$emit('update:mandatory', mandatory)
		},

		/**
		 * Allow edit-navigation through Tab-Key
		 */
		onTitleFocus() {
			console.debug('On Title focus', this.$refs)
			if (!this.readOnly) {
				this.enableEdit()
				console.debug('Store NextTick')
				this.$nextTick(() => {
					console.debug('NextTick!')
					this.$refs.titleInput.focus()
				})
			}
		},

		/**
		 * Enable the edit mode
		 */
		enableEdit() {
			console.debug('enableEdit')
			if (!this.readOnly) {
				this.$emit('update:edit', true)
			}
		},

		/**
		 * Disable the edit mode
		 */
		disableEdit() {
			console.debug('disableEdit')
			if (!this.readOnly) {
				this.$emit('update:edit', false)
			}
		},

		nextDisableEdit(event) {
			console.debug('nextDisable')
			console.debug(event)
			// event.preventDefault()
			// this.disableEdit()
			this.$nextTick(() => {
				console.debug('Later!')
			})
			console.debug('trigger')
			// this.disableEdit()
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
		left: 0;
		width: 44px;
		height: 100%;
		opacity: .5;

		// Avoid moving drag-handle due to newAnswer-input on multiple-Questions
		&--shiftup {
			height: calc(100% - 44px);
		}

		&:hover,
		&:focus {
			opacity: 1;
		}
		cursor: grab;

		&:active {
			cursor: grabbing;
		}
	}

	&__title,
	&__content {
		flex: 1 1 100%;
		max-width: 100%;
		padding: 0;
	}

	&__header {
		display: flex;
		align-items: center;
		flex: 1 1 100%;
		justify-content: space-between;
		width: auto;

		// Using type to have a higher order than the input styling of server
		&-title,
		&-title[type=text] {
			flex: 1 1 100%;
			min-height: 22px;
			margin: 0;
			padding: 0;
			padding-bottom: 6px;
			color: var(--color-text-light);
			border: 0;
			border-bottom: 1px dotted transparent;
			border-radius: 0;
			font-size: 16px;
			font-weight: bold;
			line-height: 22px;
		}

		&-title[type=text] {
			border-bottom-color: var(--color-border-dark);
		}

		&-warning {
			padding: 22px;
		}

		&-menu.action-item {
			position: sticky;
			top: var(--header-height);
			// above other actions
			z-index: 50;
		}
	}
}

</style>
