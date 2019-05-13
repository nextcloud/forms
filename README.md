# Forms

Forms allows the creation of shareable forms, with multiple question types and privacy settings. 
**Note**: This app is tested with Apache2 webserver, MySQL database, and apt-get package manager. To use alternatives, replace the relevant commands with those of your technology.

## Installation
### Download the Forms Codebase
(Placeholder, not sure if we decided we wanted this in this version since the app is not yet on git)
```sh
$ cd /var/www/html/nextcloud/apps
$ svn co https://vis.cs.umd.edu/svn/projects/forms/forms
```

### Install NPM
```sh
$ apt-get npm
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
- Open NextCloud in your browser of choice (Chrome)
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
    -	**Vote.vue**: File where voting page (responding to the survey) is handled
    -	**List.vue**: File where list of created surveys is handled (located on the forms app home page)
    -	**Results.vue**: File where page that displays survey results is handled

    -	**appinfo/routes.php**: Defines server endpoints that can be accessed by the client



