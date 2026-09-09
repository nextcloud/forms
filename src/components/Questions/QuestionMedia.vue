<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		v-on="commonListeners">
		<div class="question-media">
			<img
				v-if="isImage && url"
				:src="url"
				:alt="alt"
				class="question-media__image"
				referrerpolicy="no-referrer"
				loading="lazy" />

			<a
				v-else-if="!isImage && url"
				:href="url"
				class="question-media__link"
				target="_blank"
				rel="noopener noreferrer external">
				{{ alt || url }}
			</a>

			<p v-else class="question-media__empty">
				{{
					isImage
						? t('forms', 'No image address set yet.')
						: t('forms', 'No video address set yet.')
				}}
			</p>

			<template v-if="!readOnly">
				<NcTextField
					:label="t('forms', 'Address')"
					placeholder="https://"
					:modelValue="url"
					@update:modelValue="onUrlChange" />
				<NcTextField
					:label="
						isImage
							? t('forms', 'Description for screen readers')
							: t('forms', 'Link text')
					"
					:modelValue="alt"
					@update:modelValue="onAltChange" />
				<NcNoteCard v-if="isExternal" type="warning">
					{{
						t(
							'forms',
							'This address is on another site. Loading it tells that site the IP address of everyone who opens the form.',
						)
					}}
				</NcNoteCard>
			</template>
		</div>
	</Question>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, defineComponent } from 'vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

export default defineComponent({
	name: 'QuestionMedia',

	components: {
		NcNoteCard,
		NcTextField,
		Question,
	},

	props: QUESTION_PROPS,
	emits: QUESTION_EMITS,

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })

		const extraSettings = computed(
			() => (props.extraSettings as Record<string, unknown> | undefined) ?? {},
		)

		const isImage = computed(
			() =>
				(props.answerType as { mediaKind?: string })?.mediaKind !== 'video',
		)

		const url = computed<string>(() => (extraSettings.value.url as string) || '')
		const alt = computed<string>(() => (extraSettings.value.alt as string) || '')

		/**
		 * Whether the address points somewhere other than this instance, which is worth
		 * warning about because loading it discloses the respondent to that host.
		 */
		const isExternal = computed<boolean>(() => {
			if (!url.value) {
				return false
			}
			try {
				return (
					new URL(url.value, window.location.origin).origin
					!== window.location.origin
				)
			} catch {
				// An address that cannot be parsed is not yet worth warning about.
				return false
			}
		})

		/**
		 * @param value the new address
		 */
		function onUrlChange(value: string): void {
			question.onExtraSettingsChange({ url: value || null })
		}

		/**
		 * @param value the new description or link text
		 */
		function onAltChange(value: string): void {
			question.onExtraSettingsChange({ alt: value || null })
		}

		return {
			...question,
			alt,
			isExternal,
			isImage,
			onAltChange,
			onUrlChange,
			t,
			url,
		}
	},
})
</script>

<style lang="scss" scoped>
.question-media {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&__image {
		border-radius: var(--border-radius);
		max-height: 400px;
		max-width: 100%;
		object-fit: contain;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
	}
}
</style>
