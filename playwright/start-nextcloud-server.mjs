import { startNextcloud, stopNextcloud } from '@nextcloud/cypress/docker'
import { readFileSync } from 'fs'

const start = async () => {
	const appinfo = readFileSync('appinfo/info.xml').toString()
	const maxVersion = appinfo.match(
		/<nextcloud min-version="\d+" max-version="(\d\d+)" \/>/,
	)?.[1]
	const branch = maxVersion ? `stable${maxVersion}` : undefined

	return await startNextcloud(branch, true, {
		exposePort: 8089,
	})
}

// Start the Nextcloud docker container
await start()
// Listen for process to exit (tests done) and shut down the docker container
process.on('beforeExit', (code) => {
	stopNextcloud()
})

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
