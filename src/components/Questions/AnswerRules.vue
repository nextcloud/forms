<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<fieldset ref="rootElement" class="answer-rules">
		<legend class="answer-rules__legend">
			{{ t('forms', 'Answer rules') }}
		</legend>
		<div v-for="row in rows" :key="row.id" class="answer-rules__rule">
			<div
				class="answer-rules__row"
				:class="{
					'answer-rules__row--text':
						row.kind === 'contains' || row.kind === 'notContains',
				}">
				<NcSelect
					class="answer-rules__select"
					:ariaLabelCombobox="t('forms', 'Rule')"
					:clearable="false"
					:searchable="false"
					:options="kindOptions(row.kind)"
					:modelValue="{ id: row.kind, label: kindLabels[row.kind] }"
					@update:modelValue="(option) => changeKind(row, option.id)">
					<template #option="option">
						{{ option.label }}
					</template>
				</NcSelect>
				<NcSelect
					v-if="row.kind === 'length' || row.kind === 'number'"
					class="answer-rules__select"
					:ariaLabelCombobox="t('forms', 'Condition')"
					:clearable="false"
					:searchable="false"
					:options="conditionOptions(row.kind)"
					:modelValue="{
						id: row.condition,
						label: conditionLabels[row.condition ?? ''],
					}"
					@update:modelValue="(option) => changeCondition(row, option.id)">
					<template #option="option">
						{{ option.label }}
					</template>
				</NcSelect>
				<template v-if="row.kind === 'length'">
					<NcTextField
						v-if="row.condition !== 'atMost'"
						type="number"
						min="1"
						:label="t('forms', 'Minimum characters')"
						:modelValue="String(extraSettings.lengthMin ?? '')"
						v-bind="fieldState('lengthMin')"
						@update:modelValue="
							(value) => saveField('lengthMin', value)
						" />
					<NcTextField
						v-if="row.condition !== 'atLeast'"
						type="number"
						min="1"
						:label="t('forms', 'Maximum characters')"
						:modelValue="String(extraSettings.lengthMax ?? '')"
						v-bind="fieldState('lengthMax')"
						@update:modelValue="
							(value) => saveField('lengthMax', value)
						" />
				</template>
				<template
					v-else-if="
						row.kind === 'number'
						&& (row.condition === 'between'
							|| row.condition === 'notBetween')
					">
					<NcTextField
						type="number"
						step="any"
						:label="t('forms', 'Lowest number')"
						:modelValue="String(extraSettings.numberMin ?? '')"
						v-bind="fieldState('numberMin')"
						@update:modelValue="
							(value) => saveField('numberMin', value)
						" />
					<NcTextField
						type="number"
						step="any"
						:label="t('forms', 'Highest number')"
						:modelValue="String(extraSettings.numberMax ?? '')"
						v-bind="fieldState('numberMax')"
						@update:modelValue="
							(value) => saveField('numberMax', value)
						" />
				</template>
				<NcTextField
					v-else-if="row.kind === 'number'"
					type="number"
					step="any"
					:label="t('forms', 'Number')"
					:modelValue="
						String(
							extraSettings.numberMin ?? extraSettings.numberMax ?? '',
						)
					"
					v-bind="fieldState('number')"
					@update:modelValue="(value) => saveField('number', value)" />
				<NcTextField
					v-else-if="row.kind === 'contains' || row.kind === 'notContains'"
					:label="
						row.kind === 'contains'
							? t('forms', 'Required text')
							: t('forms', 'Excluded text')
					"
					:maxlength="answerLengthMax"
					:modelValue="extraSettings[textField(row.kind)] ?? ''"
					v-bind="fieldState(textField(row.kind))"
					@update:modelValue="
						(value) => saveField(textField(row.kind), value)
					" />
				<NcButton
					variant="tertiary"
					:aria-label="
						t('forms', 'Remove rule {rule}', {
							rule: kindLabels[row.kind],
						})
					"
					@click="removeRow(row)">
					<template #icon>
						<NcIconSvgWrapper :svg="IconClose" />
					</template>
				</NcButton>
			</div>
			<p v-if="rowErrors[row.kind]" class="answer-rules__error" role="alert">
				{{ rowErrors[row.kind] }}
			</p>
		</div>
		<NcButton
			v-if="unusedKinds.length"
			ref="addButton"
			variant="tertiary"
			@click="addRow">
			<template #icon>
				<NcIconSvgWrapper :svg="IconAdd" />
			</template>
			{{ t('forms', 'Add rule') }}
		</NcButton>
	</fieldset>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type {
	AnswerLimitSettings,
	AnswerRuleKind,
	NumberRule,
} from '../../composables/useAnswerLimits.ts'

import IconAdd from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconClose from '@material-symbols/svg-400/outlined/close.svg?raw'
import { n, t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	numberRule,
	numberRuleSettings,
	rulesConflict,
} from '../../composables/useAnswerLimits.ts'

type LengthCondition = 'atLeast' | 'atMost' | 'between'

type RuleRow = {
	id: number
	kind: AnswerRuleKind
	condition: LengthCondition | NumberRule | null
}

type RuleField =
	| 'lengthMin'
	| 'lengthMax'
	| 'numberMin'
	| 'numberMax'
	| 'number'
	| 'textContains'
	| 'textNotContains'

const RULE_KEYS: Record<AnswerRuleKind, (keyof AnswerLimitSettings)[]> = {
	length: ['lengthMin', 'lengthMax'],
	contains: ['textContains'],
	notContains: ['textNotContains'],
	number: ['numberMin', 'numberMax', 'numberExclude'],
	whole: ['numberInteger'],
}

/**
 * The extra settings of one kind of rule, each removed
 *
 * @param kind the kind of rule
 */
function cleared(kind: AnswerRuleKind): AnswerLimitSettings {
	return Object.fromEntries(RULE_KEYS[kind].map((key) => [key, undefined]))
}

/**
 * Whether a kind of rule is set
 *
 * @param kind the kind of rule
 * @param settings the extra settings of the question
 */
function isSet(kind: AnswerRuleKind, settings: AnswerLimitSettings): boolean {
	return RULE_KEYS[kind].some((key) => settings[key] !== undefined)
}

/**
 * The condition the settings of a kind of rule describe
 *
 * @param kind the kind of rule
 * @param settings the extra settings of the question
 */
function conditionOf(
	kind: AnswerRuleKind,
	settings: AnswerLimitSettings,
): RuleRow['condition'] {
	if (kind === 'length') {
		if (settings.lengthMin && settings.lengthMax) {
			return 'between'
		}
		return settings.lengthMin ? 'atLeast' : 'atMost'
	}
	if (kind === 'number') {
		const rule = numberRule(settings)
		return rule === 'any' ? 'between' : rule
	}
	return null
}

export default defineComponent({
	name: 'AnswerRules',

	components: {
		NcButton,
		NcIconSvgWrapper,
		NcSelect,
		NcTextField,
	},

	props: {
		/**
		 * The kinds of rules the question offers, in display order
		 */
		kinds: {
			type: Array as PropType<AnswerRuleKind[]>,
			required: true,
		},

		/**
		 * The extra settings of the question
		 */
		extraSettings: {
			type: Object as PropType<AnswerLimitSettings>,
			required: true,
		},

		/**
		 * The longest answer that can be stored
		 */
		answerLengthMax: {
			type: Number,
			required: true,
		},
	},

	emits: ['change'],

	setup(props, { emit }) {
		const rootElement = ref<HTMLElement | null>(null)
		const addButton = ref<{ $el: HTMLElement } | null>(null)

		const kindLabels: Record<AnswerRuleKind, string> = {
			length: t('forms', 'Length'),
			contains: t('forms', 'Must contain'),
			notContains: t('forms', 'Must not contain'),
			number: t('forms', 'Number'),
			whole: t('forms', 'Whole numbers only'),
		}

		const lengthConditions: Record<LengthCondition, string> = {
			atLeast: t('forms', 'At least'),
			atMost: t('forms', 'At most'),
			between: t('forms', 'Between'),
		}

		const numberConditions: Record<Exclude<NumberRule, 'any'>, string> = {
			between: t('forms', 'Between'),
			notBetween: t('forms', 'Not between'),
			greaterThan: t('forms', 'Greater than'),
			atLeast: t('forms', 'At least'),
			lessThan: t('forms', 'Less than'),
			atMost: t('forms', 'At most'),
			equal: t('forms', 'Equal to'),
			notEqual: t('forms', 'Not equal to'),
		}

		const conditionLabels: Record<string, string> = {
			...numberConditions,
			...lengthConditions,
		}

		let nextRowId = 0

		const rows = ref<RuleRow[]>(
			props.kinds
				.filter((kind) => isSet(kind, props.extraSettings))
				.map((kind) => ({
					id: nextRowId++,
					kind,
					condition: conditionOf(kind, props.extraSettings),
				})),
		)

		watch(
			() => props.kinds,
			(kinds) => {
				rows.value = rows.value.filter((row) => kinds.includes(row.kind))
			},
		)

		const unusedKinds = computed(() =>
			props.kinds.filter(
				(kind) => !rows.value.some((row) => row.kind === kind),
			),
		)

		/**
		 * Why the last change of a rule was not saved, by kind of rule
		 */
		const rowErrors = ref<Partial<Record<AnswerRuleKind, string>>>({})

		/**
		 * Why the value last entered in a field was not saved, by field
		 */
		const fieldErrors = ref<Partial<Record<RuleField, string>>>({})

		/**
		 * The kinds a row can switch to: its own and those without a row
		 *
		 * @param kind the kind of the row
		 */
		const kindOptions = (kind: AnswerRuleKind) =>
			[kind, ...unusedKinds.value]
				.sort((a, b) => props.kinds.indexOf(a) - props.kinds.indexOf(b))
				.map((id) => ({ id, label: kindLabels[id] }))

		/**
		 * The conditions of a kind of rule
		 *
		 * @param kind the kind of rule
		 */
		const conditionOptions = (kind: AnswerRuleKind) =>
			Object.entries(
				kind === 'length' ? lengthConditions : numberConditions,
			).map(([id, label]) => ({ id, label }))

		/**
		 * The settings of a new rule of a kind
		 *
		 * @param kind the kind of rule
		 * @param settings the extra settings of the question without the rule
		 */
		const defaults = (
			kind: AnswerRuleKind,
			settings: AnswerLimitSettings,
		): AnswerLimitSettings => {
			if (kind === 'number') {
				return numberRuleSettings('between', settings)
			}
			return kind === 'whole' ? { numberInteger: true } : {}
		}

		/**
		 * Save changed settings of a rule unless they contradict another rule,
		 * which is then reported below the rule
		 *
		 * @param kind the kind of rule
		 * @param changes the changed settings
		 * @param fallback the settings saved instead when the changes contradict another rule
		 * @return true if the changes were saved
		 */
		const apply = (
			kind: AnswerRuleKind,
			changes: AnswerLimitSettings,
			fallback?: AnswerLimitSettings,
		): boolean => {
			const problem = rulesConflict({ ...props.extraSettings, ...changes })
			rowErrors.value = { ...rowErrors.value, [kind]: problem ?? undefined }
			if (problem) {
				if (fallback) {
					emit('change', fallback)
				}
				return false
			}
			if (Object.keys(changes).length) {
				emit('change', changes)
			}
			return true
		}

		/**
		 * Add a row of the first kind without one and focus it
		 */
		const addRow = async (): Promise<void> => {
			const kind = unusedKinds.value[0]
			rows.value = [
				...rows.value,
				{ id: nextRowId++, kind, condition: conditionOf(kind, {}) },
			]
			apply(kind, defaults(kind, props.extraSettings))
			await nextTick()
			rootElement.value
				?.querySelector<HTMLElement>(
					'.answer-rules__rule:last-of-type input',
				)
				?.focus()
		}

		/**
		 * Remove a rule and focus the button to add one
		 *
		 * @param row the row of the rule
		 */
		const removeRow = async (row: RuleRow): Promise<void> => {
			rows.value = rows.value.filter((other) => other !== row)
			rowErrors.value = { ...rowErrors.value, [row.kind]: undefined }
			emit('change', cleared(row.kind))
			await nextTick()
			addButton.value?.$el.focus()
		}

		/**
		 * Turn a row into a rule of another kind, removing its previous rule
		 *
		 * @param row the row
		 * @param kind the new kind of rule
		 */
		const changeKind = (row: RuleRow, kind: AnswerRuleKind): void => {
			const removed = cleared(row.kind)
			rowErrors.value = { ...rowErrors.value, [row.kind]: undefined }
			rows.value = rows.value.map((other) =>
				other === row
					? { ...row, kind, condition: conditionOf(kind, {}) }
					: other,
			)
			apply(
				kind,
				{
					...removed,
					...defaults(kind, { ...props.extraSettings, ...removed }),
				},
				removed,
			)
		}

		/**
		 * Change the condition of a rule, keeping its numbers where possible
		 *
		 * @param row the row of the rule
		 * @param condition the new condition
		 */
		const changeCondition = (
			row: RuleRow,
			condition: LengthCondition | NumberRule,
		): void => {
			const settings = props.extraSettings
			let changes: AnswerLimitSettings = {}
			if (row.kind === 'number') {
				changes = numberRuleSettings(condition as NumberRule, settings)
			} else if (condition === 'atLeast') {
				changes = {
					lengthMin: settings.lengthMin ?? settings.lengthMax,
					lengthMax: undefined,
				}
			} else if (condition === 'atMost') {
				changes = {
					lengthMin: undefined,
					lengthMax: settings.lengthMax ?? settings.lengthMin,
				}
			}
			if (apply(row.kind, changes)) {
				rows.value = rows.value.map((other) =>
					other === row ? { ...row, condition } : other,
				)
			}
		}

		/**
		 * The settings field of a rule about a text
		 *
		 * @param kind the kind of rule
		 */
		const textField = (
			kind: AnswerRuleKind,
		): 'textContains' | 'textNotContains' =>
			kind === 'contains' ? 'textContains' : 'textNotContains'

		/**
		 * The settings a value entered in a field gives, or null if the value is
		 * incomplete
		 *
		 * @param field the field
		 * @param input the entered value
		 */
		const fieldSettings = (
			field: RuleField,
			input: string,
		): AnswerLimitSettings | null => {
			if (field === 'textContains' || field === 'textNotContains') {
				return { [field]: input || undefined }
			}
			const value = field.startsWith('length')
				? Number.parseInt(input, 10)
				: Number.parseFloat(input)
			if (isNaN(value)) {
				return null
			}
			if (field === 'number') {
				const { numberMin, numberMax } = props.extraSettings
				return {
					numberMin: numberMin === undefined ? undefined : value,
					numberMax: numberMax === undefined ? undefined : value,
				}
			}
			return { [field]: value }
		}

		/**
		 * Why the settings of a field cannot be saved: a length below 1 or above
		 * the stored answer length, a minimum above its maximum, or a contradiction
		 * with another rule; null if they can
		 *
		 * @param field the field
		 * @param changes the settings the field gives
		 */
		const fieldProblem = (
			field: RuleField,
			changes: AnswerLimitSettings,
		): string | null => {
			const settings = { ...props.extraSettings, ...changes }
			if (field === 'lengthMin' || field === 'lengthMax') {
				const value = changes[field] as number
				if (value < 1) {
					return t('forms', 'Enter a number of at least {min}', { min: 1 })
				}
				if (value > props.answerLengthMax) {
					return n(
						'forms',
						'An answer can have at most %n character',
						'An answer can have at most %n characters',
						props.answerLengthMax,
					)
				}
			}
			const [min, max] = field.startsWith('length')
				? [settings.lengthMin, settings.lengthMax]
				: [settings.numberMin, settings.numberMax]
			if (min !== undefined && max !== undefined && min > max) {
				return t('forms', 'The minimum must not be greater than the maximum')
			}
			return rulesConflict(settings)
		}

		/**
		 * Save the value of a field while it is typed, if it fits; a value that
		 * does not fit is left unsaved and reported when the field loses focus
		 *
		 * @param field the field
		 * @param value the entered value
		 */
		const saveField = (field: RuleField, value: string | number): void => {
			const changes = fieldSettings(field, String(value))
			if (!changes || fieldProblem(field, changes)) {
				return
			}
			fieldErrors.value = { ...fieldErrors.value, [field]: undefined }
			emit('change', changes)
		}

		/**
		 * The saved value of a field
		 *
		 * @param field the field
		 */
		const savedValue = (field: RuleField): string => {
			const { extraSettings } = props
			const value =
				field === 'number'
					? (extraSettings.numberMin ?? extraSettings.numberMax)
					: extraSettings[field]
			return String(value ?? '')
		}

		/**
		 * The error state of a field, and the check when it loses focus: a value
		 * that was not saved is reported and replaced by the saved one
		 *
		 * @param field the field
		 */
		const fieldState = (field: RuleField) => {
			const error = fieldErrors.value[field]
			return {
				error: !!error,
				helperText: error,
				'aria-invalid': error ? ('true' as const) : undefined,
				onBlur: (event: FocusEvent): void => {
					const input = event.target as HTMLInputElement
					const changes = fieldSettings(field, input.value)
					const saved =
						changes
						&& Object.entries(changes).every(
							([key, value]) =>
								props.extraSettings[key as keyof AnswerLimitSettings]
								=== value,
						)
					if (saved) {
						return
					}
					const problem = changes && fieldProblem(field, changes)
					if (problem) {
						fieldErrors.value = {
							...fieldErrors.value,
							[field]: problem,
						}
					}
					input.value = savedValue(field)
				},
			}
		}

		return {
			IconAdd,
			IconClose,
			addButton,
			addRow,
			changeCondition,
			changeKind,
			conditionLabels,
			conditionOptions,
			fieldState,
			kindLabels,
			kindOptions,
			removeRow,
			rootElement,
			rowErrors,
			rows,
			saveField,
			t,
			textField,
			unusedKinds,
		}
	},
})
</script>

<style lang="scss" scoped>
.answer-rules {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block-start: calc(var(--default-grid-baseline) * 3);
	padding: 0;
	border: none;

	&__legend {
		margin-block-end: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		font-weight: bold;
	}

	&__row {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-start;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__select {
		width: 200px;
		min-width: 0;
		margin: 0;
	}

	&__row > :deep(.input-field) {
		width: 160px;
		flex: 0 0 auto;
	}

	&__row--text > :deep(.input-field) {
		width: 240px;
	}

	&__error {
		color: var(--color-text-error, var(--color-error));
	}
}
</style>
