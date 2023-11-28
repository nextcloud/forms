module.exports = {
	globals: {
		appName: true,
	},
	extends: [
		'@nextcloud',
	],
	rules: {
		// We are using the @nextcloud/logger
		'no-console': ['error', { allow: undefined }],
		'import/no-unresolved': ['error', { ignore: ['\\?raw'] }],
	},
}
