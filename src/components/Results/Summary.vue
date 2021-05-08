<!--
  - @copyright Copyright (c) 2020 Jan C. Borchardt https://jancborchardt.net
  -
  - @author Jan C. Borchardt https://jancborchardt.net
  -
  - @license GNU AGPL version 3 or any later version
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
	<div class="section question-summary">
		<h3>{{ question.text }}</h3>
		<p class="question-summary__detail">
			{{ answerTypes[question.type].label }}
		</p>

		<!-- Answers with countable results for visualization -->
		<ol v-if="answerTypes[question.type].predefined"
			class="question-summary__statistic">
			<li v-for="option in questionOptions"
				:key="option.id">
				<label :for="`option-${option.questionId}-${option.id}`">
					{{ option.count }}
					<span class="question-summary__statistic-percentage">
						({{ option.percentage }}%):
					</span>
					<span :class="{'question-summary__statistic-text--best':option.best}">
						{{ option.text }}
					</span>
				</label>
				<meter :id="`option-${option.questionId}-${option.id}`"
					min="0"
					:max="submissions.length"
					:value="option.count" />
			</li>
		</ol>

		<!-- Text answers are simply listed for now, could be automatically grouped in the future -->
		<ul v-else class="question-summary__text">
			<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
			<!-- eslint-disable-next-line -->
			<li v-for="answer in textAnswers" :key="answer.id">{{ answer }}</li>
		</ul>
	</div>
</template>

<script>
import answerTypes from '../../models/AnswerTypes'
import moment from '@nextcloud/moment'

export default {
	name: 'Summary',

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
			const questionOptionsStats = this.question.options.map(option => ({
				...option,
				count: 0,
				percentage: 0,
			}))

			// Also record 'No response'
			questionOptionsStats.unshift({
				// TRANSLATORS Counts on Results-Summary, how many users did not respond to this question.
				text: t('forms', 'No response'),
				count: 0,
				percentage: 0,
			})

			// Go through submissions to check which options have how many responses
			this.submissions.forEach(submission => {
				const answers = submission.answers.filter(answer => answer.questionId === this.question.id)
				if (!answers.length) {
					// Record 'No response'
					questionOptionsStats[0].count++
				}

				// Check question options to find which needs to be increased
				answers.forEach(answer => {
					const optionsStatIndex = questionOptionsStats.findIndex(option => option.text === answer.text)
					if (optionsStatIndex < 0) {
						questionOptionsStats.push({
							text: answer.text,
							count: 1,
							percentage: 0,
						})
					} else {
						questionOptionsStats[optionsStatIndex].count++
					}
				})
			})

			// Sort options by response count
			questionOptionsStats.sort((object1, object2) => {
				return object2.count - object1.count
			})

			questionOptionsStats.forEach(questionOptionsStat => {
				// Fill percentage values
				questionOptionsStat.percentage = Math.round((100 * questionOptionsStat.count) / this.submissions.length)
				// Mark all best results. First one is best for sure due to sorting
				questionOptionsStat.best = (questionOptionsStat.count === questionOptionsStats[0].count)
			})

			return questionOptionsStats
		},

		// For text answers like short answer and long text
		textAnswers() {
			const textAnswers = []
			let answerText

			// Also record 'No response'
			let noResponseCount = 0

			// Go through submissions to check which options have how many responses
			this.submissions.forEach(submission => {
				const answers = submission.answers.filter(answer => answer.questionId === this.question.id)
				if (!answers.length) {
					// Record 'No response'
					noResponseCount++
				}

				// Add text answers
				answers.forEach(answer => {
					// Format answerText for date/datetime answers
					if (this.question.type === 'date') {
						answerText = moment(answer.text, 'x').format('LL')
					} else if (this.question.type === 'datetime') {
						answerText = moment(answer.text, 'x').format('LLL')
					} else {
						answerText = answer.text
					}

					textAnswers.push(answerText)
				})
			})

			// Calculate no response percentage
			const noResponsePercentage = Math.round((100 * noResponseCount) / this.submissions.length)
			textAnswers.unshift(noResponseCount + ' (' + noResponsePercentage + '%): ' + t('forms', 'No response'))

			return textAnswers
		},
	},
}
</script>

<style lang="scss" scoped>
.question-summary {
	padding-left: 16px;
	padding-right: 16px;

	h3 {
		font-weight: bold;
	}

	&__detail {
		color: var(--color-text-lighter);
		margin-top: -8px;
	}

	&__text,
	&__statistic {
		margin-top: 8px;
	}

	&__text {
		list-style-type: initial;

		li {
			padding: 4px 0;
			white-space: pre-line;

			&:first-child {
				font-weight: bold;
			}
		}
	}

	&__statistic {
		list-style-type: none;

		li {
			position: relative;
			padding: 8px 0;

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
				margin-top: 4px;
				background: var(--color-background-dark);
				height: calc(var(--border-radius) * 2);
				border-radius: var(--border-radius);

				&::-webkit-meter-bar {
					height: calc(var(--border-radius) * 2);
				}

				// The pseudo-classes of -moz and -webkit have to stay separated even with SCSS, otherwise they don’t work
				&::-webkit-meter-optimum-value {
					background: linear-gradient(40deg, var(--color-primary-element) 0%, var(--color-primary-element-light) 100%);
					border-radius: var(--border-radius);
				}

				&::-moz-meter-bar {
					background: linear-gradient(40deg, var(--color-primary-element) 0%, var(--color-primary-element-light) 100%);
					border-radius: var(--border-radius);
				}
			}
		}
	}
}
</style>
