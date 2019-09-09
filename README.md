# Forms

Forms allows the creation of shareable forms, with multiple question types and privacy settings.


**Note**: This app is tested with Apache2 webserver, MySQL database, and apt-get package manager. To use alternatives, replace the relevant commands with those of your technology. This document assumes that a working
NextCloud development environment has been installed. See https://docs.nextcloud.com/server/stable/developer_manual/general/devenv.html for help with this.

## Build the app

``` bash
# set up and build for production
make

# install dependencies
make dev-setup

# build for dev and watch changes
make watch-js

# build for dev
make build-js

# build for production with minification
make build-js-production

```
## Running tests
You can use the provided Makefile to run all tests by using:

_ps: only works if you're using php locally and have forms installed info your apps default folder_

```
make test
```

## :v: Code of conduct

The Nextcloud community has core values that are shared between all members during conferences,
hackweeks and on all interactions in online platforms including [Github](https://github.com/nextcloud) and [Forums](https://help.nextcloud.com).
If you contribute, participate or interact with this community, please respect [our shared values](https://nextcloud.com/code-of-conduct/). :relieved:

## :heart: How to create a pull request

This guide will help you get started: 
- :dancer: :smile: [Opening a pull request](https://opensource.guide/how-to-contribute/#opening-a-pull-request) 

## Code Overview
The following are the most important code files for development of the Forms App.
**Note**: all paths are relative to nextcloud/apps/forms/

-	**lib/Controller/apiController.php**: The main API of the application. The functions defined in this file are called from http requests, and interface with the database

-	**lib/Controller/pageController.php**: Passes objects between screens

-	**lib/Db/**: All the files where database entities are defined and SQL queries are written. Mapper files define functions that retrieve data from the database

-	**src/js/**
	- **Main.js**: where Vue app is created
    - **App.vue**: The root component for the vue app
    - **Router.js**: Defines URLs that can be navigated to from the Vue app

-	**src/js/components/**
    - **formsListItem.vue**: Defines the list items (created surveys) within the forms app home page
    - **quizFormItem.vue**: Questions (for any survey) are defined as a quizFormItem here

-	**src/js/views/**
    -	**Create.vue**: File where survey creation page is handled

    -	**List.vue**: File where list of created surveys is handled (located on the forms app home page)
    -	**Results.vue**: File where page that displays survey results is handled

    -	**appinfo/routes.php**: Defines server endpoints that can be accessed by the client

- **/js/vote.js**: File that contains the logic for the response page and responding to a form

- **/css/vote.scss**: File that contains CSS formatting for the response page

- **/templates/vote.tmpl.php**: File that contains the form template that is dynamically populated by the database

