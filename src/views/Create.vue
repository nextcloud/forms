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
			:name="
				t(
					'forms',
					'Loading {title} …',
					{ title: form.title },
					{ escape: false, sanitize: false },
				)
			">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isFormArchived"
			class="emtpycontent"
			:name="t('forms', 'Form is archived')"
			:description="
				t(
					'forms',
					'Form \'{title}\' is archived and cannot be modified.',
					{ title: form.title },
					{ escape: false, sanitize: false },
				)
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
						lockedBy: form.lockedBy ?? '',
						lockedUntil:
							form.lockedUntil === 0
								? t('forms', 'never')
								: lockedUntilFormatted,
					},
					{ escape: false, sanitize: false },
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
						:value="form.title"
						class="form-title"
						rows="1"
						dir="auto"
						:maxlength="maxStringLengths.formTitle"
						:placeholder="t('forms', 'Form title')"
						required
						autofocus
						@keydown.enter.prevent
						@input="onTitleInput" />
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
				<NcNoteCard v-if="form.submissionCount" type="warning">
					{{
						n(
							'forms',
							'This form already has %n response. Changing the options, rows or columns of a question, or the range of a linear scale, does not change it: answers that are no longer offered stay in the results.',
							'This form already has %n responses. Changing the options, rows or columns of a question, or the range of a linear scale, does not change them: answers that are no longer offered stay in the results.',
							form.submissionCount,
						)
					}}
				</NcNoteCard>
			</header>

			<section>
				<div ref="sectionSticky" class="section-sticky">
					<div v-show="stuckSection" class="section-sticky__bar">
						<span class="section-sticky__label" aria-hidden="true">
							{{
								t('forms', 'Section {index} of {total}', {
									index: stuckSectionNumber ?? '',
									total: sectionCount,
								})
							}}
						</span>
						<span
							class="section-sticky__title"
							:title="stuckSection?.text"
							aria-hidden="true">
							{{ stuckSection?.text }}
						</span>
						<div id="section-sticky-menu" />
					</div>
				</div>
				<!-- Questions list -->
				<Draggable
					:modelValue="form.questions"
					:disabled="isChangingQuestions"
					:animation="300"
					target=".sort-target"
					direction="vertical"
					invertSwap
					handle=".question__drag-handle"
					@update:modelValue="onUpdateQuestions"
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
							:ref="registerQuestionRef(question)"
							:class="sectionFrames[index]"
							v-bind="form.questions[index]"
							:canMoveDown="!isChangingQuestions && canMoveDown(index)"
							:canMoveUp="!isChangingQuestions && canMoveUp(index)"
							:answerType="answerTypes[question.type]"
							:index="questionNumbers.get(question.id)"
							:maxStringLengths="maxStringLengths"
							@update:text="updateQuestionText(index, $event)"
							@update:description="
								updateQuestionDescription(index, $event)
							"
							@update:isRequired="
								updateQuestionIsRequired(index, $event)
							"
							@update:name="updateQuestionName(index, $event)"
							@update:extraSettings="
								updateQuestionExtraSettings(index, $event)
							"
							@update:options="updateQuestionOptions(index, $event)"
							@clone="cloneQuestion(question, index)"
							@delete="onDelete(question.id)"
							@moveDown="onMoveDown(index)"
							@moveUp="onMoveUp(index)"
							@moveSection="reorderSectionsFrom = question.id">
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
										:aria-label="
											question.type === QUESTION_TYPE_SECTION
												? t(
														'forms',
														'Insert question after section {index}',
														{
															index:
																questionNumbers.get(
																	question.id,
																) ?? '',
														},
													)
												: t(
														'forms',
														'Insert question after question {index}',
														{
															index:
																questionNumbers.get(
																	question.id,
																) ?? '',
														},
													)
										"
										variant="tertiary"
										:position="index"
										:isLoadingQuestions="isLoadingQuestions"
										:answerTypesFilter="answerTypesFilter"
										:hasSubtypes="hasSubtypes"
										wide
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
				<ReorderSectionsDialog
					:open="reorderSectionsFrom !== null"
					:sections="sections"
					@update:open="closeReorderSections"
					@save="onReorderSections" />
			</section>
		</template>
	</NcAppContent>
</template>

<script lang="ts">
import type { ComponentPublicInstance, PropType } from 'vue'
import type { FormsForm, FormsOption, FormsQuestion } from '../types/Entities.d.ts'

import IconLock from '@material-symbols/svg-400/outlined/lock.svg?raw'
import axios from '@nextcloud/axios'
import { showConfirmation, showError, showWarning } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { getLanguage, n, t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import debounce from 'debounce'
import {
	computed,
	defineComponent,
	nextTick,
	onBeforeUnmount,
	onMounted,
	provide,
	ref,
	watch,
} from 'vue'
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
import ReorderSectionsDialog from '../components/ReorderSectionsDialog.vue'
import TopBar from '../components/TopBar.vue'
import { FORM_JUMP_ISSUES_KEY, FORM_QUESTIONS_KEY } from '../composables/useJumps.ts'
import { queueQuestionRequest } from '../composables/useQuestion.ts'
import { useViewForm } from '../composables/useViewForm.ts'
import answerTypes from '../models/AnswerTypes.ts'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import {
	formatList,
	getJumpIssues,
	getNewlyBrokenQuestions,
	getQuestionsWithDisallowedJumps,
	withoutDisallowedJumps,
	withoutRemovedJumps,
} from '../utils/Jumps.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import {
	countQuestionsOutsideSections,
	getNumbers,
	QUESTION_TYPE_SECTION,
	reorderSections,
	STUCK_SECTION_KEY,
	withNextSectionMovedUp,
} from '../utils/Sections.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

const formsAppName = 'forms'

interface QuestionRefInstance extends ComponentPublicInstance {
	focus?: () => void
}

;(window as Window & { axios?: typeof axios }).axios = axios

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Create',
	components: {
		Draggable,
		NcIconSvgWrapper,
		AddQuestionMenu,
		ReorderSectionsDialog,
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

	props: {
		hash: {
			type: String,
			default: '',
		},

		form: {
			type: Object as PropType<FormsForm>,
			required: true,
		},

		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:form', 'open-sharing'],

	setup(props, { emit }) {
		const formQuestions = computed(() => props.form.questions)
		const questionNumbers = computed(() => getNumbers(formQuestions.value))
		const sectionFrames = computed(() => {
			let inSection = false
			return formQuestions.value.map((question, index) => {
				if (question.type === QUESTION_TYPE_SECTION) {
					inSection = true
				}
				if (!inSection) {
					return undefined
				}
				const next = formQuestions.value[index + 1]
				return {
					'section-frame': true,
					'section-frame--start': question.type === QUESTION_TYPE_SECTION,
					'section-frame--end':
						next === undefined || next.type === QUESTION_TYPE_SECTION,
				}
			})
		})
		const sectionCount = computed(
			() =>
				formQuestions.value.filter(
					(question) => question.type === QUESTION_TYPE_SECTION,
				).length,
		)
		const sectionSticky = ref<HTMLElement | null>(null)
		const stuckSectionId = ref<number | null>(null)
		const stuckSection = computed(() =>
			formQuestions.value.find(
				(question) => question.id === stuckSectionId.value,
			),
		)
		const stuckSectionNumber = computed(() =>
			stuckSection.value
				? (questionNumbers.value.get(stuckSection.value.id) ?? null)
				: null,
		)
		provide(FORM_QUESTIONS_KEY, formQuestions)
		provide(STUCK_SECTION_KEY, stuckSectionNumber)
		provide(
			FORM_JUMP_ISSUES_KEY,
			computed(() => getJumpIssues(formQuestions.value ?? [])),
		)
		const title = ref<HTMLTextAreaElement | null>(null)
		const description = ref<HTMLTextAreaElement | null>(null)
		const viewForm = useViewForm({
			form: () => props.form,
			emit,
			titleRef: title,
		})

		// Various states
		const isLoadingQuestions = ref<boolean>(false)
		const changingFormIds = ref<number[]>([])
		const deletingQuestionIds = new Set<number>()
		const isChangingQuestions = computed<boolean>(() =>
			changingFormIds.value.includes(props.form.id),
		)

		/**
		 * Mark a question of a form as being added, copied or deleted
		 *
		 * @param formId the id of the form
		 */
		const startChangingQuestions = (formId: number): void => {
			changingFormIds.value = [...changingFormIds.value, formId]
		}

		/**
		 * Mark one adding, copying or deleting of a question of a form as done
		 *
		 * @param formId the id of the form
		 */
		const endChangingQuestions = (formId: number): void => {
			const index = changingFormIds.value.indexOf(formId)
			changingFormIds.value = changingFormIds.value.filter(
				(id, position) => position !== index,
			)
		}
		const isDragging = ref<boolean>(false)
		const maxStringLengths = loadState(
			formsAppName,
			'maxStringLengths',
		) as Record<string, number>
		const questionMenuOpened = ref<boolean>(false)
		const activeQuestionType = ref<string | null>(null)
		const questionRefsMap = ref<Record<number, QuestionRefInstance>>({})
		const reorderSectionsFrom = ref<number | null>(null)
		// when set to a number, the next created question will be inserted at this index
		const insertMenuOpenedIndex = ref<number | null>(null)
		// controls per-question insert menu visibility
		const insertMenuOpened = ref<boolean>(false)

		// Helper functions
		const setQuestionRef = (
			el: QuestionRefInstance | null,
			question: FormsQuestion,
		): void => {
			if (el) {
				questionRefsMap.value[question.id] = el
			} else {
				delete questionRefsMap.value[question.id]
			}
		}

		const registerQuestionRef = (
			question: FormsQuestion,
		): ((el: Element | ComponentPublicInstance | null) => void) => {
			return (el) => {
				setQuestionRef(el as QuestionRefInstance | null, question)
			}
		}

		/**
		 * Find the section whose own bar has scrolled under the sticky section bar while its questions are still visible
		 */
		const updateStuckSection = (): void => {
			const line = sectionSticky.value?.getBoundingClientRect().top
			const elementOf = (question: FormsQuestion) =>
				questionRefsMap.value[question.id]?.$el as HTMLElement | undefined
			let current: number | undefined
			formQuestions.value.forEach((question, index) => {
				if (line === undefined || question.type !== QUESTION_TYPE_SECTION) {
					return
				}
				const bar = elementOf(question)?.querySelector(
					'.question__section-bar',
				)
				if (bar && bar.getBoundingClientRect().bottom <= line) {
					current = index
				}
			})
			if (current === undefined || line === undefined) {
				stuckSectionId.value = null
				return
			}
			let last = current
			while (
				formQuestions.value[last + 1]
				&& formQuestions.value[last + 1].type !== QUESTION_TYPE_SECTION
			) {
				last++
			}
			const end = elementOf(formQuestions.value[last])?.getBoundingClientRect()
				.bottom
			stuckSectionId.value =
				end !== undefined && end > line
					? formQuestions.value[current].id
					: null
		}
		let stuckSectionFrame = 0
		const onScroll = (): void => {
			cancelAnimationFrame(stuckSectionFrame)
			stuckSectionFrame = requestAnimationFrame(updateStuckSection)
		}

		/**
		 * Auto adjust the title height based its scroll height
		 */
		const resizeTitle = (): void => {
			nextTick(() => {
				const textarea = title.value
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		}

		/**
		 * Auto adjust the description height based on its scroll height
		 */
		const resizeDescription = (): void => {
			// nextTick to ensure textarea is attached to DOM
			nextTick(() => {
				const textarea = description.value
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		}

		/**
		 * Title & description save methods
		 */
		const saveTitle = debounce(async () => {
			await viewForm.saveFormProperty('title')
		}, INPUT_DEBOUNCE_MS)

		const saveDescription = debounce(async () => {
			await viewForm.saveFormProperty('description')
		}, INPUT_DEBOUNCE_MS)

		// Computed properties
		const hasQuestions = computed<boolean>(
			() => props.form.questions.length === 0,
		)

		const isRequiredUsed = computed<boolean>(() =>
			props.form.questions.reduce(
				(isUsed: boolean, question: FormsQuestion) =>
					isUsed
					|| (Boolean(question.isRequired)
						&& question.type !== QUESTION_TYPE_SECTION),
				false,
			),
		)

		/**
		 * Check if form is expired
		 */
		const isExpired = computed<boolean>(
			() => props.form.expires > 0 && moment().unix() > props.form.expires,
		)

		/**
		 * Check if the form was archived
		 */
		const isFormArchived = computed<boolean>(
			() => props.form.state === FormState.FormArchived,
		)

		const infoMessage = computed<string>(() => {
			let message = ''
			if (props.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}

			// On Submit, this is dependent on `isLoggedIn`. Create-view is always logged in and the variable isLoggedIn does not exist.
			if (!props.form.isAnonymous && true) {
				message += t('forms', 'Responses are connected to your account.')
			}

			if (isRequiredUsed.value) {
				message +=
					' '
					+ t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		})

		const expirationMessage = computed<string>(() => {
			const relativeDate = moment(props.form.expires, 'X')
				.locale(window.OC?.getLanguage())
				.fromNow()
			if (isExpired.value) {
				return t(
					'forms',
					'Expired {relativeDate}.',
					{ relativeDate },
					{ escape: false, sanitize: false },
				)
			}
			return t(
				'forms',
				'Expires {relativeDate}.',
				{ relativeDate },
				{ escape: false, sanitize: false },
			)
		})

		// Remove properties from answerTypes for create button
		const answerTypesFilter = computed<Omit<typeof answerTypes, 'datetime'>>(
			() => {
				// Remove 'datetime' from answerTypes for create button
				// eslint-disable-next-line @typescript-eslint/no-unused-vars
				const { datetime, ...filteredAnswerTypes } = answerTypes
				return filteredAnswerTypes
			},
		)

		const hasSubtypes = computed<
			(
				answer: { subtypes?: Record<string, unknown> } | null | undefined,
			) => boolean
		>(() => {
			return (answer) =>
				Boolean(answer?.subtypes)
				&& Object.keys(answer?.subtypes ?? {}).length > 0
		})

		const lockedUntilFormatted = computed<string>(() =>
			moment(props.form.lockedUntil, 'X').fromNow(),
		)

		// Event handlers
		let draggedId: number | undefined
		const onDragStart = (event: { oldIndex?: number }): void => {
			isDragging.value = true
			draggedId = props.form.questions[event.oldIndex ?? -1]?.id
		}

		const onDragEnd = (): void => {
			nextTick(() => {
				isDragging.value = false
			})
		}

		/**
		 * The numbers of questions or of sections, as a list; questions and sections are numbered apart
		 *
		 * @param questions the questions of the form in their order
		 * @param ids the ids of the questions or of the sections
		 */
		const numbersOf = (questions: FormsQuestion[], ids: number[]): string => {
			const numbers = getNumbers(questions)
			return formatList(
				questions.flatMap((question) =>
					ids.includes(question.id)
						? [String(numbers.get(question.id))]
						: [],
				),
				getLanguage(),
			)
		}

		/**
		 * The ids of the sections among some ids
		 *
		 * @param questions the questions of the form in their order
		 * @param ids the ids of questions and sections
		 */
		const sectionIdsOf = (questions: FormsQuestion[], ids: number[]): number[] =>
			questions
				.filter(
					(question) =>
						question.type === QUESTION_TYPE_SECTION
						&& ids.includes(question.id),
				)
				.map((question) => question.id)

		/**
		 * Show a warning when a change breaks jumps, makes questions unreachable or lets jumps skip more questions
		 *
		 * @param oldQuestions the questions before the change
		 * @param newQuestions the questions after the change
		 * @param reportSkippable whether to name the questions that jumps can skip now
		 */
		const warnAboutJumpChanges = (
			oldQuestions: FormsQuestion[],
			newQuestions: FormsQuestion[],
			reportSkippable = true,
		): void => {
			const before = getJumpIssues(oldQuestions)
			const after = getJumpIssues(newQuestions)
			const questionIds = new Set(
				newQuestions
					.filter((question) => question.type !== QUESTION_TYPE_SECTION)
					.map((question) => question.id),
			)

			const newlyBroken = getNewlyBrokenQuestions(before, after)
			const broken = newlyBroken.filter((id) => questionIds.has(id))
			const brokenSections = newlyBroken.filter((id) => !questionIds.has(id))
			const unreachable = [...after.unreachable].filter(
				(id) => !before.unreachable.has(id) && questionIds.has(id),
			)
			const skipped = reportSkippable
				? [...after.skippable].filter(
						(id) => !before.skippable.has(id) && questionIds.has(id),
					)
				: []

			const newIds = new Set(newQuestions.map((question) => question.id))
			const deletedSection = oldQuestions.some(
				(question) =>
					question.type === QUESTION_TYPE_SECTION
					&& !newIds.has(question.id),
			)
			const messages = [
				brokenSections.length > 0
					&& n(
						'forms',
						'Section {sections} went to a section that was deleted, so respondents now go to the next section instead.',
						'Sections {sections} went to sections that were deleted, so respondents now go to the next section instead.',
						brokenSections.length,
						{ sections: numbersOf(newQuestions, brokenSections) },
					),
				broken.length > 0
					&& (deletedSection
						? n(
								'forms',
								'Question {questions} jumped to the deleted section, so respondents now go to the next question instead.',
								'Questions {questions} jumped to the deleted section, so respondents now go to the next question instead.',
								broken.length,
								{ questions: numbersOf(newQuestions, broken) },
							)
						: n(
								'forms',
								'Question {questions} jumped to the deleted question, so respondents now go to the next question instead.',
								'Questions {questions} jumped to the deleted question, so respondents now go to the next question instead.',
								broken.length,
								{ questions: numbersOf(newQuestions, broken) },
							)),
				unreachable.length === 1
					&& t(
						'forms',
						'Question {questions} can no longer be reached by respondents.',
						{ questions: numbersOf(newQuestions, unreachable) },
					),
				unreachable.length > 1
					&& t(
						'forms',
						'Questions {questions} can no longer be reached by respondents.',
						{ questions: numbersOf(newQuestions, unreachable) },
					),
				skipped.length === 1
					&& t(
						'forms',
						'Question {questions} can now be skipped through a jump.',
						{ questions: numbersOf(newQuestions, skipped) },
					),
				skipped.length > 1
					&& t(
						'forms',
						'Questions {questions} can now be skipped through jumps.',
						{ questions: numbersOf(newQuestions, skipped) },
					),
			].filter(Boolean)

			if (messages.length > 0) {
				showWarning(
					[...messages, t('forms', 'Please review the jumps.')].join(' '),
				)
			}
		}

		const updateQuestionProperty = <K extends keyof FormsQuestion>(
			index: number,
			property: K,
			value: FormsQuestion[K],
		): FormsQuestion[] => {
			const questions = [...props.form.questions]
			questions[index] = { ...questions[index], [property]: value }
			emit('update:form', { ...props.form, questions })
			return questions
		}

		const updateQuestionText = (index: number, value: string): void => {
			warnAboutJumpChanges(
				props.form.questions,
				updateQuestionProperty(index, 'text', value),
				false,
			)
		}

		const updateQuestionDescription = (index: number, value: string): void => {
			updateQuestionProperty(index, 'description', value)
		}

		const updateQuestionIsRequired = (index: number, value: boolean): void => {
			updateQuestionProperty(index, 'isRequired', value)
		}

		const updateQuestionName = (index: number, value: string): void => {
			updateQuestionProperty(index, 'name', value)
		}

		const updateQuestionExtraSettings = (
			index: number,
			value: Record<string, unknown> | null,
		): void => {
			warnAboutJumpChanges(
				props.form.questions,
				updateQuestionProperty(index, 'extraSettings', value),
				false,
			)
		}

		const updateQuestionOptions = (
			index: number,
			value: FormsOption[],
		): void => {
			warnAboutJumpChanges(
				props.form.questions,
				updateQuestionProperty(index, 'options', value),
				false,
			)
		}

		/**
		 * The closing question of a move confirmation, naming the moved question, section or sections
		 *
		 * @param moved the moved question, section or sections
		 */
		const moveBackText = (moved: FormsQuestion[]): string => {
			const options = { escape: false, sanitize: false }
			if (moved.length > 1) {
				return t(
					'forms',
					'Move the sections {sections} back, or remove these jumps?',
					{
						sections: formatList(
							moved.map(
								({ text }) =>
									`"${text || t('forms', 'Untitled section')}"`,
							),
							getLanguage(),
						),
					},
					undefined,
					options,
				)
			}
			return moved[0]?.type === QUESTION_TYPE_SECTION
				? t(
						'forms',
						'Move the section "{title}" back, or remove these jumps?',
						{ title: moved[0].text || t('forms', 'Untitled section') },
						undefined,
						options,
					)
				: t(
						'forms',
						'Move the question "{title}" back, or remove these jumps?',
						{ title: moved[0]?.text || t('forms', 'Untitled question') },
						undefined,
						options,
					)
		}

		/**
		 * Ask whether to remove the jumps that a new order of the questions would turn into jumps to an earlier question or into the middle of another section, or to move the moved question back
		 *
		 * @param questions the questions in the new order
		 * @param ids the ids of the questions with jumps to an earlier question
		 * @param intoSectionIds the ids of the other questions with jumps into the middle of another section
		 * @param moved the moved question, section or sections
		 * @return whether to remove the jumps
		 */
		const confirmRemovingBackwardJumps = async (
			questions: FormsQuestion[],
			ids: number[],
			intoSectionIds: number[],
			moved: FormsQuestion[],
		): Promise<boolean> => {
			try {
				if (ids.length === 0) {
					return await showConfirmation({
						name: t('forms', 'Jumps go to the start of a section'),
						text: [
							n(
								'forms',
								'After this move, question {questions} would jump into the middle of another section.',
								'After this move, questions {questions} would jump into the middle of other sections.',
								intoSectionIds.length,
								{ questions: numbersOf(questions, intoSectionIds) },
							),
							moveBackText(moved),
						].join(' '),
						labelConfirm: t('forms', 'Remove jumps'),
						labelReject: t('forms', 'Move back'),
						severity: 'warning',
					})
				}
				const sectionIds = sectionIdsOf(questions, ids)
				const questionIds = ids.filter((id) => !sectionIds.includes(id))
				const sectionsText =
					sectionIds.length === 0
						? ''
						: questionIds.length === 0
							? n(
									'forms',
									'After this move, section {sections} would go to an earlier section.',
									'After this move, sections {sections} would go to earlier sections.',
									sectionIds.length,
									{ sections: numbersOf(questions, sectionIds) },
								)
							: n(
									'forms',
									'Section {sections} would go to an earlier section as well.',
									'Sections {sections} would go to earlier sections as well.',
									sectionIds.length,
									{ sections: numbersOf(questions, sectionIds) },
								)
				const questionsText =
					questionIds.length === 0
						? ''
						: n(
								'forms',
								'After this move, question {questions} would jump to an earlier question.',
								'After this move, questions {questions} would jump to earlier questions.',
								questionIds.length,
								{ questions: numbersOf(questions, questionIds) },
							)
				return await showConfirmation({
					name: t('forms', 'Jumps cannot go back'),
					text: [questionsText, sectionsText, moveBackText(moved)]
						.filter(Boolean)
						.join(' '),
					labelConfirm: t('forms', 'Remove jumps'),
					labelReject: t('forms', 'Move back'),
					severity: 'warning',
				})
			} catch {
				return false
			}
		}

		/**
		 * Reorder questions on dragEnd
		 *
		 * @param questions the freshly reordered questions (avoids reading the not-yet-updated `form` prop)
		 * @param moved the question, section or sections the user moved
		 */
		const onQuestionOrderChange = async (
			questions: FormsQuestion[],
			moved: FormsQuestion[] = [],
		): Promise<void> => {
			const formId = props.form.id
			const oldQuestions = props.form.questions
			const focused = document.activeElement
			const backward = getNewlyBrokenQuestions(
				getJumpIssues(oldQuestions),
				getJumpIssues(questions),
			)
			const disallowedBefore = new Set(
				getQuestionsWithDisallowedJumps(oldQuestions),
			)
			const intoSection = getQuestionsWithDisallowedJumps(questions).filter(
				(id) => !backward.includes(id) && !disallowedBefore.has(id),
			)
			let order = questions
			if (backward.length > 0 || intoSection.length > 0) {
				const removeJumps = await confirmRemovingBackwardJumps(
					questions,
					backward,
					intoSection,
					moved,
				)
				if (!removeJumps) {
					const current = new Map(
						props.form.questions.map((question) => [
							question.id,
							question,
						]),
					)
					const restored = oldQuestions.flatMap(
						(question) => current.get(question.id) ?? [],
					)
					props.form.questions.forEach((question, position) => {
						if (!oldQuestions.some(({ id }) => id === question.id)) {
							restored.splice(position, 0, question)
						}
					})
					emit('update:form', { ...props.form, questions: restored })
				}
				await nextTick()
				if (focused instanceof HTMLElement && focused.isConnected) {
					focused.focus()
				}
				if (!removeJumps) {
					return
				}
				order = props.form.questions
			}

			isLoadingQuestions.value = true
			const newOrder = order.map((question: FormsQuestion) => question.id)

			try {
				await queueQuestionRequest(newOrder, () =>
					axios.patch(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
							id: props.form.id,
						}),
						{
							newOrder,
						},
					),
				)
				if (props.form.id !== formId) {
					return
				}
				const adjustedOrder = withoutDisallowedJumps(order)
				warnAboutJumpChanges(oldQuestions, adjustedOrder)
				const changes = new Map(
					order.flatMap((question, position) =>
						adjustedOrder[position] === question
							? []
							: [
									[
										question.id,
										[
											question.extraSettings ?? {},
											adjustedOrder[position].extraSettings
												?? {},
										],
									] as const,
								],
					),
				)
				const questions = props.form.questions.map((question) => {
					const change = changes.get(question.id)
					if (!change) {
						return question
					}
					const extraSettings = withoutRemovedJumps(
						question.extraSettings ?? {},
						...change,
					)
					return extraSettings === question.extraSettings
						? question
						: { ...question, extraSettings }
				})
				if (
					questions.some(
						(question, index) =>
							question !== props.form.questions[index],
					)
				) {
					emit('update:form', { ...props.form, questions })
				}
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				logger.error('Error while saving form', { error })
				showError(t('forms', 'Error while saving form'))
			} finally {
				isLoadingQuestions.value = false
			}
		}

		/**
		 * Swap the question at a position with the one before it
		 *
		 * @param index the position of the question
		 * @param moved the question the user moved
		 */
		const swapWithPrevious = (index: number, moved: FormsQuestion): void => {
			const questions = [...props.form.questions]
			;[questions[index - 1], questions[index]] = [
				questions[index],
				questions[index - 1],
			]
			emit('update:form', { ...props.form, questions })
			onQuestionOrderChange(questions, [moved])
		}

		/**
		 * Whether the question at a position can swap with the one after it: both exist, and the swap moves no question before the first section
		 *
		 * @param index the position of the question
		 */
		const canSwapWithNext = (index: number): boolean => {
			const questions = props.form.questions
			return (
				index >= 0
				&& index < questions.length - 1
				&& (index !== countQuestionsOutsideSections(questions)
					|| questions[index].type !== QUESTION_TYPE_SECTION
					|| questions[index + 1].type === QUESTION_TYPE_SECTION)
			)
		}

		/**
		 * Whether the question at a position can move up without leaving the first section; a section, whether another section is before it
		 *
		 * @param index the position of the question
		 */
		const canMoveUp = (index: number): boolean =>
			props.form.questions[index]?.type === QUESTION_TYPE_SECTION
				? props.form.questions
						.slice(0, index)
						.some((question) => question.type === QUESTION_TYPE_SECTION)
				: canSwapWithNext(index - 1)

		/**
		 * Whether the question at a position can move down; a section, whether another section is after it
		 *
		 * @param index the position of the question
		 */
		const canMoveDown = (index: number): boolean =>
			props.form.questions[index]?.type === QUESTION_TYPE_SECTION
				? props.form.questions
						.slice(index + 1)
						.some((question) => question.type === QUESTION_TYPE_SECTION)
				: canSwapWithNext(index)

		const sections = computed(() =>
			props.form.questions.filter(
				(question) => question.type === QUESTION_TYPE_SECTION,
			),
		)

		/**
		 * Focus the title of a question or section
		 *
		 * @param id the id of the question or section
		 */
		const focusQuestion = (id: number | null): void => {
			nextTick(() => {
				if (id !== null) {
					questionRefsMap.value[id]?.focus?.()
				}
			})
		}

		/**
		 * Close the reorder sections dialog and focus the section it was opened from
		 */
		const closeReorderSections = (): void => {
			focusQuestion(reorderSectionsFrom.value)
			reorderSectionsFrom.value = null
		}

		/**
		 * Move the sections with their questions into a new order, then focus the section the dialog was opened from
		 *
		 * @param sectionIds the ids of the sections in their new order
		 * @param movedIds the ids of the sections the user moved
		 */
		const onReorderSections = async (
			sectionIds: number[],
			movedIds: number[],
		): Promise<void> => {
			const id = reorderSectionsFrom.value
			const questions = reorderSections(props.form.questions, sectionIds)
			emit('update:form', { ...props.form, questions })
			await onQuestionOrderChange(
				questions,
				sections.value.filter((section) => movedIds.includes(section.id)),
			)
			focusQuestion(id)
		}

		const onMoveUp = (index: number): void => {
			if (canSwapWithNext(index - 1)) {
				swapWithPrevious(index, props.form.questions[index])
			}
		}

		const onMoveDown = (index: number): void => {
			if (canSwapWithNext(index)) {
				swapWithPrevious(index + 1, props.form.questions[index])
			}
		}

		const insertQuestion = (
			questionData: FormsQuestion,
			defaultFields: Partial<FormsQuestion> = {},
			position: number | null = null,
		): void => {
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

			const questions = [...props.form.questions]
			if (insertAt !== null && insertAt <= questions.length) {
				questions.splice(insertAt, 0, newQuestionObj)
				emit('update:form', { ...props.form, questions })
				nextTick(() => {
					// Prefer ref by id when available, fallback to positional refs
					questionRefsMap.value[newQuestionObj.id]?.focus?.()
				})
			} else {
				questions.push(newQuestionObj)
				emit('update:form', { ...props.form, questions })
				nextTick(() => {
					questionRefsMap.value[newQuestionObj.id]?.focus?.()
				})
			}

			warnAboutJumpChanges(props.form.questions, questions, false)
			emitEvent('forms:last-updated:set', props.form.id)
		}

		const onTitleChange = (): void => {
			resizeTitle()
			saveTitle()
		}

		const onTitleInput = (event: Event): void => {
			emit('update:form', {
				...props.form,
				title: (event.target as HTMLTextAreaElement).value,
			})
			onTitleChange()
		}

		/**
		 * Update the description
		 *
		 * @param event The input event of the textarea
		 */
		const updateDescription = (event: Event): void => {
			const target = event.target as HTMLTextAreaElement | null
			if (!target) {
				return
			}
			emit('update:form', { ...props.form, description: target.value })
			resizeDescription()
			saveDescription()
		}

		/**
		 * Remove the jumps into the middle of a section that a new section split off, as the server does when it inserts the section
		 */
		const removeJumpsIntoNewSection = (): void => {
			const questions = withoutDisallowedJumps(props.form.questions)
			const changed = props.form.questions
				.filter((question, position) => questions[position] !== question)
				.map((question) => question.id)
			if (changed.length === 0) {
				return
			}
			emit('update:form', { ...props.form, questions })
			showWarning(
				n(
					'forms',
					'Question {questions} jumped into the part of a section that is now a new section, so its jumps there were removed. Please review the jumps.',
					'Questions {questions} jumped into the part of a section that is now a new section, so their jumps there were removed. Please review the jumps.',
					changed.length,
					{ questions: numbersOf(questions, changed) },
				),
			)
		}

		/**
		 * Add a new question to the current form
		 *
		 * @param type the question type, see AnswerTypes
		 * @param subtype the question subtype, see AnswerTypes.subtypes
		 * @param position where the new question should be added
		 */
		const addQuestion = async (
			type: string,
			subtype: string | null = null,
			position: number | null = null,
		): Promise<void> => {
			activeQuestionType.value = null
			const text = ''
			const formId = props.form.id
			isLoadingQuestions.value = true
			startChangingQuestions(formId)

			try {
				if (
					type === QUESTION_TYPE_SECTION
					&& props.form.questions.length > 0
					&& props.form.questions[0].type !== QUESTION_TYPE_SECTION
				) {
					const sectionText = t('forms', 'Untitled section')
					const implicitResponse = await queueQuestionRequest(
						props.form.questions.map(({ id }) => id),
						() =>
							axios.post(
								generateOcsUrl(
									'apps/forms/api/v3/forms/{id}/questions',
									{ id: props.form.id },
								),
								{ type, text: sectionText, subtype, position: 1 },
							),
					)
					if (props.form.id !== formId) {
						return
					}
					insertQuestion(
						OcsResponse2Data<FormsQuestion>(implicitResponse),
						{ text: sectionText, type, answers: [] },
						0,
					)
					if (position !== null) {
						position += 1
					}
				}

				const body: {
					type: string
					text: string
					subtype: string | null
					position?: number
				} = { type, text, subtype }
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await queueQuestionRequest(
					props.form.questions.map(({ id }) => id),
					() =>
						axios.post(
							generateOcsUrl(
								'apps/forms/api/v3/forms/{id}/questions',
								{
									id: props.form.id,
								},
							),
							body,
						),
				)
				if (props.form.id !== formId) {
					return
				}
				const question = OcsResponse2Data<FormsQuestion>(response)

				// Delegate insertion & focus handling to helper
				insertQuestion(question, { text, type, answers: [] }, position)
				if (type === QUESTION_TYPE_SECTION) {
					await nextTick()
					removeJumpsIntoNewSection()
				}
			} catch (error) {
				logger.error('Error while adding new question', { error })
				showError(
					t('forms', 'There was an error while adding the new question'),
				)
			} finally {
				isLoadingQuestions.value = false
				endChangingQuestions(formId)
			}
		}

		/**
		 * Delete questions one after the other, one request each, and warn once about the jumps this changes; a failed request keeps the remaining questions. Deleting the first section moves the next section up to its place, as the server does
		 *
		 * @param questionIds the ids of the questions to delete
		 */
		const deleteQuestions = async (questionIds: number[]): Promise<void> => {
			const ids = questionIds.filter((id) => !deletingQuestionIds.has(id))
			if (ids.length === 0) {
				return
			}
			ids.forEach((id) => deletingQuestionIds.add(id))
			const formId = props.form.id
			const oldQuestions = props.form.questions
			isLoadingQuestions.value = true
			startChangingQuestions(formId)

			const deleted = new Set<number>()
			try {
				for (const questionId of ids) {
					await queueQuestionRequest(
						props.form.questions.map(({ id }) => id),
						() =>
							axios.delete(
								generateOcsUrl(
									'apps/forms/api/v3/forms/{id}/questions/{questionId}',
									{
										id: props.form.id,
										questionId,
									},
								),
							),
					)
					deleted.add(questionId)
				}
			} catch (error) {
				logger.error('Error while removing questions', { error, ids })
				showError(
					t('forms', 'There was an error while removing the question'),
				)
			} finally {
				if (props.form.id === formId && deleted.size > 0) {
					let questions = props.form.questions
					let moved = false
					for (const id of deleted) {
						const position = questions.findIndex(
							(question) => question.id === id,
						)
						const remaining = questions.filter(
							(question) => question.id !== id,
						)
						const next =
							questions[position]?.type === QUESTION_TYPE_SECTION
								? withNextSectionMovedUp(remaining, position)
								: remaining
						moved = moved || next !== remaining
						questions = next
					}
					if (moved) {
						questions = withoutDisallowedJumps(questions)
					}
					warnAboutJumpChanges(oldQuestions, questions)
					emit('update:form', { ...props.form, questions })
					emitEvent('forms:last-updated:set', props.form.id)
				}
				isLoadingQuestions.value = false
				endChangingQuestions(formId)
				ids.forEach((id) => deletingQuestionIds.delete(id))
			}
		}

		/**
		 * Delete a question; for a section with questions, ask whether to delete only the section, so its questions join the section before it, the next section or, for the only section, the form without sections, or the section with its questions, deleting the section last
		 *
		 * @param questionId the id of the question or section
		 */
		const onDelete = async (questionId: number): Promise<void> => {
			const position = props.form.questions.findIndex(
				({ id }) => id === questionId,
			)
			if (props.form.questions[position]?.type !== QUESTION_TYPE_SECTION) {
				await deleteQuestions([questionId])
				return
			}
			const end = props.form.questions.findIndex(
				(question, index) =>
					index > position && question.type === QUESTION_TYPE_SECTION,
			)
			const sectionQuestions = props.form.questions.slice(
				position + 1,
				end === -1 ? undefined : end,
			)
			if (sectionQuestions.length === 0) {
				await deleteQuestions([questionId])
				return
			}
			const isFirst = !props.form.questions
				.slice(0, position)
				.some((question) => question.type === QUESTION_TYPE_SECTION)
			let text = n(
				'forms',
				'Delete only the section, so its question joins the part before it, or delete the section with its question?',
				'Delete only the section, so its %n questions join the part before it, or delete the section with its %n questions?',
				sectionQuestions.length,
			)
			if (isFirst && end !== -1) {
				text = n(
					'forms',
					'Delete only the section, so its question joins the next section, or delete the section with its question?',
					'Delete only the section, so its %n questions join the next section, or delete the section with its %n questions?',
					sectionQuestions.length,
				)
			} else if (isFirst) {
				text = n(
					'forms',
					'Delete only the section, so its question stays in a form without sections, or delete the section with its question?',
					'Delete only the section, so its %n questions stay in a form without sections, or delete the section with its %n questions?',
					sectionQuestions.length,
				)
			}
			let withQuestions: boolean
			try {
				withQuestions = await showConfirmation({
					name: t('forms', 'Delete section'),
					text,
					labelConfirm: t('forms', 'Delete questions'),
					labelReject: t('forms', 'Keep questions'),
					severity: 'warning',
				})
			} catch {
				return
			}
			await deleteQuestions(
				withQuestions
					? [...sectionQuestions.map(({ id }) => id), questionId]
					: [questionId],
			)
		}

		/**
		 * Clone a question
		 *
		 * @param question the question to clone in the current form
		 * @param question.id the question id to clone in the current form
		 * @param position where the cloned question should be added
		 */
		const cloneQuestion = async (
			question: Pick<FormsQuestion, 'id'>,
			position: number | null,
		): Promise<void> => {
			const formId = props.form.id
			isLoadingQuestions.value = true
			startChangingQuestions(formId)

			try {
				const url = generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
					{
						id: props.form.id,
						questionId: question.id,
					},
				)

				const body: { position?: number } = {}
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await queueQuestionRequest(
					props.form.questions.map(({ id }) => id),
					() => axios.post(url, body),
				)
				if (props.form.id !== formId) {
					return
				}
				const clonedQuestion = OcsResponse2Data<FormsQuestion>(response)

				// Delegate insertion & focus handling to helper
				insertQuestion(clonedQuestion, { answers: [] })
			} catch (error) {
				logger.error(`Error while duplicating question ${question.id}`, {
					error,
				})
				showError('There was an error while duplicating the question')
			} finally {
				isLoadingQuestions.value = false
				endChangingQuestions(formId)
			}
		}

		const onUpdateQuestions = (questions: FormsQuestion[]): void => {
			if (
				countQuestionsOutsideSections(questions)
				> countQuestionsOutsideSections(props.form.questions)
			) {
				showWarning(t('forms', 'Every question needs to be in a section'))
				return
			}
			emit('update:form', { ...props.form, questions })
			onQuestionOrderChange(
				questions,
				questions.filter(({ id }) => id === draggedId),
			)
		}

		// Watchers
		// Fetch full form on change
		watch(
			() => props.hash,
			() => {
				viewForm.fetchFullForm(props.form.id)
			},
		)

		// Update Window-Title on title change
		watch(
			() => props.form.title,
			() => {
				SetWindowTitle(viewForm.formTitle.value)
			},
		)

		// resize description if form is loaded
		watch(
			() => viewForm.isLoadingForm.value,
			(value: boolean) => {
				if (!value) {
					resizeTitle()
					resizeDescription()
				}
			},
		)

		// Lifecycle
		onMounted(() => {
			viewForm.fetchFullForm(props.form.id)
			SetWindowTitle(viewForm.formTitle.value)
			document.addEventListener('scroll', onScroll, {
				capture: true,
				passive: true,
			})
			window.addEventListener('resize', onScroll)
		})
		onBeforeUnmount(() => {
			document.removeEventListener('scroll', onScroll, { capture: true })
			window.removeEventListener('resize', onScroll)
			cancelAnimationFrame(stuckSectionFrame)
		})
		watch(formQuestions, onScroll, { deep: true, flush: 'post' })

		return {
			...viewForm,
			answerTypes,
			title,
			description,
			isLoadingQuestions,
			isChangingQuestions,
			isDragging,
			maxStringLengths,
			questionMenuOpened,
			activeQuestionType,
			questionRefsMap,
			insertMenuOpenedIndex,
			insertMenuOpened,
			hasQuestions,
			isRequiredUsed,
			isExpired,
			isFormArchived,
			infoMessage,
			expirationMessage,
			answerTypesFilter,
			hasSubtypes,
			lockedUntilFormatted,
			onDragStart,
			onDragEnd,
			setQuestionRef,
			registerQuestionRef,
			updateQuestionText,
			updateQuestionDescription,
			updateQuestionIsRequired,
			updateQuestionName,
			updateQuestionExtraSettings,
			updateQuestionOptions,
			onMoveUp,
			onMoveDown,
			onTitleInput,
			updateDescription,
			resizeTitle,
			resizeDescription,
			addQuestion,
			canMoveDown,
			canMoveUp,
			closeReorderSections,
			onReorderSections,
			reorderSectionsFrom,
			sections,
			onDelete,
			insertQuestion,
			cloneQuestion,
			questionNumbers,
			sectionFrames,
			sectionCount,
			sectionSticky,
			stuckSection,
			stuckSectionNumber,
			QUESTION_TYPE_SECTION,
			onUpdateQuestions,
			onQuestionOrderChange,
			IconLock,
			isMobile: useIsMobile(),
			n,
			t,
		}
	},
})
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
		padding-inline-start: 44px;

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
			width: calc(100% - 46px);
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
			width: calc(100% - 46px);
		}

		.form-desc {
			color: var(--color-main-text);
			line-height: 22px;
			min-height: 47px; // one line (25px padding + 22px text height)
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
		container-type: inline-size;
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

.section-frame,
.section-sticky {
	--section-frame-inset-start: 0px;
	--section-frame-inset-end: calc(-2 * var(--default-grid-baseline));
}

@container (max-width: 749px) {
	.section-frame,
	.section-sticky {
		--section-frame-inset-start: var(--default-grid-baseline);
		--section-frame-inset-end: var(--default-grid-baseline);
	}
}

.section-frame::before {
	content: '';
	position: absolute;
	inset-block: 0 -64px;
	inset-inline: var(--section-frame-inset-start) var(--section-frame-inset-end);
	border-inline: 2px solid var(--color-border-dark);
	pointer-events: none;
}

.section-sticky {
	position: sticky;
	z-index: 50;
	inset-block-start: calc(
		var(--default-clickable-area) + 2 * var(--app-navigation-padding)
	);
	height: 0;

	&__bar {
		position: absolute;
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		inset-block-start: 0;
		inset-inline: var(--section-frame-inset-start) var(--section-frame-inset-end);
		min-height: var(--default-clickable-area);
		padding-inline: calc(56px - var(--section-frame-inset-start))
			var(--default-grid-baseline);
		border: 2px solid var(--color-border-dark);
		border-start-start-radius: var(--border-radius-large);
		border-start-end-radius: var(--border-radius-large);
		background-color: var(--color-primary-element-light);
		color: var(--color-main-text);

		&::before {
			position: absolute;
			z-index: -1;
			inset-block-end: calc(100% - var(--border-radius-large));
			inset-inline: -2px;
			height: calc(
				var(--default-clickable-area) + 2 * var(--app-navigation-padding) +
					2px + var(--border-radius-large)
			);
			background-color: var(--color-main-background);
			content: '';
		}
	}

	&__label {
		flex-shrink: 0;
		white-space: nowrap;
	}

	&__title {
		flex: 1 1 auto;
		min-width: 0;
		overflow: hidden;
		font-weight: bold;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.section-frame--start::before {
	inset-block-start: 0;
	border-block-start: 2px solid var(--color-border-dark);
	border-start-start-radius: var(--border-radius-large);
	border-start-end-radius: var(--border-radius-large);
}

.section-frame--end::before {
	inset-block-end: calc(-2 * var(--default-grid-baseline));
	border-block-end: 2px solid var(--color-border-dark);
	border-end-start-radius: var(--border-radius-large);
	border-end-end-radius: var(--border-radius-large);
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
