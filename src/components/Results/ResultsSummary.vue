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

		<!-- Ranking questions: Borda count with average rank -->
		<div v-if="question.type === 'ranking'" class="question-summary__statistic">
			<p class="question-summary__ranking-description">
				{{
					t(
						'forms',
						'Ranked by Borda count: each 1st place receives {n} points, 2nd place {n1} points, and so on. Higher score means more preferred.',
						{
							n: (question.options ?? []).length,
							n1: (question.options ?? []).length - 1,
						},
					)
				}}
			</p>
			<ol>
				<li v-for="option in rankingStats" :key="option.id">
					<label>
						<span class="question-summary__statistic-score">
							{{ option.bordaTotal }}
						</span>
						<span class="question-summary__statistic-percentage">
							({{
								t('forms', 'avg. rank {average}', {
									average: option.avgRank,
								})
							}}):
						</span>
						<span
							:class="{
								'question-summary__statistic-text--best':
									option.best,
							}">
							{{ option.text }}
						</span>
					</label>
					<meter min="0" :max="maxBordaScore" :value="option.bordaTotal" />
				</li>
			</ol>
		</div>

		<!-- Answers with countable results for visualization -->
		<ol
			v-else-if="answerTypes[question.type].predefined"
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

		<div v-else-if="question.type === 'grid'">
			<table class="answer-grid">
				<thead>
					<tr>
						<th class="first-column"></th>

						<th v-for="column of gridColumns" :key="column.id">
							{{ column.text }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row of gridRows" :key="row.id">
						<td class="first-column">{{ row.text }}</td>
						<td v-for="column of gridColumns" :key="column.id">
							<template
								v-if="
									question.extraSettings?.questionType === 'radio'
								">
								{{ gridValue[row.id][column.id].answersCount }} ({{
									gridValue[row.id][column.id].percentage
								}}%)
							</template>

							<template
								v-if="
									question.extraSettings?.questionType
									=== 'checkbox'
								">
								{{ gridValue[row.id][column.id].answersCount }} ({{
									gridValue[row.id][column.id].percentage
								}}%)
							</template>

							<template
								v-if="
									question.extraSettings?.questionType === 'number'
								">
								{{ gridValue[row.id][column.id].averageValue }}
							</template>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Text answers are simply listed for now, could be automatically grouped in the future -->
		<ul v-else class="question-summary__text">
			<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
			<!-- eslint-disable-next-line -->
			<li v-for="(answer, index) in answers" :key="answer.id" dir="auto">
				<template v-if="answer.url">
					<a :href="answer.url" target="_blank">
						<NcIconSvgWrapper :svg="IconFile" inline />
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

<script lang="ts">
import type { PropType } from 'vue'
import type {
	BordaRankStats,
	FormsAnswer,
	FormsQuestion,
	FormsSubmission,
	GridMatrixCell,
	OptionStats,
} from '../../types/Entities.d.ts'

import IconFile from '@material-symbols/svg-400/outlined/draft.svg?raw'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, defineComponent } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import answerTypes from '../../models/AnswerTypes.ts'
import { GridCellType, OptionType } from '../../models/Constants.ts'

export default defineComponent({
	name: 'ResultsSummary',

	components: {
		NcIconSvgWrapper,
	},

	props: {
		submissions: {
			type: Array as PropType<FormsSubmission[]>,
			required: true,
		},

		question: {
			type: Object as PropType<FormsQuestion>,
			required: true,
		},
	},

	setup(props) {
		const questionTypeLabel = computed(() => {
			const label = answerTypes[props.question.type]?.label ?? ''

			if (props.question.type === 'grid') {
				if (
					props.question.extraSettings?.questionType
					=== GridCellType.Checkbox
				) {
					return `${label} (${t('forms', 'Checkbox')})`
				}
				if (
					props.question.extraSettings?.questionType
					=== GridCellType.Number
				) {
					return `${label} (${t('forms', 'Number')})`
				}
				if (
					props.question.extraSettings?.questionType === GridCellType.Radio
				) {
					return `${label} (${t('forms', 'Radio')})`
				}
			}

			if (props.question.type === 'linearscale') {
				const labelLowest =
					props.question.extraSettings?.optionsLabelLowest
					?? t('forms', 'Strongly disagree')
				const labelHighest =
					props.question.extraSettings?.optionsLabelHighest
					?? t('forms', 'Strongly agree')
				const optionsLowest =
					props.question.extraSettings?.optionsLowest?.toString() ?? '1'
				const optionsHighest =
					props.question.extraSettings?.optionsHighest?.toString() ?? '5'
				const descriptionParts: string[] = []

				if (labelLowest !== '') {
					descriptionParts.push(`${optionsLowest}: ${labelLowest}`)
				}
				if (labelHighest !== '') {
					descriptionParts.push(`${optionsHighest}: ${labelHighest}`)
				}

				return `${label} (${descriptionParts.join(', ')})`
			}

			return label
		})

		// For countable questions like multiple choice and checkboxes
		const questionOptions = computed<OptionStats[]>(() => {
			// Build list of question options
			let questionOptionsStats: OptionStats[]
			if (props.question.type !== 'linearscale') {
				questionOptionsStats = (props.question.options ?? []).map(
					(option) => ({
						...option,
						count: 0,
						percentage: 0,
					}),
				)
			} else {
				questionOptionsStats = Array.from(
					{
						length:
							(props.question.extraSettings?.optionsHighest ?? 5)
							- (props.question.extraSettings?.optionsLowest ?? 1)
							+ 1,
					},
					(_, i) => ({
						local: false,
						id: i,
						text: (
							i + (props.question.extraSettings?.optionsLowest ?? 1)
						).toString(),
						order: 0,
						questionId: props.question.id,
						optionType: '',
						count: 0,
						percentage: 0,
					}),
				)
			}

			// Also record 'Other'
			if (props.question.extraSettings?.allowOtherAnswer) {
				questionOptionsStats.unshift({
					local: false,
					id: -1,
					text: t('forms', 'Other'),
					order: 0,
					questionId: props.question.id,
					optionType: '',
					count: 0,
					percentage: 0,
				})
			}

			// Also record 'No response'
			questionOptionsStats.unshift({
				// TRANSLATORS Counts on Results-Summary, how many users did not respond to this question.
				local: false,
				id: -2,
				text: t('forms', 'No response'),
				order: 0,
				questionId: props.question.id,
				optionType: '',
				count: 0,
				percentage: 0,
			})

			// Go through submissions to check which options have how many responses
			props.submissions.forEach((submission) => {
				const answers = submission.answers.filter(
					(answer) => answer.questionId === props.question.id,
				)
				if (!answers.length) {
					questionOptionsStats[0].count++
					return
				}

				// Check question options to find which needs to be increased
				answers.forEach((answer) => {
					const optionsStatIndex = questionOptionsStats.findIndex(
						(option) => option.text === answer.text,
					)
					if (optionsStatIndex < 0) {
						if (props.question.extraSettings?.allowOtherAnswer) {
							questionOptionsStats[1].count++
						} else {
							questionOptionsStats.push({
								local: false,
								id: -3,
								text: answer.text,
								order: 0,
								questionId: props.question.id,
								optionType: '',
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
			if (props.question.type !== 'linearscale') {
				questionOptionsStats.sort(
					(object1, object2) => object2.count - object1.count,
				)
			} else {
				// for linear scale questions move the "No response" element to the end
				const noResponse = questionOptionsStats.shift()
				if (noResponse) {
					questionOptionsStats.push(noResponse)
				}
			}

			questionOptionsStats.forEach((questionOptionsStat) => {
				// Fill percentage values
				questionOptionsStat.percentage = Math.round(
					(100 * questionOptionsStat.count) / props.submissions.length,
				)
				// Mark all best results
				const maxCount = Math.max(
					...questionOptionsStats.map((option) => option.count),
				)
				questionOptionsStat.best = questionOptionsStat.count === maxCount
			})

			return questionOptionsStats
		})

		/**
		 * Borda count ranking statistics
		 */
		const rankingStats = computed<BordaRankStats[]>(() => {
			const n = (props.question.options ?? []).length
			const stats: Record<string | number, BordaRankStats> = {}

			for (const opt of props.question.options ?? []) {
				stats[opt.id] = {
					id: opt.id,
					text: opt.text,
					bordaTotal: 0,
					rankSum: 0,
					count: 0,
					avgRank: '-',
				}
			}

			for (const submission of props.submissions) {
				const answer = submission.answers.find(
					(a) => a.questionId === props.question.id,
				)
				if (!answer) continue
				const ranked = JSON.parse(answer.text) as (string | number)[]
				ranked.forEach((optionId, index) => {
					if (stats[optionId]) {
						stats[optionId].bordaTotal += n - index
						stats[optionId].rankSum += index + 1
						stats[optionId].count++
					}
				})
			}

			const result = Object.values(stats)
				.map((s) => ({
					...s,
					avgRank: s.count > 0 ? (s.rankSum / s.count).toFixed(1) : '-',
				}))
				.sort((a, b) => b.bordaTotal - a.bordaTotal)

			// Mark best (highest Borda score)
			if (result.length > 0 && result[0].bordaTotal > 0) {
				const best = result[0].bordaTotal
				result.forEach((o) => {
					o.best = o.bordaTotal === best
				})
			}

			return result
		})

		const maxBordaScore = computed(
			() => (props.question.options ?? []).length * props.submissions.length,
		)

		const gridColumns = computed(() => {
			return (props.question.options ?? []).filter(
				(option) => option.optionType === OptionType.Column,
			)
		})

		const gridRows = computed(() => {
			return (props.question.options ?? []).filter(
				(option) => option.optionType === OptionType.Row,
			)
		})

		const gridValue = computed<
			Record<string | number, Record<string | number, GridMatrixCell>>
		>(() => {
			const matrix: Record<
				string | number,
				Record<string | number, GridMatrixCell>
			> = {}
			for (const row of gridRows.value) {
				for (const column of gridColumns.value) {
					matrix[row.id] = matrix[row.id] || {}
					matrix[row.id][column.id] = {
						answersCount: 0,
						percentage: 0,
						totalValue: 0,
						averageValue: 0,
					}
				}
			}

			const answersList: FormsAnswer[] = []
			props.submissions.forEach((submission) => {
				submission.answers.forEach((answer) => {
					if (answer.questionId === props.question.id) {
						answersList.push(answer)
					}
				})
			})

			answersList.forEach((answer) => {
				const answerJson = JSON.parse(answer.text)

				if (
					props.question.extraSettings?.questionType === GridCellType.Radio
				) {
					for (const rowId of Object.keys(answerJson)) {
						const columnId = answerJson[rowId]

						if (matrix[rowId]?.[columnId]) {
							matrix[rowId][columnId].answersCount++
						}
					}
				} else if (
					props.question.extraSettings?.questionType
					=== GridCellType.Checkbox
				) {
					for (const rowId of Object.keys(answerJson)) {
						if (!Array.isArray(answerJson[rowId])) {
							continue
						}
						for (const columnId of answerJson[rowId]) {
							if (matrix[rowId]?.[columnId]) {
								matrix[rowId][columnId].answersCount++
							}
						}
					}
				} else if (
					props.question.extraSettings?.questionType
					=== GridCellType.Number
				) {
					for (const rowId of Object.keys(answerJson)) {
						if (!matrix[rowId]) {
							continue
						}
						for (const columnId of Object.keys(answerJson[rowId])) {
							if (answerJson[rowId][columnId] === '') {
								continue
							}

							if (matrix[rowId][columnId]) {
								matrix[rowId][columnId].totalValue += parseFloat(
									answerJson[rowId][columnId],
								)
								matrix[rowId][columnId].answersCount++
							}
						}
					}
				}
			})

			for (const rowId of Object.keys(matrix)) {
				for (const columnId of Object.keys(matrix[rowId])) {
					let totalAnswersCount = props.submissions.length
					if (
						props.question.extraSettings?.questionType
						=== GridCellType.Checkbox
					) {
						totalAnswersCount = Object.entries(matrix[rowId])
							.map(([, cell]) => cell.answersCount)
							.reduce((a, b) => a + b, 0)
					}
					if (totalAnswersCount === 0) {
						totalAnswersCount = 1
					}

					if (!matrix[rowId][columnId].answersCount) {
						matrix[rowId][columnId].answersCount = 0
					}

					matrix[rowId][columnId].percentage = Math.round(
						(100 * matrix[rowId][columnId].answersCount)
							/ totalAnswersCount,
					)

					if (
						props.question.extraSettings?.questionType
							=== GridCellType.Number
						&& matrix[rowId][columnId].answersCount > 0
					) {
						matrix[rowId][columnId].averageValue = (
							matrix[rowId][columnId].totalValue
							/ matrix[rowId][columnId].answersCount
						).toFixed(1)
					}
				}
			}

			return matrix
		})

		// For text answers like short answer and long text
		const answers = computed<(FormsAnswer & { url?: string })[]>(() => {
			const answersModels: (FormsAnswer & { url?: string })[] = []

			// Also record 'No response'
			let noResponseCount = 0
			let combinedAnswerId = -1000 // Use negative IDs for combined answers

			// Go through submissions to check which options have how many responses
			props.submissions.forEach((submission) => {
				const answersForQuestion = submission.answers.filter(
					(answer) => answer.questionId === props.question.id,
				)
				if (!answersForQuestion.length) {
					// Record 'No response'
					noResponseCount++
				}

				// Add text answers
				if (
					['date', 'time'].includes(props.question.type)
					&& answersForQuestion.length === 2
				) {
					// Combine the first two answers in order for date range questions
					answersModels.push({
						id: combinedAnswerId--,
						questionId: props.question.id,
						text: `${answersForQuestion[0].text} - ${answersForQuestion[1].text}`,
					})
				} else {
					answersForQuestion.forEach((answer) => {
						if (answer.fileId) {
							answersModels.push({
								...answer,
								url: generateUrl('/f/{fileId}', {
									fileId: answer.fileId,
								}),
							})
						} else {
							answersModels.push(answer)
						}
					})
				}
			})

			// Calculate no response percentage
			const noResponsePercentage = Math.round(
				(100 * noResponseCount) / props.submissions.length,
			)
			answersModels.unshift({
				id: -1,
				questionId: props.question.id,
				text: `${noResponseCount} (${noResponsePercentage}%): ${t('forms', 'No response')}`,
			})

			return answersModels
		})

		return {
			IconFile,
			answerTypes,
			questionTypeLabel,
			questionOptions,
			rankingStats,
			maxBordaScore,
			gridColumns,
			gridRows,
			gridValue,
			answers,
			t,
		}
	},
})
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

			.question-summary__ranking-description {
				color: var(--color-text-maxcontrast);
				font-style: italic;
				margin-block-end: 8px;
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

	.answer-grid {
		border-collapse: collapse;
		width: 100%;

		thead tr {
			border-bottom: 2px solid var(--color-border);
		}

		td {
			min-height: 34px;
			min-width: 64px;
			text-align: center;
			padding: 8px 4px;

			.checkbox-radio-switch {
				display: flex;
				justify-content: center;
			}
		}

		th {
			min-height: 44px;
			padding: 8px 4px;
			text-align: center;
		}

		.first-column {
			min-width: 200px;
			text-align: start;
			position: sticky;
			inset-inline-start: 0;
		}
	}
}
</style>
