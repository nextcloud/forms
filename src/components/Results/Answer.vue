<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="answer">
		<h4 class="answer__question-text" dir="auto">
			{{ questionText }}
		</h4>
		<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
		<!-- eslint-disable-next-line -->
		<template v-if="questionType === 'file' && answers.length">
			<p
				v-for="answer of answers"
				:key="answer.id"
				class="answer__text"
				dir="auto">
				<a :href="answer.url" target="_blank">
					<IconFile :size="20" class="answer__text-icon" />
					<NcHighlight :text="answer.text" :search="highlight" />
				</a>
			</p>
		</template>
		<template v-else-if="questionType === 'color'">
			<div class="color__result">
				<div
					:style="{ 'background-color': answerText }"
					class="color__field" />
				<NcHighlight :text="answerText" :search="highlight" />
			</div>
		</template>
		<template v-else-if="questionType === 'grid'">
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
							<template v-if="gridCellType === 'radio'">
								<NcCheckboxRadioSwitch
									:model-value="gridValue[row.id]"
									:name="`${row.id}-answer`"
									:value="column.id.toString()"
									disabled
									type="radio" />
							</template>

							<template v-if="gridCellType === 'checkbox'">
								<NcCheckboxRadioSwitch
									:model-value="gridValue[row.id] || []"
									:name="`${row.id}-answer`"
									:value="column.id.toString()"
									disabled
									type="checkbox" />
							</template>

							<template v-if="gridCellType === 'number'">
								{{ gridValue[row.id][column.id] }}
							</template>
						</td>
					</tr>
				</tbody>
			</table>
		</template>
		<p v-else class="answer__text" dir="auto">
			<NcHighlight :text="answerText" :search="highlight" />
		</p>
	</div>
</template>

<script>
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import IconFile from 'vue-material-design-icons/FileOutline.vue'

export default {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Answer',
	components: {
		NcCheckboxRadioSwitch,
		IconFile,
		NcHighlight,
	},

	props: {
		answers: {
			type: Array,
			required: false,
			default: () => [],
		},

		answerText: {
			type: String,
			required: false,
			default: '',
		},

		questionText: {
			type: String,
			required: true,
		},

		questionType: {
			type: String,
			required: true,
		},

		gridCellType: {
			type: String,
			required: false,
			default: null,
		},

		gridColumns: {
			type: Array,
			required: false,
			default: () => [],
		},

		gridRows: {
			type: Array,
			required: false,
			default: () => [],
		},

		gridValue: {
			type: Object,
			required: false,
			default: () => null,
		},

		highlight: {
			type: String,
			required: false,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
.answer {
	margin-block-start: 12px;
	width: 100%;

	&__question-text {
		font-weight: bold;
	}

	&__text {
		white-space: pre-line;

		&-icon {
			display: inline-flex;
			position: relative;
			top: 4px;
		}
	}

	.color__field {
		width: 100px;
		height: var(--default-clickable-area);
		border-radius: var(--border-radius-element);
		position: relative;
		inset-block-start: 12px;
		margin-block-start: -12px;
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
			text-align: left;
			position: sticky;
			left: 0;
		}
	}
}
</style>
