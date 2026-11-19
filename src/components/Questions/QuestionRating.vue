<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		ref="rootElement"
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionInput
				:modelValue="optionsHighest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Highest rating')"
				labelOutside
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				required
				@update:modelValue="onOptionsHighestChange">
				<template #icon>
					<NcIconSvgWrapper :svg="currentIconChoice.outline" />
				</template>
			</NcActionInput>
			<NcActionRadio
				v-for="icon in iconChoices"
				:key="icon.id"
				:modelValue="ratingIcon"
				:name="`ratingIcon_${id}`"
				:value="icon.id"
				@change="onRatingIconChange(icon.id)">
				{{ icon.label }}
			</NcActionRadio>
		</template>

		<fieldset
			class="rating"
			:style="`--rating-color: ${currentIconChoice.color}`"
			:aria-describedby="description ? descriptionId : undefined"
			:disabled="!readOnly">
			<legend class="hidden-visually">
				{{ text || t('forms', 'Rating') }}
			</legend>
			<label
				v-for="value in optionsHighest"
				:key="value"
				class="rating__icon"
				:class="{ 'rating__icon--on': value <= currentValue }">
				<!-- TRANSLATORS Accessible name of one option of a rating, e.g. "3 of 5" -->
				<input
					class="rating__input"
					type="radio"
					:name="`rating_${id}`"
					:aria-label="
						t('forms', '{value} of {max}', {
							value,
							max: optionsHighest,
						})
					"
					:aria-errormessage="hasError ? errorId : undefined"
					:aria-invalid="hasError ? 'true' : undefined"
					:value="value"
					:checked="value === currentValue"
					:required="isRequired"
					@change="onPick(value)"
					@invalid.prevent="validate"
					@keydown.enter.exact.prevent="onKeydownEnter" />
				<NcIconSvgWrapper
					:svg="
						value <= currentValue
							? currentIconChoice.filled
							: currentIconChoice.outline
					" />
			</label>
		</fieldset>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import IconHeartFilled from '@material-symbols/svg-400/outlined/favorite-fill.svg?raw'
import IconHeart from '@material-symbols/svg-400/outlined/favorite.svg?raw'
import IconStarFilled from '@material-symbols/svg-400/outlined/star-fill.svg?raw'
import IconStar from '@material-symbols/svg-400/outlined/star.svg?raw'
import IconThumbFilled from '@material-symbols/svg-400/outlined/thumb_up-fill.svg?raw'
import IconThumb from '@material-symbols/svg-400/outlined/thumb_up.svg?raw'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, ref } from 'vue'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

const DEFAULT_OPTIONS_HIGHEST = 5

interface IconChoice {
	id: 'star' | 'heart' | 'thumb'
	label: string
	outline: string
	filled: string
	color: string
}

const ICON_CHOICES: IconChoice[] = [
	{
		id: 'star',
		label: t('forms', 'Stars'),
		outline: IconStar,
		filled: IconStarFilled,
		color: 'var(--color-element-warning)',
	},
	{
		id: 'heart',
		label: t('forms', 'Hearts'),
		outline: IconHeart,
		filled: IconHeartFilled,
		color: 'var(--color-element-error)',
	},
	{
		id: 'thumb',
		label: t('forms', 'Thumbs up'),
		outline: IconThumb,
		filled: IconThumbFilled,
		color: 'var(--color-element-info)',
	},
]

const VALID_ICONS = ICON_CHOICES.map((choice) => choice.id)
const DEFAULT_ICON = ICON_CHOICES[0].id

export default defineComponent({
	name: 'QuestionRating',

	components: {
		NcActionInput,
		NcActionRadio,
		NcIconSvgWrapper,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const rootElement = ref<{ $el?: HTMLElement } | null>(null)
		const question = useQuestion(props, { emit, rootElement })

		const extraSettings = computed(
			() => (props.extraSettings as Record<string, unknown> | undefined) ?? {},
		)

		const optionsHighest = computed<number>(() => {
			const configured = extraSettings.value.optionsHighest
			return typeof configured === 'number'
				&& configured >= 2
				&& configured <= 10
				? configured
				: DEFAULT_OPTIONS_HIGHEST
		})

		const ratingIcon = computed<string>(() => {
			const icon = extraSettings.value.ratingIcon
			return typeof icon === 'string' && VALID_ICONS.includes(icon as never)
				? icon
				: DEFAULT_ICON
		})

		const currentIconChoice = computed(() =>
			ICON_CHOICES.find((choice) => choice.id === ratingIcon.value)!,
		)

		const currentValue = computed<number>(
			() => parseInt((props.values as string[])?.[0]) || 0,
		)

		/**
		 * @param value the chosen count, or 0 to clear the answer
		 */
		function onPick(value: number): void {
			emit('update:values', value ? [String(value)] : [])
			question.errorMessage.value = null
		}

		/**
		 * @param value how many icons to offer
		 */
		function onOptionsHighestChange(value: number): void {
			question.onExtraSettingsChange({
				optionsHighest: value === DEFAULT_OPTIONS_HIGHEST ? null : value,
			})
		}

		/**
		 * @param icon the chosen icon set
		 */
		function onRatingIconChange(icon: string): void {
			question.onExtraSettingsChange({
				ratingIcon: icon === DEFAULT_ICON ? null : icon,
			})
		}

		/**
		 * A rating cannot be partly filled in, so the only failure is a required
		 * question left unanswered.
		 */
		async function validate(): Promise<boolean> {
			if (props.isRequired && !currentValue.value) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}
			question.errorMessage.value = null
			return true
		}

		return {
			...question,
			currentIconChoice,
			currentValue,
			iconChoices: ICON_CHOICES,
			optionsHighest,
			onOptionsHighestChange,
			onPick,
			onRatingIconChange,
			ratingIcon,
			rootElement,
			t,
			validate,
		}
	},
})
</script>

<style lang="scss" scoped>
.rating {
	align-items: center;
	border: none;
	display: flex;
	// Ten icons plus a Clear button do not fit one line on a narrow screen.
	flex-wrap: wrap;
	gap: 2px;
	margin: 0;
	padding: 0;

	&__icon {
		align-items: center;
		border-radius: var(--border-radius);
		color: var(--color-text-maxcontrast);
		cursor: pointer;
		display: inline-flex;
		justify-content: center;
		// A comfortable pointer target; the icon itself stays small.
		min-height: var(--default-clickable-area);
		min-width: var(--default-clickable-area);
		position: relative;
		transition:
			color 0.1s ease-in-out,
			transform 0.1s ease-in-out;

		&:hover {
			transform: scale(1.1);
		}

		&:focus-within {
			outline: 2px solid var(--color-primary-element);
			outline-offset: -2px;
		}

		&--on {
			color: var(--rating-color);
		}

		@media (prefers-reduced-motion: reduce) {
			transition: none;

			&:hover {
				transform: none;
			}
		}
	}

	&__input {
		height: 1px;
		inset-block-start: 0;
		inset-inline-start: 0;
		opacity: 0 !important;
		pointer-events: none;
		position: absolute;
		width: 1px;
	}

	&:disabled &__icon {
		cursor: default;
	}
}
</style>
