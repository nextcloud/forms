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
		<div class="question__drag-handle icon-drag-handle"
			:aria-label="t('forms', 'Drag to re-order the questions')" />

		<!-- Header -->
		<div class="question__header">
			<input v-if="edit"
				:placeholder="t('forms', 'Enter a title for this question')"
				:aria-label="t('forms', 'The title of the question number {index}', {index})"
				:value="title"
				class="question__header-title"
				type="text"
				minlength="1"
				maxlength="256"
				required
				@input="onInput">
			<h3 v-else class="question__header-title" v-text="title" />
			<Actions class="question__header-menu" :force-menu="true">
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

export default {
	name: 'Question',

	directives: {
		ClickOutside,
	},

	components: {
		Actions,
		ActionButton,
	},

	props: {
		id: {
			type: Number,
			required: true,
		},
		index: {
			type: Number,
			required: true,
		},
		title: {
			type: String,
			required: true,
		},
		edit: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
		onInput({ target }) {
			this.$emit('update:title', target.value)
		},

		/**
		 * Enable the edit mode
		 */
		enableEdit() {
			this.$emit('update:edit', true)
		},

		/**
		 * Disable the edit mode
		 */
		disableEdit() {
			this.$emit('update:edit', false)
		},

		/**
		 * Delete this question
		 */
		onDelete() {
			this.$emit('delete', this.id)
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
	margin-bottom: 22px;
	padding-left: 44px;
	// room for the new question menu
	padding-right: 44px;
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
		cursor: grab;
		&:active {
			cursor: grabbing;
		}
	}

	&__title,
	&__content {
		flex: 1 1 100%;
		max-width: 100%;
		margin: 20px;
		padding: 0;
	}

	&__header {
		display: flex;
		align-items: center;
		flex: 1 1 100%;
		justify-content: space-between;
		width: auto;
		margin: 20px;
		margin-bottom: 0;

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
			line-height: 22px;
		}

		&-title[type=text] {
			border-bottom-color: var(--color-border-dark);
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
