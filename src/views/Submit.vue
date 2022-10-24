<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @license AGPL-3.0-or-later
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
	<NcAppContent v-if="isLoadingForm">
		<NcEmptyContent :title="t('forms', 'Loading {title} …', { title: form.title })">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :class="{'app-content--public': publicView}">
		<TopBar v-if="!publicView"
			:permissions="form?.permissions"
			@share-form="onShareForm" />

		<!-- Forms title & description-->
		<header>
			<h2 ref="title" class="form-title">
				{{ formTitle }}
			</h2>
			<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
			<!-- eslint-disable-next-line -->
			<p v-if="!loading && !success" class="form-desc">{{ form.description }}</p>
			<!-- Generate form information message-->
			<p class="info-message" v-text="infoMessage" />
		</header>

		<NcEmptyContent v-if="loading"
			:title="t('forms', 'Submitting form …')">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="success || !form.canSubmit"
			:title="t('forms', 'Thank you for completing the form!')">
			<template #icon>
				<IconCheck :size="64" />
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="isExpired"
			:title="t('forms', 'Form expired')"
			:description="t('forms', 'This form has expired and is no longer taking answers')">
			<template #icon>
				<IconCheck :size="64" />
			</template>
		</NcEmptyContent>

		<!-- Questions list -->
		<form v-else
			ref="form"
			@submit.prevent="onSubmit">
			<ul>
				<Questions :is="answerTypes[question.type].component"
					v-for="(question, index) in validQuestions"
					ref="questions"
					:key="question.id"
					:read-only="true"
					:answer-type="answerTypes[question.type]"
					:index="index + 1"
					:max-string-lengths="maxStringLengths"
					v-bind="question"
					:values.sync="answers[question.id]"
					@keydown.enter="onKeydownEnter"
					@keydown.ctrl.enter="onKeydownCtrlEnter" />
			</ul>
			<input ref="submitButton"
				class="primary"
				type="submit"
				:value="t('forms', 'Submit')"
				:disabled="loading"
				:aria-label="t('forms', 'Submit form')">
		</form>
	</NcAppContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import answerTypes from '../models/AnswerTypes.js'
import logger from '../utils/Logger.js'
import Question from '../components/Questions/Question.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import TopBar from '../components/TopBar.vue'
import SetWindowTitle from '../utils/SetWindowTitle.js'
import ViewsMixin from '../mixins/ViewsMixin.js'

export default {
	name: 'Submit',

	components: {
		IconCheck,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
		TopBar,
	},

	mixins: [ViewsMixin],

	props: {
		isLoggedIn: {
			type: Boolean,
			required: false,
			default: true,
		},
		shareHash: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			maxStringLengths: loadState('forms', 'maxStringLengths'),
			answerTypes,
			answers: {},
			loading: false,
			success: false,
		}
	},

	computed: {
		/**
		 * Return form title, or placeholder if not set
		 *
		 * @return {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		validQuestions() {
			return this.form.questions.filter(question => {
				// All questions must have a valid title
				if (question.text?.trim() === '') {
					return false
				}

				// If specific conditions provided, test against them
				if ('validate' in answerTypes[question.type]) {
					return answerTypes[question.type].validate(question)
				}
				return true
			})
		},

		isRequiredUsed() {
			return this.form.questions.reduce((isUsed, question) => isUsed || question.isRequired, false)
		},

		/**
		 * Check if form is expired
		 */
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		infoMessage() {
			let message = ''
			if (this.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}
			if (!this.form.isAnonymous && this.isLoggedIn) {
				message += t('forms', 'Responses are connected to your Nextcloud account.')
			}
			if (this.isRequiredUsed) {
				message += ' ' + t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		},
	},

	watch: {
		hash() {
			// If public view, abort. Should normally not occur.
			if (this.publicView) {
				logger.error('Hash changed on public View. Aborting.')
				return
			}
			this.resetData()
			// Fetch full form on change
			this.fetchFullForm(this.form.id)
			SetWindowTitle(this.formTitle)
		},
	},

	beforeMount() {
		// Public Views get their form by initial-state from parent. No fetch necessary.
		if (this.publicView) {
			this.isLoadingForm = false
		} else {
			this.fetchFullForm(this.form.id)
		}
		SetWindowTitle(this.formTitle)
	},

	methods: {
		/**
		 * On Enter, focus next form-element
		 * Last form element is the submit button, the form submits on enter then
		 *
		 * @param {object} event The fired event.
		 */
		onKeydownEnter(event) {
			const formInputs = Array.from(this.$refs.form)
			const sourceInputIndex = formInputs.findIndex(input => input === event.originalTarget)

			// Focus next form element
			formInputs[sourceInputIndex + 1].focus()
		},

		/**
		 * Ctrl+Enter typically fires submit on forms.
		 * Some inputs do automatically, while some need explicit handling
		 */
		onKeydownCtrlEnter() {
			// Using button-click event to not bypass validity-checks and use our specified behaviour
			this.$refs.submitButton.click()
		},

		/**
		 *  save Submission to Files 💾
		 */
		async onStoreToFiles() {
			const path = '/forms'
			const user = OC.getCurrentUser().uid
			const parser = new DOMParser()
			const url = generateRemoteUrl(`dav/files/${user}/`)
			axios({
				method: 'PROPFIND',
				url,

			}).then(async (response) => {
				const xmlDoc = parser.parseFromString(response.data, 'text/xml')
				const files = xmlDoc.getElementsByTagName('d:href')
				let isFormsFolder = false
				for (let i = 0; i < files.length; i++) {
					const filesNames = files[i].innerHTML.split('/')
					filesNames.pop()
					const folder = filesNames.pop()
					if (folder === 'forms') {
						isFormsFolder = true
						break
					}
				} if (isFormsFolder) {
					await axios.post(generateOcsUrl('apps/forms/api/v2/submissions/export'), {
						hash: this.form.hash,
						path,
					})
					showSuccess(t('forms', 'Succesfully saved to Files'))
				} else {
					const formsUrl = generateRemoteUrl(`dav/files/${user}/forms/`)
					axios({
						method: 'MKCOL',
						url: formsUrl,
					}).then(async (response) => {
						await axios.post(generateOcsUrl('apps/forms/api/v2/submissions/export'), {
							hash: this.form.hash,
							path,
						})
						showSuccess(t('forms', 'Succesfully saved to Files'))
					})
				}
			})
		},
		/**
		 * Submit the form after the browser validated it 🚀
		 */
		async onSubmit() {
			this.loading = true
			try {
				await axios.post(generateOcsUrl('apps/forms/api/v2/submission/insert'), {
					formId: this.form.id,
					answers: this.answers,
					shareHash: this.shareHash,
				})
				await this.onStoreToFiles()
				this.success = true
			} catch (error) {
				logger.error('Error while submitting the form', { error })
				showError(t('forms', 'There was an error submitting the form'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Reset View-Data
		 */
		resetData() {
			this.answers = {}
			this.loading = false
			this.success = false
		},
	},

}
</script>
<style lang="scss" scoped>
.app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	&--public {
		// Compensate top-padding for missing topbar
		padding-top: 50px;
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
		margin-bottom: 24px;
		margin-left: 56px;

		.form-title,
		.form-desc,
		.info-message {
			width: 100%;
			padding: 0 16px;
			border: none;
		}
		.form-title {
			font-size: 28px;
			font-weight: bold;
			color: var(--color-main-text);
			line-height: 34px;
			min-height: 36px;
			margin: 32px 0;
			padding-left: 14px; // align with description (compensate font size diff)
			padding-bottom: 4px;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.form-desc {
			font-size: 100%;
			line-height: 150%;
			padding-bottom: 20px;
			resize: none;
			white-space: pre-line;
		}

		.info-message {
			font-size: 100%;
			padding-bottom: 20px;
			margin-top: 4px;
			resize: none;
			color: var(--color-text-maxcontrast);
		}
	}

	form {
		.question {
			// Less padding needed as submit view does not have drag handles
			padding-left: 44px;
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
