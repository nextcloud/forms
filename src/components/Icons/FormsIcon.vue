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
				d="M 2 8.5 c -0.83 0 -1.5 0.67 -1.5 1.5 s 0.67 1.5 1.5 1.5 s 1.5 -0.67 1.5 -1.5 s -0.67 -1.5 -1.5 -1.5 z m 0 -6 c -0.83 0 -1.5 0.67 -1.5 1.5 S 1.17 5.5 2 5.5 S 3.5 4.83 3.5 4 S 2.83 2.5 2 2.5 z m 0 12 c -0.83 0 -1.5 0.68 -1.5 1.5 s 0.68 1.5 1.5 1.5 s 1.5 -0.68 1.5 -1.5 s -0.67 -1.5 -1.5 -1.5 z M 5 17 h 14 v -2 H 5 v 2 z m 0 -6 h 14 v -2 H 5 v 2 z m 0 -8 v 2 h 14 V 3 H 5 z" />
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
}
</script>
