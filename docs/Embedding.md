<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-only
-->

# Embedding

Besides sharing and using the [API](./API_v3.md) for custom forms it is possible to embed forms inside external
websites.

## Obtaining the embedding code

For embedding a form it is **required** to create a _public share link_ first and then _convert it to an embeddable link_.\
The embedding code can be copied from the _sharing sidebar_ or crafted manually by using the public share link:

If the public share link looks like this:\
`https://SERVER_DOMAIN/apps/forms/s/SHARE_HASH`

The embeddable URL looks like this:\
`https://SERVER_DOMAIN/apps/forms/embed/SHARE_HASH`

Using the copy-embedding-code button on the _sharing sidebar_ will automatically generate ready-to-use HTML code for embedding which looks like this:

```html
<iframe src="EMBEDDABLE_URL" width="750" height="900"></iframe>
```

The size parameters are based on our default forms styling.

## Window message events

The embedded view provides a `MessageEvent` to communicate its size with its parent window.
This is done as accessing the document within an `iframe` is not possible if not on the same domain.

### Auto resizing the `iframe`

The emitted message on the embedded view looks like this:

```json
{
	"type": "resize-iframe",
	"payload": {
		"width": 750,
		"height": 900
	}
}
```

To receive this information on your parent site:

```js
window.addEventListener(
	'message',
	(event) => {
		if (event.origin !== 'http://your-nextcloud-server.com') {
			return
		}

		if (event.data.type !== 'resize-iframe') {
			return
		}

		const { width, height } = event.data.payload

		iframe.width = width
		iframe.height = height
	},
	false,
)
```

### Form submitted

When the form is submitted a message event like this is emitted:

The emitted message on the embedded view looks like this:

```json
{
	"type": "form-saved",
	"payload": {
		"id": 1234
	}
}
```

## Custom styling

To apply custom styles on the embedded forms the [Custom CSS App](https://apps.nextcloud.com/apps/theming_customcss) can be used.

The embedded form provides the `app-forms-embedded` class, so you can apply your styles.\
For example if you want the form to be displayed without margins you can use this:

```css
#content-vue.app-forms-embedded {
	width: 100%;
	height: 100%;
	border-radius: 0;
	margin: 0;
}
```

Or if you want the form to fill the screen:

```css
#content-vue.app-forms-embedded .app-content header,
#content-vue.app-forms-embedded .app-content form {
	max-width: unset;
}
```
