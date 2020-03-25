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
					v-model="form.event.title"
					:class="{ error: titleEmpty }"
					type="text">

				<label>{{ t('forms', 'Description') }}</label>
				<textarea id="formDesc" v-model="form.event.description" style="resize: vertical; width: 100%;" />
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
						<option v-for="option in options" :key="option.value" :value="option.value">
							{{ option.text }}
						</option>
					</select>
					<input v-model="newQuizQuestion" :placeholder="t('forms', 'Add Question')" @keyup.enter="addQuestion()">
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
					<li
						is="quiz-form-item"
						v-for="(question, index) in form.options.formQuizQuestions"
						:key="question.id"
						:question="question"
						:type="question.type"
						@add-answer="addAnswer"
						@remove-answer="removeAnswer"
						@remove="form.options.formQuizQuestions.splice(index, 1)" />
				</transitionGroup>
			</div>
		</div>
	</AppContent>
</template>

<script>
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
			newQuizAnswer: '',
			newQuizQuestion: '',
			nextQuizAnswerId: 1,
			nextQuizQuestionId: 1,
			writingForm: false,
			loadingForm: true,
			titleEmpty: false,
			selected: '',
			uniqueName: false,
			uniqueAns: false,
			haveAns: false,
			options: [
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
			if (this.form.event.title === '') {
				return t('forms', 'Create new form')
			} else {
				return this.form.event.title

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
			this.form.event.owner = OC.getCurrentUser().uid
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

		checkNames() {
			this.uniqueName = true
			this.form.options.formQuizQuestions.forEach(q => {
				if (q.text === this.newQuizQuestion) {
					this.uniqueName = false
				}
			})
		},

		addQuestion() {
			this.checkNames()
			if (this.selected === '') {
				showError(t('forms', 'Select a question type!'), { duration: 3000 })
			} else if (!this.uniqueName) {
				showError(t('forms', 'Cannot have the same question!'))
			} else {
				if (this.newQuizQuestion !== null & this.newQuizQuestion !== '' & (/\S/.test(this.newQuizQuestion))) {
					this.form.options.formQuizQuestions.push({
						id: this.nextQuizQuestionId++,
						text: this.newQuizQuestion,
						type: this.selected,
						answers: [],
					})
				}
				this.newQuizQuestion = ''
			}
		},

		checkAnsNames(item, question) {
			this.uniqueAnsName = true
			question.answers.forEach(q => {
				if (q.text === item.newQuizAnswer) {
					this.uniqueAnsName = false
				}
			})
		},

		removeAnswer(item, question, index) {
			item.formQuizAnswers.splice(index, 1)
			question.answers.splice(index, 1)
		},

		addAnswer(item, question) {
			this.checkAnsNames(item, question)
			if (!this.uniqueAnsName) {
				showError(t('forms', 'Two answers cannot be the same!'), { duration: 3000 })
			} else {
				if (item.newQuizAnswer !== null & item.newQuizAnswer !== '' & (/\S/.test(item.newQuizAnswer))) {
					item.formQuizAnswers.push({
						id: item.nextQuizAnswerId,
						text: item.newQuizAnswer,
					})
					question.answers.push({
						id: item.nextQuizAnswerId,
						text: item.newQuizAnswer,
					})
					item.nextQuizAnswerId++
				}
				item.newQuizAnswer = ''
			}
		},

		allHaveAns() {
			this.haveAns = true
			this.form.options.formQuizQuestions.forEach(q => {
				if (q.type !== 'text' && q.type !== 'comment' && q.answers.length === 0) {
					this.haveAns = false
				}
			})
		},

		debounceWriteForm: debounce(function() {
			this.writeForm()
		}, 200),

		writeForm() {
			this.allHaveAns()
			if (this.form.event.title.length === 0 | !(/\S/.test(this.form.event.title))) {
				this.titleEmpty = true
				showError(t('forms', 'Title must not be empty!'), { duration: 3000 })
			} else if (!this.haveAns) {
				showError(t('forms', 'All questions need answers!'), { duration: 3000 })
			} else if (this.form.event.expiration & this.form.event.expirationDate === '') {
				showError(t('forms', 'Need to pick an expiration date!'), { duration: 3000 })
			} else {
				this.writingForm = true
				this.titleEmpty = false

				axios.post(OC.generateUrl('apps/forms/write/form'), this.form)
					.then((response) => {
						this.form.mode = 'edit'
						this.form.event.hash = response.data.hash
						this.form.event.id = response.data.id
						this.writingForm = false
						showSuccess(t('forms', '%n successfully saved', 1, this.form.event.title), { duration: 3000 })
					}, (error) => {
						this.form.event.hash = ''
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
