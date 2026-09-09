<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionInput
				:modelValue="maxRating"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Number of icons')"
				labelOutside
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				required
				@update:modelValue="onMaxRatingChange">
				<template #icon>
					<NcIconSvgWrapper :svg="outlineIcon" />
				</template>
			</NcActionInput>
			<NcActionRadio
				v-for="icon in iconChoices"
				:key="icon.id"
				:modelValue="ratingIcon"
				:name="`ratingIcon_${id}`"
				:value="icon.id"
				@update:modelValue="onRatingIconChange(icon.id)">
				{{ icon.label }}
			</NcActionRadio>
		</template>

		<fieldset class="rating" :disabled="!readOnly">
			<legend class="hidden-visually">
				{{ text || t('forms', 'Rating') }}
			</legend>
			<label
				v-for="value in maxRating"
				:key="value"
				class="rating__icon"
				:class="{ 'rating__icon--on': value <= currentValue }">
				<input
					class="hidden-visually"
					type="radio"
					:name="`rating_${id}`"
					:aria-label="
						n('forms', '%n of {max}', '%n of {max}', value, {
							max: maxRating,
						})
					"
					:value="value"
					:checked="value === currentValue"
					:required="isRequired && !currentValue"
					@change="onPick(value)" />
				<NcIconSvgWrapper
					:svg="value <= currentValue ? filledIcon : outlineIcon" />
			</label>
			<NcButton
				v-if="readOnly && currentValue"
				variant="tertiary"
				@click="onPick(0)">
				{{ t('forms', 'Clear') }}
			</NcButton>
		</fieldset>
	</Question>
</template>

<script lang="ts">
import IconHeartFilled from '@material-symbols/svg-400/outlined/favorite-fill.svg?raw'
import IconHeart from '@material-symbols/svg-400/outlined/favorite.svg?raw'
import IconStarFilled from '@material-symbols/svg-400/outlined/star-fill.svg?raw'
import IconStar from '@material-symbols/svg-400/outlined/star.svg?raw'
import IconThumbFilled from '@material-symbols/svg-400/outlined/thumb_up-fill.svg?raw'
import IconThumb from '@material-symbols/svg-400/outlined/thumb_up.svg?raw'
import { n, t } from '@nextcloud/l10n'
import { computed, defineComponent } from 'vue'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

/** Matches the default assumed server-side when maxRating is unset. */
const DEFAULT_MAX_RATING = 5

export default defineComponent({
	name: 'QuestionRating',

	components: {
		NcActionInput,
		NcActionRadio,
		NcButton,
		NcIconSvgWrapper,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })

		const extraSettings = computed(
			() => (props.extraSettings as Record<string, unknown> | undefined) ?? {},
		)

		const maxRating = computed<number>(() => {
			const configured = extraSettings.value.maxRating
			return typeof configured === 'number'
				&& configured >= 2
				&& configured <= 10
				? configured
				: DEFAULT_MAX_RATING
		})

		const ratingIcon = computed<string>(() => {
			const icon = extraSettings.value.ratingIcon
			return typeof icon === 'string'
				&& ['star', 'heart', 'thumb'].includes(icon)
				? icon
				: 'star'
		})

		const iconChoices = computed(() => [
			{ id: 'star', label: t('forms', 'Stars') },
			{ id: 'heart', label: t('forms', 'Hearts') },
			{ id: 'thumb', label: t('forms', 'Thumbs up') },
		])

		const outlineIcon = computed(
			() =>
				({ star: IconStar, heart: IconHeart, thumb: IconThumb })[
					ratingIcon.value
				],
		)

		const filledIcon = computed(
			() =>
				({
					star: IconStarFilled,
					heart: IconHeartFilled,
					thumb: IconThumbFilled,
				})[ratingIcon.value],
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
		function onMaxRatingChange(value: number): void {
			question.onExtraSettingsChange({
				maxRating: value === DEFAULT_MAX_RATING ? null : value,
			})
		}

		/**
		 * @param icon the chosen icon set
		 */
		function onRatingIconChange(icon: string): void {
			question.onExtraSettingsChange({
				ratingIcon: icon === 'star' ? null : icon,
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
			currentValue,
			filledIcon,
			iconChoices,
			maxRating,
			n,
			onMaxRatingChange,
			onPick,
			onRatingIconChange,
			outlineIcon,
			ratingIcon,
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
		transition:
			color 0.1s ease-in-out,
			transform 0.1s ease-in-out;

		&--on {
			color: var(--color-favorite, var(--color-warning));
		}

		&:hover {
			transform: scale(1.1);
		}

		&:focus-within {
			outline: 2px solid var(--color-primary-element);
			outline-offset: -2px;
		}

		@media (prefers-reduced-motion: reduce) {
			transition: none;

			&:hover {
				transform: none;
			}
		}
	}

	&:disabled &__icon {
		cursor: default;
	}
}
</style>
