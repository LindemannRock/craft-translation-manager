# Basic usage

Translate your site's own text by wrapping it in Craft's translation filter and pointing it at your translation category. When **Capture Missing Translations** is enabled, Translation Manager stores new strings the first time they're rendered, you translate them in the Control Panel, and it generates the PHP file Craft loads for each language — so the same template works in every language with no code changes.

## What you'll use it for

- Translating static copy, labels, and microcopy in your Twig templates
- Storing new strings automatically as pages are rendered, when runtime capture is enabled
- Generating production PHP translation files from the managed database rows

## Translate site text

Use your configured translation category in templates:

```twig
{{ 'Welcome to our site'|t('messages') }}
{{ 'Contact Us'|t('messages') }}
{{ 'All Rights Reserved.'|t('messages') }}
```

The category — `messages` by default — is set under **Translation Manager → Settings → Translation Sources → Translation Categories**. Use the same category consistently so all of your site strings land together.

Your keys are written in the configured **Source Language** (English by default). That language is treated as already translated — the key *is* its own value — so you only ever translate into your other site languages. Set Source Language to match your keys before capturing; see [Source Language](../get-started/configuration.md#source-language).

## How it works

1. **Capture** — when **Capture Missing Translations** is enabled, a page rendering text in your category stores the string.
2. **Store** — it's saved to the database once per language (sites sharing a language share the row).
3. **Translate** — you edit the translation in the Control Panel (see [Managing translations](managing-translations.md)).
4. **Generate** — production PHP translation files are generated for each language; choose whether runtime reads those files directly or uses database rows with PHP fallback in [Runtime translation source](../get-started/configuration.md#runtime-translation-source).

## Auto-capture missing strings

Rather than registering every string by hand, let Translation Manager record them as they're used. Turn it on under **Translation Manager → Settings → Auto-Capture**:

- **Capture Missing Translations** — when a `|t('category')` (or `Craft::t()`) call hits text that isn't stored yet, the string is added to the database as a **Pending** translation with origin **System**, ready to translate.
- **Only in devMode** — nested under the toggle and **on by default**. Capturing adds a small amount of work to each request, so the recommended pattern is to collect strings in your dev/staging environment (devMode on) and ship the finished PHP files to production. Turn this off only if you intend to capture in production too.

What does and doesn't get captured:

- **Your enabled categories only.** System categories — `app`, `craft`, `yii`, `site` — are always ignored, so you only collect your own strings (plus form-provider strings when an integration is enabled).
- **No Twig in the key.** Text containing `{{`, `{%`, or `{#` is skipped automatically.
- **Skip patterns apply.** Anything matching a configured [skip pattern](../get-started/configuration.md#skip-patterns) is never captured.
- **Captured once.** A string already in the database (or already seen earlier in the same request) isn't duplicated.

Capture isn't limited to front-end requests — strings rendered in the Control Panel or a console command can be captured too, as long as they're in an enabled category and an allowed site language.

## More examples

### With variables

```twig
{{ 'Hello, {name}!'|t('messages', { name: user.name }) }}
{{ '{count} items in cart'|t('messages', { count: cart.count }) }}
```

### Pluralization

```twig
{{ '{count, plural, =0{No items} =1{One item} other{# items}}'|t('messages', { count: items|length }) }}
```

## Status reference

In the Control Panel each translation shows a status:

| Status | Color | Meaning |
|--------|-------|---------|
| Pending | Orange | Needs translation |
| Draft | Blue | Translated, awaiting approval (only when [approval](managing-translations.md#approval-workflow) is on) |
| Translated | Green | Has an approved translation |
| Unused | Gray | No longer used in templates |

## Best practices

1. **Consistent category** — always use the same translation category for your site strings.
2. **Meaningful keys** — use readable English text as the key.
3. **Avoid Twig in keys** — text containing `{{`, `{%`, or `{#` is automatically excluded from capture.
4. **Enabled categories only** — runtime capture stores only strings in your enabled categories and allowed site languages. It is not limited to front-end requests, so strings rendered in the Control Panel or console can also be captured.
