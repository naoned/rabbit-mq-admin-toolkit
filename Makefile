.DEFAULT_GOAL := help

DIR := ${CURDIR}
QA_IMAGE := jakzal/phpqa:latest

HOST_SOURCE_PATH=$(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))

USER_ID=$(shell id -u)
GROUP_ID=$(shell id -g)
HOST_IP=$(shell ip addr show docker0 | grep -w inet | sed 's%.*inet \([^/]*\).*%\1%')

export USER_ID
export GROUP_ID
export HOST_IP
export PHP_VERSION=8.4

include makefiles/executables.mk

-include .env

include makefiles/composer.mk
include makefiles/phpunit.mk
include makefiles/whalephant.mk

.env:
	cp .env.example .env

var:
	mkdir -m a+w var

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m"
	@echo "  make [command]"
	@echo ""
	@echo "\033[33mAvailable commands:\033[0m"
	@echo "$$(grep -hE '^\S+:.*##' $(MAKEFILE_LIST) | sort | sed -e 's/:.*##\s*/:/' -e 's/^\(.\+\):\(.*\)/  \\033[32m\1\\033[m:\2/' | column -c2 -t -s :)"

.PHONY: cs-fix
cs-fix: ## CS Fix with php-cs-fixer
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix -vvv

.PHONY: cs-lint
cs-lint: ## CS Lint with php-cs-fixer
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix --dry-run -vvv --diff

.PHONY: phpstan
phpstan: ## Run PHPStan
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) phpstan analyze

.PHONY: static
static: cs-lint phpstan
