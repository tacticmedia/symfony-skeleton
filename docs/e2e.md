# End-to-end journeys

Real-browser tests that click through whole workflows the way a person does. Two suites run the same
journeys on two drivers:

| Suite | Directory | Driver | Groups |
| --- | --- | --- | --- |
| Panther | `tests/E2e/Panther/` | Symfony Panther, Chromium through chromedriver | `e2e`, `panther` |
| Playwright | `tests/E2e/Playwright/` | playwright-php, Chromium through its Node.js bridge | `e2e`, `playwright` |

Both run inside the `php-e2e` container against its own `app_e2e` database, so the dev database is
never touched, and both screenshot every screen they settle on.

The suites catch regressions only a real browser can surface: Turbo Drive and frame targeting,
Stimulus wiring, Mercure streams. They also produce a reviewable picture of the whole interface at
five viewports in light and dark ([Screenshot review](qa.md)).

## Running

```bash
make test-e2e                                                                        # Panther, then Playwright
docker compose exec php-e2e composer test:panther                                    # Panther only
docker compose exec php-e2e composer test:playwright                                 # Playwright only
docker compose exec php-e2e vendor/bin/phpunit --group panther --filter AuthJourney  # one journey
docker compose exec php composer test                                                # everything else
```

`composer test` is `phpunit --exclude-group e2e`, so the halves never overlap. `composer test:e2e`
runs `test:panther`, then `test:playwright`. `test:playwright` first runs
`vendor/bin/playwright-install chromium`, which installs the library's npm packages into `vendor/`,
then `phpunit --group playwright --fail-on-skipped`: playwright-php skips every test when it finds no
Node.js, and the flag turns that into a failure.

Run the suites in `php-e2e` only. A reset attempted from any other container fails fast
([Data reset](#data-reset)).

The journeys drive `E2E_BASE_URI`, `https://localhost` in `.env.test`. Inside `php-e2e` that is the
container's own Caddy, which also runs the Mercure hub. The container trusts the shared local
authority, in the NSS database Chromium reads too ([TLS Certificates](tls.md)).

`php-e2e` runs `APP_ENV=dev`, and `.env.dev` sets `APP_DEBUG=1`, because the developer toggles are
part of a journey. Each capture hides the debug toolbar with CSS, and the repeated-id check skips the
toolbar's markup ([JourneyScripts](../tests/E2e/JourneyScripts.php)).

**Never run the suites in parallel processes.** All journeys share one `app_e2e` database, and every
Panther process uses chromedriver port 9515.

## Layout

| File | Role |
| --- | --- |
| `tests/E2e/Panther/JourneyTestCase.php` | Panther base class: client construction, sign-in, the interaction helpers. Uses `TacticMedia\QaBundle\Test\JourneyScreenshots`, root `var/screenshots/panther`. |
| `tests/E2e/Playwright/JourneyTestCase.php` | Playwright base class, extends `Playwright\Testing\PlaywrightTestCase`: the same helpers on `$this->page`. Uses `TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots`, root `var/screenshots/playwright`. |
| `tests/E2e/JourneyScripts.php` | Page scripts both drivers evaluate. Each is an expression, so Panther runs it after `return` and playwright-php evaluates it as it is. |
| `tests/E2e/ResetsE2eDatabase.php` | `loadFixture()`, used by both base classes. |
| `tests/E2e/*/*E2eTest.php` | The journeys. |
| `tests/Story/AppStory.php` | The `#[AsFixture]` story `main`: one admin, `AppStory::ADMIN_EMAIL` (`staff@tacticmedia.com.au`). |

The capture traits are vendor code, documented in `vendor/tacticmedia/qa-bundle/docs/host-setup.md`.

## The journeys

Each class exists in both suites with the same test methods and TestDox. Change both in the same
change. Each journey owns a slice of the interface; extend the one whose surface you changed rather
than adding a new file.

| Journey | Fixture | Covers |
| --- | --- | --- |
| `AuthJourneyE2eTest` | `main`, none for the broken links | The signed-out home page, the sign-in form, the server refusing an address without a dotted domain, a link emailed to an outside address, sign-in through the dev shortcut, the signed-in home page, sign-out and the signed-out page; an expired and a tampered link each offering a new one. |
| `DevToolsJourneyE2eTest` | `main` | The translation toggle showing keys and back, and the Turbo Drive toggle off and on, proven by whether a link click keeps the window. |
| `HealthCheckJourneyE2eTest` | `main` | A private Mercure update reaching the open health-check page, and the test email. |
| `ScreenshotReviewE2eTest` | none | `/_dev/screenshots` opening through the `dev` firewall ([Screenshot review](qa.md#coverage)). |

## Anatomy of a journey

```php
#[Group('e2e')]
#[Group('panther')]
final class ExampleJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in user opens the health check')]
    public function testSomebodyDoesTheWholeThing(): void
    {
        self::loadFixture(AppStory::NAME);
        $client = $this->journeyClient();
        $this->signIn($client, AppStory::ADMIN_EMAIL);

        $this->click($client, 'default-health-check');
        $client->waitFor('body[data-test-id="screen-health_check_functional"]');
        $this->captureFullPageScreenshot($client, 'Health Check');
    }
}
```

The Playwright twin:

```php
#[Group('e2e')]
#[Group('playwright')]
final class ExampleJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in user opens the health check')]
    public function testSomebodyDoesTheWholeThing(): void
    {
        self::loadFixture(AppStory::NAME);
        $this->signIn(AppStory::ADMIN_EMAIL);

        $this->click('default-health-check');
        $this->page->waitForSelector('body[data-test-id="screen-health_check_functional"]');
        $this->captureFullPageScreenshot('Health Check');
    }
}
```

A journey carries both groups. Without `e2e`, `composer test` runs it in the `php` container; without
`panther` or `playwright`, `composer test:e2e` never runs it. Split a long journey into private
scene methods; the scenes run in order and share browser state, so a later scene may rely on what an
earlier one created.

### Data reset

`loadFixture($name)` runs `php bin/console app:e2e:reset-database <name> --no-interaction --env=dev`
in a subprocess. The command, `src/Command/E2eDatabaseResetCommand.php`, truncates every
table in the `public` schema except `doctrine_migration_versions`, with `RESTART IDENTITY CASCADE`,
then loads the named `#[AsFixture]` story with `foundry:load-fixtures --append`. Without a name it
only truncates. It refuses any database whose name does not end in `_e2e`, and the subprocess
inherits the container's `DATABASE_NAME`, so it fails everywhere but `php-e2e`. `app_e2e` needs no
setup: the entrypoint creates it when missing and migrates it on every boot.

Never let a journey reset through Foundry's drop-and-recreate. Dropping the database force-kills the
FrankenPHP workers' open PostgreSQL connections, after which the e2e app serves connection errors,
and it wipes the migration bookkeeping the container entrypoint depends on.

### Sign-in

`signIn()` drives the real flow. In dev, an address ending in `@tacticmedia.com.au` skips the inbox:
`App\Security\LoginLinkSender` returns the link instead of mailing it, the request redirects to the
confirmation page, and in dev that page's form carries the `submit-on-connect` Stimulus controller,
which submits it as soon as it connects. A new company address is provisioned with `ROLE_ADMIN` on
its first request. Any other address gets the email, which Mailpit catches, and the journey sees the
link-sent screen.

`requestLink()` first waits for `JourneyScripts::LOGIN_FORM_READY`: the `login` Stimulus controller
fills `#login_form_timezone` on connect, so a value means the module graph, CSRF controller included,
is live. `signIn()` then waits for `JourneyScripts::SIGN_IN_SETTLED`, the path leaving `/login`.

`LOGIN_LINK_MAX_USES=1` makes a sign-in link single-use, and the confirmation submits itself, so
never click `login-confirm` in a journey: a second POST finds the link used.

Panther reuses one browser per process, and its cookies survive between tests. `journeyClient()`
requests `/login` and clears the cookie jar, so every Panther journey starts signed out. Playwright
gives each test a new browser context.

## Screenshots

### Why

The shots exist to be looked at. They catch what an assertion never will: content overlapping,
elements clipped at a breakpoint, a colour pair that is unreadable in dark mode, a panel that renders
blank. A journey that passes with a visibly broken page is a journey that missed the point.

### The rule

Call `captureFullPageScreenshot()` immediately after the wait that proves a navigation, frame swap
or stream update finished rendering, and before the next interaction. Anchor on the wait that is
already there; do not add a wait for the sake of a shot.

Capture each distinct screen **and each distinct state of it**: an error, a sent confirmation, a
delivered update. Never capture the same rendering twice. Those states are where broken interface
usually hides.

### Deduplication

Labels are deduplicated per test. A helper called several times captures on its first pass only.
Reuse a label deliberately to suppress a repeat you know about, and give a genuinely different state
its own label.

### Output

Each call writes five viewports in light and dark, ten PNGs per screen:

```
var/screenshots/<suite>/<mode>/<WxH>/<orientation>/<Class>-<method>-<NNN>_<label>.png
```

`<suite>` is `panther` or `playwright`. `NNN` is the capture's position within its test, so one
directory listing reads as the journey in order. Viewports: 390x844 and 768x1024 portrait, 1024x768,
1920x1080 and 1728x1117 landscape. The capture measures the document at the device width, grows the
viewport to fit, and clips to the full page height, so a long page produces a tall image.

Each PNG gets a `.json` sidecar of the same basename with the absolute page URL, the title, the
journey file that produced it, and the geometry and identity of every meaningful element, which the
review resolves notes against ([Screenshot review](qa.md)).

Each suite's base class empties its own root once per run, before its first test, so the root holds
exactly what that run produced. Running a single journey, `ScreenshotReviewE2eTest` included, leaves
only that journey's screenshots; run the whole suite for a full review set. The two roots are
separate, so `make test-e2e` keeps both. `composer test` clears nothing.

The viewports and colour schemes are the traits' defaults; override `screenshotViewports()` and
`screenshotColorSchemes()` on a base class to change them, and the review page picks the new
directories up on its own.

**Never delete `var/screenshots` itself.** It is the source of a bind mount in
`compose.override.yaml`. Deleting it leaves the container with a stale mount, and the run writes
nothing the host sees until the service restarts. The suites clear the contents for you.

Failure artifacts are separate from the review tree. Panther writes the page a failing test stopped
on to `var/error-screenshots`, which `PANTHER_ERROR_SCREENSHOT_DIR` in `.env.test` names.
playwright-php writes it to `test-failures/` in the project root, which is gitignored.

### Capture gotchas

- **A capture perturbs the layout.** The sweep drives the page through every viewport and colour
  scheme before restoring the real viewport. An element hidden at one breakpoint may not come back:
  an open `<dialog>` that turns `display: none` drops out of the top layer for good. Capture such a
  screen **last** in its scene.
- **Only anchor on settled pages.** A mid-navigation capture is ten useless files.
- **A viewport-restricted screen is only honest at one viewport.** Say so in a comment at the call
  site.

## Helpers

Reuse these rather than reaching for the driver directly.

| Panther | Playwright | Use |
| --- | --- | --- |
| `journeyClient()` | `setUp()` | Panther: check the app answers, build the client on `E2E_BASE_URI`, start signed out. Playwright: replace the library's context with one whose `baseURL` is `E2E_BASE_URI`, so `goto('/')` resolves. |
| `signIn($client, $email)` | `signIn($email)` | The real sign-in through the dev shortcut, waiting until the path leaves `/login`. |
| `requestLink($client, $email)` | `requestLink($email)` | Submit the sign-in form only, for a scene that checks the outcome itself. |
| `click($client, $testId)` | `click($testId)` | Wait for the test id, then a native click. |
| `waitUntil($client, $condition, $expectation, $seconds = 10)` | `$this->page->waitForFunction($condition)` | Wait for a `JourneyScripts` expression. Panther's fails with the page the browser sat on. |
| `$client->waitForVisibility($selector)` | `waitForTestId($testId)` | Wait for an element to show. |
| `captureFullPageScreenshot($client, $label)` | `captureFullPageScreenshot($label)` | Fail a screen that repeats an `id` or `data-test-id`, then capture it. |

`JourneyScripts`:

| Member | Is true or returns |
| --- | --- |
| `REPEATED_IDS` | Every `id` and `data-test-id` that occurs more than once on the page. |
| `LOGIN_FORM_READY` | The `login` controller filled the timezone. |
| `SIGN_IN_SETTLED` | The path no longer starts with `/login`. |
| `MARK_WINDOW`, `WINDOW_MARKED` | Set a marker on `window`, then read it: Turbo Drive keeps the window between visits, a full page load does not. |
| `textContains($selector, $text)` | The `innerText` of the first match contains the text. |

## House rules

- **Navigate by clicking the real DOM.** No `request()` or `goto()` shortcuts past a screen the user
  would click through. Direct requests are for repositioning between scenes and for a specific URL's
  response, such as the broken sign-in links.
- **Locate by test id, never by wording.** Wait on a `data-test-id`, not on English, and put a state
  into the id; text checks stay for data the journey typed or seeded ([Test ids](test-ids.md)). The
  capture fails a screen that repeats an `id` or a `data-test-id`.
- **Wait on state, not on time.** No journey uses a flat wait.
- **Wait for the stream before you publish.** A Mercure update published before the EventSource opens
  is lost, so wait for `turbo-mercure-stream-source[connected]` first. Playwright waits for it with
  `['state' => 'attached']`, because the element has no box and never becomes visible.
- **Elements go stale on every frame swap.** Re-find by selector after a swap rather than holding a
  reference.
- One click per repeated function. When many rows carry the same button, exercising one is enough.

## Adding to the suite

When you add or change any user-facing behaviour, extend the matching journey in the same change. A
feature without journey coverage is not done.

1. Pick the journey that owns the surface, and change it in both suites.
2. Seed anything new in a story, with a constant for any identifier or value the test asserts on.
3. Click through the real interface by test id ([Test ids](test-ids.md)). Every new screen visited,
   every new distinct function triggered once.
4. Screenshot each new screen and each new state, anchored on its settle wait.
5. Run the journey alone in each suite, then `make test-e2e`.
6. Look at the new screenshots in both modes and both trees before calling it done.

New template file? Restart the `tailwind` compose service: its watcher does not pick up new files, so
their classes never compile.

## Troubleshooting

**`The port 9515 is already in use`.** Panther probes the port with `fsockopen()` before starting
chromedriver and refuses if anything answers, so the cause is a chromedriver still alive from an
aborted run, still shutting down, or belonging to a concurrent run. It can take about a minute to go
away, and a run that starts inside that window fails its first test classes and then succeeds once
the old process exits, which reads as random flakiness.

Wait until nothing at all is on the port, `TIME_WAIT` residue included. `/proc/net/tcp` writes ports
in hex, and `252B` is 9515:

```bash
while [ "$(docker compose exec -T php-e2e sh -c 'grep -c 252B /proc/net/tcp || true' | tr -d '\r')" != "0" ]; do sleep 15; done
```

Before killing a chromedriver, confirm it is not a concurrent run's:
`docker compose exec php-e2e ps -eo pid,stat,etime,args | grep chromedriver`.

**Every journey times out on its first wait, with an empty exception message.** The browser is on an
error or TLS interstitial page. Check the base URI; `curl` from the container proves nothing, since
it does not share Chromium's trust store.

**A green run wrote no screenshots the host sees.** The bind mount source was deleted. Recreate
`var/screenshots` and run `docker compose restart php-e2e`.

**The e2e app is not answering.** The FrankenPHP workers have stopped serving requests, which
deleting a class or changing vendor dependencies causes; `bin/console` continues to respond. Run
`docker compose restart php-e2e`, or `make restart` for both app containers. Panther's
`journeyClient()` requests `/health-check` once per process and fails at once with that instruction.
Playwright has no such check, so its first `goto()` times out instead.

**A journey fails only in a full run.** Journeys share the database, and Panther journeys share one
browser, so a preceding journey may have left data behind. Run the suite in order to reproduce;
running the one journey alone will not.

## CI

The `panther` and `playwright` jobs run on pushes to `main` and on manual dispatch, after `tests`,
not on pull requests, each with a 45 minute timeout. Each builds the images, starts the services,
runs `composer install` in `php`, compiles Tailwind, then runs `composer test:panther` or
`composer test:playwright` in `php-e2e`. Unless the run was cancelled, it uploads its tree and its
failure artifacts as one artifact, kept for 7 days:

| Job | Artifact | Contents |
| --- | --- | --- |
| `panther` | `panther-screenshots` | `var/screenshots/panther`, `var/error-screenshots` |
| `playwright` | `playwright-screenshots` | `var/screenshots/playwright`, `test-failures` |

That artifact is the interface review for the merge. `make ci JOB=panther` and
`make ci JOB=playwright` run the jobs locally ([Continuous integration](ci.md)).
