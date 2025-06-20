PHP_CLI="docker-compose exec php"
SERVER_CLI="docker-compose exec server"

env-copy:
	cp -i .env.example .env

build:
	docker-compose build

up:
	docker-compose up

up-php:
	docker start sp-php

stop:
	docker-compose stop

down:
	docker-compose down

bash-php:
	"$(PHP_CLI)" bash

bash-server:
	"$(SERVER_CLI)" bash

server-linter:
	"$(SERVER_CLI)" golangci-lint run

composer:
	"$(PHP_CLI)" composer ${c}

phpstan:
	"$(PHP_CLI)" ./vendor/bin/phpstan analyse \
		--memory-limit=1G

check:
	make phpstan
	make test
	make command-workers-benchmark

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

command-load-server-bin:
	"$(PHP_CLI)" php tests/commands/load-server-bin.php

command-server-sleep:
	"$(PHP_CLI)" php tests/commands/server-sleep.php

command-server-wake-up:
	"$(PHP_CLI)" php tests/commands/server-wake-up.php

command-server-stats:
	"$(PHP_CLI)" php tests/commands/server-stats.php

command-server-stop:
	"$(PHP_CLI)" php tests/commands/server-stop.php

command-server-workers-reload:
	"$(PHP_CLI)" php tests/commands/server-workers-reload.php

command-workers-benchmark:
	"$(PHP_CLI)" php tests/commands/workers-benchmark.php
