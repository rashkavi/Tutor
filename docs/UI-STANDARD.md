# ENLIXO Tutor UI standard

Every Tutor page follows the ENLIXO V3 look: Bootstrap 5 for layout, the app's own classes from
`assets/css/style.css` for colour and type, and a few shared `ex-` classes from `assets/css/enlixo-ui.css`.
New screens are built from the shared kit in `assets/js/enlixo/components` so buttons, text and spacing
come out the same everywhere. Don't add one-off sizes or colours in a page.

## No borders

Components have **no borders**: cards, list rows, panels, menus, popovers, switchers, the side menu and tool buttons
are white (or light grey) blocks on the grey page, separated by space and, where needed, a soft shadow.
Only form inputs keep their 1px line, and menus and tables keep thin dividers between rows.
Don't add `border: 1px` to a new component; use a background change for hover and a blue ring for focus.

## Page skeleton

| Part | Use |
|---|---|
| Header card | `enlixo_form_header($title, $crumbs, $guide, $actionsId)` (forms, settings) or `enlixo_page_header()` (lists). Title, (i) quick guide, breadcrumb, buttons on the right. |
| Content card | `<div class="component-theme p-4">`. Cards sit `mb-4` apart on the grey page. |
| Stat cards | Always `StatCard.row([...])` (`components/StatCard.js`), see **Stat cards** below. |
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

**One size.** Every button is **48px high with 15px text**, the same height as the input boxes, search box, filter
button and count pill, so a toolbar lines up like V3. `style.css` sets this for `custom-btn` / `custom-outline-btn`
on every page (old pages too); `ex-btn-lg` and `ex-btn-md` are the same size and only kept so older code still works.

| Height | Classes | For |
|---|---|---|
| 48px | `custom-btn` / `custom-outline-btn` (+ `ex-btn-md`) | Every button: page header, toolbars, forms, modals |
| 32px | `custom-btn ex-btn-sm` / `custom-outline-btn ex-btn-sm` | Only inside a table row or a small panel tool (View, Edit, Expand all) |

* One filled (`custom-btn`) button per area: the main action. Other actions are outline (`custom-outline-btn`):
  white, a 1px blue line, blue text. Cancel / Close / Previous / Next / clear use the grey V3 secondary
  `custom-btn-outline`. The older `ex-btn` looks the same now.
* Danger: `custom-danger-btn` (red), approve: `custom-success-btn` (green), after a `BsModal.confirm`.
* Small text links (Add new, Crop again): `.ex-link-add`.
* In JS use `BsModal.button(text, { primary, icon, danger, onclick })`, which applies the rules above.
* The size lives in one place: `--ex-control-h` and `--ex-row-btn-h` in `enlixo-ui.css`. Don't set a button height in a page.

## Stat cards

One card for every summary number, like the V3 dashboard: icon tile + label + ⋯ menu, big number, grey caption,
and an optional mini bar chart (one series; the last bar, "now", is dark blue).

```js
StatCard.row([
  { icon: 'application', label: 'Applications', value: 8, caption: '3 this month vs last month',
    trend: StatCard.trend(thisMonth, lastMonth), bars: [{ label: 'Mon 3 Nov', value: 1 }, ...],
    menu: [{ label: 'Show all', icon: 'filter', onClick }], onClick, selected },
], { cols: 'col-12 col-md-6 col-xl-3' });   // 4 per row; use col-xl-4 for 3 per row
```

* Label in Title Case, caption one short line ("Waiting for a first look").
* `onClick` when the card can filter the list under it (Requested Students); the chosen card gets a blue underline.
* Only give a ⋯ menu when it has something useful (show these, open the page, copy the link). No empty menus.

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

## Lists, views and ordering

Lists follow V3 Branches. The default view is **rows**: one white row per item with the ⋮⋮ handle on the left,
a small picture, a title, one or two grey lines under it, then the orange **Actions** dropdown (`.ex-btn-actions`)
and the blue **View** button on the right. Above the list: the count on the left; export and the view switcher
(icon-only squares, the chosen one filled blue, `.ex-view-switch`) on the right. Other views: **table** (many items)
and **grid** (picture first). The chosen view is remembered on the device.

Changing the order:
* Only by dragging the ⋮⋮ handle (`drag-dots` icon), never the whole card. The other items move aside while you drag,
  so what you see when you let go is the new order. The arrow keys move a focused handle too.
* One plain line says how: "To change the order, hold ⋮⋮ and drag a class up or down."
* With a search or filter on, the handles are hidden and the line says to clear them. No separate sort control.
* Save on drop and show "Order saved."

## Filters and the table toolbar

Every list uses the V3 `table_toolbar()` layout, built by `DataView` (and by hand on Classes with the same classes):
`[ N Things ] [ search ............ ] [ filter ] [ Export Report ]`, then Results Per Page on the right and, when rows
can be ticked, "Update ticked ..." + Apply in its own row.

* Count tile: `custom-div` with the number ("12 Students", "3 of 4 Classes" while filtering).
* Search: `bl-solid ex-search-box` with the search icon; 300 ms after the last key, never per key press.
* Filter button: filled `custom-btn ex-filter-btn` with the `sliders` icon; a small arrow shows open / closed and a red
  `tf-badge` counts the filters in use. It opens the filter row below: a small label over each select
  (`col-12 col-md-4 col-lg-2`, first option "- All … -"), and a grey `custom-btn-outline tf-clear` refresh button
  at the end, enabled only while a filter is set. The row starts open when a filter is already set.
* Export Report: `custom-btn` + download icon, exports every matching row (not only the page).

## Calendar

The time table follows the V3 Lecture Schedule: Previous / Next buttons in the pager style
(`bl-solid br-8 component-theme previous-button | next-button`) around the month name,
a short legend of the status badges, a 7-column month grid (days of the other months greyed, today ringed
in blue with a "Today" tag), each lecture a small white card with a coloured left line, its time and type,
the class name and an outlined status badge (`.ex-cal-badge.ex-st-*`: live, upcoming, done, cancelled,
postponed). At most 3 per day, then "+N more". Pointing at a lecture shows a hover card (date, time, tutor,
class chips, status); clicking opens the details. Clicking a day opens the day with "Schedule a Lecture".

## Dialogs

One design, the V3 Finance dialog (`.fin-dlg`): header = icon tile + title + grey sub line (pieces joined by dots)
+ round close icon; the body scrolls; footer bar with Cancel / Close on the left of the main button.

* `BsModal.open({ title, subtitle, icon, tone, body, size, footerStart, footerEnd })`. `icon` is a Solar name
  (`edit`, `plus`, `link`, `crop` ...), `tone` = `success` / `danger` / `warning` colours the tile.
  `footerStart` goes to the far left of the bar, `footerEnd` to the right (Cancel first, main button last).
* Buttons: `BsModal.button(text, { primary, danger, success })` -> `custom-btn` / `custom-danger-btn` /
  `custom-success-btn` (approve) / `custom-btn-outline` (Cancel).
* Questions: `BsModal.confirm({ title, text, yes, danger | success })`, the V3 `FIN.ask` card (round icon, title, text).

## Tables and pager

`table ex-v3-table align-middle` inside `table-responsive-wrapper > table-responsive`: grey header row
(`--table-header-color`), 14px cells, thin `--line-color` lines. Cells don't wrap and a wide table scrolls sideways
inside its own box (V3 rule); give a cell that may wrap `cell-wrap`, and a settings table that must fit its card `ex-fit`.
Tick column: 18px `form-check-input`. One row action = small `custom-btn ex-btn-sm`, more go into the ⋯ menu.
Switches are Bootstrap `form-switch`. Locked items show the lock icon (`.ex-lock`). Turned-off rows use `.ex-row-off`.

The pager (`Pagination.js`) sits inside the wrapper under the table, outside the scrolling part:
Previous (`previous-button`) on the left, numbered `page-item`s in the middle, Next (`next-button`) on the right.

## Loading, empty and error states

* Loading: Bootstrap `placeholder-glow` blocks in the shape of the page.
* Empty: `EmptyState.empty({ title, text, action })` = V3 `.empty-state`: the `inbox` icon in a soft circle, a bold
  title and one grey line. In a table it is a row inside `tbody`. With a search or filter on: "No matching results"
  + "Try changing the search or filters." and a Clear button.
* Error: message + `custom-btn` "Try again".
* Feedback after an action: `Toast.success()` / `Toast.error()` (top right).

## Side menu and header

Like V3 the side menu is open by default on desktop; the menu button in the header slides it out
(`#sideMenu.collapsed`, remembered in `localStorage.menuVisible`, `assets/js/script.js`). The header uses Solar icons
through `enlixo_icon()` only (menu, sun / moon, check, settings, edit, logout, devices).

## Info tips

The (i) next to a page title (`data-quick-guide`, `QuickGuide.attachAll()`), a section title and a field label
(`fieldInfo()` in `FormBuilder.js`) all open the same V3 card on hover, focus or tap: dark-blue header with the
title and "Quick guide" / "Field guide", then the text and a checklist of points.

## Words

Short, plain English for tutors: "Add your own field", not "Create custom attribute". Say what happens
("Only you see it"), not how it works.
