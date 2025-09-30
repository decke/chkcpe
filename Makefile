all: build

clean:
	rm -rf vendor
	make -C css clean

vendor:
	composer install

build: vendor
	make -C css

test: vendor
	phpstan --memory-limit=256M analyse -l 8 index.php bin src

lint:
	php-cs-fixer fix

run-server:
	php -S localhost:9000 index.php

.PHONY: all clean build test lint run-server
