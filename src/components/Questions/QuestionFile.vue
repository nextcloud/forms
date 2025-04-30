<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<template #actions>
			<template v-if="!allowedFileTypesDialogOpened">
				<NcActionButton is-menu @click="allowedFileTypesDialogOpened = true">
					<template #icon>
						<IconFileDocumentAlert :size="20" />
					</template>
					{{ allowedFileTypesLabel }}
				</NcActionButton>
			</template>

			<template v-else>
				<NcActionSeparator />

				<NcActionButton @click="allowedFileTypesDialogOpened = false">
					<template #icon>
						<IconChevronLeft :size="20" />
					</template>
					{{ t('forms', 'Allow only specific file types') }}
				</NcActionButton>

				<NcActionCheckbox
					v-for="({ label: fileTypeLabel }, fileType) in fileTypes"
					:key="fileType"
					:checked="extraSettings?.allowedFileTypes?.includes(fileType)"
					:value="fileType"
					class="file-type-checkbox"
					@update:checked="onAllowedFileTypesChange(fileType, $event)">
					{{ fileTypeLabel }}
				</NcActionCheckbox>

				<NcActionInput
					:label="t('forms', 'Custom file extensions')"
					type="multiselect"
					multiple
					taggable
					:value="extraSettings?.allowedFileExtensions || []"
					@option:created="onAllowedFileExtensionsAdded"
					@option:deselected="onAllowedFileExtensionsDeleted" />

				<NcActionSeparator />
			</template>

			<template v-if="!allowedFileTypesDialogOpened">
				<NcActionInput
					type="number"
					:value="maxAllowedFilesCount"
					label-outside
					:label="t('forms', 'Maximum number of files')"
					:show-trailing-button="false"
					@input="onMaxAllowedFilesCountInput($event.target.value)" />

				<NcActionInput
					type="number"
					:value="maxFileSizeValue"
					label-outside
					:show-trailing-button="false"
					:label="t('forms', 'Maximum file size')"
					@input="onMaxFileSizeValueInput($event.target.value)" />

				<NcActionInput
					type="multiselect"
					:value="maxFileSizeUnit"
					:options="availableUnits"
					required
					:clearable="false"
					@input="onMaxFileSizeUnitInput($event)" />
			</template>
		</template>

		<div class="question__content">
			<ul>
				<NcListItem
					v-for="uploadedFile of values"
					:key="uploadedFile.uploadedFileId"
					:name="uploadedFile.fileName"
					compact>
					<template #icon>
						<IconFile :size="20" />
					</template>

					<template #actions>
						<NcActionButton
							@click="
								onDeleteUploadedFile(uploadedFile.uploadedFileId)
							">
							<template #icon>
								<IconDelete :size="20" />
							</template>
							{{ t('forms', 'Delete') }}
						</NcActionButton>
					</template>
				</NcListItem>
				<li v-if="fileLoading" class="question__loading">
					<NcLoadingIcon v-show="fileLoading" />
					{{ t('forms', 'Uploading …') }}
				</li>
				<li v-else-if="values.length < maxAllowedFilesCount">
					<div class="question__input-wrapper">
						<label>
							{{ t('forms', 'Add new file as answer') }}
							<input
								ref="fileInput"
								class="hidden-visually"
								type="file"
								:disabled="!readOnly"
								:multiple="maxAllowedFilesCount > 1"
								:name="name || undefined"
								:accept="accept.length ? accept.join(',') : null"
								@input="onFileInput" />
						</label>
						<NcButton
							:disabled="
								!readOnly || values.length >= maxAllowedFilesCount
							"
							variant="tertiary-no-background"
							@click="toggleFileInput">
							<template #icon>
								<IconUploadMultiple
									v-if="maxAllowedFilesCount > 1"
									:size="20" />
								<IconUpload v-else :size="20" />
							</template>
						</NcButton>
					</div>
				</li>
			</ul>
		</div>
	</Question>
</template>

<script>
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import IconFileDocumentAlert from 'vue-material-design-icons/FileDocumentAlert.vue'
import IconUpload from 'vue-material-design-icons/Upload.vue'
import IconUploadMultiple from 'vue-material-design-icons/UploadMultiple.vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import axios from '@nextcloud/axios'
import fileTypes from '../../models/FileTypes.js'
import logger from '../../utils/Logger.js'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'

/**
 * A constant object representing file size units in bytes.
 *
 * @example
 * ```typescript
 * const kilobytes = FILE_SIZE_UNITS.kb; // 1024
 * const megabytes = FILE_SIZE_UNITS.mb; // 1048576
 * const gigabytes = FILE_SIZE_UNITS.gb; // 1073741824
 * ```
 */
const FILE_SIZE_UNITS = {
	kb: 1024,
	mb: 1024 ** 2,
	gb: 1024 ** 3,
}

export default {
	name: 'QuestionFile',
	components: {
		IconChevronLeft,
		IconDelete,
		IconFile,
		IconFileDocumentAlert,
		IconUpload,
		IconUploadMultiple,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcActionSeparator,
		NcButton,
		NcListItem,
		NcLoadingIcon,
	},

	mixins: [QuestionMixin],
	data() {
		return {
			fileTypes,
			fileLoading: false,
			maxFileSizeUnit: Object.keys(FILE_SIZE_UNITS)[0],
			maxFileSizeValue: '',
			allowedFileTypesDialogOpened: false,
		}
	},

	computed: {
		availableUnits() {
			return Object.keys(FILE_SIZE_UNITS)
		},

		maxAllowedFilesCount() {
			return this.extraSettings?.maxAllowedFilesCount || 1
		},

		allowedFileTypesLabel() {
			const allowedFileTypes = []
			if (this.extraSettings?.allowedFileTypes?.length) {
				allowedFileTypes.push(
					...this.extraSettings.allowedFileTypes.map(
						(type) => fileTypes[type].label,
					),
				)
			}

			if (this.extraSettings?.allowedFileExtensions?.length) {
				allowedFileTypes.push(...this.extraSettings.allowedFileExtensions)
			}

			if (allowedFileTypes.length) {
				return t('forms', 'Allowed file types: {fileTypes}.', {
					fileTypes: allowedFileTypes.join(', '),
				})
			}

			return t('forms', 'All file types are allowed.')
		},
	},

	mounted() {
		if (this.extraSettings.maxFileSize) {
			Object.keys(FILE_SIZE_UNITS).forEach((unit) => {
				if (this.extraSettings.maxFileSize > FILE_SIZE_UNITS[unit]) {
					this.maxFileSizeUnit = unit
				}
			})

			this.maxFileSizeValue =
				this.extraSettings.maxFileSize
				/ FILE_SIZE_UNITS[this.maxFileSizeUnit]
		}
	},

	methods: {
		toggleFileInput() {
			this.$refs.fileInput.click()
		},

		async onFileInput() {
			const fileInput = this.$refs.fileInput
			const formData = new FormData()
			let fileInvalid = false

			;[...fileInput.files].forEach((file) => {
				formData.append('files[]', file)

				if (
					this.extraSettings.maxFileSize > 0
					&& file.size > this.extraSettings.maxFileSize
				) {
					showError(
						t(
							'forms',
							'The file {fileName} is too large. The maximum file size is {maxFileSize}.',
							{
								fileName: file.name,
								maxFileSize: formatFileSize(
									this.extraSettings.maxFileSize,
								),
							},
						),
					)

					fileInvalid = true
				}
			})

			if (fileInvalid) {
				return
			}

			formData.append('shareHash', loadState('forms', 'shareHash', null))

			const url = generateOcsUrl(
				'apps/forms/api/v3/forms/{id}/submissions/files/{questionId}',
				{
					id: this.formId,
					questionId: this.id,
				},
			)

			let response
			try {
				this.fileLoading = true
				response = await axios.post(url, formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
			} catch (error) {
				logger.error('Error while submitting the form', { error })
				showError(
					t(
						'forms',
						'There was an error during submitting the file: {message}.',
						{ message: error.response.data.ocs.meta.message },
					),
				)

				return
			} finally {
				this.fileLoading = false
				fileInput.value = null
			}

			this.$emit('update:values', [
				...this.values,
				...OcsResponse2Data(response),
			])
		},

		onMaxAllowedFilesCountInput(maxAllowedFilesCount) {
			return this.onExtraSettingsChange({
				maxAllowedFilesCount: parseInt(maxAllowedFilesCount),
			})
		},

		onMaxFileSizeValueInput(maxFileSizeValue) {
			this.maxFileSizeValue = maxFileSizeValue
			const maxFileSize = Math.round(
				maxFileSizeValue * FILE_SIZE_UNITS[this.maxFileSizeUnit],
			)

			return this.onExtraSettingsChange({ maxFileSize })
		},

		onMaxFileSizeUnitInput(maxFileSizeUnit) {
			this.maxFileSizeUnit = maxFileSizeUnit
			const maxFileSize = Math.round(
				this.maxFileSizeValue * FILE_SIZE_UNITS[maxFileSizeUnit],
			)

			return this.onExtraSettingsChange({ maxFileSize })
		},

		onAllowedFileTypesChange(fileType, allowed) {
			let allowedFileTypes = this.extraSettings.allowedFileTypes || []

			if (allowed) {
				allowedFileTypes.push(fileType)
			} else {
				allowedFileTypes = allowedFileTypes.filter(
					(type) => type !== fileType,
				)
			}

			return this.onExtraSettingsChange({ allowedFileTypes })
		},

		onAllowedFileExtensionsAdded(fileExtension) {
			const allowedFileExtensions =
				this.extraSettings.allowedFileExtensions || []
			allowedFileExtensions.push(fileExtension)

			return this.onExtraSettingsChange({ allowedFileExtensions })
		},

		onAllowedFileExtensionsDeleted(fileExtension) {
			let allowedFileExtensions =
				this.extraSettings.allowedFileExtensions || []
			allowedFileExtensions = allowedFileExtensions.filter(
				(extension) => extension !== fileExtension,
			)

			return this.onExtraSettingsChange({ allowedFileExtensions })
		},

		onDeleteUploadedFile(uploadedFileId) {
			const values = this.values.filter(
				(value) => value.uploadedFileId !== uploadedFileId,
			)

			this.$emit('update:values', values)
		},
	},
}
</script>

<style scoped lang="scss">
.file-type-checkbox {
	margin-left: 30px;
}
.question {
	&--editable {
		.question__input-wrapper {
			margin-inline-start: -13px;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		width: 300px;
	}

	&__input-wrapper {
		--focus-offset: calc(
			(var(--border-width-input-focused, 2px) - var(--border-width-input, 2px))
		);
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: space-between;
		border: var(--border-width-input, 2px) solid var(--color-border-dark);
		border-radius: var(--border-radius-element, var(--border-radius-large));
		padding-inline: calc(3 * var(--default-grid-baseline)) var(--focus-offset);
		padding-block: var(--focus-offset);
		height: var(--default-clickable-area);
		width: 100%;
		max-width: 300px;

		label {
			color: var(--color-text-maxcontrast);

			&:has(input:disabled) {
				cursor: default;
			}
		}

		&:hover,
		&:focus-within {
			border-color: var(--color-main-text);
			border-width: var(--border-width-input-focused, 2px);
			padding-block: 0;
			padding-inline: calc(
					3 * var(--default-grid-baseline) - var(--focus-offset)
				)
				0;
		}
	}
}
</style>
