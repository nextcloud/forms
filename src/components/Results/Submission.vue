<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="section submission">
		<div class="submission-head">
			<h3 dir="auto">
				{{ submission.userDisplayName }}
			</h3>
			<NcActions class="submission-menu" force-menu>
				<NcActionRouter
					v-if="canEditSubmission"
					:to="{
						name: 'submit',
						params: { hash: formHash, submissionId: submission.id },
					}">
					<template #icon>
						<IconPencil :size="20" />
					</template>
					{{ t('forms', 'Edit this response') }}
				</NcActionRouter>
				<NcActionButton v-if="canDeleteSubmission" @click="onDelete">
					<template #icon>
						<IconDelete :size="20" />
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
			:answer-text="question.squashedAnswers"
			:answers="question.answers"
			:question-text="question.text"
			:question-type="question.type" />
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/components/NcActionRouter'
import NcActions from '@nextcloud/vue/components/NcActions'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import Answer from './Answer.vue'

export default {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Submission',

	components: {
		Answer,
		IconDelete,
		IconPencil,
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
			type: Object,
			required: true,
		},

		questions: {
			type: Array,
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

	computed: {
		// Format submission-timestamp to DateTime
		submissionDateTime() {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},

		/**
		 * Join answered Questions with corresponding answers.
		 * Multiple answers to a question are squashed into one string.
		 *
		 * @return {Array}
		 */
		answeredQuestions() {
			const answeredQuestionsArray = []

			this.questions.forEach((question) => {
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
		onDelete() {
			this.$emit('delete')
		},
	},
}
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
