#!/bin/sh
# Development only, enforced twice: APP_ENV must say so, and the production image is
# built from a clean base that never copies this file. Either alone would do; recording
# migrations as applied without running them must never reach a production database.
#
# A fixtures load builds the schema from the entity mapping and records no migrations.
# The initial migration then runs against tables that already exist, fails, and the
# container restarts continuously - so recreating the service against a database in
# that state leaves the application unable to start. Record the existing migrations as
# applied rather than re-running them.
#
# dbal:run-sql reports rows rather than exit codes, so the condition is signalled by a
# raised exception. Dollar-quoted literals keep the statement free of shell quoting.
# Sequential IFs: planning a FROM over a missing doctrine_migration_versions fails, so
# an empty database must reach RETURN before any statement that names the table.
set -e

if [ "${APP_ENV:-prod}" = 'prod' ]; then
	echo 'Refusing to adopt an unversioned schema: APP_ENV is prod.' >&2
	exit 0
fi

if php bin/console dbal:run-sql -q 'DO $do$ BEGIN IF to_regclass($t$public.user_account$t$) IS NULL THEN RETURN; END IF; IF to_regclass($t$public.doctrine_migration_versions$t$) IS NULL THEN RAISE EXCEPTION $t$unversioned$t$; END IF; IF NOT EXISTS (SELECT 1 FROM doctrine_migration_versions) THEN RAISE EXCEPTION $t$unversioned$t$; END IF; END $do$;' >/dev/null 2>&1; then
	exit 0
fi

echo 'The schema exists without migration bookkeeping; recording the migrations as applied instead of re-running them.'
# version --add does not create the metadata table itself.
php bin/console doctrine:migrations:sync-metadata-storage --no-interaction
php bin/console doctrine:migrations:version --add --all --no-interaction
