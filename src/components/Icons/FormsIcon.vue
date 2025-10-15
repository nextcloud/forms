<!--
  - SPDX-FileCopyrightText: 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span
		:aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon forms-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			class="material-design-icon__svg"
			:height="size"
			:width="size"
			viewBox="0 0 20 20">
			<path
				d="M9 19v-2h12v2H9Zm0-6v-2h12v2H9Zm0-6V5h12v2H9ZM5 20c-.55 0-1.021-.196-1.412-.587A1.927 1.927 0 0 1 3 18c0-.55.196-1.021.588-1.412A1.923 1.923 0 0 1 5 16c.55 0 1.021.196 1.413.588.391.391.587.862.587 1.412a1.93 1.93 0 0 1-.587 1.413A1.93 1.93 0 0 1 5 20Zm0-6c-.55 0-1.021-.196-1.412-.587A1.927 1.927 0 0 1 3 12c0-.55.196-1.021.588-1.413A1.926 1.926 0 0 1 5 10c.55 0 1.021.196 1.413.587.391.392.587.863.587 1.413a1.93 1.93 0 0 1-.587 1.413A1.93 1.93 0 0 1 5 14Zm0-6c-.55 0-1.021-.196-1.412-.588A1.923 1.923 0 0 1 3 6c0-.55.196-1.021.588-1.413A1.926 1.926 0 0 1 5 4c.55 0 1.021.196 1.413.587C6.804 4.979 7 5.45 7 6s-.196 1.021-.587 1.412A1.927 1.927 0 0 1 5 8Z" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'FormsIcon',
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
