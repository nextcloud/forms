import { getLoggerBuilder } from '@nextcloud/logger'

const logger = getLoggerBuilder()
	.setApp('forms')
	.detectUser()
	.build()

export default logger
