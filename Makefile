# Makefile for building the project

app_name=forms
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_package_name=$(appstore_dir)/$(app_name)
source_dir=$(build_dir)/source

all: clean install-composer-deps install-npm-deps-dev build-js-production appstore

clean:
	rm -rf $(build_dir)
	rm -rf node_modules
	rm -rf vendor
	rm -rf js

install-deps: install-composer-deps-dev install-npm-deps-dev

install-composer-deps:
	composer install --no-dev -o

install-composer-deps-dev:
	composer install -o

install-npm-deps:
	npm install --production

install-npm-deps-dev:
	npm install

optimize-js: install-npm-deps-dev
	npm run build

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

dev-setup: install-composer-deps-dev install-npm-deps-dev build-js

appstore:
	rm -rf $(build_dir)
	mkdir -p $(build_dir)
	tar cvzf $(appstore_package_name).tar.gz \
	--exclude-vcs \
	$(project_dir)/appinfo \
	$(project_dir)/COPYING \
	$(project_dir)/css \
	$(project_dir)/img \
	$(project_dir)/js \
	$(project_dir)/l10n \
	$(project_dir)/lib \
	$(project_dir)/templates \
	$(project_dir)/vendor \
	$(project_dir)/CHANGELOG.md
