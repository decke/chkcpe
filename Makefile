all: build

clean:
	rm -rf vendor
	make -C css clean

vendor:
	pkg install -q parallel sassc sqlite3
	composer install

build: vendor
	make -C css

test: vendor
	phpstan --memory-limit=256M analyse -l 8 index.php bin src

lint:
	php-cs-fixer fix

scan:
	rm -rf data/nvdcpe-2.0-chunks/ && fetch -o - https://nvd.nist.gov/feeds/json/cpe/2.0/nvdcpe-2.0.tar.gz | tar -C data/ -zxf -
	rm -rf logs && mkdir -p logs
	rm -f data/chkcpe.db && sqlite3 data/chkcpe.db < data/schema.sql
	./bin/chkcpe

run-server:
	php -S localhost:9000 index.php

.PHONY: all clean build test lint run-server
