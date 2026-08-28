/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module '*?raw' {
	const content: string
	export default content
}

declare module '*.svg?raw' {
	const content: string
	export default content
}

declare module '*.svg' {
	const content: string
	export default content
}

declare module '@material-symbols/*' {
	const content: string
	export default content
}

interface NextcloudOCConfig {
	'sharing.maxAutocompleteResults'?: string | number
	'sharing.minSearchStringLength'?: string | number
	[key: string]: unknown
}

interface NextcloudOCShare {
	SHARE_TYPE_USER?: number
	SHARE_TYPE_GROUP?: number
	SHARE_TYPE_LINK?: number
	SHARE_TYPE_EMAIL?: number
	SHARE_TYPE_REMOTE?: number
	SHARE_TYPE_CIRCLE?: number
	SHARE_TYPE_GUEST?: number
	SHARE_TYPE_REMOTE_GROUP?: number
	SHARE_TYPE_ROOM?: number
	[key: string]: unknown
}

interface NextcloudOC {
	config: NextcloudOCConfig
	Share?: NextcloudOCShare
	getLanguage: () => string
	theme?: {
		title: string
		[key: string]: unknown
	}
	[key: string]: unknown
}

interface Window {
	OC?: NextcloudOC
}

declare const OC: NextcloudOC | undefined
