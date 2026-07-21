<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<template v-if="!allowedFileTypesDialogOpened">
				<NcActionButton isMenu @click="allowedFileTypesDialogOpened = true">
					<template #icon>
						<NcIconSvgWrapper :svg="IconFileDocumentAlert" />
					</template>
					{{ allowedFileTypesLabel }}
				</NcActionButton>

				<NcActionInput
					type="number"
					:modelValue="maxAllowedFilesCount"
					labelOutside
					:label="t('forms', 'Maximum number of files')"
					:showTrailingButton="false"
					@update:modelValue="onMaxAllowedFilesCountInput" />

				<NcActionInput
					type="number"
					:modelValue="maxFileSizeValue"
					labelOutside
					:showTrailingButton="false"
					:label="t('forms', 'Maximum file size')"
					@update:modelValue="onMaxFileSizeValueInput" />

				<NcActionInput
					type="multiselect"
					:modelValue="maxFileSizeUnit"
					:options="availableUnits"
					required
					:clearable="false"
					:searchable="false"
					@update:modelValue="onMaxFileSizeUnitInput" />
			</template>

			<template v-else>
				<NcActionSeparator />

				<NcActionButton @click="allowedFileTypesDialogOpened = false">
					<template #icon>
						<NcIconSvgWrapper :svg="IconChevronLeft" />
					</template>
					{{ t('forms', 'Allow only specific file types') }}
				</NcActionButton>

				<NcActionCheckbox
					v-for="({ label: fileTypeLabel }, fileType) in fileTypes"
					:key="fileType"
					:modelValue="allowedFileTypes.includes(fileType)"
					:value="fileType"
					class="file-type-checkbox"
					@update:modelValue="onAllowedFileTypesChange(fileType, $event)">
					{{ fileTypeLabel }}
				</NcActionCheckbox>

				<NcActionInput
					key="allowed-file-extensions-multiselect"
					:label="t('forms', 'Custom file extensions')"
					type="multiselect"
					multiple
					taggable
					:modelValue="allowedFileExtensions"
					@option:created="onAllowedFileExtensionsAdded"
					@option:deselected="onAllowedFileExtensionsDeleted" />

				<NcActionSeparator />
			</template>
		</template>

		<div class="question__content">
			<ul>
				<NcListItem
					v-for="uploadedFile of uploadedFiles"
					:key="uploadedFile.uploadedFileId"
					:name="uploadedFile.fileName"
					compact>
					<template #icon>
						<NcIconSvgWrapper :svg="IconFile" />
					</template>

					<template #actions>
						<NcActionButton
							@click="
								onDeleteUploadedFile(uploadedFile.uploadedFileId)
							">
							<template #icon>
								<NcIconSvgWrapper :svg="IconDelete" />
							</template>
							{{ t('forms', 'Delete') }}
						</NcActionButton>
					</template>
				</NcListItem>
				<li v-if="fileLoading" class="question__loading">
					<NcLoadingIcon v-show="fileLoading" />
					{{ t('forms', 'Uploading …') }}
				</li>
				<li v-else-if="uploadedFiles.length < maxAllowedFilesCount">
					<div
						class="question__input-wrapper"
						role="group"
						:aria-labelledby="titleId"
						:aria-describedby="description ? descriptionId : undefined"
						:aria-errormessage="hasError ? errorId : undefined"
						:aria-invalid="hasError ? 'true' : undefined">
						<label>
							{{ t('forms', 'Add new file as answer') }}
							<input
								ref="fileInput"
								class="hidden-visually"
								type="file"
								:required="isRequired && values.length === 0"
								:disabled="!readOnly"
								:multiple="maxAllowedFilesCount > 1"
								:name="name || undefined"
								:accept="
									accept.length ? accept.join(',') : undefined
								"
								@invalid.prevent="validate"
								@input="onFileInput" />
						</label>
						<NcButton
							:disabled="
								!readOnly || values.length >= maxAllowedFilesCount
							"
							variant="tertiary-no-background"
							@click="toggleFileInput">
							<template #icon>
								<NcIconSvgWrapper
									v-if="maxAllowedFilesCount > 1"
									:svg="IconUploadMultiple" />
								<NcIconSvgWrapper v-else :svg="IconUpload" />
							</template>
						</NcButton>
					</div>
				</li>
			</ul>
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import IconChevronLeft from '@material-symbols/svg-400/outlined/chevron_left.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconFile from '@material-symbols/svg-400/outlined/draft.svg?raw'
import IconFileDocumentAlert from '@material-symbols/svg-400/outlined/quick_reference.svg?raw'
import IconUpload from '@material-symbols/svg-400/outlined/upload.svg?raw'
import IconUploadMultiple from '@material-symbols/svg-400/outlined/upload_file.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.ts'
import fileTypes from '../../models/FileTypes.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

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

type FileSizeUnit = keyof typeof FILE_SIZE_UNITS

type UploadedFileValue = {
	fileName: string
	uploadedFileId: number | string
}

type QuestionFileExtraSettings = {
	allowedFileExtensions?: string[]
	allowedFileTypes?: string[]
	maxAllowedFilesCount?: number
	maxFileSize?: number
}

interface QuestionFileData {
	fileTypes: typeof fileTypes
	fileLoading: boolean
	maxFileSizeUnit: FileSizeUnit
	maxFileSizeValue: number
	allowedFileTypesDialogOpened: boolean
}

export default defineComponent({
	name: 'QuestionFile',
	components: {
		NcIconSvgWrapper,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcActionSeparator,
		NcButton,
		NcListItem,
		NcLoadingIcon,
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values'],

	setup() {
		return {
			IconChevronLeft,
			IconDelete,
			IconFile,
			IconFileDocumentAlert,
			IconUpload,
			IconUploadMultiple,
			t,
		}
	},

	data(): QuestionFileData {
		return {
			fileTypes,
			fileLoading: false,
			maxFileSizeUnit: Object.keys(FILE_SIZE_UNITS)[0] as FileSizeUnit,
			maxFileSizeValue: 0,
			allowedFileTypesDialogOpened: false,
		}
	},

	computed: {
		availableUnits(): FileSizeUnit[] {
			return Object.keys(FILE_SIZE_UNITS) as FileSizeUnit[]
		},

		uploadedFiles(): UploadedFileValue[] {
			return this.values as UploadedFileValue[]
		},

		maxAllowedFilesCount(): number {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.maxAllowedFilesCount ?? 1
		},

		allowedFileExtensions(): string[] {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.allowedFileExtensions ?? []
		},

		allowedFileTypes(): string[] {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.allowedFileTypes ?? []
		},

		allowedFileTypesLabel(): string {
			const allowedFileTypes: string[] = []
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			if (extraSettings?.allowedFileTypes?.length) {
				allowedFileTypes.push(
					...extraSettings.allowedFileTypes.map(
						(type: string) => fileTypes[type].label,
					),
				)
			}

			if (extraSettings?.allowedFileExtensions?.length) {
				allowedFileTypes.push(...extraSettings.allowedFileExtensions)
			}

			if (allowedFileTypes.length) {
				return t('forms', 'Allowed file types: {fileTypes}.', {
					fileTypes: allowedFileTypes.join(', '),
				})
			}

			return t('forms', 'All file types are allowed.')
		},
	},

	mounted(): void {
		const extraSettings = this.extraSettings as
			QuestionFileExtraSettings | undefined
		if (extraSettings?.maxFileSize) {
			const maxFileSize = extraSettings.maxFileSize
			Object.keys(FILE_SIZE_UNITS).forEach((unit) => {
				const typedUnit = unit as FileSizeUnit
				if (maxFileSize > FILE_SIZE_UNITS[typedUnit]) {
					this.maxFileSizeUnit = typedUnit
				}
			})

			this.maxFileSizeValue =
				maxFileSize / FILE_SIZE_UNITS[this.maxFileSizeUnit]
		}
	},

	methods: {
		toggleFileInput(): void {
			;(this.$refs.fileInput as HTMLInputElement | undefined)?.click()
		},

		async onFileInput(): Promise<void> {
			const fileInput = this.$refs.fileInput as HTMLInputElement | undefined
			if (!fileInput?.files) {
				return
			}

			const formData = new FormData()
			let fileInvalid = false
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined

			;[...fileInput.files].forEach((file) => {
				formData.append('files[]', file)

				if (
					extraSettings?.maxFileSize
					&& extraSettings.maxFileSize > 0
					&& file.size > extraSettings.maxFileSize
				) {
					showError(
						t(
							'forms',
							'The file {fileName} is too large. The maximum file size is {maxFileSize}.',
							{
								fileName: file.name,
								maxFileSize: formatFileSize(
									extraSettings.maxFileSize,
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

			formData.append(
				'shareHash',
				String(loadState('forms', 'shareHash', null) ?? ''),
			)

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
						{
							message:
								(
									error as {
										response?: {
											data?: {
												ocs?: {
													meta?: {
														message?: string
													}
												}
											}
										}
									}
								).response?.data?.ocs?.meta?.message ?? '',
						},
					),
				)

				return
			} finally {
				this.fileLoading = false
				fileInput.value = ''
			}

			this.$emit('update:values', [
				...(this.values as UploadedFileValue[]),
				...(OcsResponse2Data(response) as UploadedFileValue[]),
			])
		},

		onMaxAllowedFilesCountInput(maxAllowedFilesCount: number | string): void {
			return this.onExtraSettingsChange({
				maxAllowedFilesCount: parseInt(String(maxAllowedFilesCount), 10),
			})
		},

		onMaxFileSizeValueInput(maxFileSizeValue: number | string): void {
			this.maxFileSizeValue = Number(maxFileSizeValue)
			const maxFileSize = Math.round(
				Number(maxFileSizeValue) * FILE_SIZE_UNITS[this.maxFileSizeUnit],
			)

			return this.onExtraSettingsChange({ maxFileSize })
		},

		onMaxFileSizeUnitInput(maxFileSizeUnit: FileSizeUnit): void {
			this.maxFileSizeUnit = maxFileSizeUnit
			const maxFileSize = Math.round(
				this.maxFileSizeValue * FILE_SIZE_UNITS[maxFileSizeUnit],
			)

			return this.onExtraSettingsChange({ maxFileSize })
		},

		onAllowedFileTypesChange(fileType: string, allowed: boolean): void {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			let allowedFileTypes = extraSettings?.allowedFileTypes ?? []

			if (allowed) {
				allowedFileTypes.push(fileType)
			} else {
				allowedFileTypes = allowedFileTypes.filter(
					(type) => type !== fileType,
				)
			}

			return this.onExtraSettingsChange({ allowedFileTypes })
		},

		onAllowedFileExtensionsAdded(fileExtension: string): void {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			const allowedFileExtensions = extraSettings?.allowedFileExtensions ?? []
			allowedFileExtensions.push(fileExtension)

			return this.onExtraSettingsChange({ allowedFileExtensions })
		},

		onAllowedFileExtensionsDeleted(fileExtension: string): void {
			const extraSettings = this.extraSettings as
				QuestionFileExtraSettings | undefined
			let allowedFileExtensions = extraSettings?.allowedFileExtensions ?? []
			allowedFileExtensions = allowedFileExtensions.filter(
				(extension) => extension !== fileExtension,
			)

			return this.onExtraSettingsChange({ allowedFileExtensions })
		},

		onDeleteUploadedFile(uploadedFileId: number | string): void {
			const values = (this.values as UploadedFileValue[]).filter(
				(value) => value.uploadedFileId !== uploadedFileId,
			)

			this.$emit('update:values', values)
		},

		async validate(): Promise<boolean> {
			if (this.fileLoading) {
				this.errorMessage = t(
					'forms',
					'Please wait until the file has been uploaded.',
				)
				return false
			}

			if (this.isRequired && this.uploadedFiles.length === 0) {
				this.errorMessage = t('forms', 'You must answer this question')
				return false
			}

			this.errorMessage = null
			return true
		},
	},
})
</script>

<style scoped lang="scss">
.file-type-checkbox {
	margin-inline-start: 30px;
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
