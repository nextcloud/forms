<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		v-if="readOnly"
		ref="stickySentinel"
		class="question-section__sentinel"
		aria-hidden="true" />
	<Question
		v-bind="{ ...questionProps, ...$attrs }"
		ref="questionElement"
		:class="{ 'question--section-stuck': isStuck }"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners"
		@click="onSectionClick">
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import { defineComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

export default defineComponent({
	name: 'QuestionSection',

	components: {
		Question,
	},

	// The sentinel <li> must stay a sibling of the section, so attributes are
	// forwarded to the inner Question element explicitly.
	inheritAttrs: false,

	props: QUESTION_PROPS,
	emits: QUESTION_EMITS,

	setup(props, { emit }) {
		const stickySentinel = ref<HTMLElement | null>(null)
		const questionElement = ref<{ $el: HTMLElement } | null>(null)
		const question = useQuestion(props, {
			emit,
			rootElement: questionElement,
		})
		const isStuck = ref(false)
		let observer: IntersectionObserver | null = null

		/**
		 * Watch the sentinel right before the sticky section to detect when it
		 * is stuck to the top, so the description can be limited while scrolling
		 */
		onMounted(() => {
			if (!props.readOnly || !stickySentinel.value) {
				return
			}
			const top =
				parseFloat(
					getComputedStyle(
						questionElement.value?.$el ?? stickySentinel.value,
					).top,
				) || 0
			observer = new IntersectionObserver(
				([entry]) => {
					// Once the sentinel scrolled past the sticky offset the
					// section is stuck to the top
					isStuck.value = entry.boundingClientRect.top < top
				},
				{ rootMargin: `-${top + 1}px 0px 0px 0px` },
			)
			observer.observe(stickySentinel.value)
		})

		onBeforeUnmount(() => {
			observer?.disconnect()
		})

		/**
		 * Sections cannot be answered, they are always valid
		 */
		const validate = async (): Promise<boolean> => true

		/**
		 * Scrolling the sentinel into view un-sticks the section so the full
		 * description becomes visible again
		 */
		const onSectionClick = (): void => {
			if (isStuck.value) {
				stickySentinel.value?.scrollIntoView({ behavior: 'smooth' })
			}
		}

		return {
			...question,
			isStuck,
			stickySentinel,
			questionElement,
			validate,
			onSectionClick,
		}
	},
})
</script>

<style lang="scss" scoped>
.question-section__sentinel {
	height: 0;
	margin: 0;
	padding: 0;
	list-style: none;
	// Land below the sticky top offset when scrolling up to the section
	scroll-margin-block-start: calc(
		var(--default-clickable-area) + 2 * var(--app-navigation-padding, 0px) +
			var(--default-grid-baseline, 4px)
	);
}
</style>
