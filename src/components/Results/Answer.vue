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
		<template v-if="answers.length">
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
		<p v-else class="answer__text" dir="auto">
			<NcHighlight :text="answerText" :search="highlight" />
		</p>
	</div>
</template>

<script>
import IconFile from 'vue-material-design-icons/File.vue'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'

export default {
	name: 'Answer',
	components: {
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
}
</style>
