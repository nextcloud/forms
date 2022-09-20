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
	<NcAppContent v-if="isLoadingForm">
		<NcEmptyContent :title="t('forms', 'Loading {title} …', { title: form.title })">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else>
		<!-- Show results & sidebar button -->
		<TopBar :permissions="form?.permissions"
			:sidebar-opened="sidebarOpened"
			@update:sidebarOpened="onSidebarChange"
			@share-form="onShareForm" />
		<!-- Forms title & description-->
		<header>
			<h2>
				<label class="hidden-visually" for="form-title">{{ t('forms', 'Form title') }}</label>
				<textarea id="form-title"
					ref="title"
					v-model="form.title"
					class="form-title"
					rows="1"
					:minlength="0"
					:maxlength="maxStringLengths.formTitle"
					:placeholder="t('forms', 'Form title')"
					:required="true"
					autofocus
					type="text"
					@input="onTitleChange" />
			</h2>
			<label class="hidden-visually" for="form-desc">{{ t('forms', 'Description') }}</label>
			<textarea ref="description"
				v-model="form.description"
				class="form-desc"
				rows="1"
				:maxlength="maxStringLengths.formDescription"
				:placeholder="t('forms', 'Description')"
				@input="onDescChange" />
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

			<!-- Add new questions menu -->
			<div class="question-menu">
				<NcActions ref="questionMenu"
					:open.sync="questionMenuOpened"
					:menu-title="t('forms', 'Add a question')"
					:aria-label="t('forms', 'Add a question')"
					:primary="true">
					<template #icon>
						<NcLoadingIcon v-if="isLoadingQuestions" :size="20" />
						<IconPlus v-else :size="20" />
					</template>
					<NcActionButton v-for="(answer, type) in answerTypesFilter"
						:key="answer.label"
						:close-after-click="true"
						:disabled="isLoadingQuestions"
						class="question-menu__question"
						@click="addQuestion(type)">
						<template #icon>
							<Icon :is="answer.icon" :size="20" />
						</template>
						{{ answer.label }}
					</NcActionButton>
				</NcActions>
			</div>
		</section>
	</NcAppContent>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import Draggable from 'vuedraggable'
import IconPlus from 'vue-material-design-icons/Plus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton'
import NcActions from '@nextcloud/vue/dist/Components/NcActions'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon'

import answerTypes from '../models/AnswerTypes.js'
import Question from '../components/Questions/Question.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import TopBar from '../components/TopBar.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'
import logger from '../utils/Logger.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'

window.axios = axios

export default {
	name: 'Create',
	components: {
		Draggable,
		IconPlus,
		NcActionButton,
		NcActions,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
		TopBar,
	},

	mixins: [ViewsMixin],

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

		// Remove properties from answerTypes for create button
		answerTypesFilter() {
			// Extract property datetime from answerTypes and copy rest to filteredAnswerTypes
			const { datetime, ...filteredAnswerTypes } = answerTypes
			return filteredAnswerTypes
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
		this.autoSizeTitle()
		this.autoSizeDescription()
	},

	methods: {
		onTitleChange() {
			this.autoSizeTitle()
			this.saveTitle()
		},
		onDescChange() {
			this.autoSizeDescription()
			this.saveDescription()
		},
		onSidebarChange(newState) {
			this.$emit('update:sidebarOpened', newState)
		},

		/**
		 * Title & description save methods
		 */
		saveTitle: debounce(async function() {
			this.saveFormProperty('title')
		}, 200),
		saveDescription: debounce(async function() {
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/question'), {
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
				logger.error('Error while adding new question', { error })
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
				await axios.delete(generateOcsUrl('apps/forms/api/v2/question/{id}', { id }))
				const index = this.form.questions.findIndex(search => search.id === id)
				this.form.questions.splice(index, 1)
			} catch (error) {
				logger.error(`Error while removing question ${id}`, { error })
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
				await axios.post(generateOcsUrl('apps/forms/api/v2/question/reorder'), {
					formId: this.form.id,
					newOrder,
				})
			} catch (error) {
				logger.error('Error while saving form', { error })
				showError(t('forms', 'Error while saving form'))
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
		 * Auto adjust the title height based on lines number
		 */
		async autoSizeTitle() {
			this.$nextTick(() => {
				const textarea = this.$refs.title
				if (textarea) {
					textarea.style.cssText = 'height:auto'
					textarea.style.cssText = `height: ${textarea.scrollHeight}px`
				}
			})
		},
		/**
		 * Auto adjust the description height based on lines number
		 */
		async autoSizeDescription() {
			this.$nextTick(() => {
				const textarea = this.$refs.description
				if (textarea) {
					textarea.style.cssText = 'height:auto'
					textarea.style.cssText = `height: ${textarea.scrollHeight}px`
				}
			})
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
			line-height: 34px;
			min-height: 36px;
			margin: 32px 0;
			padding-left: 14px; // align with description (compensate font size diff)
			padding-bottom: 4px;
			overflow: hidden;
			text-overflow: ellipsis;
			resize: none;
		}
		.form-desc {
			font-size: 100%;
			line-height: 150%;
			padding-bottom: 20px;
			margin: 0px;
			resize: none;
		}

		.info-message {
			font-size: 100%;
			padding-bottom: 20px;
			margin-top: 4px;
			resize: none;
			color: var(--color-text-maxcontrast);
		}
	}

	// Questions container
	section {
		position: relative;
		display: flex;
		flex-direction: column;
		margin-bottom: 250px;

		.question-menu {
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
		}
	}
}
</style>
