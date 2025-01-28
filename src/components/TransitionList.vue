<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<TransitionGroup name="forms-list-transition" tag="ul">
		<slot />
	</TransitionGroup>
</template>

<style>
.forms-list-transition-move,
.forms-list-transition-enter-active,
.forms-list-transition-leave-active {
	transition: all var(--animation-slow) ease;
}

.forms-list-transition-enter-from,
.forms-list-transition-leave-to {
	opacity: 0;
	transform: translateX(44px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.forms-list-transition-leave-active {
	position: absolute;
}
</style>
