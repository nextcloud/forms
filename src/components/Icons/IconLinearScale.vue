<!--
  - SPDX-FileCopyrightText: 2025 Christian Hartmann <chris-hartmann@gmx.de>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span
		:aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon linear-scale-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			class="material-design-icon__svg"
			:height="size"
			:width="size"
			viewBox="0 -960 960 960">
			<path
				d="M672-288q-71 0-123.38-44.36Q496.24-376.73 482.9-444H281q-11 26-35 43t-54 17q-40.32 0-68.16-27.77Q96-439.55 96-479.77 96-520 123.84-548q27.84-28 68.16-28 30 0 54 17t35.46 43H484q13.21-67.28 65.1-111.64Q601-672 672-672q79.68 0 135.84 56.23 56.16 56.22 56.16 136Q864-400 807.84-344 751.68-288 672-288Zm0-72q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Z" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'IconLinearScale',
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
}
</script>
