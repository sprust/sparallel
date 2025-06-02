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

phpstan:
	"$(PHP_CLI)" ./vendor/bin/phpstan analyse \
		--memory-limit=1G

benchmark:
	"$(PHP_CLI)" php tests/benchmark.php

check:
	make phpstan
	make test
	make benchmark

run-server:
	go run ./cmd/server/main.go ${c}

htop-workers:
	htop -t --filter=server-process-handler.php

serv-stats:
	"$(SERVER_CLI)" go run ./cmd/server/main.go stats
