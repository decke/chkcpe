all: build

clean:
	rm -rf vendor

build: vendor

vendor:
	composer install

test: vendor
	php-cs-fixer list-files
	phpstan analyse -l max chkcpe

fix:
	php-cs-fixer fix

.PHONY: all clean build test fix
