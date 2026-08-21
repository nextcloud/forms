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
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, defineComponent, onMounted, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
import fileTypes from '../../models/FileTypes.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

const formsAppName = 'forms'

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

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })
		const fileInput = ref<HTMLInputElement | null>(null)
		const fileLoading = ref(false)
		const maxFileSizeUnit = ref<FileSizeUnit>(
			Object.keys(FILE_SIZE_UNITS)[0] as FileSizeUnit,
		)
		const maxFileSizeValue = ref(0)
		const allowedFileTypesDialogOpened = ref(false)

		const availableUnits = computed<FileSizeUnit[]>(() => {
			return Object.keys(FILE_SIZE_UNITS) as FileSizeUnit[]
		})

		const uploadedFiles = computed<UploadedFileValue[]>(() => {
			return props.values as UploadedFileValue[]
		})

		const maxAllowedFilesCount = computed<number>(() => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.maxAllowedFilesCount ?? 1
		})

		const allowedFileExtensions = computed<string[]>(() => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.allowedFileExtensions ?? []
		})

		const allowedFileTypes = computed<string[]>(() => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			return extraSettings?.allowedFileTypes ?? []
		})

		const allowedFileTypesLabel = computed<string>(() => {
			const allowedFileTypeLabels: string[] = []
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			if (extraSettings?.allowedFileTypes?.length) {
				allowedFileTypeLabels.push(
					...extraSettings.allowedFileTypes.map(
						(type: string) => fileTypes[type].label,
					),
				)
			}

			if (extraSettings?.allowedFileExtensions?.length) {
				allowedFileTypeLabels.push(...extraSettings.allowedFileExtensions)
			}
			if (allowedFileTypeLabels.length) {
				return t('forms', 'Allowed file types: {fileTypes}.', {
					fileTypes: allowedFileTypeLabels.join(', '),
				})
			}

			return t('forms', 'All file types are allowed.')
		})

		onMounted(() => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			if (extraSettings?.maxFileSize) {
				const maxFileSize = extraSettings.maxFileSize
				Object.keys(FILE_SIZE_UNITS).forEach((unit) => {
					const typedUnit = unit as FileSizeUnit
					if (maxFileSize > FILE_SIZE_UNITS[typedUnit]) {
						maxFileSizeUnit.value = typedUnit
					}
				})
				maxFileSizeValue.value =
					maxFileSize / FILE_SIZE_UNITS[maxFileSizeUnit.value]
			}
		})

		const toggleFileInput = (): void => {
			;(fileInput.value as HTMLInputElement | undefined)?.click()
		}

		const onFileInput = async (): Promise<void> => {
			const currentInput = fileInput.value
			if (!currentInput?.files) {
				return
			}

			const formData = new FormData()
			let fileInvalid = false
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined

			;[...currentInput.files].forEach((file) => {
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
				String(loadState(formsAppName, 'shareHash', null) ?? ''),
			)

			const url = generateOcsUrl(
				'apps/forms/api/v3/forms/{id}/submissions/files/{questionId}',
				{
					id: props.formId,
					questionId: props.id,
				},
			)

			let response
			try {
				fileLoading.value = true
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
				fileLoading.value = false
				currentInput.value = ''
			}

			emit('update:values', [
				...(props.values as UploadedFileValue[]),
				...(OcsResponse2Data(response) as UploadedFileValue[]),
			])
		}

		const onMaxAllowedFilesCountInput = (
			maxAllowedFilesCountValue: number | string,
		): void => {
			question.onExtraSettingsChange({
				maxAllowedFilesCount: parseInt(
					String(maxAllowedFilesCountValue),
					10,
				),
			})
		}

		const onMaxFileSizeValueInput = (
			newMaxFileSizeValue: number | string,
		): void => {
			maxFileSizeValue.value = Number(newMaxFileSizeValue)
			const maxFileSize = Math.round(
				Number(newMaxFileSizeValue) * FILE_SIZE_UNITS[maxFileSizeUnit.value],
			)

			question.onExtraSettingsChange({ maxFileSize })
		}

		const onMaxFileSizeUnitInput = (newMaxFileSizeUnit: FileSizeUnit): void => {
			maxFileSizeUnit.value = newMaxFileSizeUnit
			const maxFileSize = Math.round(
				maxFileSizeValue.value * FILE_SIZE_UNITS[newMaxFileSizeUnit],
			)

			question.onExtraSettingsChange({ maxFileSize })
		}

		const onAllowedFileTypesChange = (
			fileType: string,
			allowed: boolean,
		): void => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			let allowedFileTypesList = extraSettings?.allowedFileTypes ?? []

			if (allowed) {
				allowedFileTypesList.push(fileType)
			} else {
				allowedFileTypesList = allowedFileTypesList.filter(
					(type) => type !== fileType,
				)
			}

			question.onExtraSettingsChange({
				allowedFileTypes: allowedFileTypesList,
			})
		}

		const onAllowedFileExtensionsAdded = (fileExtension: string): void => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			const allowedFileExtensionsList =
				extraSettings?.allowedFileExtensions ?? []
			allowedFileExtensionsList.push(fileExtension)
			question.onExtraSettingsChange({
				allowedFileExtensions: allowedFileExtensionsList,
			})
		}

		const onAllowedFileExtensionsDeleted = (fileExtension: string): void => {
			const extraSettings = props.extraSettings as
				QuestionFileExtraSettings | undefined
			let allowedFileExtensionsList =
				extraSettings?.allowedFileExtensions ?? []
			allowedFileExtensionsList = allowedFileExtensionsList.filter(
				(extension) => extension !== fileExtension,
			)

			question.onExtraSettingsChange({
				allowedFileExtensions: allowedFileExtensionsList,
			})
		}

		const onDeleteUploadedFile = (uploadedFileId: number | string): void => {
			const values = (props.values as UploadedFileValue[]).filter(
				(value) => value.uploadedFileId !== uploadedFileId,
			)

			emit('update:values', values)
		}

		const validate = async (): Promise<boolean> => {
			if (fileLoading.value) {
				question.errorMessage.value = t(
					'forms',
					'Please wait until the file has been uploaded.',
				)
				return false
			}

			if (props.isRequired && uploadedFiles.value.length === 0) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			question.errorMessage.value = null
			return true
		}

		return {
			...question,
			IconChevronLeft,
			IconDelete,
			IconFile,
			IconFileDocumentAlert,
			IconUpload,
			IconUploadMultiple,
			t,
			fileInput,
			fileTypes,
			fileLoading,
			maxFileSizeUnit,
			maxFileSizeValue,
			allowedFileTypesDialogOpened,
			availableUnits,
			uploadedFiles,
			maxAllowedFilesCount,
			allowedFileExtensions,
			allowedFileTypes,
			allowedFileTypesLabel,
			toggleFileInput,
			onFileInput,
			onMaxAllowedFilesCountInput,
			onMaxFileSizeValueInput,
			onMaxFileSizeUnitInput,
			onAllowedFileTypesChange,
			onAllowedFileExtensionsAdded,
			onAllowedFileExtensionsDeleted,
			onDeleteUploadedFile,
			validate,
		}
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
