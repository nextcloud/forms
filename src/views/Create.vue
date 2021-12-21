<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  - @author Nick Gallo
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AppContent v-if="isLoadingForm">
		<EmptyContent icon="icon-loading">
			{{ t('forms', 'Loading {title} …', { title: form.title }) }}
		</EmptyContent>
	</AppContent>

	<AppContent v-else>
		<!-- Show results & sidebar button -->
		<TopBar>
			<template #default>
				<button @click="showResults">
					<span class="icon-comment" role="img" />
					<!-- TRANSLATORS Button to switch to the Result-View -->
					{{ t('forms', 'Results') }}
				</button>
				<button v-if="!sidebarOpened" @click="copyInternalShareLink($event, form.hash)">
					<span class="icon-clippy" role="img" />
					{{ t('forms', 'Share link') }}
				</button>
			</template>
			<template #small>
				<button v-tooltip="t('forms', 'Toggle settings')"
					@click="toggleSidebar">
					<span class="icon-menu-sidebar" role="img" />
				</button>
			</template>
		</TopBar>

		<!-- Forms title & description-->
		<header>
			<h2>
				<label class="hidden-visually" for="form-title">{{ t('forms', 'Form title') }}</label>
				<input id="form-title"
					ref="title"
					v-model="form.title"
					class="form-title"
					:minlength="0"
					:maxlength="maxStringLengths.formTitle"
					:placeholder="t('forms', 'Form title')"
					:required="true"
					autofocus
					type="text"
					@click="selectIfUnchanged"
					@keyup="onTitleChange">
			</h2>
			<label class="hidden-visually" for="form-desc">{{ t('forms', 'Description') }}</label>
			<textarea ref="description"
				v-model="form.description"
				class="form-desc"
				:maxlength="maxStringLengths.formDescription"
				:placeholder="t('forms', 'Description')"
				@change="autoSizeDescription"
				@keydown="autoSizeDescription"
				@keyup="onDescChange" />
			<!-- Generate form information message-->
			<p class="info-message" v-text="infoMessage" />
		</header>

		<section>
			<!-- Questions list -->
			<Draggable v-model="form.questions"
				:animation="200"
				tag="ul"
				handle=".question__drag-handle"
				@change="onQuestionOrderChange"
				@start="isDragging = true"
				@end="isDragging = false">
				<Questions :is="answerTypes[question.type].component"
					v-for="(question, index) in form.questions"
					ref="questions"
					:key="question.id"
					:answer-type="answerTypes[question.type]"
					:index="index + 1"
					:max-string-lengths="maxStringLengths"
					v-bind.sync="form.questions[index]"
					@delete="deleteQuestion(question)" />
			</Draggable>

			<!-- Add new questions toolbar -->
			<div class="question-toolbar" role="toolbar">
				<Actions ref="questionMenu"
					:open.sync="questionMenuOpened"
					:menu-title="t('forms', 'Add a question')"
					:primary="true"
					:default-icon="isLoadingQuestions ? 'icon-loading-small' : 'icon-add-primary'">
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
		</section>
	</AppContent>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
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
import QuestionMultiple from '../components/Questions/QuestionMultiple'
import QuestionShort from '../components/Questions/QuestionShort'
import TopBar from '../components/TopBar'
import ShareLinkMixin from '../mixins/ShareLinkMixin'
import ViewsMixin from '../mixins/ViewsMixin'
import SetWindowTitle from '../utils/SetWindowTitle'
import OcsResponse2Data from '../utils/OcsResponse2Data'

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
		TopBar,
	},

	mixins: [ViewsMixin, ShareLinkMixin],

	props: {
		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			maxStringLengths: loadState('forms', 'maxStringLengths'),

			questionMenuOpened: false,
			answerTypes,

			// Various states
			isLoadingQuestions: false,
			isDragging: false,
		}
	},

	computed: {
		/**
		 * Return form title, or placeholder if not set
		 *
		 * @return {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		hasQuestions() {
			return this.form.questions && this.form.questions.length === 0
		},

		isRequiredUsed() {
			return this.form.questions.reduce((isUsed, question) => isUsed || question.isRequired, false)
		},

		infoMessage() {
			let message = ''
			if (this.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}

			// On Submit, this is dependent on `isLoggedIn`. Create-view is always logged in and the variable isLoggedIn does not exist.
			if (!this.form.isAnonymous && true) {
				message += t('forms', 'Responses are connected to your Nextcloud account.')
			}

			if (this.isRequiredUsed) {
				message += ' ' + t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		},
	},

	watch: {
		// Fetch full form on change
		hash() {
			this.fetchFullForm(this.form.id)
		},

		// Update Window-Title on title change
		'form.title'() {
			SetWindowTitle(this.formTitle)
		},
	},

	beforeMount() {
		this.fetchFullForm(this.form.id)
		SetWindowTitle(this.formTitle)
	},

	updated() {
		this.autoSizeDescription()
	},

	methods: {
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
			const text = ''
			this.isLoadingQuestions = true

			try {
				const response = await axios.post(generateOcsUrl('apps/forms/api/v1.1/question'), {
					formId: this.form.id,
					type,
					text,
				})
				const question = OcsResponse2Data(response)

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
		 * @param {object} question the question to delete
		 * @param {number} question.id the question id to delete
		 */
		async deleteQuestion({ id }) {
			this.isLoadingQuestions = true

			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v1.1/question/{id}', { id }))
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
				await axios.post(generateOcsUrl('apps/forms/api/v1.1/question/reorder'), {
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
			this.$emit('update:sidebarOpened', !this.sidebarOpened)
		},

		/**
		 * Select the text in the input if it is still set to 'Form title'
		 *
		 * @param {Event} e the click event
		 */
		selectIfUnchanged(e) {
			if (e.target && e.target.value === t('forms', 'Form title')) {
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
.app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	header,
	section {
		width: 100%;
		max-width: 750px;
	}

	// Title & description header
	header {
		display: flex;
		flex-direction: column;
		margin-top: 44px;
		margin-bottom: 24px;

		h2 {
			margin-bottom: 0; // because the input field has enough padding
		}

		.form-title,
		.form-desc,
		.info-message {
			width: 100%;
			padding: 0 16px;
			border: none;
		}
		.form-title {
			font-size: 28px;
			font-weight: bold;
			color: var(--color-main-text);
			min-height: 36px;
			margin: 32px 0;
			padding-left: 14px; // align with description (compensate font size diff)
			padding-bottom: 6px; // align with h2 of .form-title on submit page
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.form-desc {
			font-size: 100%;
			line-height: 150%;
			padding-bottom: 20px;
			resize: none;
		}

		.info-message {
			font-size: 100%;
			padding-bottom: 20px;
			resize: none;
			color: var(--color-text-maxcontrast);
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
			bottom: 0px;
			padding-bottom: 16px;
			display: flex;
			align-items: center;
			align-self: flex-start;

			// To align with Drag-Handle
			margin-left: 16px;

			.icon-add-white {
				opacity: 1;
			}
		}
	}
}
</style>
