<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  - @author Nick Gallo
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -
  - UPDATE: Adds Quiz option and takes the input:
  - is yet to store input of quizzes and cannot represtent them
  - requires quizFormItem.vue (should be added to svn)
  -->

<template>
	<AppContent>
		<div class="workbench">
			<div>
				<h2>{{ t('forms', 'Form description') }}</h2>

				<label>{{ t('forms', 'Title') }}</label>
				<input id="formTitle"
					v-model="form.form.title"
					:class="{ error: titleEmpty }"
					type="text">

				<label>{{ t('forms', 'Description') }}</label>
				<textarea id="formDesc" v-model="form.form.description" style="resize: vertical; width: 100%;" />
			</div>

			<div>
				<h2>{{ t('forms', 'Make a Form') }}</h2>
				<div id="quiz-form-selector-text">
					<!--shows inputs for question types: drop down box to select the type, text box for question, and button to add-->
					<label for="ans-type">Answer Type: </label>
					<select v-model="selected">
						<option value="" disabled>
							Select
						</option>
						<option v-for="type in questionTypes" :key="type.value" :value="type.value">
							{{ type.text }}
						</option>
					</select>
					<input
						v-model="newQuestion"
						:placeholder=" t('forms', 'Add Question') "
						maxlength="2048"
						@keyup.enter="addQuestion()">
					<button id="questButton"
						@click="addQuestion()">
						{{ t('forms', 'Add Question') }}
					</button>
				</div>
				<!--Transition group to list the already added questions (in the form of quizFormItems)-->
				<transitionGroup
					id="form-list"
					name="list"
					tag="ul"
					class="form-table">
					<QuizFormItem
						v-for="(question, index) in form.questions"
						:key="question.id"
						:question="question"
						:type="question.type"
						@addOption="addOption"
						@deleteOption="deleteOption"
						@deleteQuestion="deleteQuestion(question, index)" />
				</transitionGroup>
			</div>
		</div>
	</AppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import debounce from 'debounce'

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import { showError, showSuccess } from '@nextcloud/dialogs'

import QuizFormItem from '../components/quizFormItem'

import ViewsMixin from '../mixins/ViewsMixin'

export default {
	name: 'Create',
	components: {
		AppContent,
		QuizFormItem,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			placeholder: '',
			newOption: '',
			newQuestion: '',
			nextOptionId: 1,
			nextQuestionId: 1,
			writingForm: false,
			loadingForm: true,
			titleEmpty: false,
			selected: '',
			uniqueQuestionText: false,
			uniqueOptionText: false,
			allHaveOpt: false,
			questionTypes: [
				{ text: 'Radio Buttons', value: 'radiogroup' },
				{ text: 'Checkboxes', value: 'checkbox' },
				{ text: 'Short Response', value: 'text' },
				{ text: 'Long Response', value: 'comment' },
				{ text: 'Drop Down', value: 'dropdown' },
			],
		}
	},

	computed: {
		langShort() {
			return this.lang.split('-')[0]
		},

		title() {
			if (this.form.form.title === '') {
				return t('forms', 'Create new form')
			} else {
				return this.form.form.title

			}
		},

		saveButtonTitle() {
			if (this.writingForm) {
				return t('forms', 'Writing form')
			} else if (this.form.mode === 'edit') {
				return t('forms', 'Update form')
			} else {
				return t('forms', 'Done')
			}
		},

		localeData() {
			return moment.localeData(moment.locale(this.locale))
		},

	},

	watch: {
		title() {
			// only used when the title changes after page load
			document.title = t('forms', 'Forms') + ' - ' + this.title
		},

		form: {
			deep: true,
			handler: function() {
				this.debounceWriteForm()
			},
		},
	},

	created() {
		if (this.$route.name === 'create') {
			// TODO: manage this from Forms.vue, request a new form to the server
			this.form.form.owner = OC.getCurrentUser().uid
			this.loadingForm = false
		} else if (this.$route.name === 'edit') {
			// TODO: fetch & update form?
			this.form.mode = 'edit'
		} else if (this.$route.name === 'clone') {
			// TODO: CLONE
		}
	},

	methods: {

		switchSidebar() {
			this.sidebar = !this.sidebar
		},

		checkQuestionText() {
			this.uniqueQuestionText = true
			this.form.questions.forEach(q => {
				if (q.text === this.newQuestion) {
					this.uniqueQuestionText = false
				}
			})
		},

		async addQuestion() {
			this.checkQuestionText()
			if (this.selected === '') {
				showError(t('forms', 'Select a question type!'), { duration: 3000 })
			} else if (!this.uniqueQuestionText) {
				showError(t('forms', 'Cannot have the same question!'))
			} else {
				if (this.newQuestion !== null & this.newQuestion !== '' & (/\S/.test(this.newQuestion))) {
					const response = await axios.post(generateUrl('/apps/forms/api/v1/question/'), { formId: this.form.id, type: this.selected, text: this.newQuestion })
					const questionId = response.data

					this.form.questions.push({
						id: questionId,
						text: this.newQuestion,
						type: this.selected,
						options: [],
					})
				}
				this.newQuestion = ''
			}
		},

		async deleteQuestion(question, index) {
			await axios.delete(generateUrl('/apps/forms/api/v1/question/{id}', { id: question.id }))
			// TODO catch Error
			this.form.questions.splice(index, 1)
		},

		checkOptionText(item, question) {
			this.uniqueOptionText = true
			question.options.forEach(o => {
				if (o.text === item.newOption) {
					this.uniqueOptionText = false
				}
			})
		},

		async addOption(item, question) {
			this.checkOptionText(item, question)
			if (!this.uniqueOptionText) {
				showError(t('forms', 'Two options cannot be the same!'), { duration: 3000 })
			} else {
				if (item.newOption !== null & item.newOption !== '' & (/\S/.test(item.newOption))) {
					const response = await axios.post(generateUrl('/apps/forms/api/v1/option/'), { formId: this.form.id, questionId: question.id, text: item.newOption })
					const optionId = response.data

					question.options.push({
						id: optionId,
						text: item.newOption,
					})
				}
				item.newOption = ''
			}
		},

		async deleteOption(question, option, index) {
			await axios.delete(generateUrl('/apps/forms/api/v1/option/{id}', { id: option.id }))
			// TODO catch errors
			question.options.splice(index, 1)
		},

		checkAllHaveOpt() {
			this.allHaveOpt = true
			this.form.questions.forEach(q => {
				if (q.type !== 'text' && q.type !== 'comment' && q.options.length === 0) {
					this.allHaveOpt = false
				}
			})
		},

		debounceWriteForm: debounce(function() {
			this.writeForm()
		}, 200),

		writeForm() {
			this.checkAllHaveOpt()
			if (this.form.form.title.length === 0 | !(/\S/.test(this.form.form.title))) {
				this.titleEmpty = true
				showError(t('forms', 'Title must not be empty!'), { duration: 3000 })
			} else if (!this.allHaveOpt) {
				showError(t('forms', 'All questions need answers!'), { duration: 3000 })
			} else if (this.form.form.expires & this.form.form.expirationDate === '') {
				showError(t('forms', 'Need to pick an expiration date!'), { duration: 3000 })
			} else {
				this.writingForm = true
				this.titleEmpty = false

				axios.post(OC.generateUrl('apps/forms/write/form'), this.form)
					.then((response) => {
						this.form.mode = 'edit'
						this.form.form.hash = response.data.hash
						this.form.form.id = response.data.id
						this.writingForm = false
						showSuccess(t('forms', '%n successfully saved', 1, this.form.form.title), { duration: 3000 })
					}, (error) => {
						this.form.form.hash = ''
						this.writingForm = false
						showError(t('forms', 'Error on saving form, see console'))
						/* eslint-disable-next-line no-console */
						console.log(error.response)
					})
			}
		},
	},
}
</script>

<style lang="scss">
#app-content {
	input.hasTimepicker {
		width: 75px;
	}
}

.warning {
	color: var(--color-error);
	font-weight: bold;
}

.forms-content {
	display: flex;
	padding-top: 45px;
	flex-grow: 1;
}

input[type="text"] {
	display: block;
	width: 100%;
}

.workbench {
	display: flex;
	flex-grow: 1;
	flex-wrap: wrap;
	overflow-x: hidden;

	> div {
		min-width: 245px;
		max-width: 540px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
		padding: 8px;
	}
}

/* Transitions for inserting and removing list items */
.list-enter-active,
.list-leave-active {
	transition: all 0.5s ease;
}

.list-enter,
.list-leave-to {
	opacity: 0;
}

.list-move {
	transition: transform 0.5s;
}
/*  */

#form-item-selector-text {
	> input {
		width: 100%;
	}
}

.form-table {
	> li {
		display: flex;
		align-items: baseline;
		padding-left: 8px;
		padding-right: 8px;
		line-height: 24px;
		min-height: 24px;
		border-bottom: 1px solid var(--color-border);
		overflow: hidden;
		white-space: nowrap;

		&:active,
		&:hover {
			transition: var(--background-dark) 0.3s ease;
			background-color: var(--color-background-dark); //$hover-color;

		}

		> div {
			display: flex;
			flex-grow: 1;
			font-size: 1.2em;
			opacity: 0.7;
			white-space: normal;
			padding-right: 4px;
			&.avatar {
				flex-grow: 0;
			}
		}

		> div:nth-last-child(1) {
			justify-content: center;
			flex-grow: 0;
			flex-shrink: 0;
		}
	}
}

button {
	&.button-inline {
		border: 0;
		background-color: transparent;
	}
}

.tab {
	display: flex;
	flex-wrap: wrap;
}
.selectUnit {
	display: flex;
	align-items: center;
	flex-wrap: nowrap;
	> label {
		padding-right: 4px;
	}
}

#shiftDates {
	background-repeat: no-repeat;
	background-position: 10px center;
	min-width: 16px;
	min-height: 16px;
	padding: 10px;
	padding-left: 34px;
	text-align: left;
	margin: 0;
}
</style>
