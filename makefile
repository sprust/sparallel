PHP_CLI="docker-compose exec php"
SERVER_CLI="docker-compose exec server"

env-copy:
	cp -i .env.example .env

build:
	docker-compose build

up:
	docker-compose up

down:
	docker-compose down

bash-php:
	"$(PHP_CLI)" bash

bash-server:
	"$(SERVER_CLI)" bash

composer:
	"$(PHP_CLI)" composer ${c}

phpstan:
	"$(PHP_CLI)" ./vendor/bin/phpstan analyse \
		--memory-limit=1G

check:
	make phpstan
	make test
	make test-benchmark

htop-workers:
	htop -t --filter=server-process-handler.php

zombies:
	top -b n1 | grep 'Z'

test:
	"$(PHP_CLI)" ./vendor/bin/phpunit \
		-d memory_limit=512M \
		--colors=auto \
		--testdox \
  		--display-incomplete \
  		--display-skipped \
  		--display-deprecations \
  		--display-phpunit-deprecations \
  		--display-errors \
  		--display-notices \
  		--display-warnings \
		tests ${c}

test-benchmark:
	"$(PHP_CLI)" php tests/test-workers-benchmark.php

test-stats-get:
	"$(PHP_CLI)" php tests/test-stats-get.php

test-workers-reload:
	"$(PHP_CLI)" php tests/test-workers-reload.php

test-workers-stop:
	"$(PHP_CLI)" php tests/test-workers-stop.php
