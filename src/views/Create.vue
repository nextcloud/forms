<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent
		:pageHeading="
			form.title ? t('forms', 'Edit form') : t('forms', 'Create form')
		">
		<!-- Show results & sidebar button -->
		<TopBar
			:archived="isFormArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebarOpened="sidebarOpened"
			:submissionCount="form?.submissionCount"
			@shareForm="onShareForm" />

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
				<NcIconSvgWrapper :svg="IconLock" :size="64" />
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
				<NcIconSvgWrapper :svg="IconLock" :size="64" />
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
					:animation="300"
					target=".sort-target"
					direction="vertical"
					invertSwap
					handle=".question__drag-handle"
					@change="onQuestionOrderChange"
					@start="onDragStart"
					@end="onDragEnd">
					<TransitionGroup
						tag="ul"
						:name="isDragging ? undefined : 'question-list'"
						class="sort-target">
						<component
							:is="answerTypes[question.type].component"
							v-for="(question, index) in form.questions"
							:key="question.id"
							:ref="(el) => setQuestionRef(el, question)"
							v-bind="form.questions[index]"
							:canMoveDown="index < form.questions.length - 1"
							:canMoveUp="index > 0"
							:answerType="answerTypes[question.type]"
							:index="index + 1"
							:maxStringLengths="maxStringLengths"
							@update:text="
								(val) => (form.questions[index].text = val)
							"
							@update:description="
								(val) => (form.questions[index].description = val)
							"
							@update:isRequired="
								(val) => (form.questions[index].isRequired = val)
							"
							@update:name="
								(val) => (form.questions[index].name = val)
							"
							@update:extraSettings="
								(val) => (form.questions[index].extraSettings = val)
							"
							@update:options="
								(val) => (form.questions[index].options = val)
							"
							@clone="cloneQuestion(question, index)"
							@delete="deleteQuestion(question.id)"
							@moveDown="onMoveDown(index)"
							@moveUp="onMoveUp(index)">
							<template
								v-if="index < form.questions.length - 1"
								#insert>
								<div
									class="question-insert"
									:class="[
										{
											'is-open':
												insertMenuOpenedIndex === index,
										},
										{
											'is-mobile': isMobile,
										},
									]">
									<AddQuestionMenu
										:menuName="t('forms', 'Insert question')"
										:aria-label="
											t(
												'forms',
												'Insert question after question {index}',
												{ index: index + 1 },
											)
										"
										variant="tertiary"
										:position="index"
										:isLoadingQuestions="isLoadingQuestions"
										:answerTypesFilter="answerTypesFilter"
										:hasSubtypes="hasSubtypes"
										@update:open="
											(v) =>
												(insertMenuOpenedIndex = v
													? index
													: null)
										"
										@addQuestion="addQuestion" />
								</div>
							</template>
						</component>
					</TransitionGroup>
				</Draggable>

				<!-- Add new questions menu -->
				<div class="question-menu">
					<AddQuestionMenu
						v-model:open="questionMenuOpened"
						:menuName="t('forms', 'Add a question')"
						:aria-label="t('forms', 'Add a question')"
						:isLoadingQuestions="isLoadingQuestions"
						:answerTypesFilter="answerTypesFilter"
						:hasSubtypes="hasSubtypes"
						primary
						@addQuestion="addQuestion" />
				</div>
			</section>
		</template>
	</NcAppContent>
</template>

<script>
import IconLock from '@material-symbols/svg-400/outlined/lock.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import debounce from 'debounce'
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AddQuestionMenu from '../components/AddQuestionMenu.vue'
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
		NcIconSvgWrapper,
		AddQuestionMenu,
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

	setup() {
		return {
			IconLock,
			isMobile: useIsMobile(),
		}
	},

	data() {
		return {
			answerTypes,

			// Various states
			isLoadingQuestions: false,
			isDragging: false,

			maxStringLengths: loadState('forms', 'maxStringLengths'),
			questionMenuOpened: false,
			activeQuestionType: null,
			questionRefsMap: {},

			// when set to a number, the next created question will be inserted at this index
			insertMenuOpenedIndex: null,
			// controls per-question insert menu visibility
			insertMenuOpened: false,
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

		hasSubtypes() {
			return (answer) =>
				answer && answer.subtypes && Object.keys(answer.subtypes).length > 0
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
		onDragStart() {
			this.isDragging = true
		},

		onDragEnd() {
			this.$nextTick(() => {
				this.isDragging = false
			})
		},

		setQuestionRef(el, question) {
			if (el) {
				this.questionRefsMap[question.id] = el
			} else {
				delete this.questionRefsMap[question.id]
			}
		},

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
		 * @param {number|null} position where the new question should be added
		 */
		async addQuestion(type, subtype = null, position = null) {
			this.activeQuestionType = null
			const text = ''
			this.isLoadingQuestions = true

			try {
				const body = { type, text, subtype }
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: this.form.id,
					}),
					body,
				)
				const question = OcsResponse2Data(response)

				// Delegate insertion & focus handling to helper
				this.insertQuestion(question, { text, type, answers: [] }, position)
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

		insertQuestion(questionData, defaultFields = {}, position = null) {
			const newQuestionObj = {
				...defaultFields,
				...questionData,
			}

			let insertAt = null
			if (
				questionData
				&& questionData.order !== undefined
				&& questionData.order !== null
			) {
				insertAt = Number(questionData.order) - 1
			} else if (position !== null) {
				insertAt = position
			}

			if (insertAt !== null && insertAt <= this.form.questions.length) {
				this.form.questions.splice(insertAt, 0, newQuestionObj)
				this.$nextTick(() => {
					// Prefer ref by id when available, fallback to positional refs
					this.questionRefsMap[newQuestionObj.id]?.focus()
				})
			} else {
				this.form.questions.push(newQuestionObj)
				this.$nextTick(() => {
					this.questionRefsMap[newQuestionObj.id]?.focus()
				})
			}

			emit('forms:last-updated:set', this.form.id)
		},

		/**
		 * Clone a question
		 *
		 * @param {number} id the question id to clone in the current form
		 * @param {number} position where the cloned question should be added
		 */
		async cloneQuestion({ id }, position) {
			this.isLoadingQuestions = true

			try {
				const url = generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
					{
						id: this.form.id,
						questionId: id,
					},
				)

				const body = {}
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await axios.post(url, body)
				const question = OcsResponse2Data(response)

				// Delegate insertion & focus handling to helper
				this.insertQuestion(question, { answers: [] })
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

<style lang="scss" scoped>
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

.question-list-move,
.question-list-enter-active,
.question-list-leave-active {
	transition: all var(--animation-slow) ease;
}

.question-list-enter-from,
.question-list-leave-to {
	opacity: 0;
	transform: translateX(var(--clickable-area-large));
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.question-list-leave-active {
	position: absolute;
}

.question-insert {
	/* closer to the question above */
	position: relative;
	margin-block-end: -34px;
	inset-block-end: -16px;
	margin-inline-start: -12px;
	width: calc(100% - var(--default-clickable-area));
	display: flex;
	justify-content: center;
	opacity: 0;
	transition: opacity 0.12s ease;
}

.question-insert.is-mobile {
	opacity: 0.3;
}

.question:hover > .question-insert,
.question-insert:focus-within,
.question-insert.is-open {
	opacity: 1;
}
</style>
