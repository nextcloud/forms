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
	<Question :index="index"
		:title="title"
		:edit.sync="edit"
		@update:title="onTitleChange">
		<ul class="question__content" :role="isUnique ? 'radiogroup' : ''">
			<template v-for="(answer, index) in options">
				<li :key="index" class="question__item">
					<!-- Answer radio/checkbox + label -->
					<!-- TODO: migrate to radio/checkbox component once ready -->
					<input :id="`${id}-answer-${index}`"
						ref="checkbox"
						:aria-checked="isChecked(index)"
						:checked="isChecked(index)"
						:class="{
							'radio question__radio': isUnique,
							'checkbox question__checkbox': !isUnique,
						}"
						:name="`${id}-answer`"
						:readonly="true"
						:type="isUnique ? 'radio' : 'checkbox'">
					<label v-if="!edit"
						ref="label"
						:for="`${id}-answer-${index}`"
						class="question__label">{{ answer }}</label>

					<!-- Answer text input edit -->
					<!-- TODO: properly choose max length -->
					<input v-else
						ref="input"
						:aria-label="t('forms', 'An answer for the {index} option', { index: index + 1 })"
						:placeholder="t('forms', 'Answer number {index}', { index: index + 1 })"
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
					:aria-label="t('forms', 'Add a new answer')"
					:placeholder="t('forms', 'Add a new answer')"
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

// Implementations docs
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/radio/radio.html
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/checkbox/checkbox-2.html
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

		isUnique() {
			return this.model.unique === true
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

		/**
		 * Is the provided index checked
		 * @param {number} index the option index
		 * @returns {boolean}
		 */
		isChecked(index) {
			// TODO implement based on answers
			return false
		},

		/**
		 * Update the values
		 * @param {Array} values values to change
		 */
		updateValues(values) {
			this.$emit('update:values', this.isUnique ? [values[0]] : values)
		},

		onInput(index) {
			// Update values
			const input = this.$refs.input[index]
			const values = this.values.slice()
			values[index] = input.value

			// Update question
			this.updateValues(values)
		},

		addNewEntry() {
			// Add entry
			const values = this.values.slice()
			values.push('')

			// Update question
			this.updateValues(values)

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
				this.updateValues(values)

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

	// make sure to respect readonly on radio/checkbox
	input[readonly] {
		pointer-events: none;
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
