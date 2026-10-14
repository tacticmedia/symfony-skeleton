#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if [ -x /usr/local/bin/trust-local-ca ]; then
		trust-local-ca
	fi

	# Outside prod the probe also creates a missing database; the prod role may
	# lack CREATEDB.
	db_probe() {
		if [ "${APP_ENV:-prod}" != 'prod' ]; then
			php bin/console doctrine:database:create --if-not-exists --no-interaction 2>&1
		else
			php bin/console dbal:run-sql -q "SELECT 1" 2>&1
		fi
	}

	if grep -q ^DATABASE_HOST= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(db_probe); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
			if [ "${APP_ENV:-prod}" != 'prod' ] && [ -x /usr/local/bin/adopt-unversioned-schema ]; then
				adopt-unversioned-schema
			fi

			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi

		# The Messenger queue table lives outside the migrations (doctrine.yaml schema_filter).
		php bin/console messenger:setup-transports --no-interaction
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
