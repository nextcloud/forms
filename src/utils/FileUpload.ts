/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { IUpload, Uploader } from '@nextcloud/files/upload'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'
import OcsResponse2Data from './OcsResponse2Data.ts'

export type UploadedFileValue = {
	fileName: string
	uploadedFileId: number | string
	uploadToken?: string
}

const formsAppName = 'forms'

function getShareHash(): string {
	return String(loadState(formsAppName, 'shareHash', null) ?? '')
}

/**
 * Inject the public share state expected by the upload library.
 *
 * `@nextcloud/files` resolves the public DAV endpoints via
 * `isPublicShare()`/`getSharingToken()` which read the `files_sharing`
 * initial state or matching hidden inputs. As the Forms app does not run
 * on a files_sharing public page, the hidden inputs are provided here for
 * the upload share that was created by the API.
 */
function setPublicShareToken(token: string): void {
	if (
		!document.querySelector(
			'input#isPublic[type="hidden"][name="isPublic"][value="1"]',
		)
	) {
		const isPublicInput = document.createElement('input')
		isPublicInput.id = 'isPublic'
		isPublicInput.type = 'hidden'
		isPublicInput.name = 'isPublic'
		isPublicInput.setAttribute('value', '1')
		document.body.appendChild(isPublicInput)
	}

	let sharingTokenInput = document.querySelector<HTMLInputElement>(
		'input#sharingToken[type="hidden"]',
	)
	if (!sharingTokenInput) {
		sharingTokenInput = document.createElement('input')
		sharingTokenInput.id = 'sharingToken'
		sharingTokenInput.type = 'hidden'
		sharingTokenInput.name = 'sharingToken'
		document.body.appendChild(sharingTokenInput)
	}
	sharingTokenInput.value = token
}

/**
 * Create a temporary create-only public share for file uploads of a question
 *
 * @param formId id of the form
 * @param questionId id of the question
 * @return token of the created share
 */
export async function createUploadShare(
	formId: number | string,
	questionId: number | string,
): Promise<string> {
	const response = await axios.post(
		generateOcsUrl(
			'apps/forms/api/v3/forms/{formId}/submissions/files/{questionId}/share',
			{ formId, questionId },
		),
		{ shareHash: getShareHash() },
	)

	return OcsResponse2Data<{ shareToken: string }>(response).shareToken
}

/**
 * Create an uploader that targets the public DAV endpoint of an upload share
 *
 * @param shareToken token of the upload share created by `createUploadShare`
 */
export async function createPublicUploader(
	shareToken: string,
): Promise<Uploader> {
	setPublicShareToken(shareToken)

	// The uploader resolves the public share token lazily, so importing the
	// library only after the state is set is not strictly required but keeps
	// the (large) module out of the initial bundle.
	const [{ Uploader }, { Folder, Permission }] = await Promise.all([
		import('@nextcloud/files/upload'),
		import('@nextcloud/files'),
	])

	const root = `/files/${shareToken}`
	const remoteUrl = generateRemoteUrl('dav').replace(
		'remote.php',
		'public.php',
	)
	const destination = new Folder({
		id: 0,
		owner: 'anonymous',
		permissions: Permission.ALL,
		root,
		source: `${remoteUrl}${root}`,
	})

	return new Uploader(true, destination)
}

/**
 * Wait until an upload reached a final state and return whether it succeeded
 *
 * `Uploader.upload()` resolves once the upload is scheduled. For chunked
 * uploads the final MOVE assembly happens asynchronously afterwards and is
 * signalled by the `finished` event of the upload.
 *
 * @param upload the upload as returned by `Uploader.upload()`
 * @return `true` if the upload finished successfully
 */
export async function waitForUploadFinished(
	upload: IUpload,
): Promise<boolean> {
	const { UploadStatus } = await import('@nextcloud/files/upload')
	const finalStates = [
		UploadStatus.FINISHED,
		UploadStatus.CANCELLED,
		UploadStatus.FAILED,
	]

	if (!finalStates.includes(upload.status)) {
		await new Promise<void>((resolve) => {
			upload.addEventListener('finished', () => resolve())
		})
	}

	return upload.status === UploadStatus.FINISHED
}

/**
 * Register a file that was uploaded to an upload share
 *
 * @param formId id of the form
 * @param questionId id of the question
 * @param shareToken token of the upload share the file was uploaded to
 * @param fileName name of the uploaded file inside the share
 */
export async function registerUploadedFile(
	formId: number | string,
	questionId: number | string,
	shareToken: string,
	fileName: string,
): Promise<UploadedFileValue> {
	const response = await axios.post(
		generateOcsUrl(
			'apps/forms/api/v3/forms/{formId}/submissions/files/{questionId}/register',
			{ formId, questionId },
		),
		{ shareToken, fileName, shareHash: getShareHash() },
	)

	return OcsResponse2Data<UploadedFileValue>(response)
}

/**
 * Upload files via the legacy multipart endpoint
 *
 * @param formId id of the form
 * @param questionId id of the question
 * @param files files to upload
 */
export async function uploadFilesLegacy(
	formId: number | string,
	questionId: number | string,
	files: File[],
): Promise<UploadedFileValue[]> {
	const formData = new FormData()
	files.forEach((file) => formData.append('files[]', file))
	formData.append('shareHash', getShareHash())

	const response = await axios.post(
		generateOcsUrl(
			'apps/forms/api/v3/forms/{formId}/submissions/files/{questionId}',
			{ formId, questionId },
		),
		formData,
		{ headers: { 'Content-Type': 'multipart/form-data' } },
	)

	return OcsResponse2Data<UploadedFileValue[]>(response)
}
