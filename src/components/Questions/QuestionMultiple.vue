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
	<Question :title="title" :edit.sync="edit" @update:title="onTitleChange">
		<ul class="question__content">
			<template v-for="(answer, index) in values">
				<li :key="index" class="question__item">
					<input :id="`${id}-check-${index}`"
						ref="checkbox"
						:checked="false"
						:readonly="true"
						type="checkbox"
						class="checkbox question__checkbox">
					<label v-if="!edit"
						ref="label"
						:for="`${id}-check-${index}`"
						class="question__label">{{ answer }}</label>
					<!-- TODO: properly choose max length -->
					<input v-else
						ref="input"
						:aria-label="t('forms', 'An answer for checkbox {index}', { index: index + 1 })"
						:placeholder="t('forms', 'Answer for checkbox {index}', { index: index + 1 })"
						:value="answer"
						class="question__input"
						maxlength="256"
						minlength="1"
						type="text"
						@input="onInput(index)"
						@keydown.enter.prevent="addNewEntry"
						@keydown.delete="deleteEntry($event, index)">

					<!-- Delete answer -->
					<Actions v-if="edit">
						<ActionButton icon="icon-close" @click="deleteEntry($event, index)">
							{{ t('forms', 'Delete answer') }}
						</ActionButton>
					</Actions>
				</li>
			</template>
			<li v-if="edit && !isLastEmpty" class="question__item">
				<!-- TODO: properly choose max length -->
				<input
					:aria-label="t('forms', 'Add a new checkbox')"
					:placeholder="t('forms', 'Add a new checkbox')"
					class="question__input"
					maxlength="256"
					minlength="1"
					type="text"
					@click="addNewEntry">
			</li>
		</ul>
	</Question>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import QuestionMixin from '../../mixins/QuestionMixin'
import GenRandomId from '../../utils/GenRandomId'

export default {
	name: 'QuestionMultiple',

	components: {
		Actions,
		ActionButton,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			id: GenRandomId(),
		}
	},

	computed: {
		isLastEmpty() {
			const value = this.values[this.values.length - 1]
			return value && value.trim().length === 0
		},
	},

	watch: {
		edit(edit) {
			if (!edit) {
				// Filter and update questions
				this.$emit('update:values', this.values.filter(answer => !!answer))
			}
		},
	},

	methods: {
		onInput(index) {
			// Update values
			const input = this.$refs.input[index]
			const values = this.values.slice()
			values[index] = input.value

			// Update question
			this.$emit('update:values', values)
		},

		addNewEntry() {
			// Add entry
			const values = this.values.slice()
			values.push('')

			// Update question
			this.$emit('update:values', values)

			this.$nextTick(() => {
				this.focusIndex(values.length - 1)
			})
		},

		deleteEntry(e, index) {
			const input = this.$refs.input[index]

			if (input.value.length === 0) {
				// Dismiss delete action
				e.preventDefault()

				// Remove entry
				const values = this.values.slice()
				values.splice(index, 1)

				// Update question
				this.$emit('update:values', values)

				this.$nextTick(() => {
					 this.focusNext(index)
				})
			}
		},

		/**
		 * Focus the input matching the index
		 *
		 * @param {Number} index the value index
		 */
		focusIndex(index) {
			const input = this.$refs.input[index]
			if (input) {
				 input.focus()
			}
		},
	},
}
</script>

<style lang="scss">
.question__content {
	display: flex;
	flex-direction: column;
}

.question__item {
	display: inline-flex;
	align-items: center;
	height: 44px;

	.question__label {
		flex: 1 1 100%;
		&::before {
			margin: 14px !important;
		}
	}
}

// Using type to have a higher order than the input styling of server
.question__input[type=text] {
	width: 100%;
	min-height: 44px;
	margin: 0;
	padding: 6px 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;
}

</style>
