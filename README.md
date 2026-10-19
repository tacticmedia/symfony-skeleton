# Tactic Media Skeleton

A Symfony 8.1 starter kit on PHP 8.5, FrankenPHP and PostgreSQL.

## Features

1. **Docker development stack.** FrankenPHP/ with HTTPS on https://localhost, PostgreSQL, Mailpit
   at https://localhost:8025, a Tailwind watcher, a Messenger worker and a cron runner. See
   [docs/tls.md](docs/tls.md).
2. **Passwordless sign-in.** Single-use magic links with rate limits, return targets, account
   suspension and closure, and admin impersonation. In dev, `@tacticmedia.com.au` addresses skip
   the inbox.
3. **Access check on every route.** `app:security:check-access` fails when a controller has no
   `#[IsGranted]`.
4. **Doctrine with UUID v7 and an audit trail.** Entities use `UuidEntityTrait`, migrations hold
   the schema, and `#[Auditable]` entities get an audit table.
5. **Real-time updates.** Mercure runs inside Caddy. `MercureAuthorizer` grants subscriber cookies
   per topic, and `/health-check/functional` tests the hub and email delivery.
6. **Front end without a build step.** AssetMapper, Stimulus, Turbo, Tailwind v4, Twig components
   and UX Icons.
7. **Translation conventions.** ICU XLIFF catalogues, key rules enforced by tests, translation keys
   in the test environment, and a dev toggle that shows keys. See
   [docs/translations.md](docs/translations.md).
8. **Two end-to-end suites.** The same journeys run on Panther and on Playwright against their own
   database. See [docs/e2e.md](docs/e2e.md) and [docs/test-ids.md](docs/test-ids.md).
9. **Visual review.** Every journey captures each screen at five viewports in light and dark mode
   with tacticmedia/qa-bundle. Review them at https://localhost/_dev/screenshots. See
   [docs/qa.md](docs/qa.md).
10. **Quality gates and local CI.** Rector, PHP CS Fixer, Twig CS Fixer, PHPStan level 8, a
    dependency analyser and linters in `make qa-check`. `make ci` runs the GitHub workflow through
    act. See [docs/ci.md](docs/ci.md).

## New project

Create the project from the skeleton repository:

```bash
composer create-project tacticmedia/symfony-skeleton my-app dev-main \
    --repository='{"type": "vcs", "url": "https://github.com/tacticmedia/symfony-skeleton"}' \
    --no-install --remove-vcs
cd my-app
git init
```

`--no-install` leaves `vendor/` to the `php` container, which installs it on its first start, so the
host needs only Composer. When the skeleton has a tagged release on Packagist, the command is
`composer create-project tacticmedia/symfony-skeleton my-app`.

Change the values that belong to the skeleton:

| File | Change |
| --- | --- |
| `composer.json` | `name` and `description`. |
| `.env.dev` | `APP_SECRET`, to the output of `openssl rand -hex 16`. |
| `.env` | `MAILER_FROM`. |
| `src/Security/LoginLinkSender.php` | `DEV_SHORTCUT_DOMAIN`, the domain whose addresses skip the inbox in dev. |
| `tests/Story/AppStory.php` | `ADMIN_EMAIL`, an address in that domain. The journeys sign in with it. |
| `compose.override.yaml` | The `app-php-` image name prefix. Every project on the machine shares an image name. |
| `frankenphp/crontab` | `CRON_TZ`. |
| `README.md` | The description of the new project. |

Build and start the stack, then trust the local certificate authority once ([docs/tls.md](docs/tls.md)):

```bash
docker compose --env-file /dev/null up --wait --build
```

Open https://localhost. `make qa-check` and `make test-e2e` must pass before the first commit. Only one
stack at a time can publish ports 80, 443, 8025 and 25432.

## Start

```bash
docker compose --env-file /dev/null up --wait
```

| Command | Does |
| --- | --- |
| `make test` | Unit and functional tests |
| `make test-e2e` | Panther, then Playwright journeys |
| `make qa` | Every gate; rewrites files |
| `make qa-check` | Every gate; writes nothing |
| `make ci` | The GitHub workflow through act |
