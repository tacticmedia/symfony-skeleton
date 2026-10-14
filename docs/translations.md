# Translations

Wording lives in `translations/messages+intl-icu.en.xlf` and authentication errors in
`translations/security.en.xlf`, both XLIFF 2.0. The only locale is `en`: `default_locale` and
`enabled_locales` in `config/packages/translation.yaml`. [Naming keys](#naming-keys),
[Nouns](#nouns) and [Writing a message](#writing-a-message) have the rules. Two decorators of the
`translator` service decide what `trans()` returns:

| Class | Environments | `trans()` returns |
| --- | --- | --- |
| `App\I18n\TestingTranslator` | `test` | The fake string, always. |
| `App\I18n\SuspendableTranslator` | every environment except `test` | The real translation, or the fake string while the session holds `translation-disabled`. |

Both register themselves through `#[AsDecorator(decorates: 'translator')]`, scoped by `#[When('test')]`
and `#[WhenNot('test')]`, and both build the fake in `SuspendableTranslator::fakeTrans()`.
`getLocale()`, `setLocale()`, `getCatalogue()` and `getCatalogues()` go to the real translator, so
the request locale and `debug:translation` are unaffected.

## Domains

| Domain | Catalogue | Holds |
| --- | --- | --- |
| `messages` | `messages+intl-icu.en.xlf` | Every text the application shows: pages, the sign-in flow, emails, nouns, enum cases and `shared.*`. ICU MessageFormat. |
| `security` | `security.en.xlf` | The messages `App\Security\UserChecker` throws, `account.closed` and `account.suspended`. |

`messages` is the default and is never named. A call site names any other domain as a literal,
because `translation:extract` and `debug:translation` read only literals:
`{{ error.messageKey|trans(error.messageData, 'security') }}`.

`composer lint` runs `bin/console lint:translations`, which checks every message against ICU syntax
for the enabled locales, and `bin/console debug:translation en --only-missing`, which fails a literal
key that the named domain does not define. It does not catch a key that `|trans` receives from a
variable.

## Naming keys

Keys are assigned by rule, so two people or two sessions adding the same text arrive at the same
key. `tests/Functional/I18n/CatalogueKeysTest.php` enforces the shape of every key in
`messages+intl-icu.en.xlf`, with the segment rules in `CatalogueKeyRules.php`, and checks that the
noun class, entity, property, enum case, form or email template a key names exists. A key whose first
character is upper case is a framework message, keeps its English source as its key, and is skipped.
Every other segment is lower camelCase.

### Placeholders

**Every placeholder of a message is part of its key.** This is mandatory. The key ends with one
`_{name}` per placeholder, in alphabetical order, so the call site shows what it must pass:

```
default.signedInAs_{email}
login.confirm.error.tooFarInTheFuture_{expires}
email.common.sentOn_{datetime}
```

Placeholders inside `plural` and `select` branches count too. Alphabetical order keeps a key stable
when the English is reworded. A message that gains or loses a placeholder gets a new key, and every
call site changes with it. In the rest of this section, a key means the part before the placeholders.

A noun is the one exception. `entity.user` takes a `count`, and its key does not say so: the key is
reached only through its class's `noun()`, which always passes the count. See [Nouns](#nouns).

### Scope

The first segment says what kind of text a key holds. Walk this list; the first match wins.

1. **The name of a kind of record**: `entity.<entity>`, the noun of a class that implements
   `TranslatableNounInterface`. See [Nouns](#nouns).
2. **Field wording**: a form field's label, help or placeholder, a column heading, a detail-list
   term. See [Field keys](#field-keys).
3. **An enum case**: `enum.<enum>.<case>`, or `enum.<enum>.<case>.<text>` when a case has several
   texts. `<enum>` names `App\Enum\<Enum>` and `<case>` a case of it, both lower camelCase. The enum's
   `trans()` returns it.
4. **Wording that reads the same wherever it appears**: `shared.<kind>.<name>`. See
   [Shared kinds](#shared-kinds).
5. **An email**: `email.<email>.<element>`, the email named after its template in lower camelCase:
   `templates/_emails/login_link.html.twig` gives `email.loginLink.*`, subject included. The template
   can also be `_<snake_case>.html.twig`. `email.common.*` holds text several emails share.
6. **Everything else, the text of a screen**: `<area>.<context>.<element>`. See
   [Screen keys](#screen-keys).

#### Field keys

| The wording is | Key |
| --- | --- |
| the name of a kind of record: User, Users | its noun: `User::noun()` |
| the plain noun for a field no class names, right on every screen that shows it: Name, Email, Status | `shared.field.<noun>`, label only |
| specific to an entity property, or any help or placeholder of one | `entity.<entity>.<property>.<label\|help\|placeholder>` |
| specific to a form field without an entity property: the sign-in email, a filter | `form.<form>.<field>.<label\|help\|placeholder>`, a fixed choice `form.<form>.<field>.choice.<name>` |

- `<entity>` is the class name in `App\Entity` in lower camelCase, and `<property>` a property of
  that class, not the form field's name. A field belongs to an entity when it sets a property of the
  entity its form edits.
- `<form>` is the form type or payload class without its suffix. The test accepts
  `App\Form\<Form>FormType`, `App\Form\<Form>Type`, `App\Dto\<Form>Dto` and `App\Dto\<Form>FormDto`:
  `LoginFormType` and `LoginFormDto` give `form.login.*`.
- `entity.*` holds only wording every form of the entity shares. Where two forms word a part
  differently, each uses `form.<form>.<field>.<part>` for that part.
- A column heading or a detail term reuses the key of the field's label.

#### Shared kinds

`CatalogueKeyRules` accepts these kinds only:

| Kind | Holds |
| --- | --- |
| `aria` | Accessible names of generic landmarks: Tabs, Pagination, Breadcrumb. |
| `button` | Generic action verbs: Continue, Try Again, Save, Cancel. A verb followed by the name of a kind of record is a verb template, `shared.button.<verb>_{what}`. |
| `count` | The plural count of a thing: `shared.count.items_{count}`. |
| `field` | Plain field nouns, see [Field keys](#field-keys). |
| `heading` | Section headings and tab labels that recur. |
| `status` | Transient states: Loading. |
| `title` | Page titles that recur, and the verb templates `shared.title.<verb>_{what}`. |
| `value` | Stand-in and formatted values: `shared.value.none`. |
| `dropzone`, `pagination` | Text owned by that shared component. |

Wording that depends on what it describes never goes in `shared`, even when the English matches:
many languages make adjectives and participles agree with their noun.
[Nesting a noun](#nesting-a-noun) draws the same line for the verb templates.

#### Screen keys

`<area>.<context>.<element>`

- **Area**: an entity in `App\Entity`, in lower camelCase (`user`), or a feature with no entity. The
  features are the `FEATURES` list in `CatalogueKeyRules`: `default`, `layout`, `login`, `logout`.
  A new feature area goes into that list. `layout` is the page shell: navigation, footer, banners,
  toggles.
- **Context**: the screen, named after its template or action, such as `request`, `sent` or
  `confirm`; an action's own texts; or a group that spans screens, such as `flash`, `tab`, `badge`,
  `status`, `filter`, `empty` or `devTools`. An area with a single text may leave the context out:
  `logout.heading`, `default.signIn`.
- **Element**: the text's role on the screen.

| Element | Holds |
| --- | --- |
| `title`, `subtitle`, `intro` | The page title, the line under it, the opening paragraph. |
| `heading`, `body` | A section heading and its paragraph. |
| `button`, `submit` | The control that starts an action; a form's submit button. |
| `confirm` | The text of a confirmation dialog. |
| `back` | The link back to the context it is named under. |
| `new`, `search` | A list's create link; its search field placeholder. |
| `empty.header`, `empty.text` | An empty state. |

A text no element fits takes a short lower camelCase name for its role: `login.confirm.getNewLink`.

### Examples

| Text | Key |
| --- | --- |
| "Users", a list title | `entity.user`, through `User::noun(TranslatableNounInterface::PLURAL)` |
| "Email Address", the sign-in form's label | `form.login.email.label` |
| "Remember Me" on the sign-in form | `form.login.remember.label` |
| "Continue", the sign-in form and the link confirmation | `shared.button.continue` |
| "None", for a missing address in a link error | `shared.value.none` |
| "Signed in as {email}" on the home page | `default.signedInAs_{email}` |
| "Please wait a minute before trying again" | `login.request.rateLimited_{retry}` |
| "Your sign-in link", the email subject | `email.loginLink.subject` |
| "Sent on {datetime}", every email's footer | `email.common.sentOn_{datetime}` |

### Procedure

To add a text:

1. Settle the English and where it shows.
2. If the text is the name of a kind of record on its own, use the class's noun. If it is a verb and
   that name, use a verb template ([Nesting a noun](#nesting-a-noun)). A class without a noun gets
   one first ([Nouns](#nouns)).
3. Search the catalogue for the same English. Reuse a unit only when its context note describes the
   same meaning in the same role, and the wording would read the same everywhere it shows.
4. Otherwise build the key: the scope from the list, the segments the scope defines, then the
   placeholders.
5. Add the unit to the catalogue in key order, with its notes
   ([Notes for the translator](#notes-for-the-translator)) and its id.
6. Write the key as one literal string at the call site. A key built by concatenation cannot be found
   by `grep` or checked by the tests.
7. Run `vendor/bin/phpunit tests/Functional/I18n` and `bin/console lint:translations`.

To change a text, edit its target and notes. Rename the key when its placeholders change or the text
moves to another scope or screen, and change every call site and test with it.

## Nouns

Every entity names itself once in the catalogue, as a plural noun: `entity.user` is
`{count, plural, one {User} other {Users}}`. Navigation items, list titles, tabs and column headings
print the noun on its own, and the verb templates insert it as `{what}`.

A class that names itself implements `App\I18n\TranslatableNounInterface`:

```php
public static function noun(int $count = 1): TranslatableMessage
{
    return new TranslatableMessage('entity.user', ['count' => $count]);
}
```

`tests/Functional/I18n/NounTest.php` enforces:

- **Who implements it**: every Doctrine entity that is not embedded and not a mapped superclass.
  An embeddable an entity carries is checked too once it implements the interface.
- **The key** is `entity.<class>`, the short class name in lower camelCase, a literal in the class's
  own `noun()`, defined in the catalogue, with `count` as its only ICU argument. The literal keeps the
  key visible to `translation:extract` and `debug:translation`. The test fails when the key appears in
  `src/`, `templates/` or `assets/` outside its own class, because a bare `'entity.user'|trans` would
  print `{count}`.
- **The count** defaults to 1. ICU prints `{count}` literally when the argument is missing, so every
  `noun()` passes it. `TranslatableNounInterface::PLURAL` is 2, which selects the nominative plural:
  `other` in English.

In PHP: `'label' => User::noun()`, `['what' => User::noun(TranslatableNounInterface::PLURAL)]`. Twig
has no `noun()` function; pass the message from PHP and print it with `|trans`.

### Nesting a noun

- `{what}` always receives a noun, and `{name}` always receives data, such as a person's name.
- A noun nests only where no language needs its gender, article or case: an imperative verb followed
  by the bare noun in a label, or the noun on its own. A label or sentence with an article, a
  demonstrative, a possessive, an adjective or a participle keeps one unit per screen or per entity:
  New User, Back to Users, No Users Yet, every confirmation and every flash.
- A number and its noun agree, so a count stays its own unit: `shared.count.items_{count}`.
- The verb templates are `shared.button.<verb>_{what}` and `shared.title.<verb>_{what}`.

## Writing a message

A translator works from the catalogue alone, so every unit has to make sense without the screen.

- **A sentence is one message.** Never join translated fragments, or a fragment and a value; a
  placeholder lets the translator move the value.
- **Counts** are ICU plurals, `{count, plural, one {# item} other {# items}}`. The translator adds
  the categories the language needs.
- **Dates** are formatted by ICU in the viewer's locale. Pass a `DateTimeInterface` and write
  `{date, date, medium}` in the message, as `login.confirm.error.tooFarInTheFuture_{expires}` does.
  A date printed on its own is `format_date('medium')`, a timestamp `format_datetime('medium', 'short')`.
  `|date('j M Y')` leaves the month in English.
- **A value formatted before it is passed** is said so in the placeholders note:
  `email.common.sentOn_{datetime}` receives `format_datetime()` output, `login.confirm.expired.text_{ago}`
  receives `|ago` output.
- **Enums** implement `TranslatableInterface` and print with `|trans`.
- **Constraint messages** are keys in the `validators` domain, with the validator's `{{ name }}`
  placeholders instead of ICU arguments, named after the field they are about:
  `entity.<entity>.<property>.<rule>`, `form.<form>.<field>.<rule>`, or `form.<form>.<rule>` for a
  constraint on the whole form. No `validators` catalogue exists yet.
- **Strings a Stimulus controller shows** arrive as values, translated by Twig with the placeholders
  left for the controller to fill: `'x_{current}'|trans({current: '{current}'})`.
- **Flash messages** are `TranslatableMessage` objects. `phpstan.dist.neon` bans an `addFlash()`
  call whose message is not a `TranslatableInterface`.

### Capitalisation

- A label is a short phrase with no sentence punctuation: a button, link, navigation item, tab, page
  title, heading, field label, column heading, badge or empty-state header. Labels take Title Case.
- In a label, capitalise every word except the articles a, an and the; the coordinating
  conjunctions and, but, or and nor; and prepositions of four letters or fewer, such as at, by, for,
  from, in, into, of, on, to and with. Those still take a capital when they come first or last: Back
  to Users, Continue to the Functional Health Check.
- The particle of a phrasal verb takes a capital: Sign In, Sign Out, Sign Back In.
- Text that reads as a sentence keeps sentence case, even as a heading: "You're signed out",
  "Nothing arrived?". So do placeholders, stand-in values and a link inside a sentence.
- A noun is Title Case in its standalone form, so a label prints it unchanged. The file-level hint
  asks a translator to follow the capitalisation of their own language; never adjust case in CSS or
  with `|lower`.

### Notes for the translator

Each unit carries short, factual `<note>` elements, ASCII only:

| Category | When | Holds |
| --- | --- | --- |
| `context` | always | Where the text appears and what it refers to. |
| `placeholders` | the target contains `{` | Every argument as `{name}: what it holds`, or `{{ name }}: ...` in `validators`. |
| `example` | the result is not obvious | The rendered English with sample values. |
| `hint` | the translation is constrained | What to keep or avoid: a symbol, a length, a glossary term. |

The file-level `<notes>` of `messages+intl-icu.en.xlf` hold the ICU and capitalisation hint.
`tests/Functional/I18n/CatalogueNotesTest.php` fails a `messages` unit without a `context` note, and
a unit whose target contains `{` without a `placeholders` note. A plural target counts.

```xml
<unit id="F33ixIO" name="default.signedInAs_{email}">
  <notes>
    <note category="context">Home page: who is signed in.</note>
    <note category="placeholders">{email}: email address of the account.</note>
  </notes>
  <segment>
    <source>default.signedInAs_{email}</source>
    <target>Signed in as {email}</target>
  </segment>
</unit>
```

Units are sorted by key. Every unit needs a unique `id`: the one the XLIFF dumper derives from the
key, so `translation:extract --force` keeps it:

```bash
php -r 'echo strtr(substr(base64_encode(hash("xxh128", "default.signedInAs_{email}", true)), 0, 7), "/+", "._"), PHP_EOL;'
```

## The fake string

The message id, then one `_{name:value}` segment per parameter, in the order the call site passes
them:

```
id_{name1:value1}_{name2:value2}
```

A catalogue key already ends with its placeholders, so its fake string names each one twice:
`default.signedInAs_{email}` renders `default.signedInAs_{email}_{email:staff@tacticmedia.com.au}`.
A noun's key announces no placeholder, so its count appears once: `entity.user_{count:1}`.

Output of the `test` environment:

| Call | Renders |
| --- | --- |
| `'logout.heading'\|trans` | `logout.heading` |
| `'Save changes'\|trans` | `Save changes` |
| `'default.signedInAs_{email}'\|trans({email: 'a@example.com'})` | `default.signedInAs_{email}_{email:a@example.com}` |
| `User::noun()->trans($translator)` | `entity.user_{count:1}` |
| `t('a', {b: 'c', d: t('e', {f: t('g', {h: 'i'})})})\|trans` | `a_{b:c}_{d:e_{f:g_{h:i}}}` |
| `$translator->trans('a', ['date' => new \DateTimeImmutable('2026-09-16T00:00:00+00:00')])` | `a_{date:2026-09-16T00:00:00+00:00}` |
| `'x_{a}'\|trans({a: 'b'}, count: 3)` | `x_{a}_{a:b}_{%count%:3}` |
| `'Hello %name%'\|trans({'%name%': 'Jan'})` | `Hello %name%_{%name%:Jan}` |
| `'x'\|trans({}, 'security', 'it')` | `x` |
| `date('-5 minutes')\|ago` | `diff.ago.minute_{%count%:5}` |

What follows from it:

- The catalogue is never read. A key renders as the key whether the catalogue defines it or not,
  and no ICU syntax is evaluated, so `plural` and `select` never pick a branch.
- The domain and the locale are ignored, so a key translated in the wrong domain renders the same.
- A parameter name appears as the call site spells it: `{email:...}` for an ICU name,
  `{%count%:...}` for Twig's `count` argument, `{{{ value }}:...}` for a validator placeholder.
- A `TranslatableInterface` parameter renders as its own fake string.
- A `DateTimeInterface` renders in ATOM notation, the value an ICU `date` argument receives, so a
  test expects `$date->format(\DATE_ATOM)` and not a formatted date.
- Any other value goes through PHP string conversion: `true` is `1`, `false` and `null` are empty,
  a float uses PHP's own notation. An enum or another object without `__toString()` throws
  `Error: Object of class ... could not be converted to string`, and an array raises
  "Array to string conversion".
- Twig escapes the result like any string, so `'` renders as `&#039;`. The DomCrawler decodes it,
  so an assertion uses the plain character.

## What renders fake in tests

Every caller of the `translator` service: Twig `|trans`, `t()` and `|ago`,
`TranslatableMessage::trans()`, the form and validator components, and PHP that translates for
itself, such as the sign-in email subject in `App\Security\LoginLinkSender`.

| Source | Renders |
| --- | --- |
| Form `label`, `help`, `placeholder`, `attr.placeholder`, choice labels | As given, key or English, with any translation parameters appended. A noun label renders its fake, `entity.user_{count:1}`. A label humanised from the field name renders unchanged. |
| Validator violations | The message key and its parameters. A framework constraint without a custom message renders its English template: `This value should not be blank._{{{ value }}:null}`. |
| Security messages | The key: `account.suspended`. |
| Email templates and subjects rendered by a test | Ids, like any template. |

Not affected:

- Text a template writes without `|trans`, such as the English on `health-check/functional.html.twig`
  and in `_emails/test.html.twig`.
- Data as stored, such as an email address or a display name.
- Money, and dates printed through `format_date` or `format_datetime`. Their formatting does not go
  through the translator, so they render for real. A test builds the expected date with
  `IntlDateFormatter`, never `date('j M Y')`.
- The journeys. The browser drives the `php-e2e` container, which runs `APP_ENV=dev`, so the
  journeys see the real English. They assert test ids, and text only for data they typed or seeded
  ([End-to-end journeys](e2e.md), [Test ids](test-ids.md)).

## Writing a test

1. Find the id at the call site: `{{ 'logout.heading'|trans }}` in the template,
   `new TranslatableMessage('login.confirm.error.expired')` in PHP.
2. Expect the fake string: the key, which already ends with its placeholders, then one
   `_{name:value}` per parameter in call-site order. A nested noun renders inside its parameter.
   ```php
   self::assertSelectorTextContains('[data-test-id="page-title"]', 'logout.heading');
   self::assertSelectorTextContains('[data-test-id="login-error"]', 'account.suspended');
   ```
3. Assert absence with the id as well. English never renders, so
   `assertSelectorTextNotContains('body', 'Sign In')` can never fail;
   `assertSelectorTextNotContains('body', 'default.signIn')` can.
4. Assert text a template writes without `|trans`, or stored data, as written.

Limits:

- An id without parameters renders the same translated or not, so a test cannot catch a missing
  `|trans` on a plain string key. A `TranslatableMessage` printed without `|trans` still fails the
  render, because the object has no `__toString()`.
- `CatalogueKeysTest` and `CatalogueNotesTest` read the catalogue for its keys and notes. The
  messages themselves are checked by `bin/console lint:translations`, and
  `debug:translation en --only-missing` in `composer lint` checks that the domain a call site names
  defines its key.
- When only the real wording proves the point, translate through the undecorated translator:
  `self::getContainer()->get('translator.default')->trans(...)`.

## Suspending translation in the browser

Outside `test`, `SuspendableTranslator` renders the fake string while the session holds
`translation-disabled`. Every translated text then shows its id and parameters, so text that stays
English is text the template does not translate.

`GET /navigation/toggle-translation`, the route `NavigationController::ROUTE_TOGGLE_TRANSLATION`,
flips the flag for the current session of a signed-in user. `?force=off` clears it, and `_return_to`
names the same-origin path to return to. The home page offers the toggle as
`layout-toggle-translation` to a signed-in user while `app.debug` is true.
