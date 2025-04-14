build:
	docker-compose build

down:
	docker-compose down

bash:
	docker-compose run -it --rm --user $$(id -u):$$(id -g) php bash

composer:
	docker-compose run -it --rm --user $$(id -u):$$(id -g) -e XDEBUG_MODE=off php composer ${c}

test:
	docker-compose run -it --rm --user $$(id -u):$$(id -g) php ./vendor/bin/phpunit \
		--colors=auto \
		--testdox \
		--display-phpunit-deprecations \
		--display-errors \
		--display-notices \
		--display-warnings \
		tests ${c}

phpstan:
	docker-compose run -it --rm --user $$(id -u):$$(id -g) php ./vendor/bin/phpstan analyse \
		--memory-limit=1G
