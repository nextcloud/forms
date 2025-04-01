<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="section question-summary">
		<h3 dir="auto">
			{{ question.text }}
		</h3>
		<p class="question-summary__detail">
			{{ answerTypes[question.type].label }}
		</p>

		<!-- Answers with countable results for visualization -->
		<ol
			v-if="answerTypes[question.type].predefined"
			class="question-summary__statistic">
			<li v-for="option in questionOptions" :key="option.id">
				<label :for="`option-${option.questionId}-${option.id}`">
					{{ option.count }}
					<span class="question-summary__statistic-percentage">
						({{ option.percentage }}%):
					</span>
					<span
						:class="{
							'question-summary__statistic-text--best': option.best,
						}">
						{{ option.text }}
					</span>
				</label>
				<meter
					:id="`option-${option.questionId}-${option.id}`"
					min="0"
					:max="submissions.length"
					:value="option.count" />
			</li>
		</ol>

		<!-- Text answers are simply listed for now, could be automatically grouped in the future -->
		<ul v-else class="question-summary__text">
			<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
			<!-- eslint-disable-next-line -->
			<li v-for="answer in answers" :key="answer.id" dir="auto">
				<template v-if="answer.url">
					<a :href="answer.url" target="_blank">
						<IconFile :size="20" class="question-summary__text-icon" />
						{{ answer.text }}
					</a>
				</template>
				<template v-else>
					{{ answer.text }}
				</template>
			</li>
		</ul>
	</div>
</template>

<script>
import answerTypes from '../../models/AnswerTypes.js'
import { generateUrl } from '@nextcloud/router'
import IconFile from 'vue-material-design-icons/File.vue'

export default {
	name: 'ResultsSummary',

	components: {
		IconFile,
	},

	props: {
		submissions: {
			type: Array,
			required: true,
		},
		question: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			answerTypes,
		}
	},

	computed: {
		// For countable questions like multiple choice and checkboxes
		questionOptions() {
			// Build list of question options
			const questionOptionsStats = this.question.options.map((option) => ({
				...option,
				count: 0,
				percentage: 0,
			}))

			// Also record 'Other'
			if (this.question.extraSettings?.allowOtherAnswer) {
				questionOptionsStats.unshift({
					text: t('forms', 'Other'),
					count: 0,
					percentage: 0,
				})
			}

			// Also record 'No response'
			questionOptionsStats.unshift({
				// TRANSLATORS Counts on Results-Summary, how many users did not respond to this question.
				text: t('forms', 'No response'),
				count: 0,
				percentage: 0,
			})

			// Go through submissions to check which options have how many responses
			this.submissions.forEach((submission) => {
				const answers = submission.answers.filter(
					(answer) => answer.questionId === this.question.id,
				)
				if (!answers.length) {
					// Record 'No response'
					questionOptionsStats[0].count++
				}

				// Check question options to find which needs to be increased
				answers.forEach((answer) => {
					const optionsStatIndex = questionOptionsStats.findIndex(
						(option) => option.text === answer.text,
					)
					if (optionsStatIndex < 0) {
						if (this.question.extraSettings?.allowOtherAnswer) {
							questionOptionsStats[1].count++
						} else {
							questionOptionsStats.push({
								text: answer.text,
								count: 1,
								percentage: 0,
							})
						}
					} else {
						questionOptionsStats[optionsStatIndex].count++
					}
				})
			})

			// Sort options by response count
			questionOptionsStats.sort((object1, object2) => {
				return object2.count - object1.count
			})

			questionOptionsStats.forEach((questionOptionsStat) => {
				// Fill percentage values
				questionOptionsStat.percentage = Math.round(
					(100 * questionOptionsStat.count) / this.submissions.length,
				)
				// Mark all best results. First one is best for sure due to sorting
				questionOptionsStat.best =
					questionOptionsStat.count === questionOptionsStats[0].count
			})

			return questionOptionsStats
		},

		// For text answers like short answer and long text
		answers() {
			const answersModels = []

			// Also record 'No response'
			let noResponseCount = 0

			// Go through submissions to check which options have how many responses
			this.submissions.forEach((submission) => {
				const answers = submission.answers.filter(
					(answer) => answer.questionId === this.question.id,
				)
				if (!answers.length) {
					// Record 'No response'
					noResponseCount++
				}

				// Add text answers
				if (this.question.type === 'date' && answers.length === 2) {
					// Combine the first two answers in order for date range questions
					answersModels.push({
						id: `${answers[0].id}-${answers[1].id}`,
						text: `${answers[0].text} - ${answers[1].text}`,
					})
				} else {
					answers.forEach((answer) => {
						if (answer.fileId) {
							answersModels.push({
								id: answer.id,
								text: answer.text,
								url: generateUrl('/f/{fileId}', {
									fileId: answer.fileId,
								}),
							})
						} else {
							answersModels.push({
								id: answer.id,
								text: answer.text,
							})
						}
					})
				}
			})

			// Calculate no response percentage
			const noResponsePercentage = Math.round(
				(100 * noResponseCount) / this.submissions.length,
			)
			answersModels.unshift({
				id: 0,
				text:
					noResponseCount +
					' (' +
					noResponsePercentage +
					'%): ' +
					t('forms', 'No response'),
			})

			return answersModels
		},
	},
}
</script>

<style lang="scss" scoped>
.question-summary {
	padding-inline: var(--default-clickable-area) 16px;

	h3 {
		font-weight: bold;
	}

	&__detail {
		color: var(--color-text-lighter);
		margin-block-start: -8px;
	}

	&__text,
	&__statistic {
		margin-block-start: 8px;
	}

	&__text {
		list-style-type: initial;

		li {
			padding-block: 4px;
			padding-inline: 0;
			white-space: pre-line;

			&:first-child {
				font-weight: bold;
			}
		}

		&-icon {
			display: inline-flex;
			position: relative;
			top: 4px;
		}
	}

	&__statistic {
		list-style-type: none;

		li {
			position: relative;
			padding-block: 8px;
			padding-inline: 0;

			label {
				cursor: default;
			}

			.question-summary__statistic-text--best {
				font-weight: bold;
			}

			.question-summary__statistic-percentage {
				color: var(--color-text-maxcontrast);
			}

			meter {
				display: block;
				width: 100%;
				margin-block-start: 4px;
				background: var(--color-background-dark);
				height: calc(var(--border-radius) * 2);
				border-radius: var(--border-radius);

				&::-webkit-meter-bar {
					height: calc(var(--border-radius) * 2);
				}

				// The pseudo-classes of -moz and -webkit have to stay separated even with SCSS, otherwise they don’t work
				&::-webkit-meter-optimum-value {
					// TODO switch to old gradient if it becomes available in server
					background: var(--gradient-primary-background);
					border-radius: var(--border-radius);
				}

				&::-moz-meter-bar {
					// TODO switch to old gradient if it becomes available in server
					background: var(--gradient-primary-background);
					border-radius: var(--border-radius);
				}
			}
		}
	}
}
</style>
