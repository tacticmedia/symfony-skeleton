# Test ids

Tests find elements by a `data-test-id` attribute written literally in the template, never by the
wording, the styling or the position of what a person sees. A copy change or a translation key rename
then breaks no test whose behaviour stayed the same. The attribute renders in every environment.

## The rule

1. A test locates an element only through `[data-test-id="..."]`. A form field is located only through
   its `id`, such as `#login_form_email`. A choice is located only through its option `value`.
2. The id goes on the element the test acts on or reads: the `<button>`, the `<a>`, the element that
   holds the asserted text. A form with no submit button carries it on the `<form>`.
3. Every `id` and every `data-test-id` is unique on the page.
4. Functional tests assert translation ids ([Translations](translations.md#writing-a-test)), scoped
   by a test id; `body` is for page-wide absence only. Journeys assert that a test id is present, and
   assert text only for data they typed or seeded, such as an email address. A state goes into the
   id, not into asserted wording.

## Grammar

Lowercase kebab-case: `<area>-<element>[-<state>][-<key>]`.

| Part | Meaning | Examples |
| --- | --- | --- |
| `area` | The screen's area, kebab-cased, as the translation screen keys name it; `layout` for the shell and its developer tools | `default`, `login`, `logout`, `health-check`, `layout` |
| `element` | A verb for a control, a noun for content | `request`, `retry`, `confirm`, `sign-out`, `errors`, `title` |
| `state` | The enum backing value or the boolean name a test reads | `health-check-email-sent`, `health-check-email-failed` |
| `key` | A repeated element's record UUID, `{{ record.id.toRfc4122 }}`; the collection index where there is no record | `user-row-<uuid>` |

One record rendered in several places gives each placement its own element, such as
`layout-user-desktop-<uuid>` and `layout-user-mobile-<uuid>`. A partial included twice takes a
placement variable.

| Fixed id | Element |
| --- | --- |
| `screen-<route name>` | `<body>`, from `app.current_route` in `base.html.twig`. It changes on a full or Turbo Drive visit only; after a frame swap, wait on an id inside the new content. |
| `page-title` | The page's `h1`. |
| `layout-flashes` | The flash container in `_partials/flash-messages.html.twig`. |
| `layout-toggle-turbo`, `layout-toggle-translation` | The developer toggles, shown to a signed-in user while `app.debug` is true. |
| `login-frame`, `login-step-title` | The `login` turbo-frame and its step heading in `auth/login/base.html.twig`. |

## Markup

- Write the attribute literally, `data-test-id="user-row-{{ user.id.toRfc4122 }}"`. There is no Twig
  function.
- The components in `templates/components/` render `attributes` on their root, so a
  `data-test-id` given to them lands on the element a test reads:

  | Component | Root |
  | --- | --- |
  | `Alert` | `<div>` |
  | `Badge` | `<span>` |
  | `Button` | `<a>` when `href` is given, else `<button>` |
  | `Card:Body`, `Card:Header` | `<div>` |
  | `Heading` | `h1` to `h4`, from `level` |
  | `Link` | `<a>` |
- The same Symfony form rendered several times on one page gets a per-record name through
  `FormFactoryInterface::createNamed()`. `form_attr` does not do it: it stamps `form="<id>"` on every
  field while `form_start` renders no matching `id`, which detaches the fields.
- A bare `data-*` attribute that only a test reads is a test id under another name. Replace it, and
  delete it when nothing in `assets/`, `templates/` or `src/` reads it.

## Idioms

```php
$client->submit($crawler->filter('[data-test-id="login-confirm"]')->form());
self::assertSelectorTextContains('[data-test-id="page-title"]', 'logout.heading');
self::assertSelectorExists('body[data-test-id="screen-default"]');
self::assertSelectorNotExists('[data-test-id="default-dev-tools"]');
$client->request('GET', (string) $crawler->filter('[data-test-id="default-sign-out"]')->attr('href'));
```

Journeys, through their base class helpers ([End-to-end journeys](e2e.md#helpers)):

```php
$this->click($client, 'login-request');                                    // Panther
$client->waitFor('body[data-test-id="screen-health_check_functional"]');   // Panther
$this->click('login-request');                                             // Playwright
$this->waitForTestId('default-signed-in');                                 // Playwright
```

A prefix match, `[data-test-id^="<area>-row-"]`, counts the rows of a repeated element. DomCrawler `form()` accepts a button or a form
node. Its value map keeps the field `name` key, such as `login_form[email]`, because that API has no
id lookup. Panther `Client::submit()` clicks the form's button.

## Records a journey addresses

A journey runs against a fixture another process loads, so it knows a record's UUID only when the
fixture fixes it:

- Story constants for the records and values a journey locates, as `AppStory::ADMIN_EMAIL`.
- A record the journey creates: from the URL it lands on, or from the row that shows the name the
  journey typed.

## Exceptions

- Third-party markup: the `vendor/tacticmedia/qa-bundle` templates, Symfony error pages.
- Turbo Stream responses, asserted by `action` and `target`: that protocol is what the test checks.
- Elements a library renders, such as `turbo-mercure-stream-source[connected]`.
- `assertResponseRedirects()`, email assertions.

## Enforcement

- `spaze/phpstan-disallowed-calls` in `phpstan.tests.dist.neon` bans `selectButton()`,
  `selectLink()`, `selectImage()` and `filterXPath()` on both crawlers, `clickLink()` and
  `submitForm()` on `AbstractBrowser`, and `WebDriverBy::linkText()`, `partialLinkText()` and
  `xpath()`, with the message "locate the element by its data-test-id, see docs/test-ids.md".
  `composer stan` runs it.
- `captureFullPageScreenshot()` in both journey base classes fails a screen that repeats an `id` or a
  `data-test-id` (`JourneyScripts::REPEATED_IDS`), before it captures the screen.
