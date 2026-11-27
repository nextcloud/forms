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
		<div class="question-media" :class="{ 'question-media--edit': !readOnly }">
			<img
				v-if="isImage && url && !isExternal"
				:src="url"
				:alt="alt"
				class="question-media__image"
				referrerpolicy="no-referrer"
				loading="lazy" />

			<template v-else-if="!isImage && url">
				<div v-if="player" class="question-media__player">
					<iframe
						v-if="playerLoaded"
						:src="player.src"
						:title="alt || player.provider"
						allow="autoplay; fullscreen; picture-in-picture"
						referrerpolicy="strict-origin"
						sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" />
					<div v-else class="question-media__consent">
						<p>
							{{
								t(
									'forms',
									'This video is hosted by {provider}. Playing it here lets {provider} see your IP address and may set cookies.',
									{ provider: player.provider },
								)
							}}
						</p>
						<NcButton variant="primary" @click="playerLoaded = true">
							{{ t('forms', 'Play video') }}
						</NcButton>
					</div>
				</div>
				<a
					:href="url"
					class="question-media__link"
					target="_blank"
					rel="noopener noreferrer external">
					{{ alt || url }}
				</a>
			</template>

			<p v-else-if="!url" class="question-media__empty">
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
				<NcNoteCard v-if="isImage && isExternal" type="error">
					{{
						t(
							'forms',
							'This image is on another site and will not be shown. Only images stored on this Nextcloud can be shown, for example through a public share link.',
						)
					}}
				</NcNoteCard>
			</template>
		</div>
	</Question>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

/**
 * Get the privacy-friendly player of a YouTube or Vimeo video address
 *
 * @param address the address of the video
 * @return the player's provider name and source, or null for any other address
 */
function toPlayer(address: string): { provider: string; src: string } | null {
	let parsed: URL
	try {
		parsed = new URL(address)
	} catch {
		return null
	}
	const host = parsed.hostname.replace(/^(www|m)\./, '')

	if (['youtube.com', 'youtube-nocookie.com', 'youtu.be'].includes(host)) {
		const id =
			host === 'youtu.be'
				? parsed.pathname.split('/')[1]
				: (parsed.searchParams.get('v')
					?? parsed.pathname.match(
						/^\/(?:embed|shorts|live)\/([^/]+)/,
					)?.[1])
		return id && /^[\w-]{11}$/.test(id)
			? {
					provider: 'YouTube',
					src: `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`,
				}
			: null
	}

	if (['vimeo.com', 'player.vimeo.com'].includes(host)) {
		const id = parsed.pathname.match(/^\/(?:video\/)?(\d+)/)?.[1]
		return id
			? {
					provider: 'Vimeo',
					src: `https://player.vimeo.com/video/${id}?dnt=1&autoplay=1`,
				}
			: null
	}

	return null
}

export default defineComponent({
	name: 'QuestionMedia',

	components: {
		NcButton,
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
		 * Whether the address points somewhere other than this instance; such images
		 * are blocked by the content security policy.
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
				return false
			}
		})

		const player = computed(() => toPlayer(url.value))
		const playerLoaded = ref(false)
		watch(url, () => {
			playerLoaded.value = false
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
			player,
			playerLoaded,
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

	&--edit {
		width: calc(100% - var(--default-clickable-area));
		margin-inline-start: -12px;
	}

	&__image {
		align-self: flex-start;
		border-radius: var(--border-radius);
		max-height: 400px;
		max-width: 100%;
		object-fit: contain;
	}

	&__player {
		aspect-ratio: 16 / 9;
		max-width: 100%;
		border-radius: var(--border-radius-large);
		overflow: hidden;
		background-color: var(--color-background-dark);

		iframe {
			width: 100%;
			height: 100%;
			border: none;
		}
	}

	&__consent {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 12px;
		height: 100%;
		padding: 16px;
		text-align: center;
	}

	&__link {
		align-self: flex-start;
		text-decoration: underline;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
	}
}
</style>
