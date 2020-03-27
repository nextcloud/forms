<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -
  - UPDATE: Adds Quiz option and takes the input:
  - is yet to store input of quizzes and cannot represtent them
  - requires quizFormItem.vue (should be added to svn)
  -->
<template>
	<div class="top-bar" role="toolbar">
		<slot />
	</div>
</template>
<script>
export default {
	name: 'TopBar',
}
</script>

<style lang="scss" scoped>
.top-bar {
	position: absolute;
	z-index: 10;
	top: 0;
	right: 0;
	display: flex;
	align-items: center;
	justify-content: flex-end;
	height: 60px;
	padding: 0 6px;

	button {
		cursor: pointer;
		&:not(.primary) {
			width: 44px;
			height: 44px;
			background-color: transparent;
			border: none;
			&:hover, a:active, a:focus {
				background-color: var(--color-background-darker);
			}
		}

		> span {
			cursor: pointer;
			opacity: 1;
			background-size: 16px;
		}
	}
}

</style>
