<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
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
  -->

<template>
	<div class="controls">
		<div class="breadcrumb">
			<div class="crumb svg crumbhome">
				<router-link :to="{ name: 'list'}" class="icon-home">
					Home
				</router-link>
			</div>
			<div v-show="intitle ===''" class="crumb svg last">
				<span v-text="intitle" />
			</div>
			<div class="action">
				<slot />
			</div>
		</div>
		<slot name="after" class="after" />
	</div>
</template>

<script>
export default {

	props: {
		intitle: {
			type: String,
			default: undefined
		}
	},
	data() {
		return {
			imagePath: OC.imagePath('core', 'places/home.svg')
		}
	}
}
</script>

<style lang="scss">

.controls {
    display: flex;
	position: fixed;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-main-background);
    width: 100%;
    height: 45px;
    z-index: 1001;

	.action {
		order: 999;
	}

	.button, button {
		box-sizing: border-box;
		display: inline-block;
		display: flex;
		height: 36px;
		padding: 9px;
		align-items: center;
		justify-content: center;
		margin-left: 7px;
		&.symbol {
			width: 36px;
		}
		&.primary {
			background: var(--color-primary);
			color: var(--color-primary-text);
		}
	}

    .breadcrumb {
		flex-grow: 1;
        overflow: hidden;
        min-width: 35px;
        // div.crumb:last-child {
        //     flex-shrink: 1;
        //     overflow: hidden;
        //     > span {
        //         flex-shrink: 1;
        //         text-overflow: ellipsis;
        //     }
        // }
    }
}
</style>
