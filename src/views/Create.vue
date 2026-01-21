<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent
		:page-heading="
			form.title ? t('forms', 'Edit form') : t('forms', 'Create form')
		">
		<!-- Show results & sidebar button -->
		<TopBar
			:archived="isFormArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebar-opened="sidebarOpened"
			:submission-count="form?.submissionCount"
			@share-form="onShareForm" />

		<NcEmptyContent
			v-if="isLoadingForm"
			class="emtpycontent"
			:name="t('forms', 'Loading {title} …', { title: form.title })">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isFormArchived"
			class="emtpycontent"
			:name="t('forms', 'Form is archived')"
			:description="
				t('forms', 'Form \'{title}\' is archived and cannot be modified.', {
					title: form.title,
				})
			">
			<template #icon>
				<IconLock :size="64" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isFormLocked"
			class="emtpycontent"
			:name="t('forms', 'Form is locked')"
			:description="
				t(
					'forms',
					'Form \'{title}\' is locked by {lockedBy} and cannot be modified. The lock expires: {lockedUntil}',
					{
						title: form.title,
						lockedBy: form.lockedBy,
						lockedUntil:
							form.lockedUntil === 0
								? t('forms', 'never')
								: lockedUntilFormatted,
					},
				)
			">
			<template #icon>
				<IconLock :size="64" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<!-- Forms title & description-->
			<header>
				<h2>
					<label class="hidden-visually" for="form-title">{{
						t('forms', 'Form title')
					}}</label>
					<textarea
						id="form-title"
						ref="title"
						v-model="form.title"
						class="form-title"
						rows="1"
						dir="auto"
						:maxlength="maxStringLengths.formTitle"
						:placeholder="t('forms', 'Form title')"
						required
						autofocus
						@keydown.enter.prevent
						@input="onTitleChange" />
				</h2>
				<label class="hidden-visually" for="form-desc">
					{{ t('forms', 'Description') }}
				</label>
				<textarea
					id="form-desc"
					ref="description"
					class="form-desc"
					rows="1"
					dir="auto"
					:value="form.description"
					:placeholder="
						t(
							'forms',
							'Description (formatting using Markdown is supported)',
						)
					"
					:maxlength="maxStringLengths.formDescription"
					@input="updateDescription" />
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
				<Draggable
					v-model="form.questions"
					:animation="200"
					tag="ul"
					handle=".question__drag-handle"
					@change="onQuestionOrderChange"
					@start="isDragging = true"
					@end="isDragging = false">
					<transition-group
						:name="
							isDragging
								? 'no-external-transition-on-drag'
								: 'question-list'
						">
						<component
							:is="answerTypes[question.type].component"
							v-for="(question, index) in form.questions"
							ref="questions"
							:key="question.id"
							:can-move-down="index < form.questions.length - 1"
							:can-move-up="index > 0"
							:answer-type="answerTypes[question.type]"
							:index="index + 1"
							:max-string-lengths="maxStringLengths"
							v-bind.sync="form.questions[index]"
							@clone="cloneQuestion(question)"
							@delete="deleteQuestion(question.id)"
							@move-down="onMoveDown(index)"
							@move-up="onMoveUp(index)" />
					</transition-group>
				</Draggable>

				<!-- Add new questions menu -->
				<div class="question-menu">
					<NcActions
						:open.sync="questionMenuOpened"
						:menu-name="t('forms', 'Add a question')"
						:aria-label="t('forms', 'Add a question')"
						primary>
						<template #icon>
							<NcLoadingIcon v-if="isLoadingQuestions" :size="20" />
							<IconPlus v-else :size="20" />
						</template>

						<template v-if="!activeQuestionType">
							<NcActionButton
								v-for="(answer, type) in answerTypesFilter"
								:key="answer.label"
								:close-after-click="!answer.subtypes"
								:disabled="isLoadingQuestions"
								class="question-menu__question"
								@click="
									answer.subtypes
										? (activeQuestionType = type)
										: addQuestion(type)
								">
								<template #icon>
									<component :is="answer.icon" :size="20" />
								</template>
								{{ answer.label }}
							</NcActionButton>
						</template>

						<template v-else>
							<NcActionButton
								:disabled="isLoadingQuestions"
								class="question-menu__question"
								@click="activeQuestionType = null">
								<template #icon>
									<IconChevronLeft :size="20" />
								</template>
								{{ t('forms', 'Grid') }}
							</NcActionButton>
							<NcActionSeparator />

							<NcActionButton
								v-for="(answer, type) in answerTypesFilter[
									activeQuestionType
								].subtypes"
								:key="'subtype-' + answer.label"
								close-after-click
								:disabled="isLoadingQuestions"
								class="question-menu__question"
								@click="addQuestion(activeQuestionType, type)">
								<template #icon>
									<component :is="answer.icon" :size="20" />
								</template>
								{{ answer.label }}
							</NcActionButton>
						</template>
					</NcActions>
				</div>
			</section>
		</template>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import Draggable from 'vuedraggable'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconLock from 'vue-material-design-icons/LockOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import Question from '../components/Questions/Question.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import TopBar from '../components/TopBar.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'
import answerTypes from '../models/AnswerTypes.js'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'

window.axios = axios

export default {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Create',
	components: {
		Draggable,
		IconChevronLeft,
		IconLock,
		IconPlus,
		NcActionButton,
		NcActionSeparator,
		NcActions,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
		TopBar,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			answerTypes,

			// Various states
			isLoadingQuestions: false,
			isDragging: false,

			maxStringLengths: loadState('forms', 'maxStringLengths'),
			questionMenuOpened: false,
			activeQuestionType: null,
		}
	},

	computed: {
		hasQuestions() {
			return this.form.questions && this.form.questions.length === 0
		},

		isRequiredUsed() {
			return this.form.questions.reduce(
				(isUsed, question) => isUsed || question.isRequired,
				false,
			)
		},

		/**
		 * Check if form is expired
		 */
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		/**
		 * Check if the form was archived
		 */
		isFormArchived() {
			return this.form.state === FormState.FormArchived
		},

		infoMessage() {
			let message = ''
			if (this.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}

			// On Submit, this is dependent on `isLoggedIn`. Create-view is always logged in and the variable isLoggedIn does not exist.
			if (!this.form.isAnonymous && true) {
				message += t('forms', 'Responses are connected to your account.')
			}

			if (this.isRequiredUsed) {
				message +=
					' '
					+ t('forms', 'An asterisk (*) indicates mandatory questions.')
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
			// Remove 'datetime' from answerTypes for create button
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			const { datetime, ...filteredAnswerTypes } = answerTypes
			return filteredAnswerTypes
		},

		lockedUntilFormatted() {
			return moment(this.form.lockedUntil, 'X').fromNow()
		},
	},

	watch: {
		// Fetch full form on change
		hash() {
			this.fetchFullForm(this.form.id)
		},

		// Update Window-Title on title change
		'form.title': function () {
			SetWindowTitle(this.formTitle)
		},

		// resize description if form is loaded
		isLoadingForm(value) {
			if (!value) {
				this.resizeTitle()
				this.resizeDescription()
			}
		},
	},

	mounted() {
		this.fetchFullForm(this.form.id)
		SetWindowTitle(this.formTitle)
	},

	methods: {
		onMoveUp(index) {
			if (index > 0) {
				;[this.form.questions[index - 1], this.form.questions[index]] = [
					this.form.questions[index],
					this.form.questions[index - 1],
				]
				this.onQuestionOrderChange()
			}
		},

		onMoveDown(index) {
			// only if not the last one
			if (index < this.form.questions.length - 1) {
				this.onMoveUp(index + 1)
			}
		},

		onTitleChange() {
			this.resizeTitle()
			this.saveTitle()
		},

		/**
		 * Auto adjust the title height based its scroll height
		 */
		resizeTitle() {
			this.$nextTick(() => {
				const textarea = this.$refs.title
				textarea.style.cssText = 'height: 0'
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
				textarea.style.cssText = 'height: 0'
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
		saveTitle: debounce(async function () {
			this.saveFormProperty('title')
		}, INPUT_DEBOUNCE_MS),

		saveDescription: debounce(async function () {
			this.saveFormProperty('description')
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Add a new question to the current form
		 *
		 * @param {string} type the question type, see AnswerTypes
		 * @param {string|null} subtype the question subtype, see AnswerTypes.subtypes
		 */
		async addQuestion(type, subtype = null) {
			const text = ''
			this.isLoadingQuestions = true

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: this.form.id,
					}),
					{
						type,
						text,
						subtype,
					},
				)
				const question = OcsResponse2Data(response)

				// Add newly created question
				this.form.questions.push({
					text,
					type,
					answers: [],
					...question,
				})

				// Focus newly added question
				this.$nextTick(() => {
					const lastQuestion =
						this.$refs.questions[this.$refs.questions.length - 1]
					lastQuestion.focus()
				})

				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error('Error while adding new question', { error })
				showError(
					t('forms', 'There was an error while adding the new question'),
				)
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Delete a question
		 *
		 * @param {number} questionId the question id to delete
		 */
		async deleteQuestion(questionId) {
			this.isLoadingQuestions = true

			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{
							id: this.form.id,
							questionId,
						},
					),
				)
				const index = this.form.questions.findIndex(
					(search) => search.id === questionId,
				)
				this.form.questions.splice(index, 1)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error(`Error while removing question ${questionId}`, {
					error,
				})
				showError(
					t('forms', 'There was an error while removing the question'),
				)
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Clone a question
		 *
		 * @param {number} id the question id to clone in the current form
		 */
		async cloneQuestion({ id }) {
			this.isLoadingQuestions = true

			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
						{
							id: this.form.id,
							questionId: id,
						},
					),
				)
				const question = OcsResponse2Data(response)

				this.form.questions.push({
					answers: [],
					...question,
				})

				this.$nextTick(() => {
					const lastQuestion =
						this.$refs.questions[this.$refs.questions.length - 1]
					lastQuestion.focus()
				})
			} catch (error) {
				logger.error(`Error while duplicating question ${id}`, {
					error,
				})
				showError('There was an error while duplicating the question')
			} finally {
				this.isLoadingQuestions = false
			}
		},

		/**
		 * Reorder questions on dragEnd
		 */
		async onQuestionOrderChange() {
			this.isLoadingQuestions = true
			const newOrder = this.form.questions.map((question) => question.id)

			try {
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: this.form.id,
					}),
					{
						newOrder,
					},
				)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error('Error while saving form', { error })
				showError(t('forms', 'Error while saving form'))
			} finally {
				this.isLoadingQuestions = false
			}
		},
	},
}
</script>

<style lang="scss">
.question-list-move {
	transition: all 0.2s ease;
}
</style>

<style lang="scss" scoped>
@import '../scssmixins/markdownOutput';

.emptycontent {
	display: flex;
	height: 100%;
}

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
		margin: 0;
		margin-block-end: 24px;
		padding-inline-start: 32px;
		margin-inline-end: -24px;

		.form-title {
			font-size: 28px;
			font-weight: bold;
			line-height: 34px;
			color: var(--color-main-text);
			min-height: 36px;
			// padding and margin should be aligned with the submit view (but keep the 2px border in mind)
			padding-block: 4px;
			padding-inline: 10px;
			margin-block: 22px 14px;
			margin-inline: 0;
			width: calc(
				100% - 58px
			); // margin of header, needed if screen is < 806px (max-width + margin-left)
			overflow: hidden;
			text-overflow: ellipsis;
			resize: none;

			&::placeholder {
				font-size: 28px;
			}
		}

		.form-desc,
		.info-message {
			font-size: 100%;
			min-height: unset;
			padding-block: 0px 20px;
			padding-inline: 12px;
			width: calc(100% - 58px);
		}

		.form-desc {
			color: var(--color-main-text);
			line-height: 22px;
			min-height: 47px; // one line (25px padding + 22px text height)
			padding-block-start: 5px; // spacing border<>text
			margin: 0px;
			padding-block: 3px 18px; // 2px smaller because of border
			padding-inline: 10px;
			resize: none;
		}

		.info-message {
			margin-block-start: 4px;
			resize: none;
			color: var(--color-text-maxcontrast);
		}
	}

	// Questions container
	section {
		position: relative;
		display: flex;
		flex-direction: column;
		margin-block-end: 250px;

		.question-menu {
			position: sticky;
			inset-block-end: 0px;
			padding-block-end: 16px;
			// Above other menus
			z-index: 55;
			display: flex;
			align-items: center;
			align-self: flex-start;

			// To align with text
			margin-inline-start: var(--default-clickable-area);
		}
	}
}
</style>
