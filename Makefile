DOCKER_COMP = docker compose --env-file /dev/null
FRANKENPHP_CONT = $(DOCKER_COMP) exec php
E2E_CONT = $(DOCKER_COMP) exec php-e2e
DB_CONT = $(DOCKER_COMP) exec database

.PHONY: sh db restart test test-e2e qa qa-check ci

sh: ## Connect to the FrankenPHP container as root
	@$(FRANKENPHP_CONT) /bin/bash

db: ## Connect to the PostgreSQL container as root
	@$(DB_CONT) /bin/bash

restart: ## Restart the app after a deleted class or a vendor change stops the workers serving
	@$(DOCKER_COMP) restart php php-e2e

test: ## Unit and functional tests
	@$(FRANKENPHP_CONT) composer test

test-e2e: ## Panther, then Playwright journeys in the php-e2e container against app_e2e; never run in parallel
	@$(E2E_CONT) composer test:e2e

qa: ## Rector, both fixers, the linters, the dependency analyser, PHPStan and the tests; modifies files in place
	@$(FRANKENPHP_CONT) composer qa

qa-check: ## The same gates, writing nothing; safe to run while editing
	@$(FRANKENPHP_CONT) composer qa:check

ci: ## The GitHub Actions workflow through act, every job on a clean snapshot; JOB=panther runs one
	@.github/act/run.sh $(JOB)
