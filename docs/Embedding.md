# Embedding
Besides sharing and using the [API](./API.md) for custom forms it is possible to embed forms inside external
websites.

## Obtaining the embedding code

For embedding a form it is **required** to create a *public share link*.\
The embedding code can be copied from the *sharing sidebar* or crafted manually by using the public share link:

If the public share link looks like this:\
`https://SERVER_DOMAIN/apps/forms/s/SHARE_HASH`

The embeddable URL looks like this:\
`https://SERVER_DOMAIN/apps/forms/embed/SHARE_HASH`

Using the copy-embedding-code button on the *sharing sidebar* will automatically generate ready-to-use HTML code for embedding which looks like this:
```html
<iframe src="EMBEDDABLE_URL" width="750" height="900"></iframe>
```
The size parameters are based on our default forms styling.

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