#------------------------------------------------------------------------------
# PHPUnit
#------------------------------------------------------------------------------

# PHPUNIT_VERSION
#
# The latest matching version will be downloaded
# Can be
# 	- MAJOR
# 	- MAJOR.MINOR
# 	- MAJOR.MINOR.PATCH
PHPUNIT_VERSION=11

#------------------------------------------------------------------------------

# PHPUNIT_IMAGE_VERSION : this is overwritten in github action environment (cache purpose : avoid building everytime the image)
PHPUNIT_IMAGE_VERSION?=8.4

PHPUNIT_IMAGE_NAME=rabbit-mq-admin-toolkit-phpunit
CONTAINER_SOURCE_PATH=/var/www/app

#------------------------------------------------------------------------------

# XDEBUG_MODE
# 	By default xdebug is used as a debugger.
# 	For coverage report it is used in coverage mode
# 	(and value is overwritten to the right value)
XDEBUG_MODE=debug

# The following file exists when xdebug is enabled with phpunit
-include var/.xdebug

#------------------------------------------------------------------------------

phpunit-runner = $(DOCKER_RUN) -ti --rm --name phpunit \
	                 -v ${HOST_SOURCE_PATH}:${CONTAINER_SOURCE_PATH} \
	                 -w ${CONTAINER_SOURCE_PATH} \
	                 -u ${USER_ID}:${GROUP_ID} \
	                 $1 \
	                 -e HOST_IP=${XDEBUG_REMOTE_IP} \
	                 -e XDEBUG_MODE="$(XDEBUG_MODE)" \
	                 -e XDEBUG_TRIGGER="$(XDEBUG_TRIGGER)" \
	                 -e PHP_IDE_CONFIG="serverName=$(XDEBUG_PHPSTORM_SERVERNAME)" \
	                 ${PHPUNIT_IMAGE_NAME}:$(PHPUNIT_IMAGE_VERSION) \
	                 ./phpunit.phar $2

#------------------------------------------------------------------------------

# Note : network is disabled for unit tests to ensure we do not make external calls.
# ex: XSD validation make external call without some tricks...
phpunit = $(call phpunit-runner,--network none,$1)

phpunit-integration = $(call phpunit-runner, --network=memo_default, $1)

# Spread cli arguments
ifneq (,$(filter $(firstword $(MAKECMDGOALS)),phpunit phpunit-module -phpunit-coverage-module -phpunit-html-coverage-module phpunit-execute-coverage-module phpunit-execute-coverage-fixtures))
    PHPUNIT_CLI_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
    $(eval $(PHPUNIT_CLI_ARGS):;@:)
endif

#------------------------------------------------------------------------------
# Tests running
#------------------------------------------------------------------------------

.PHONY: phpunit
phpunit: -tests-unit-environment-setup
	@$(call phpunit, --configuration phpunit.xml $(PHPUNIT_CLI_ARGS))

.PHONY: phpunit-integration
phpunit-integration: -tests-integration-environment-setup
	@$(call phpunit-integration, --configuration phpunit.integration.xml $(PHPUNIT_CLI_ARGS))

.PHONY: phpunit-module
phpunit-module: -tests-unit-environment-setup
	$(call phpunit,  --configuration phpunit.xml --testsuite $(PHPUNIT_CLI_ARGS))

.PHONY: phpunit-framework
phpunit-framework: -tests-unit-environment-setup
	@$(call phpunit, --configuration phpunit.framework.xml)

.PHONY: phpunit-fixtures
phpunit-fixtures: -tests-unit-environment-setup
	@$(call phpunit,  --configuration phpunit.fixtures.xml)

.PHONY: phpunit-dox
phpunit-dox: -tests-unit-environment-setup
	@$(call phpunit, --configuration phpunit.xml --testdox)

#------------------------------------------------------------------------------
# Coverage execute and report
#------------------------------------------------------------------------------

.PHONY: -phpunit-coverage
-phpunit-coverage: -phpunit-html-coverage

.PHONY: -phpunit-coverage-module
-phpunit-coverage-module: -phpunit-html-coverage-module

.PHONY: -phpunit-coverage-module-fixtures
-phpunit-coverage-module-fixtures: -phpunit-html-coverage-fixtures

.PHONY: -phpunit-html-coverage
-phpunit-html-coverage: phpunit-execute-coverage phpunit-report-unit

.PHONY: -phpunit-html-coverage-module
-phpunit-html-coverage-module: -coverage-unit-environment-setup phpunit-execute-coverage-module phpunit-report-unit

.PHONY: -phpunit-html-coverage-framework
-phpunit-html-coverage-framework: -coverage-framework-environment-setup phpunit-execute-coverage-framework phpunit-report-framework

.PHONY: -phpunit-html-coverage-fixtures
-phpunit-html-coverage-fixtures: -coverage-fixtures-environment-setup phpunit-execute-coverage-fixtures phpunit-report-fixtures

#------------------------------------------------------------------------------
# Coverage report builders
#------------------------------------------------------------------------------

# Temporary ;-) workaround for dealing with phpunit10/phpcov mess
.PHONY: -fix-cov-files-unit
-fix-cov-files-unit:
	@perl -i -pe 's#:(\d+):"(\x0?)PHPUnitPHAR\\(SebastianBergmann\\)#":".($$1-12).":\"$$2$$3"#ge;' var/coverage/cov/unit/*.cov

.PHONY: -fix-cov-files-fixtures
-fix-cov-files-fixtures:
	@perl -i -pe 's#:(\d+):"(\x0?)PHPUnitPHAR\\(SebastianBergmann\\)#":".($$1-12).":\"$$2$$3"#ge;' var/coverage/cov/fixtures/*.cov

.PHONY: -fix-cov-files-framework
-fix-cov-files-framework:
	@perl -i -pe 's#:(\d+):"(\x0?)PHPUnitPHAR\\(SebastianBergmann\\)#":".($$1-12).":\"$$2$$3"#ge;' var/coverage/cov/framework/*.cov

# 24575 = E_ALL (32767) - E_DEPRECATED (8192)
.PHONY: phpunit-report-unit
phpunit-report-unit: phpcov -fix-cov-files-unit
	$(call php-container,php -d memory_limit=1G -d error_reporting=24575 ./phpcov merge var/coverage/cov/unit --html var/coverage/reports/unit)

.PHONY: phpunit-report-framework
phpunit-report-framework: phpcov -fix-cov-files-framework
	$(call php-container,php -d memory_limit=1G -d error_reporting=24575 ./phpcov merge var/coverage/cov/framework --html var/coverage/reports/framework)

.PHONY: phpunit-report-fixtures
phpunit-report-fixtures: phpcov -fix-cov-files-fixtures
	$(call php-container,php -d memory_limit=1G -d error_reporting=24575 ./phpcov merge var/coverage/cov/fixtures --html var/coverage/reports/fixtures)

#------------------------------------------------------------------------------
# Unit tests executors
#------------------------------------------------------------------------------

.PHONY: phpunit-execute-coverage
phpunit-execute-coverage: -coverage-unit-environment-setup
	@set -e ; \
	MODULE_NAMES=$$(ls src/application/*/ -d | awk -F '/' '{print $$3}'); \
	for MODULE_NAME in $$MODULE_NAMES; do \
		echo ; \
		echo "Generate unit coverage for \033[0;36mmodule $${MODULE_NAME}\033[0m"; \
		echo ; \
		make --no-print-directory phpunit-execute-coverage-module $${MODULE_NAME}; \
		echo ; \
		echo "Generate unit coverage for \033[0;36mmodule fixtures $${MODULE_NAME}\033[0m"; \
		echo ; \
	done

.PHONY: phpunit-execute-coverage-module
phpunit-execute-coverage-module: -enable-xdebub-coverage-mode
	@$(call phpunit, --configuration phpunit.xml --testsuite $(PHPUNIT_CLI_ARGS) --coverage-php=var/coverage/cov/unit/$(PHPUNIT_CLI_ARGS).cov)

.PHONY: phpunit-execute-coverage-framework
phpunit-execute-coverage-framework: -enable-xdebub-coverage-mode
	$(call phpunit, --configuration phpunit.framework.xml --coverage-php=var/coverage/cov/framework/framework.cov)

.PHONY: phpunit-execute-coverage-fixtures
phpunit-execute-coverage-fixtures: -enable-xdebub-coverage-mode
	@$(call phpunit, --configuration phpunit.fixtures.xml --coverage-php=var/coverage/cov/fixtures/fixtures.cov)

#------------------------------------------------------------------------------
# Environment setup
#------------------------------------------------------------------------------

.PHONY: -tests-unit-environment-setup
-tests-unit-environment-setup: phpunit.phar -create-phpunit-image var/system/.phpunit-cache

.PHONY: -tests-integration-environment-setup
-tests-integration-environment-setup: phpunit.phar -create-phpunit-image var/system/.phpunit-cache

.PHONY: -coverage-unit-environment-setup
-coverage-unit-environment-setup: -tests-unit-environment-setup var/coverage/reports/index.html var/coverage/cov/unit

.PHONY: -coverage-framework-environment-setup
-coverage-framework-environment-setup: -tests-unit-environment-setup var/coverage/reports/index.html var/coverage/cov/framework

.PHONY: -coverage-fixtures-environment-setup
-coverage-fixtures-environment-setup: -tests-unit-environment-setup var/coverage/reports/index.html var/coverage/cov/fixtures

.PHONY: -enable-xdebub-coverage-mode
-enable-xdebub-coverage-mode:
	@$(eval XDEBUG_MODE=coverage) # Must be evaluated to be known to the Make runtime environment (even if use inside the calling targets)

#------------------------------------------------------------------------------
# Docker image build
#------------------------------------------------------------------------------

.PHONY: -create-phpunit-image
-create-phpunit-image:
	@if [ -z "$$(docker images -q $(PHPUNIT_IMAGE_NAME):$(PHPUNIT_IMAGE_VERSION) 2> /dev/null)" ]; then \
		 make --no-print-directory -- -build-phpunit-image; \
	fi

-build-phpunit-image: docker/images/phpunit/Dockerfile
	docker build -q -t $(PHPUNIT_IMAGE_NAME):$(PHPUNIT_IMAGE_VERSION) docker/images/phpunit/

#------------------------------------------------------------------------------
# XDebug support
#------------------------------------------------------------------------------

.PHONY: phpunit-disable-xdebug
phpunit-disable-xdebug: ### Disable XDebug when running unit tests
	@rm -f var/.xdebug

.PHONY: phpunit-enable-xdebug
phpunit-enable-xdebug: ### Enable XDebug when running unit tests
	@echo '# This file is generated by phpunit.mk, do not edit manually' > var/.xdebug
	@echo 'export XDEBUG_TRIGGER=NaonedIsTheKey' >> var/.xdebug

#------------------------------------------------------------------------------
# phpunit.phar related targets
#------------------------------------------------------------------------------

# see https://phar.phpunit.de/
# keep it named .phar to make PHPStorm understand it...
phpunit.phar:
	@if ! command -v jq > /dev/null 2>&1; then echo "jq command not found (a JSON parser from cli). Please install it : \n\tsudo apt-get update && sudo apt-get install jq"; exit 1; fi
	@echo "Searching version ${PHPUNIT_VERSION} of phpunit.phar"
	@$(eval SELECTED_VERSION := $(shell curl -s -L -H "X-GitHub-Api-Version: 2022-11-28" https://api.github.com/repos/sebastianbergmann/phpunit/git/matching-refs/tags/${PHPUNIT_VERSION} | jq -r .[].ref | sed 's#refs/tags/\(.*\)#\1#g' | tail -1))
	@if [ -z "${SELECTED_VERSION}" ]; then echo "No version matching ${PHPUNIT_VERSION} found"; exit 3 ; fi
	@echo "Downloading found version ${SELECTED_VERSION} of phpunit.phar"
	@wget -O phpunit.phar -q https://phar.phpunit.de/phpunit-${SELECTED_VERSION}.phar
	@chmod 0755 phpunit.phar

.PHONY: download-phpunit.phar
download-phpunit.phar: clean-phpunit.phar phpunit.phar ## Download the phpunit.phar (overwrites the existing one)

#------------------------------------------------------------------------------
# Cleaners
#------------------------------------------------------------------------------

.PHONY: clean-phpunit.phar
clean-phpunit.phar:
	@-rm -f phpunit.phar

.PHONY: clean-phpunit
clean-phpunit: clean-phpunit.phar
	-rm docker/images/phpunit/Dockerfile
	-rm -rf var/coverage/
	-rm -rf var/system/.phpunit-cache
	-docker rmi ${PHPUNIT_IMAGE_NAME}:${PHPUNIT_IMAGE_VERSION}

#------------------------------------------------------------------------------

var/system/.phpunit-cache:
	@mkdir -p var/system/.phpunit-cache

#------------------------------------------------------------------------------

.PHONY: phpunit phpunit-module phpunit-dox phpunit-coverage phpunit-coverage-module phpunit-html-coverage phpunit-html-coverage-module phpunit-report phpunit-php-coverage
