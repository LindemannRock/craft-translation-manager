# GraphQL @since(5.26.0)

Use Translation Manager as a read-only translation catalog for headless frontends. GraphQL can look up one translated key or list translation rows for a language, category, status, origin, or search term.

GraphQL does not create missing translation rows, generate PHP files, call AI services, or edit translations. Translation editing, import, review, generation, and maintenance stay in the Control Panel and console commands.

## Before you query

GraphQL access is controlled by Craft schemas. Translation Manager does not have a separate GraphQL toggle.

Enable this on the schema used by your frontend token:

| Area | Required access |
|---|---|
| Translation Manager | `Query Translation Manager data` |

The Translation Manager scope is:

| Scope | Purpose |
|---|---|
| `translationManager.all:read` | Allows Translation Manager GraphQL queries |

For public frontend requests, use a GraphQL token for a schema that has the Translation Manager permission enabled. A logged-in Control Panel user may see different results in GraphiQL than an external client using a token.

## Look up one translation

Use `translationManagerTranslate` when the frontend needs one key for one category and language.

```graphql
query {
  translationManagerTranslate(
    key: "Download our app"
    category: "messages"
    language: "ar"
  ) {
    key
    category
    language
    translation
    status
  }
}
```

When no matching row exists, `null` is returned:

```json
{
  "data": {
    "translationManagerTranslate": null
  }
}
```

When a row exists, the requested fields are returned:

```json
{
  "data": {
    "translationManagerTranslate": {
      "key": "Download our app",
      "category": "messages",
      "language": "ar",
      "translation": "نزّل تطبيقنا",
      "status": "translated"
    }
  }
}
```

### Arguments

```graphql
translationManagerTranslate(
  key: "Download our app"
  category: "messages"
  language: "ar"
)
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `key` | `String` | Yes | Translation key to look up |
| `category` | `String` | No | Craft translation category. Defaults to `messages` |
| `language` | `String` | Yes | Language code to look up |

## List translations

Use `translationManagerTranslations` when a frontend needs a read-only translation payload for a language or category.

```graphql
query {
  translationManagerTranslations(
    language: "ar"
    category: "messages"
    status: "translated"
    limit: 500
  ) {
    id
    key
    source
    translation
    category
    language
    context
    status
    origin
  }
}
```

The list query:

- accepts filters for `language`, `category`, `status`, `origin`, `context`, and `search`
- defaults to 100 rows when `limit` is omitted
- caps `limit` at 500
- does not create missing rows
- does not update status, usage counts, or generated files

### Arguments

```graphql
translationManagerTranslations(
  language: "ar"
  category: "messages"
  status: "translated"
  origin: "manual"
  context: "site.messages"
  search: "Download"
  limit: 500
)
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `language` | `String` | No | Filter by language code |
| `category` | `String` | No | Filter by Craft translation category |
| `status` | `String` | No | Filter by `pending`, `draft`, `translated`, or `unused` |
| `origin` | `String` | No | Filter by `system`, `ai`, `manual`, or `import` |
| `context` | `String` | No | Filter by exact translation context |
| `search` | `String` | No | Search keys, source text, translations, and contexts |
| `limit` | `Int` | No | Maximum number of rows to return, capped at 500 |

## Field reference

The Translation Manager object exposes these fields:

| Field | Type | Description |
|---|---|---|
| `id` | `Int` | Translation row ID |
| `key` | `String` | Translation key |
| `source` | `String` | Source text |
| `translation` | `String` | Translated text |
| `category` | `String` | Craft translation category |
| `language` | `String` | Language code |
| `context` | `String` | Translation context |
| `status` | `String` | Translation status |
| `origin` | `String` | Translation origin |
| `usageCount` | `Int` | Usage count |
| `lastUsed` | `String` | Last-used datetime |

## Troubleshooting

### `Cannot query field "translationManagerTranslate" on type "Query"`

The schema does not allow Translation Manager GraphQL queries. Enable `Query Translation Manager data` on the schema, then retry.

### The query returns `null`

GraphQL is read-only and does not auto-create missing rows. Generate or capture the translation in Translation Manager first, then query the same `key`, `category`, and `language`.

### A frontend needs all messages for a language

Query `translationManagerTranslations(language: "...", status: "translated", limit: 500)` and group the response by `category` and `key` in the frontend. For larger catalogs, request by category or search term rather than asking for every row at once.
