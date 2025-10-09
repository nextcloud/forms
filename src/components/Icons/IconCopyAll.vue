<!--
  - SPDX-FileCopyrightText: 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span
		:aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon copy-all-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			class="material-design-icon__svg"
			:height="size"
			:width="size"
			viewBox="0 0 16 16">
			<path
				d="M 13.5 0 h -8 C 4.67 0 4 0.67 4 1.5 v 10 C 4 12.33 4.67 13 5.5 13 h 8 c 0.83 0 1.5 -0.67 1.5 -1.5 v -10 C 15 0.67 14.33 0 13.5 0 z M 13.5 11.5 h -8 v -10 h 8 V 11.5 z M 1 10 v -1.5 h 1.5 V 10 H 1 z M 1 13 v -1.5 h 1.5 V 13 H 1 z M 7 14.5 h 1.5 V 16 H 7 V 14.5 z M 1 5.5 h 1.5 V 7 H 1 V 5.5 z M 5.5 16 H 4 v -1.5 h 1.5 V 16 z M 2.5 16 C 1.67 16 1 15.33 1 14.5 h 1.5 V 16 z M 2.5 4 H 1 c 0 -0.83 0.67 -1.5 1.5 -1.5 V 4 z M 11.49 14.5 c 0 0.83 -0.67 1.5 -1.5 1.5 h 0 v -1.5 L 11.49 14.5 L 11.49 14.5 z" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'IconCopyAll',
	props: {
		title: {
			type: String,
			default: '',
		},

		fillColor: {
			type: String,
			default: 'currentColor',
		},

		size: {
			type: Number,
			default: 20,
		},
	},

	emits: ['click'],
}
</script>
