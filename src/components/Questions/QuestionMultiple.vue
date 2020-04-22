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
	<Question
		:id="id"
		v-bind.sync="$attrs"
		:text="text"
		:edit.sync="edit"
		@delete="onDelete"
		@update:text="onTitleChange">
		<ul class="question__content" :role="isUnique ? 'radiogroup' : ''">
			<template v-for="(answer, index) in options">
				<li v-if="!edit" :key="answer.id" class="question__item">
					<!-- Answer radio/checkbox + label -->
					<!-- TODO: migrate to radio/checkbox component once available -->
					<input :id="`${id}-answer-${answer.id}`"
						ref="checkbox"
						:aria-checked="isChecked(answer.id)"
						:checked="isChecked(answer.id)"
						:class="{
							'radio question__radio': isUnique,
							'checkbox question__checkbox': !isUnique,
						}"
						:name="`${id}-answer`"
						:readonly="true"
						:type="isUnique ? 'radio' : 'checkbox'">
					<label v-if="!edit"
						ref="label"
						:for="`${id}-answer-${answer.id}`"
						class="question__label">{{ answer.text }}</label>
				</li>

				<!-- Answer text input edit -->
				<AnswerInput v-else
					:key="index /* using index to keep the same vnode after new answer creation */"
					ref="input"
					:answer="answer"
					:index="index"
					@add="addNewEntry"
					@delete="deleteAnswer"
					@update:answer="updateAnswer"
					@restore="restoreAnswer" />
			</template>

			<li v-if="(edit && !isLastEmpty) || hasNoAnswer" class="question__item">
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
import AnswerInput from './AnswerInput'
import QuestionMixin from '../../mixins/QuestionMixin'
import GenRandomId from '../../utils/GenRandomId'

// Implementations docs
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/radio/radio.html
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/checkbox/checkbox-2.html
export default {
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
	},

	mixins: [QuestionMixin],

	props: {
		id: {
			type: Number,
			required: true,
		},
	},

	computed: {
		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim().length === 0
		},

		isUnique() {
			return this.model.unique === true
		},

		hasNoAnswer() {
			return this.options.length === 0
		},
	},

	watch: {
		edit(edit) {
			if (!edit) {
				// Filter out empty options and update question
				this.$emit('update:options', this.options.filter(answer => !!answer.text))
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
		 * Update the options
		 * @param {Array} options options to change
		 */
		updateOptions(options) {
			this.$emit('update:options', options)
		},

		/**
		 * Update an existing answer locally
		 *
		 * @param {string|number} id the answer id
		 * @param {Object} answer the answer to update
		 */
		updateAnswer(id, answer) {
			const options = this.options.slice()
			const answerIndex = options.findIndex(option => option.id === id)
			options[answerIndex] = answer

			this.updateOptions(options)
		},

		/**
		 * Add a new empty answer locally
		 */
		addNewEntry() {
			// Add local entry
			const options = this.options.slice()
			options.push({
				id: GenRandomId(),
				question_id: this.id,
				text: '',
				local: true,
			})

			// Update question
			this.updateOptions(options)

			this.$nextTick(() => {
				this.focusIndex(options.length - 1)
			})
		},

		/**
		 * Restore an answer locally
		 *
		 * @param {Object} answer the answer
		 * @param {number} index the answer index in this.options
		 */
		restoreAnswer(answer, index) {
			const options = this.options.slice()
			options.splice(index, 0, answer)

			this.updateOptions(options)
			this.focusIndex(index)
		},

		/**
		 * Delete an answer locally
		 *
		 * @param {number} id the answer is
		 * @param {number} index the answer index in this.options
		 */
		deleteAnswer(id, index) {
			// Remove entry
			const options = this.options.slice()
			const optionIndex = options.findIndex(option => option.id === id)
			options.splice(optionIndex, 1)

			// Update question
			this.updateOptions(options)

			this.$nextTick(() => {
				this.focusIndex(index + 1)
			})
		},

		/**
		 * Focus the input matching the index
		 *
		 * @param {Number} index the value index
		 */
		focusIndex(index) {
			const inputs = this.$refs.input
			if (inputs && inputs[index]) {
				const input = inputs[index]
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
