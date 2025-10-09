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
			{{ questionTypeLabel }}
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
			<li v-for="(answer, index) in answers" :key="answer.id" dir="auto">
				<template v-if="answer.url">
					<a :href="answer.url" target="_blank">
						<IconFile :size="20" class="question-summary__text-icon" />
						{{ answer.text }}
					</a>
				</template>
				<template v-else-if="question.type === 'color'">
					<div class="color__result">
						<div
							v-if="answer.id !== 0"
							:style="{ 'background-color': answer.text }"
							:class="
								index === 1
									? 'color__field color__field__first'
									: 'color__field'
							" />
						{{ answer.text }}
					</div>
				</template>
				<template v-else>
					{{ answer.text }}
				</template>
			</li>
		</ul>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import IconFile from 'vue-material-design-icons/FileOutline.vue'
import answerTypes from '../../models/AnswerTypes.js'

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
		questionTypeLabel() {
			const label = this.answerTypes[this.question.type].label

			if (this.question.type === 'linearscale') {
				const labelLowest =
					this.question.extraSettings?.optionsLabelLowest
					?? t('forms', 'Strongly disagree')
				const labelHighest =
					this.question.extraSettings?.optionsLabelHighest
					?? t('forms', 'Strongly agree')
				const optionsLowest =
					this.question.extraSettings?.optionsLowest?.toString() ?? '1'
				const optionsHighest =
					this.question.extraSettings?.optionsHighest?.toString() ?? '5'

				const descriptionParts = []
				if (labelLowest !== '') {
					descriptionParts.push(`${optionsLowest}: ${labelLowest}`)
				}
				if (labelHighest !== '') {
					descriptionParts.push(`${optionsHighest}: ${labelHighest}`)
				}
				const description = ` (${descriptionParts.join(', ')})`
				return label + description
			}

			return label
		},

		// For countable questions like multiple choice and checkboxes
		questionOptions() {
			// Build list of question options
			let questionOptionsStats
			if (this.question.type !== 'linearscale') {
				questionOptionsStats = this.question.options.map((option) => ({
					...option,
					count: 0,
					percentage: 0,
				}))
			} else {
				questionOptionsStats = Array.from(
					{
						length:
							(this.question.extraSettings?.optionsHighest ?? 5)
							- (this.question.extraSettings?.optionsLowest ?? 1)
							+ 1,
					},
					(_, i) => ({
						text: (
							i + (this.question.extraSettings?.optionsLowest ?? 1)
						).toString(),
						count: 0,
						percentage: 0,
					}),
				)
			}

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
			if (this.question.type !== 'linearscale') {
				questionOptionsStats.sort((object1, object2) => {
					return object2.count - object1.count
				})
			} else {
				// for linear scale questions move the "No response" element to the end
				questionOptionsStats.push(questionOptionsStats.shift())
			}

			questionOptionsStats.forEach((questionOptionsStat) => {
				// Fill percentage values
				questionOptionsStat.percentage = Math.round(
					(100 * questionOptionsStat.count) / this.submissions.length,
				)
				// Mark all best results
				const maxCount = Math.max(
					...questionOptionsStats.map((option) => option.count),
				)
				questionOptionsStat.best = questionOptionsStat.count === maxCount
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
				if (
					['date', 'time'].includes(this.question.type)
					&& answers.length === 2
				) {
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
					noResponseCount
					+ ' ('
					+ noResponsePercentage
					+ '%): '
					+ t('forms', 'No response'),
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

	.color__field {
		width: 100px;
		height: var(--default-clickable-area);
		border-radius: var(--border-radius-element);
		position: relative;
		inset-block-start: 12px;

		&__first {
			margin-block-start: -12px;
		}
	}

	.color__result {
		align-items: baseline;
		display: flex;
		gap: calc(var(--clickable-area-small) / 2);
	}
}
</style>
