# Screenshot review

A dev-only page for reviewing the screenshots the journeys leave in `var/screenshots/` and turning
what is wrong with them into a prompt for a coding agent.

```
https://localhost/_dev/screenshots
```

The page and the capture traits behind it are the `tacticmedia/qa-bundle` 2.0 composer package, from
github.com/tacticmedia/qa-bundle. **Change them there and release, never in `vendor/`**. This
document covers what the application provides. The package documents the rest:

| Package document | Contents |
| --- | --- |
| `vendor/tacticmedia/qa-bundle/README.md` | Install, the two capture traits. |
| `vendor/tacticmedia/qa-bundle/docs/host-setup.md` | Trait requirements, `screenshots_dir` and `review_dir`, the firewall, prompt wording. |
| `vendor/tacticmedia/qa-bundle/docs/review.md` | The review page: keyboard, coordinates. |
| `vendor/tacticmedia/qa-bundle/docs/how-it-works.md` | Measurement, the sidecar, selection resolution, crops. |
| `vendor/tacticmedia/qa-bundle/docs/frontend.md` | The page's JavaScript, styling, content security policy. |

`config/bundles.php` registers the bundle for `dev` and `test`, never `prod`. No routing file imports
the bundle: `config/routes.yaml` imports `routing.controllers`, which loads the `#[Route]` attributes
of every registered controller service, so registration alone adds the `qa_screenshots_*` routes
under `/_dev/screenshots`. Nothing requests them under `test`.

`/_dev/` matches the pattern `^/_(profiler|wdt|dev)/` of the `dev` firewall in
`config/packages/security.yaml`. That firewall has `security: false` and `stateless: true`: no
authentication and no session.
The bundle's forms carry a stateless CSRF token, which the bundle sets up itself.

## Choosing the tree

Each journey suite writes its own root, because each run empties its root:

| Suite | Root |
| --- | --- |
| Panther | `var/screenshots/panther` |
| Playwright | `var/screenshots/playwright` |

The page reads one of them. `config/packages/qa.yaml` sets `screenshots_dir` to
`var/screenshots/%env(QA_REVIEW_TREE)%`, and `.env` sets `QA_REVIEW_TREE=panther`. To review the
Playwright tree, set `QA_REVIEW_TREE=playwright` in `.env.local`, then run `make restart` so the
workers read it.

## Reviewing

The sidebar lists every mode and viewport group of the last run, with its outstanding note count. A
group shows its captures sectioned by the test class that produced them. Clicking one opens the
annotate page. **Drag a rectangle over the screenshot** to bind a note to one area, or write without
a selection to note the whole screen. Every note can be edited or withdrawn. Keys and coordinates are
in the package's `docs/review.md`. The captures are full-page, so `y` is measured from the top of the
page, not the top of the viewport.

## The prompt

**Generate prompt** turns the whole review into plain text, grouped by test class and screen. Copy
it into an agent as-is, or fetch it from `/_dev/screenshots/prompt.txt`. The wording is the
bundle's generic brief. To give it this project's conventions, document paths and test command,
override `templates/bundles/TacticMediaQaBundle/prompt.txt.twig`.

A note bound to an area also carries the DOM behind it. Every capture writes a `.json` sidecar
beside its PNG with the page URL and title, the journey file, and the box, text, selector, classes
and attributes of each meaningful element. At prompt-build time the bundle matches the stored
rectangle against the sidecar, reports the elements it covers and the containers it sits inside,
and writes a cropped PNG of the selection outlined in red.

The sidecar URL is the address the journey browser was on: `https://localhost` inside `php-e2e`,
against `app_e2e`. From the host the same URL opens the dev app on its own database, so a URL that
names a record can resolve differently there.

## Wiring

What this application provides, and what breaks if it is removed:

| File | Why |
| --- | --- |
| `config/bundles.php` | Registers the bundle, and with it the routes, in `dev` and `test`. |
| `config/packages/qa.yaml` | Points `screenshots_dir` at the tree `QA_REVIEW_TREE` names. `review_dir` keeps its default, `var/review`. |
| `config/packages/security.yaml`, the `dev` firewall | Keeps `/_dev/` off the `main` firewall. No application route may match `^/_(profiler\|wdt\|dev)/`, because `access_control` never runs for a firewall with `security: false`. |
| `.env` (`QA_REVIEW_TREE`) | The default tree, `panther`. |
| `assets/styles/app.css` (`@source "../../vendor/tacticmedia/qa-bundle/templates"`) | Puts the bundle's markup in the Tailwind build, for a layout override that links `app.css`. Restart the `tailwind` compose service after adding a source. |
| `compose.override.yaml` bind mounts | `var/screenshots`, `var/error-screenshots` and `var/review` shared between `php` and `php-e2e` and readable from the host. |

The bundle serves its own JavaScript from `/_dev/screenshots/assets/`, so `assets/controllers.json`
and `importmap.php` name nothing of it. No layout override exists, so the page loads Tailwind from a
CDN. That works because `config/packages/nelmio_security.yaml` sets no content security policy. A
policy that restricts `script-src` blocks it; then override
`templates/bundles/TacticMediaQaBundle/layout.html.twig` as the package's `docs/frontend.md` shows.

Notes live in `var/review/screenshot-feedback.json`, bind-mounted from the host so a review survives
a container rebuild. `var/review` is outside `var/screenshots/`, which the journeys empty at the start
of every run, so a note outlives the screenshot it describes: a note whose capture is gone still
appears in the prompt, flagged as belonging to an earlier run. Crops live in `var/review/crops/`,
emptied and regenerated on every prompt build.

## Coverage

The bundle owns its own tests. `ScreenshotReviewE2eTest`, in both `tests/E2e/Panther/` and
`tests/E2e/Playwright/`, is a smoke test for the wiring above: the page opens through the `dev`
firewall with the title "Screenshot review". It extends the suite's `JourneyTestCase`, so run alone
it empties that suite's tree like any journey ([End-to-end journeys](e2e.md#screenshots)).

## Working on the bundle

Fixes belong in a checkout of github.com/tacticmedia/qa-bundle, not in `vendor/`. Release a tag and
bump the constraint in `composer.json`. To try a change against this application before publishing,
add a path repository pointing at that checkout and require `@dev`; remove both again before
committing.
