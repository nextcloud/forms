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
		<header v-click-outside="disableEdit" @click="enableEdit">
			<h2>
				<label class="hidden-visually" for="form-title">{{ t('forms', 'Form title') }}</label>
				<textarea id="form-title"
					ref="title"
					v-model="form.title"
					class="form-title"
					rows="1"
					:maxlength="maxStringLengths.formTitle"
					:placeholder="t('forms', 'Form title')"
					:readonly="!edit"
					:required="true"
					autofocus
					@input="onTitleChange" />
			</h2>
			<template v-if="edit">
				<label class="hidden-visually" for="form-desc">
					{{ t('forms', 'Description') }}
				</label>
				<textarea id="form-desc"
					ref="description"
					class="form-desc form-desc__input"
					rows="1"
					:value="form.description"
					:placeholder="t('forms', 'Description (formatting using Markdown is supported)')"
					:maxlength="maxStringLengths.formDescription"
					@input="updateDescription" />
			</template>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div v-else class="form-desc form-desc__output" v-html="formDescription" />
			<!-- Show expiration message-->
			<p v-if="form.expires && form.showExpiration" class="info-message">
				{{ expirationMessage }}
			</p>
			<!-- Generate form information message-->
			<p v-if="infoMessage" class="info-message">
				{{ infoMessage }}
			</p>
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
import { directive as ClickOutside } from 'v-click-outside'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import debounce from 'debounce'
import Draggable from 'vuedraggable'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import IconPlus from 'vue-material-design-icons/Plus.vue'

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

	directives: {
		ClickOutside,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			answerTypes,
			edit: false,

			// Various states
			isLoadingQuestions: false,
			isDragging: false,

			maxStringLengths: loadState('forms', 'maxStringLengths'),
			questionMenuOpened: false,
		}
	},

	computed: {
		hasQuestions() {
			return this.form.questions && this.form.questions.length === 0
		},

		isRequiredUsed() {
			return this.form.questions.reduce((isUsed, question) => isUsed || question.isRequired, false)
		},

		/**
		 * Check if form is expired
		 */
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
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

		expirationMessage() {
			const relativeDate = moment(this.form.expires, 'X').fromNow()
			if (this.isExpired) {
				return t('forms', 'Expired {relativeDate}.', { relativeDate })
			}
			return t('forms', 'Expires {relativeDate}.', { relativeDate })
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
			this.initEdit()
		},

		// Update Window-Title on title change
		'form.title'() {
			SetWindowTitle(this.formTitle)
		},

		// resize description if form is loaded
		isLoadingForm(value) {
			if (!value && this.edit) {
				this.resizeTitle()
				this.resizeDescription()
			}
		},
	},

	mounted() {
		this.fetchFullForm(this.form.id)
		SetWindowTitle(this.formTitle)
		this.initEdit()
	},

	methods: {
		onTitleChange() {
			this.resizeTitle()
			this.saveTitle()
		},

		disableEdit() {
			// Keep edit if no title set
			if (this.form.title) {
				this.edit = false
				this.$refs.title.style.height = 'auto'
			}
		},

		enableEdit() {
			this.edit = true
			this.resizeDescription()
			this.resizeTitle()
		},

		initEdit() {
			if (this.form.title) {
				this.edit = false
			} else {
				this.edit = true
			}
		},

		/**
		 * Auto adjust the title height based its scroll height
		 */
		resizeTitle() {
			this.$nextTick(() => {
				const textarea = this.$refs.title
				textarea.style.cssText = 'height:auto'
				// include 2px border
				textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
			})
		},

		/**
		 * Auto adjust the description height based on its scroll height
		 */
		resizeDescription() {
			// nextTick to ensure textarea is attached to DOM
			this.$nextTick(() => {
				const textarea = this.$refs.description
				textarea.style.cssText = 'height:auto'
				// include 2px border
				textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
			})
		},

		/**
		 * Update the description
		 *
		 * @param {InputEvent} ev The input event of the textarea
		 */
		updateDescription({ target }) {
			this.form.description = target.value
			this.resizeDescription()
			this.saveDescription()
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2.1/question'), {
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

				emit('forms:last-updated:set', this.form.id)

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
				await axios.delete(generateOcsUrl('apps/forms/api/v2.1/question/{id}', { id }))
				const index = this.form.questions.findIndex(search => search.id === id)
				this.form.questions.splice(index, 1)
				emit('forms:last-updated:set', this.form.id)
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
				await axios.post(generateOcsUrl('apps/forms/api/v2.1/question/reorder'), {
					formId: this.form.id,
					newOrder,
				})
				emit('forms:last-updated:set', this.form.id)
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
	},
}
</script>

<style lang="scss" scoped>
@import '../scssmixins/markdownOutput';

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
		margin-left: 56px;

		.form-title {
			font-size: 28px;
			font-weight: bold;
			line-height: 34px;
			color: var(--color-main-text);
			min-height: 36px;
			// padding and margin should be aligned with the submit view (but keep the 2px border in mind)
			padding: 4px 14px;
			margin: 22px 0 14px;
			width: calc(100% - 56px); // margin of header, needed if screen is < 806px (max-width + margin-left)
			overflow: hidden;
			text-overflow: ellipsis;
			resize: none;

			&:read-only {
				border-color: transparent;
			}
			&::placeholder {
				font-size: 28px;
			}
		}

		.form-desc,
		.info-message {
			font-size: 100%;
			min-height: unset;
			padding: 0px 16px 20px;
			width: calc(100% - 56px);
		}

		.form-desc {
			color: var(--color-text-maxcontrast);
			line-height: 1.5em;
			min-height: 48px; // one line (25px padding + 1.5em text height), CSS calc will round incorrectly to hardcoded
			padding-top: 5px; // spacing border<>text
			margin: 0px;

			&__input {
				padding: 3px 14px 18px; // 2px smaller because of border
				resize: none;
			}

			// Styling for rendered Output
			&__output {
				@include markdown-output;
			}
		}

		.info-message {
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

			// To align with text
			margin-left: 44px;
		}
	}
}
</style>
