# ENLIXO Tutor UI standard

Every Tutor page follows the ENLIXO V3 look: Bootstrap 5 for layout, the app's own classes from
`assets/css/style.css` for colour and type, and a few shared `ex-` classes from `assets/css/enlixo-ui.css`.
New screens are built from the shared kit in `assets/js/enlixo/components` so buttons, text and spacing
come out the same everywhere. Don't add one-off sizes or colours in a page.

## Page skeleton

| Part | Use |
|---|---|
| Header card | `enlixo_form_header($title, $crumbs, $guide, $actionsId)` (forms, settings) or `enlixo_page_header()` (lists). Title, (i) quick guide, breadcrumb, buttons on the right. |
| Content card | `<div class="component-theme p-4">`. Cards sit `mb-4` apart on the grey page. |
| Stat cards | Row of `col-12 col-md-6 col-xl-3`, each `component-theme px-4 py-3`: `.ex-stat-num`, then `text-14 main-text` title, then `text-12 sub-text` note. |
| Settings layout | One `component-theme` card: left menu `.ex-side-nav` (`.ex-side-group` headings, `.ex-side-item` buttons, `.active`), right panel `p-4`. |
| Lists | `DataView` (search, filters, export, per page, table). |

## Text

| Size | Class | For |
|---|---|---|
| 18px, 600 | `main-topic` / `.ex-panel-title` / `.ex-modal-title` | Page, panel and modal titles |
| 18px, 500, blue-grey | `sub-topic` | Form section titles |
| 14px | `text-14 main-text` | Labels, table cells, body text |
| 12px | `text-12 sub-text` | Help lines, subtitles, counts |
| 12px red | `text-12 text-dark-red` | Field errors (under the field) |

Required labels get the `required` class (red star). Section and panel titles have an (i).
Field labels get an (i) **only when the field is hard to understand** (exam year, batch code, how students join).
Easy fields (subject, medium, hall, description) have none. The (i) opens a small card with two lines:
"What it does" and "What to enter". In a schema field that is `info` and `enter`; in JS `fieldInfo(info, label, enter)`.

## Buttons

| Height | Classes | For |
|---|---|---|
| 48px | `custom-btn ex-btn-lg` | The one main action in the page header (Save Class) |
| 40px | `custom-btn ex-btn-md` / `custom-outline-btn ex-btn-md` | Modal footers, panel toolbars (Add, Reset to Default) |
| 32px | `custom-btn ex-btn-sm` | Row actions in tables (Edit) |

* One filled (`custom-btn`) button per area: the main action. Everything else is outline (`custom-outline-btn`).
* Danger: `ex-btn-danger-fill` on the button (red) after a `BsModal.confirm`.
* Small text links (Add new, Crop again): `.ex-link-add`.
* In JS use `BsModal.button(text, { primary, icon, danger, onclick })`, which applies the rules above.

## Forms

* `FormBuilder` draws fields from a server schema: grid `row gx-4 gy-3`, fields `col-12 col-md-6 col-lg-4`,
  inputs `form-control custom-input` / `form-select custom-select` (48px).
* Sections are `mt-5` apart. Errors show under the field and the field gets `is-invalid`.
* Pictures go through `ImageCropper.open()` (16:9 crop, zoom, rotate, flip, preview).

## Icons

One icon set everywhere: **Solar** (outline for normal, bold-duotone for the active menu group), served from our own
files, no CDN. Use the names in `assets/icons/icon-map.json`, never a raw icon from another set.

* JS: `icon('edit', { size: 20 })` from `assets/js/enlixo/core/icons.js`.
* PHP: `enlixo_icon('edit', 20)` from `includes/enlixo/icons.php`.
* New icon: add the name to `icon-map.json`, then run `node bin/build-icons.mjs <path to @iconify-json/solar/icons.json>`.

Sizes: 18 in text and buttons, 20 in menus, 22 in the side menu, 24 in stat cards.

## Action menus

`ActionMenu.button('More', items)` in a header, `ActionMenu.kebab(items)` (three dots) in a row or card.
White card, 10px corners, soft shadow, one outline icon and a short label per row, thin lines between rows.
The risky item (Deactivate, Delete) is last and red, and asks with `BsModal.confirm` first. The menu opens below the
button, or above it when there is no room.

## Views and ordering

A list that people browse (Classes) offers **Cards**, **Table** and **Details** with a segmented switch; the choice is
remembered on the device. Drag and drop to change the order works only in "My order" with no search or filters, and
the handle also moves with the arrow keys. Save the order on its own and show "Order saved."

## Modals

`BsModal.open({ title, subtitle, body, size, footerStart, footerEnd })`: title + grey subtitle, body,
footer with secondary actions on the left and Cancel + main action on the right. Confirm with `BsModal.confirm()`.

## Tables

`table ex-v3-table align-middle`: grey header row (`--table-header-color`), 14px cells, thin `--line-color` lines.
Switches in tables are Bootstrap `form-switch`. Locked items show the lock icon (`.ex-lock`). Turned-off rows use
`.ex-row-off` (faded).

## Loading, empty and error states

* Loading: Bootstrap `placeholder-glow` blocks in the shape of the page.
* Empty: one line of `text-14 sub-text`, centred, saying what to do next.
* Error: message + `custom-btn ex-btn-md` "Try again".
* Feedback after an action: `Toast.success()` / `Toast.error()` (top right).

## Words

Short, plain English for tutors: "Add your own field", not "Create custom attribute". Say what happens
("Only you see it"), not how it works.
