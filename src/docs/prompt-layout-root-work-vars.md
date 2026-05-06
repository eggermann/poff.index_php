# Prompt Layout Root And Work Vars

This guide explains the variable split used by layout prompt generation.

## Why The Split Exists

Layout prompts need two different layers of data:

1. Root layout vars for the outer wrapper and shell
2. Work vars for the inner content and nested item data

Without this split, the model tends to mix the page shell title with the inner item title.

## Root Layout Vars

Use `root.*` for the outer wrapper or page shell.

Typical root vars:

- `root.title`
- `root.folderName`
- `root.path`
- `root.slug`
- `root.description`
- `root.type`

Use `root.title` for the visible wrapper title, such as `dominikeggermann.com` in the root folder layout.

## Work Vars

Use `work.*` for the item that lives inside the wrapper.

Typical work vars:

- `work.title`
- `work.name`
- `work.path`
- `work.slug`
- `work.description`
- `work.type`
- `work.kind`
- `work.categories`
- `work.fields`

Use `work.title` for the nested item title, such as `tests` when the folder is the work being rendered.

## Layout Prompt Rule

For layout generation:

- root vars decide the wrapper title, shell copy, and page framing
- work vars decide the nested content title and item-level copy
- `current.outerWrapper` remains the structural reference for the active outer layout

Recommended mental model:

- `root.title` = the page wrapper title
- `work.title` = the work/item title inside that wrapper

## Example

If the root folder is `dominikeggermann.com` and the current work is `tests`:

- `root.title` should stay `dominikeggermann.com`
- `work.title` should describe the nested work item, such as `tests`

Example prompt-context JSON:

```json
{
  "root": {
    "title": "dominikeggermann.com"
  },
  "work": {
    "title": "tests"
  }
}
```

## Where The UI Shows This

- The edit layout panel calls this out in the layout summary
- The prompt context panel highlights `root` and `work`
- The prompt system message tells the model to treat `root.*` and `work.*` separately
