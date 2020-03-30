<!--
  -
  -
  - @author Nick Gallo
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
	<li>
		<div>{{ question.text }}</div>
		<div>
			<input v-show="(question.type != 'text') && (question.type != 'comment')"
				v-model="newOption"
				style="height:30px;"
				:placeholder=" t('forms', 'Add Option')"
				@keyup.enter="emitNewOption(question)">
			<transitionGroup
				id="form-list"
				name="list"
				tag="ul"
				class="form-table">
				<TextFormItem
					v-for="(opt, index) in options"
					:key="opt.id"
					:option="opt"
					@remove="emitRemoveOption(question, opt, index)" />
			</transitionGroup>
		</div>
		<div>
			<a class="icon icon-delete svg delete-form" @click="$emit('deleteQuestion')" />
		</div>
	</li>
</template>

<script>
import TextFormItem from './textFormItem'
export default {
	components: {
		TextFormItem,
	},
	props: {
		question: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			nextOptionId: 1,
			newOption: '',
			type: '',
		}
	},

	computed: {
		options() {
			return this.question.options || []
		},
	},

	methods: {
		emitNewOption(question) {
			this.$emit('addOption', this, question)
		},

		emitRemoveOption(question, option, index) {
			this.$emit('deleteOption', question, option, index)
		},
	},
}

</script>
