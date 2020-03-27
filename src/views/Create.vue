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
		<!-- Show results & sidebar button -->
		<TopBar>
			<button class="primary" @click="showResults">
				<span class="icon-forms-white" role="img" />
				{{ t('forms', 'Show results') }}
			</button>
			<button v-tooltip="t('forms', 'Toggle settings')" @click="toggleSidebar">
				<span class="icon-settings" role="img" />
			</button>
		</TopBar>

		<!-- Forms title & description-->
		<header>
			<label class="hidden-visually" for="form-title">{{ t('forms', 'Title') }}</label>
			<input
				id="form-title"
				v-model="form.form.title"
				:minlength="0"
				:placeholder="t('forms', 'Title')"
				:required="true"
				autofocus
				type="text">
			<label class="hidden-visually" for="form-desc">{{ t('forms', 'Description') }}</label>
			<textarea
				id="form-desc"
				ref="description"
				v-model="form.form.description"
				:placeholder="t('forms', 'Description')"
				@keydown="autoSizeDescription" />
		</header>

		<section>
			<!-- Add new questions toolbar -->
			<!-- <div class="question-toolbar" role="toolbar">
				<button v-for="type in answerTypes"
					:key="type.label"
					class="question-toolbar__question"
					@click="addQuestion">
					<span class="question-toolbar__icon" :class="type.icon" />
					{{ type.label }}
				</button>
			</div> -->

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

			<!-- No questions -->
			<EmptyContent v-if="form.questions.length === 0">
				{{ t('forms', 'This form does not have any questions') }}
				<template #desc>
					<button class="primary" @click="openQuestionMenu">
						{{ t('forms', 'Add a new one') }}
					</button>
				</template>
			</EmptyContent>

			<!-- Questions list -->
			<transitionGroup
				v-else
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
		</section>
	</AppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import debounce from 'debounce'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'

import answerTypes from '../models/AnswerTypes'
import EmptyContent from '../components/EmptyContent'
import QuizFormItem from '../components/quizFormItem'
import TopBar from '../components/TopBar'

import ViewsMixin from '../mixins/ViewsMixin'

export default {
	name: 'Create',
	components: {
		ActionButton,
		Actions,
		AppContent,
		EmptyContent,
		QuizFormItem,
		TopBar,
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
			answerTypes,
		}
	},

	computed: {
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
		if (this.$route.name === 'edit') {
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
					const respData = response.data

					this.form.questions.push({
						id: respData.id,
						order: respData.order,
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

		autoSizeDescription() {
			const textarea = this.$refs.description
			textarea.style.cssText = 'height:auto; padding:0'
			textarea.style.cssText = `height: ${textarea.scrollHeight + 20}px`
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

		/**
		 * Topbar methods
		 */
		showResults() {
			this.$router.push({
				name: 'results',
				params: {
					hash: this.form.event.hash,
				},
			})
		},
		toggleSidebar() {
			emit('toggleSidebar')
		},

		/**
		 * Add question methods
		 */
		openQuestionMenu() {
			this.$refs.questionMenu.opened = true
		},
	},
}
</script>

<style lang="scss">
#app-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	header,
	section {
		width: 100%;
		max-width: 900px;
	}

	header {
		display: flex;
		flex-direction: column;
		margin: 44px;

		#form-title,
		#form-desc {
			width: 100%;
			border: none;
			margin: 10px; // aerate the header
			padding: 0; // makes alignment and desc height calc easier
		}
		#form-title {
			font-size: 2em;
		}
		#form-desc {
			min-height: 60px;
			max-height: 200px;
			padding-left: 2px; // align with title (compensate font size diff)
			resize: none
		}
	}

	section {
		position: relative;
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
