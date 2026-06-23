# Managing translations

Do the day-to-day translation work in **Translation Manager → Translations**: find the strings that need attention, translate them, track who reviewed what, and watch your coverage climb. This is where your translators spend their time — no code required.

## What you'll use it for

- Finding and translating pending strings across your sites
- Searching by source text, translation, or context
- Running an approval step so drafts are signed off before they publish
- Keeping an eye on coverage and the work still in the queue

![The Translations list in the Translation Manager Control Panel](images/managing-translations-list.webp)

## Translate a string

1. Go to **Translation Manager → Translations**.
2. Narrow the list with the **filter** dropdown — by **Status** (All, Pending, Draft, Translated, Unused), **Type** (All, Forms, Site), or **Origin** (All Origins, Manual, Import, System) — or **search** by source text, translated text, or context.
3. Type the translation into the input field. Right-to-left languages display with the correct text direction automatically.
4. Save with **Save All Changes**, the **Ctrl/Cmd + S** shortcut, or by turning on **Enable Auto-Save** in settings (with a configurable delay).

## Statuses and origins

Two columns tell you where each string stands.

**Status** is the translation's progress:

| Status | Meaning |
|--------|---------|
| **Pending** | No translation yet — this is the queue. New auto-captured strings start here. |
| **Draft** | Translated, but awaiting approval (only when [approval](#approval-workflow) is on). |
| **Translated** | Done and published — Craft will serve it. |
| **Unused** | The template or form behind it is gone. [Template Capture](../feature-tour/maintenance.md#template-capture) marks these; saving text won't revive an unused row. |

**Origin** is where the string came from:

| Origin | Set when |
|--------|----------|
| **Manual** | You saved it in the Control Panel. |
| **Import** | It arrived through a CSV or PHP [import](../feature-tour/import-export.md). |
| **System** | It was captured automatically at runtime — see [Auto-capture](basic-usage.md#auto-capture-missing-strings). |

The **Created By**, **Reviewed By**, and **Reviewed At** columns track who last edited a string, who approved it, and when. They're hidden by default — use the column toggle to show them when you need an audit trail.

## Approval workflow

For teams where one person translates and another signs off, turn on **Require Approval Before Publish** in **Settings → General**. Once it's on:

- A translator **without** the *Approve Translations* permission saves to **Draft** instead of Translated — nothing goes live yet.
- An **approver** (with *Approve Translations*) saves straight to **Translated**, and their name and the time are stamped into **Reviewed By** / **Reviewed At**.

To move several strings at once, select them and use the **Set status** bulk menu:

- **Mark Draft** — send strings back for more work (available to anyone who can edit).
- **Mark Translated** — publish them (only approvers see this when approval is required).

Leave **Require Approval Before Publish** off for a single-editor workflow where every save publishes immediately. The setting pairs with the *Approve Translations* permission — see [Permissions](../developers/permissions.md).

## Delete unused

Remove translations that are no longer referenced:

1. Filter by **Status → Unused**.
2. Select the translations to remove.
3. Open the **Actions** menu and click **Delete**.

For larger sweeps — cleaning a whole category, language, or provider at once — use the [Maintenance](../feature-tour/maintenance.md) screen, which takes a backup first.

## Monitor coverage

Craft's **Utilities** section includes a Translation Manager panel that shows where you stand at a glance:

![The Translation Manager statistics utility](images/managing-translations-statistics.webp)

- **Translation Coverage** — the percentage translated, with a status badge (Needs Attention → In Progress → Good → Complete — or *No translations found* when there's nothing to count yet)
- **Work Queue** — how many translations are still **Pending**, plus the **Unused** count
- **Translation Types** — the split between **Forms** (Formie + Freeform) and **Site** strings

Use the **language selector** at the top to switch which language's numbers you're looking at — the same mapped languages as the translations list, so the counts line up with the rows there — and the quick links to jump straight into managing, importing, backing up, or maintenance.

## Tips

1. **Filter before you translate** — narrow to **Pending** for your language and work the queue down.
2. **Use approval for handoffs** — Draft → Translated keeps unreviewed copy off the live site.
3. **Watch coverage** — the Utilities panel is the fastest read on what's left.
4. **Back up before big changes** — bulk cleanup and deletes run from [Maintenance](../feature-tour/maintenance.md), which backs up first.
