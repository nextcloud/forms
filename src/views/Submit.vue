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
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<Content app-name="forms">
		<AppContent>
			<!-- Forms title & description-->
			<header>
				<h2 id="form-title">
					{{ form.title }}
				</h2>
				<p v-if="!loading && !success" id="form-desc">
					{{ form.description }}
				</p>
			</header>

			<!-- Questions list -->
			<form v-if="!loading && !success" @submit.prevent="onSubmit">
				<ul>
					<Questions
						:is="answerTypes[question.type].component"
						v-for="(question, index) in validQuestions"
						ref="questions"
						:key="question.id"
						:read-only="true"
						:model="answerTypes[question.type]"
						:index="index + 1"
						:max-string-lengths="maxStringLengths"
						v-bind="question"
						:values.sync="answers[question.id]" />
				</ul>
				<input class="primary"
					type="submit"
					:value="t('forms', 'Submit')"
					:disabled="loading"
					:aria-label="t('forms', 'Submit form')">
			</form>

			<EmptyContent v-else-if="loading" icon="icon-loading">
				{{ t('forms', 'Submitting form …') }}
			</EmptyContent>

			<EmptyContent v-else-if="success" icon="icon-checkmark">
				{{ t('forms', 'Thank you for completing the form!') }}
			</EmptyContent>
		</AppContent>
	</Content>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'

import answerTypes from '../models/AnswerTypes'

import EmptyContent from '../components/EmptyContent'
import Question from '../components/Questions/Question'
import QuestionLong from '../components/Questions/QuestionLong'
import QuestionShort from '../components/Questions/QuestionShort'
import QuestionMultiple from '../components/Questions/QuestionMultiple'

export default {
	name: 'Submit',

	components: {
		AppContent,
		Content,
		EmptyContent,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
	},

	data() {
		return {
			form: loadState('forms', 'form'),
			maxStringLengths: loadState('forms', 'maxStringLengths'),
			answerTypes,
			answers: {},
			loading: false,
			success: false,
		}
	},

	computed: {
		validQuestions() {
			return this.form.questions.filter(question => {
				// All questions must have a valid title
				if (question.text && question.text.trim() === '') {
					return false
				}

				// If specific conditions provided, test against them
				if ('validate' in answerTypes[question.type]) {
					return answerTypes[question.type].validate(question)
				}
				return true
			})
		},
	},

	methods: {
		/**
		 * Submit the form after the browser validated it 🚀
		 */
		async onSubmit() {
			this.loading = true

			try {
				await axios.post(generateUrl('/apps/forms/api/v1/submissions/insert'), {
					formId: this.form.id,
					answers: this.answers,
				})
				this.success = true
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error submitting the form'))
			} finally {
				this.loading = false
			}
		},
	},

}
</script>
<style lang="scss" scoped>
#app-content-vue {
	display: flex;
	align-items: center;
	flex-direction: column;

	// Force hide navigation toggle as there is no navigation
	// stylelint-disable-next-line selector-pseudo-element-no-unknown
	::v-deep #app-navigation-toggle {
		display: none !important;
	}

	header,
	form {
		width: 100%;
		max-width: 750px;
		display: flex;
		flex-direction: column;
	}

	// Title & description header
	header {
		margin-top: 44px;

		#form-title,
		#form-desc {
			width: 100%;
			padding: 0 16px;
			border: none;
		}
		#form-title {
			font-size: 2em;
			font-weight: bold;
			margin: 32px 0;
			padding-left: 14px; // align with description (compensate font size diff)
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		#form-desc {
			min-height: 60px;
			resize: none;
		}
	}

	form {
		.question {
			// Less padding needed as submit view does not have drag handles
			padding-left: 16px;
			// stylelint-disable-next-line selector-pseudo-element-no-unknown
			::v-deep ul.question__content {
				// Left-align multiple choice and checkboxes with question text
				// Only in submit view
				// TODO: use variables
				margin-left: -14px;
			}
		}

		input[type=submit] {
			align-self: flex-end;
			margin: 5px;
			margin-bottom: 160px;
			padding: 10px 20px;
		}
	}
}
</style>
