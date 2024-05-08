<template>
	<span>
		<slot />
		<span class="icon-overlay">
			<slot name="overlay" />
		</span>
	</span>
</template>

<script setup>
</script>

<style scoped>
.icon-overlay-wrapper {
	position: relative;
}

.icon-overlay {
	position: absolute;
	inset-inline-start: 2px;
	inset-block-start: 2px;

	transform: scale(0.7);
}
</style>
