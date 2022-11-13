<!--
  - @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="section submission">
		<div class="submission-head">
			<h3 dir="auto">
				{{ submission.userDisplayName }}
			</h3>
			<NcActions class="submission-menu" :force-menu="true">
				<NcActionButton :close-after-click="true" @click="onStoreToFiles">
					<template #icon>
						<IconFolder :size="20" />
					</template>
					{{ t('forms', 'Save CSV to Files') }}
				</NcActionButton>
				<NcActionLink :href="responseDownload">
					<template #icon>
						<IconDownload :size="20" />
					</template>
					{{ t('forms', 'Download this response') }}
				</NcActionLink>
				<NcActionButton v-if="canDeleteSubmission" @click="onDelete">
					<template #icon>
						<IconDelete :size="20" />
					</template>
					{{ t('forms', 'Delete this response') }}
				</NcActionButton>
			</NcActions>
		</div>
		<p class="submission-date">
			{{ submissionDateTime }}
		</p>

		<Answer v-for="question in answeredQuestions"
			:key="question.id"
			:answer-text="question.squashedAnswers"
			:question-text="question.text" />
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import moment from '@nextcloud/moment'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconDownload from 'vue-material-design-icons/Download.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import logger from '../../utils/Logger.js'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'

import Answer from './Answer.vue'

const picker = getFilePickerBuilder(t('forms', 'Save submission to Files'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'Submission',

	components: {
		Answer,
		IconDelete,
		IconDownload,
		IconFolder,
		NcActions,
		NcActionButton,
		NcActionLink,
	},

	props: {
		submission: {
			type: Object,
			required: true,
		},
		questions: {
			type: Array,
			required: true,
		},
		canDeleteSubmission: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		responseDownload() {
			return generateOcsUrl('apps/forms/api/v2.2/submissions/exportSubmission/{submissionId}', { submissionId: this.submission.id })
		},

		// Format submission-timestamp to DateTime
		submissionDateTime() {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},

		/**
		 * Join answered Questions with corresponding answers.
		 * Multiple answers to a question are squashed into one string.
		 *
		 * @return {Array}
		 */
		answeredQuestions() {
			const answeredQuestionsArray = []

			this.questions.forEach(question => {
				const answers = this.submission.answers.filter(answer => answer.questionId === question.id)
				if (!answers.length) {
					return // no answers, go to next question
				}
				const squashedAnswers = answers.map(answer => answer.text).join('; ')

				answeredQuestionsArray.push({
					id: question.id,
					text: question.text,
					squashedAnswers,
				})
			})
			return answeredQuestionsArray
		},
	},

	methods: {
		async onStoreToFiles() {
			// picker.pick() does not reject Promise -> await would never resolve.
			picker.pick()
				.then(async (path) => {
					try {
						const response = await axios.post(generateOcsUrl('apps/forms/api/v2.2/submissions/exportSubmission'), {
							submissionId: this.submission.id,
							path,
						})
						showSuccess(t('forms', 'Export successful to {file}', { file: OcsResponse2Data(response) }))
					} catch (error) {
						logger.error('Error while exporting to Files', { error })
						showError(t('forms', 'There was an error, while exporting to Files'))
					}
				})
		},
		onDelete() {
			this.$emit('delete')
		},
	},
}
</script>

<style lang="scss" scoped>
.submission {
	padding-inline: 44px 16px;

	&-head {
		display: flex;
		align-items: flex-end;

		h3 {
			font-weight: bold;
		}

		&-menu {
			display: inline-block;
		}
	}

	&-date {
		color: var(--color-text-lighter);
		margin-block-start: -8px;
	}
}
</style>
