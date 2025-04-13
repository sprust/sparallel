build:
	docker-compose build

down:
	docker-compose down

bash:
	docker-compose run --rm php bash

composer:
	docker-compose run --rm -e XDEBUG_MODE=off php composer ${c}
