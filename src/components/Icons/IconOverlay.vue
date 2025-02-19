<!--
  - SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span>
		<slot />
		<span class="icon-overlay">
			<slot name="overlay" />
		</span>
	</span>
</template>

<script setup></script>

<style scoped>
.icon-overlay-wrapper {
	position: relative;
}

.icon-overlay {
	position: absolute;
	inset-inline-start: 0;
	inset-block-start: -2px;

	transform: scale(0.7);
}
</style>
