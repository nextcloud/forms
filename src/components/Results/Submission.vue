<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="section submission" @copy="onCopy">
		<div class="submission-head">
			<h3 dir="auto">
				{{ submission.userDisplayName }}
			</h3>
			<NcActions class="submission-menu" forceMenu>
				<NcActionRouter
					v-if="canEditSubmission"
					:to="{
						name: 'submit',
						params: { hash: formHash, submissionId: submission.id },
					}">
					<template #icon>
						<NcIconSvgWrapper :svg="IconPencil" />
					</template>
					{{ t('forms', 'Edit this response') }}
				</NcActionRouter>
				<NcActionButton v-if="canDeleteSubmission" @click="onDelete">
					<template #icon>
						<NcIconSvgWrapper :svg="IconDelete" />
					</template>
					{{ t('forms', 'Delete this response') }}
				</NcActionButton>
			</NcActions>
		</div>
		<p class="submission-date">
			{{ submissionDateTime }}
		</p>

		<Answer
			v-for="question in answeredQuestions"
			:key="question.id"
			:highlight="highlight"
			:answerText="question.squashedAnswers"
			:answers="question.answers"
			:questionText="question.text"
			:gridCellType="question.gridCellType"
			:gridColumns="question.gridColumns"
			:gridRows="question.gridRows"
			:gridValue="question.gridValue"
			:questionType="question.type" />
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'

import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconPencil from '@material-symbols/svg-400/outlined/edit.svg?raw'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/components/NcActionRouter'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Answer from './Answer.vue'
import { OptionType } from '../../models/Constants.ts'

interface SubmissionAnswer {
	id: number
	questionId: number
	text: string
	fileId?: number
}

interface SubmissionModel {
	id: number
	userDisplayName: string
	timestamp: string | number
	answers: SubmissionAnswer[]
}

interface QuestionOption {
	id: number
	text: string
	optionType: string
}

interface QuestionModel {
	id: number
	text: string
	type: string
	options: QuestionOption[]
	extraSettings: {
		questionType?: 'radio' | 'checkbox' | 'number'
	}
}

interface AnsweredQuestion {
	id: number
	text: string
	type: string
	squashedAnswers?: string
	answers?: Array<{ id: number; text: string; url?: string }>
	gridValue?: Record<string, string | string[] | Record<string, string>> | null
	gridCellType?: string
	gridRows?: QuestionOption[]
	gridColumns?: QuestionOption[]
}

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Submission',

	components: {
		Answer,
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		NcActionRouter,
	},

	props: {
		formHash: {
			type: String,
			required: true,
		},

		submission: {
			type: Object as PropType<SubmissionModel>,
			required: true,
		},

		questions: {
			type: Array as PropType<QuestionModel[]>,
			required: true,
		},

		canDeleteSubmission: {
			type: Boolean,
			required: true,
		},

		canEditSubmission: {
			type: Boolean,
			required: true,
		},

		highlight: {
			type: String,
			default: null,
		},
	},

	emits: ['delete'],

	setup() {
		return {
			IconDelete,
			IconPencil,
			t,
		}
	},

	computed: {
		// Format submission-timestamp to DateTime
		submissionDateTime(): string {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},

		/**
		 * Join answered Questions with corresponding answers.
		 * Multiple answers to a question are squashed into one string.
		 *
		 * @return
		 */
		answeredQuestions(): AnsweredQuestion[] {
			const answeredQuestionsArray: AnsweredQuestion[] = []

			this.questions.forEach((question: QuestionModel) => {
				const answers = this.submission.answers.filter(
					(answer) => answer.questionId === question.id,
				)
				if (!answers.length) {
					return // no answers, go to next question
				}

				if (question.type === 'file') {
					answeredQuestionsArray.push({
						id: question.id,
						text: question.text,
						type: question.type,
						answers: answers.map((answer) => {
							return {
								id: answer.id,
								text: answer.text,
								url: generateUrl('/f/{fileId}', {
									fileId: answer.fileId,
								}),
							}
						}),
					})
				} else if (question.type === 'grid') {
					const optionsPerId: Record<string, QuestionOption> = {}
					question.options.forEach((option) => {
						optionsPerId[option.id] = option
					})
					let squashedAnswers = ''

					const gridValue = answers[0].text
						? (JSON.parse(answers[0].text) as Record<
								string,
								string | string[] | Record<string, string>
							>)
						: null
					// fixme: rename `questionType` to `gridCellType` everywhere in BE and FE
					if (
						gridValue
						&& question.extraSettings.questionType === 'radio'
					) {
						squashedAnswers = Object.keys(gridValue)
							.filter(
								(key) =>
									optionsPerId[key]
									&& optionsPerId[gridValue[key]],
							)
							.map((key: string) => {
								return (
									optionsPerId[key].text
									+ ': '
									+ optionsPerId[gridValue[key]].text
								)
							})
							.join('\n')
					} else if (
						gridValue
						&& question.extraSettings.questionType === 'checkbox'
					) {
						squashedAnswers = Object.keys(gridValue)
							.filter(
								(key) =>
									optionsPerId[key]
									&& Array.isArray(gridValue[key]),
							)
							.map((key: string) => {
								return (
									optionsPerId[key].text
									+ ': '
									+ gridValue[key]
										.filter(
											(optionId: string) =>
												optionsPerId[optionId],
										)
										.map(
											(optionId: string) =>
												optionsPerId[optionId].text,
										)
										.join(', ')
								)
							})
							.join('\n')
					}

					answeredQuestionsArray.push({
						id: question.id,
						text: question.text,
						type: question.type,
						gridValue,
						squashedAnswers,
						gridCellType: question.extraSettings.questionType,
						gridRows: question.options.filter(
							(option) => option.optionType === OptionType.Row,
						),
						gridColumns: question.options.filter(
							(option) => option.optionType === OptionType.Column,
						),
					})
				} else if (['date', 'time'].includes(question.type)) {
					const squashedAnswers = answers
						.map((answer) => answer.text)
						.join(' - ')

					answeredQuestionsArray.push({
						id: question.id,
						text: question.text,
						type: question.type,
						squashedAnswers,
					})
				} else if (question.type === 'ranking') {
					const optionsPerId: Record<string, QuestionOption> = {}
					question.options.forEach((option) => {
						optionsPerId[option.id] = option
					})
					const rankedIds = answers[0]?.text
						? JSON.parse(answers[0].text)
						: []
					const squashedAnswers = rankedIds
						.map((id: string, index: number) => {
							const option = optionsPerId[id]
							return option
								? `${index + 1}. ${option.text}`
								: `${index + 1}. ?`
						})
						.join('\n')

					answeredQuestionsArray.push({
						id: question.id,
						text: question.text,
						type: question.type,
						squashedAnswers,
					})
				} else {
					const squashedAnswers = answers
						.map((answer) => answer.text)
						.join('; ')

					answeredQuestionsArray.push({
						id: question.id,
						text: question.text,
						type: question.type,
						squashedAnswers,
					})
				}
			})
			return answeredQuestionsArray
		},
	},

	methods: {
		onDelete(): void {
			this.$emit('delete')
		},

		onCopy(event: ClipboardEvent): void {
			if (!event.clipboardData) return

			const selection = window.getSelection()
			if (!selection || selection.isCollapsed) return

			const fragment = selection.getRangeAt(0).cloneContents()
			const text = this.serializeNode(fragment).trim()

			if (!text) return

			event.clipboardData.setData('text/plain', text)
			event.preventDefault()
		},

		serializeNode(node: Node): string {
			if (node.nodeType === Node.TEXT_NODE) {
				return node.textContent ?? ''
			}

			if (
				node.nodeType !== Node.ELEMENT_NODE
				&& node.nodeType !== Node.DOCUMENT_FRAGMENT_NODE
			) {
				return ''
			}

			const tag = (node as Element).tagName?.toLowerCase()

			if (tag && ['svg', 'script', 'style'].includes(tag)) return ''
			if (tag === 'br') return '\n'

			const children = Array.from(node.childNodes)
				.map((child) => this.serializeNode(child))
				.join('')

			// Answer blocks get a blank line before them as visual separator
			if (tag === 'div' && (node as Element).classList?.contains('answer')) {
				const trimmed = children.replace(/\s+$/, '')
				return trimmed ? '\n' + trimmed + '\n' : ''
			}

			const isBlock =
				tag
				&& [
					'div',
					'p',
					'h1',
					'h2',
					'h3',
					'h4',
					'h5',
					'h6',
					'li',
					'td',
					'tr',
					'th',
					'dt',
					'dd',
				].includes(tag)
			if (isBlock) {
				const trimmed = children.replace(/\s+$/, '')
				return trimmed ? trimmed + '\n' : ''
			}

			return children
		},
	},
})
</script>

<style lang="scss" scoped>
.submission {
	padding-inline: var(--default-clickable-area) 16px;

	&-head {
		display: flex;
		align-items: flex-end;

		h3 {
			font-weight: bold;
		}
	}

	&-menu {
		margin: 0 0 12px var(--default-grid-baseline);
		display: inline-block;
	}

	&-date {
		color: var(--color-text-lighter);
		margin-block-start: -8px;
	}
}
</style>
