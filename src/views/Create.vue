<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  - @author Nick Gallo
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
	<AppContent v-if="isLoadingForm">
		<EmptyContent icon="icon-loading">
			{{ t('forms', 'Loading form “{title}”', { title: form.title }) }}
		</EmptyContent>
	</AppContent>

	<AppContent v-else>
		<!-- Show results & sidebar button -->
		<TopBar>
			<button class="primary" @click="showResults">
				<span class="icon-forms-white" role="img" />
				{{ t('forms', 'Show results') }}
			</button>
			<button v-tooltip="t('forms', 'Toggle settings')"
				:aria-label="t('forms', 'Toggle settings')"
				@click="toggleSidebar">
				<span class="icon-settings" role="img" />
			</button>
		</TopBar>

		<!-- Forms title & description-->
		<header>
			<label class="hidden-visually" for="form-title">{{ t('forms', 'Title') }}</label>
			<input
				id="form-title"
				v-model="form.title"
				:minlength="0"
				:placeholder="t('forms', 'Title')"
				:required="true"
				autofocus
				type="text"
				@change="onTitleChange"
				@click="selectIfUnchanged">
			<label class="hidden-visually" for="form-desc">{{ t('forms', 'Description') }}</label>
			<textarea
				id="form-desc"
				ref="description"
				v-model="form.description"
				:placeholder="t('forms', 'Description')"
				@change="onDescChange"
				@keydown="autoSizeDescription" />
		</header>

		<section>
			<!-- Add new questions toolbar -->
			<div class="question-toolbar" role="toolbar">
				<Actions ref="questionMenu"
					v-tooltip="t('forms', 'Add a question to this form')"
					:aria-label="t('forms', 'Add a question to this form')"
					:open.sync="questionMenuOpened"
					:default-icon="isLoadingQuestions ? 'icon-loading-small' : 'icon-add-white'">
					<ActionButton v-for="(answer, type) in answerTypes"
						:key="answer.label"
						:close-after-click="true"
						:disabled="isLoadingQuestions"
						:icon="answer.icon"
						class="question-toolbar__question"
						@click="addQuestion(type)">
						{{ answer.label }}
					</ActionButton>
				</Actions>
			</div>

			<!-- No questions -->
			<EmptyContent v-if="hasQuestions">
				{{ t('forms', 'This form does not have any questions') }}
				<template #desc>
					<button class="empty-content__button primary" @click="openQuestionMenu">
						<span class="icon-add-white" />
						{{ t('forms', 'Add a new one') }}
					</button>
				</template>
			</EmptyContent>

			<!-- Questions list -->
			<form @submit.prevent="onSubmit">
				<Draggable v-model="form.questions"
					:animation="200"
					tag="ul"
					@change="onQuestionOrderChange"
					@start="isDragging = true"
					@end="isDragging = false">
					<Questions
						:is="answerTypes[question.type].component"
						v-for="(question, index) in form.questions"
						ref="questions"
						:key="question.id"
						:model="answerTypes[question.type]"
						:index="index + 1"
						v-bind.sync="question"
						@delete="deleteQuestion(question)" />
				</Draggable>
			</form>
		</section>
	</AppContent>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import Draggable from 'vuedraggable'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'

import answerTypes from '../models/AnswerTypes'
import EmptyContent from '../components/EmptyContent'
import Question from '../components/Questions/Question'
import QuestionLong from '../components/Questions/QuestionLong'
import QuestionShort from '../components/Questions/QuestionShort'
import QuestionMultiple from '../components/Questions/QuestionMultiple'
import QuizFormItem from '../components/quizFormItem'
import TopBar from '../components/TopBar'
import ViewsMixin from '../mixins/ViewsMixin'

window.axios = axios

export default {
	name: 'Create',
	components: {
		ActionButton,
		Actions,
		AppContent,
		Draggable,
		EmptyContent,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
		QuizFormItem,
		TopBar,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			questionMenuOpened: false,
			answerTypes,

			// Various states
			isLoadingForm: true,
			isLoadingQuestions: false,
			errorForm: false,

			isDragging: false,
		}
	},

	computed: {
		title() {
			if (this.form.title === '') {
				return t('forms', 'Create new form')
			} else {
				return this.form.title
			}
		},
		hasQuestions() {
			return this.form.questions && this.form.questions.length === 0
		},
	},

	watch: {
		// Fetch full form on change
		hash() {
			// TODO: cancel previous request if not done
			this.fetchFullForm(this.form.id)
		},
	},

	beforeMount() {
		this.fetchFullForm(this.form.id)
	},

	updated() {
		this.autoSizeDescription()
	},

	methods: {
		/**
		 * Fetch the full form data and update parent
		 *
		 * @param {number} id the unique form hash
		 */
		async fetchFullForm(id) {
			this.isLoadingForm = true
			console.debug('Loading form', id)

			try {
				const form = await axios.get(generateUrl('/apps/forms/api/v1/form/{id}', { id }))
				this.$emit('update:form', form.data)
			} catch (error) {
				console.error(error)
				this.errorForm = true
			} finally {
				this.isLoadingForm = false
			}
		},

		/**
		 * Save form on submit
		 */
		onSubmit: debounce(function() {
			this.saveForm()
		}, 200),

		/**
		 * Title & description save methods
		 */
		onTitleChange: debounce(function() {
			this.saveFormProperty('title')
		}, 200),
		onDescChange: debounce(function() {
			this.saveFormProperty('description')
		}, 200),

		/**
		 * Add a new question to the current form
		 *
		 * @param {string} type the question type, see AnswerTypes
		 */
		async addQuestion(type) {
			const text = t('forms', 'New question')
			this.isLoadingQuestions = true

			try {
				const response = await axios.post(generateUrl('/apps/forms/api/v1/question'), {
					formId: this.form.id,
					type,
					text,
				})
				const question = response.data

				// Add newly created question
				this.form.questions.push(Object.assign({
					text,
					type,
					answers: [],
				}, question))

				// Focus newly added question
				this.$nextTick(() => {
					const lastQuestion = this.$refs.questions[this.$refs.questions.length - 1]
					lastQuestion.focus()
				})

			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while adding the new question'))
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Delete a question
		 *
		 * @param {Object} question the question to delete
		 * @param {number} question.id the question id to delete
		 */
		async deleteQuestion({ id }) {
			this.isLoadingQuestions = true

			try {
				await axios.delete(generateUrl('/apps/forms/api/v1/question/{id}', { id }))
				const index = this.form.questions.findIndex(search => search.id === id)
				this.form.questions.splice(index, 1)
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while removing the question'))
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Reorder questions on dragEnd
		 */
		async onQuestionOrderChange() {
			this.isLoadingQuestions = true
			const newOrder = this.form.questions.map(question => question.id)

			try {
				await axios.post(generateUrl('/apps/forms/api/v1/question/reorder'), {
					formId: this.form.id,
					newOrder,
				})
			} catch (error) {
				showError(t('forms', 'Error while saving form'))
				console.error(error)
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Add question methods
		 */
		openQuestionMenu() {
			// TODO: fix the vue components to allow external click triggers without
			// conflicting with the click outside directive
			setTimeout(() => {
				this.questionMenuOpened = true
				this.$nextTick(() => {
					this.$refs.questionMenu.focusFirstAction()
				})
			}, 10)
		},

		/**
		 * Topbar methods
		 */
		showResults() {
			this.$router.push({
				name: 'results',
				params: {
					hash: this.form.hash,
				},
			})
		},
		toggleSidebar() {
			emit('toggleSidebar')
		},

		/**
		 * Select the text in the input if it is still set to 'New form'
		 * @param {Event} e the click event
		 */
		selectIfUnchanged(e) {
			if (e.target && e.target.value === t('forms', 'New form')) {
				e.target.select()
			}
		},

		/**
		 * Auto adjust the description height based on lines number
		 */
		autoSizeDescription() {
			const textarea = this.$refs.description
			if (textarea) {
				textarea.style.cssText = 'height:auto; padding:0'
				textarea.style.cssText = `height: ${textarea.scrollHeight + 20}px`
			}
		},
	},
}
</script>

<style lang="scss">
#app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	header,
	section {
		width: 100%;
		max-width: 900px;
	}

	// Title & description header
	header {
		display: flex;
		flex-direction: column;
		margin: 44px;

		#form-title,
		#form-desc {
			width: 100%;
			margin: 10px; // aerate the header
			padding: 0; // makes alignment and desc height calc easier
			border: none;
		}
		#form-title {
			font-size: 2em;
		}
		#form-desc {
			// make sure height calculations are correct
			box-sizing: content-box !important;
			min-height: 60px;
			max-height: 200px;
			padding-left: 2px; // align with title (compensate font size diff)
			resize: none;
		}
	}

	.empty-content__button {
		margin: 5px;
		> span {
			margin-right: 5px;
			cursor: pointer;
			opacity: 1;
		}
	}

	// Questions container
	section {
		position: relative;
		display: flex;
		flex-direction: column;
		margin-bottom: 250px;

		.question-toolbar {
			position: sticky;
			// Above other menus
			z-index: 55;
			top: var(--header-height);
			display: flex;
			align-items: center;
			align-self: flex-end;
			width: 44px;
			height: var(--top-bar-height);
			// make sure this doesn't take any space and appear floating
			margin-top: -44px;

			.icon-add-white {
				opacity: 1;
				border-radius: 50%;
				// TODO: standardize on components
				background-color: var(--color-primary-element);
				&:hover,
				&:focus,
				&:active {
					background-color: var(--color-primary-element-light) !important;
				}
			}
		}
	}
}

/* Transitions for inserting and removing list items */
/*.list-enter-active,
.list-leave-active {
	transition: all .5s ease;
}

.list-enter,
.list-leave-to {
	opacity: 0;
}

.list-move {
	transition: transform .5s;
}

#form-item-selector-text {
	> input {
		width: 100%;
	}
}

.form-table {
	> li {
		display: flex;
		overflow: hidden;
		align-items: baseline;
		min-height: 24px;
		padding-right: 8px;
		padding-left: 8px;
		white-space: nowrap;
		border-bottom: 1px solid var(--color-border);
		line-height: 24px;

		&:active,
		&:hover {
			transition: var(--background-dark) .3s ease;
			background-color: var(--color-background-dark); //$hover-color;
		}

		> div {
			display: flex;
			flex-grow: 1;
			padding-right: 4px;
			white-space: normal;
			opacity: .7;
			font-size: 1.2em;
			&.avatar {
				flex-grow: 0;
			}
		}

		> div:nth-last-child(1) {
			flex-grow: 0;
			flex-shrink: 0;
			justify-content: center;
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
	min-width: 16px;
	min-height: 16px;
	margin: 0;
	padding: 10px;
	padding-left: 34px;
	text-align: left;
	background-repeat: no-repeat;
	background-position: 10px center;
} */

</style>
