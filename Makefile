# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2016

# Dependencies:
# * make
# * which
# * npm
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
app_name=forms

project_dir=$(CURDIR)
build_dir=$(CURDIR)/build
build_tools_dir=$(build_dir)/tools
build_source_dir=$(build_dir)/source
appstore_build_dir=$(build_dir)/artifacts/appstore
appstore_package_name=$(appstore_build_dir)/$(app_name)
nc_cert_dir=$(HOME)/.nextcloud/certificates
composer=$(shell which composer 2> /dev/null)

all: dev-setup lint build-js-production test

# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (,$(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_dir)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_dir)
	php $(build_tools_dir)/composer.phar install --prefer-dist
	php $(build_tools_dir)/composer.phar update --prefer-dist
else
	composer install --prefer-dist
	composer update --prefer-dist
endif

# Dev env management
dev-setup: clean clean-dev composer npm-init

npm-init:
	npm install

npm-update:
	npm update

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

# Linting
lint:
	npm run lint

lint-fix:
	npm run lint:fix

# Style linting
stylelint:
	npm run stylelint

stylelint-fix:
	npm run stylelint:fix

# Cleaning
.PHONY: clean
clean:
	rm -rf $(build_dir)
	rm -rf js/chunks
	rm -f js/forms.js
	rm -f js/forms.js.map

clean-dev:
	rm -rf node_modules
	rm -rf vendor


# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore: clean lint build-js-production
	mkdir -p $(build_source_dir)
	mkdir -p $(appstore_build_dir)
	rsync -a \
	--exclude="ISSUE_TEMPLATE.md" \
	--exclude="*.log" \
	--exclude=".*" \
	--exclude="_*" \
	--exclude="build" \
	--exclude="bower.json" \
	--exclude="composer.*" \
	--exclude="js/.*" \
	--exclude="js/*.log" \
	--exclude="js/bower.json" \
	--exclude="js/karma.*" \
	--exclude="js/node_modules" \
	--exclude="js/package.json" \
	--exclude="js/protractor.*" \
	--exclude="js/test" \
	--exclude="js/tests" \
	--exclude="karma.*" \
	--exclude="l10n/no-php" \
	--exclude="Makefile" \
	--exclude="node_modules" \
	--exclude="package*" \
	--exclude="phpunit*xml" \
	--exclude="protractor.*" \
	--exclude="screenshots" \
	--exclude="src" \
	--exclude="tests" \
	--exclude="vendor" \
	--exclude="webpack.*" \
	$(project_dir)/ $(build_source_dir)/$(app_name)
	tar -czf $(appstore_package_name).tar.gz \
	   --directory="$(build_source_dir)" $(app_name)
	@if [ -f $(nc_cert_dir)/$(app_name).key ]; then \
		echo "Signing package..."; \
		openssl dgst -sha512 -sign $(nc_cert_dir)/$(app_name).key $(appstore_build_dir)/$(app_name).tar.gz | openssl base64; \
	fi

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml
