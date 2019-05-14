# Forms

Forms allows the creation of shareable forms, with multiple question types and privacy settings.


**Note**: This app is tested with Apache2 webserver, MySQL database, and apt-get package manager. To use alternatives, replace the relevant commands with those of your technology. This document assumes that a working
NextCloud development environment has been installed. See https://docs.nextcloud.com/server/stable/developer_manual/general/devenv.html for help with this.

## Installation
### Download the Forms Codebase

```sh
$ cd /var/www/html/nextcloud/apps
$ git clone https://github.com/affan98/forms.git
```
**Note**: This will be moved to https://github.com/nextcloud/forms.git once the app has been accepted to the NextCloud app store

### Install Prerequisites and Dependencies
#### Install NPM
```sh
$ apt-get npm
```

#### Install Yarn
```sh
$ curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
$ echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
$ apt update
$ apt install yarn
```
#### Update NodeJS
```sh
$ npm install -g n
$ n stable
```
### Build the App
```sh
$ cd /var/www/html/nextcloud/apps/forms
$ make all
```

### Start Webserver / Database
```sh
$ service apache2 start
$ service mysql start
```

### Enable the App
- Open NextCloud in your browser of choice
- Click on the user icon in the top right of the screen, and select Apps from the drop down menu
-	Find the Forms app in the list and click enable
-	The app will now be fully functional! The forms icon will appear on the top toolbar of NextCloud after it has been enabled


### To Rebuild
```
$ cd /var/www/html/nextcloud/apps/forms
$ npm run build
$ service Apache2 restart
$ service mysql restart
```
Refresh the page in your browser to reflect the changes.

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

