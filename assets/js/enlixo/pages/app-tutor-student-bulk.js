// Tutor → Students → Upload Students Bulk (tutor/upload-student-bulk.php), the ENLIXO V3 bulk upload:
// step strip -> choose the class and the file -> Check File (every row Ready / Problem, nothing saved) -> Import.
// Columns come from the tutor's student form (GET /tutor/students/bulk), rows are checked by the same rules as
// Add New Student (POST /tutor/students/bulk action=check), Import saves only the ready rows (action=import).
import { h, fill, download, toCsv } from '../core/dom.js';
import { ApiClient, ApiError, FeatureLockedError } from '../core/ApiClient.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { StatCard } from '../components/StatCard.js';
import { EmptyState } from '../components/EmptyState.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';
import { fieldInfo } from '../components/FormBuilder.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('bulk-root');
const TEMPLATE = (fmt) => `${api.baseUrl}/tutor/students/bulk/template?format=${fmt}`;
const size = (b) => (b < 1024 ? `${b} B` : b < 1048576 ? `${(b / 1024).toFixed(1)} KB` : `${(b / 1048576).toFixed(2)} MB`);
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

let info = null;
let gate = null;
let chosen = null; // File
let last = null; // check result
let filter = 'all';
const el = {}; // the parts that change

QuickGuide.attachAll();
document.getElementById('bulk-actions').append(
  h('a', { href: 'student-form-settings', class: 'ex-gear', 'aria-label': 'Change the columns (Student Form settings)', title: 'Change the columns (Student Form settings)' }, icon('settings', { size: 26 })),
  h('a', { href: TEMPLATE('xlsx'), class: 'custom-btn ex-btn-lg px-4 text-decoration-none d-inline-flex align-items-center gap-2', download: '' }, icon('download', { size: 20 }), 'Download Template'),
);

fill(root, h('div', { class: 'col-12 component-theme p-4 placeholder-glow' }, [70, 45, 85, 60].map((w) => h('span', { class: 'placeholder col-12 mb-3', style: { width: `${w}%`, height: '40px' } }))));
load();

async function load() {
  try {
    [gate, info] = await Promise.all([FeatureGate.load(api).catch(() => null), api.get('/tutor/students/bulk').then((r) => r.data)]);
  } catch (e) {
    fill(root, h('div', { class: 'col-12 component-theme p-4' }, EmptyState.error(e.message, load)));
    return;
  }
  if (!info.classes.length) {
    fill(root, h('div', { class: 'col-12 component-theme p-4' }, EmptyState.empty({
      icon: 'classes', title: 'Create a class first', text: 'Every student joins one of your classes, and you have no active class yet.',
      action: h('a', { href: 'create-class', class: 'custom-btn px-4 text-decoration-none' }, 'Create a Class'),
    })));
    return;
  }
  fill(root, steps(), uploadCard(), resultCard(), helpCard());
}

// ---------- 1. step strip ----------
function steps() {
  const step = (n, title, sub) => h('li', { class: `bu-step${n === 1 ? ' is-active' : ''}`, 'data-step': n },
    h('span', { class: 'bu-step-num' }, String(n)),
    h('span', { class: 'bu-step-text' }, h('span', { class: 'bu-step-title' }, title), h('span', { class: 'bu-step-sub' }, sub)));
  return h('div', { class: 'col-12 component-theme px-4 py-3 mb-4' },
    el.steps = h('ol', { class: 'bu-steps', 'aria-label': 'Upload steps' },
      step(1, 'Choose the file', 'Excel or CSV made from the template'),
      step(2, 'Check the rows', 'Nothing is saved yet'),
      step(3, 'Import', 'Ready rows join the class')));
}

function setStep(n) {
  el.steps.querySelectorAll('.bu-step').forEach((s) => {
    const k = Number(s.dataset.step);
    s.classList.toggle('is-done', k < n);
    s.classList.toggle('is-active', k === n);
  });
}

const cardHead = (ico, title, text) => h('div', { class: 'bu-card-head' },
  h('span', { class: 'bu-card-icon' }, icon(ico, { size: 22 })),
  h('div', { class: 'min-w-0' }, h('span', { class: 'sub-topic' }, title), h('p', { class: 'text-14 sub-text mb-0 mt-1' }, text)));

// ---------- 2. class + file ----------
function uploadCard() {
  const want = new URLSearchParams(location.search);
  const pre = want.get('class') ?? want.get('class-id');
  const preset = info.classes.find((c) => c.value === pre) ?? (info.classes.length === 1 ? info.classes[0] : null);
  el.cls = h('select', { class: 'form-select custom-select mt-2', id: 'buClass', onchange: () => { el.clsErr.textContent = ''; el.cls.classList.remove('is-invalid'); hideResult(); } },
    h('option', { value: '', disabled: true, selected: !preset }, '- Select -'),
    info.classes.map((c) => h('option', { value: c.value, selected: preset?.value === c.value }, c.label)));
  el.clsErr = h('div', { class: 'text-12 text-dark-red mt-1' });

  el.input = h('input', { type: 'file', class: 'd-none', accept: '.xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', onchange: () => { const f = el.input.files?.[0]; if (f) setFile(f); el.input.value = ''; } });
  const browse = () => el.input.click();
  const keys = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); browse(); } };
  el.drop = h('div', { class: 'uf-upload-drop', role: 'button', tabindex: '0', 'aria-label': 'Choose the file', onclick: browse, onkeydown: keys },
    h('span', { class: 'uf-upload-icon' }, icon('upload', { size: 26 })),
    h('span', { class: 'uf-upload-text' }, 'Drag & drop the file here or ', h('span', { class: 'uf-upload-browse' }, 'Browse File')),
    h('span', { class: 'uf-upload-hint' }, `Excel (.xlsx) or CSV · up to 5 MB · up to ${info.max_rows} students`));
  el.fileName = h('div', { class: 'uf-file-name' });
  el.fileMeta = h('div', { class: 'uf-file-meta' });
  el.fileState = h('span', { class: 'uf-file-state is-ready' });
  el.card = h('div', { class: 'uf-file-card d-none' },
    h('span', { class: 'uf-file-type' }, icon('tutes', { size: 22 })),
    h('div', { class: 'uf-file-body' }, el.fileName, el.fileMeta),
    el.fileState,
    h('div', { class: 'uf-file-actions' },
      h('button', { type: 'button', class: 'uf-file-btn', title: 'Choose another file', 'aria-label': 'Choose another file', onclick: browse }, icon('refresh', { size: 18 })),
      h('button', { type: 'button', class: 'uf-file-btn is-danger', title: 'Remove the file', 'aria-label': 'Remove the file', onclick: clearFile }, icon('delete', { size: 18 }))));
  el.fileErr = h('div', { class: 'text-12 text-dark-red' });
  const box = h('div', { class: 'uf-upload h-100' }, el.drop, el.card, el.fileErr, el.input);
  ['dragenter', 'dragover'].forEach((t) => box.addEventListener(t, (e) => { e.preventDefault(); box.classList.add('drag-over-effect'); }));
  ['dragleave', 'drop'].forEach((t) => box.addEventListener(t, (e) => { e.preventDefault(); if (t === 'drop' || !box.contains(e.relatedTarget)) box.classList.remove('drag-over-effect'); }));
  box.addEventListener('drop', (e) => { const f = e.dataTransfer?.files?.[0]; if (f) setFile(f); });

  const required = info.columns.filter((c) => c.required).length;
  const tip = (ico, ...text) => h('li', null, icon(ico, { size: 18 }), h('span', null, ...text));
  el.check = h('button', { type: 'button', class: 'custom-btn w-100', disabled: true, onclick: (e) => check(e.currentTarget) }, 'Check File');

  return h('div', { class: 'col-12 component-theme p-4 mb-4' },
    cardHead('upload', 'Choose the class and the file', 'Pick the class the students join, then drop the file. The file is checked first; nothing is saved until you press Import.'),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-lg-8 d-flex flex-column gap-4' },
        h('div', null,
          h('div', { class: 'd-flex align-items-center gap-1' },
            h('label', { class: 'text-14 main-text required', for: 'buClass' }, 'Class'),
            fieldInfo('Every student in the file joins this class. Students already registered with you or with another tutor are added to it, not registered again.', 'Class', 'Pick one of your active classes. To add students to two classes, upload the file once for each class.')),
          el.cls, el.clsErr),
        h('div', { class: 'flex-grow-1 d-flex flex-column' },
          h('div', { class: 'd-flex align-items-center gap-1 mb-2' },
            h('span', { class: 'text-14 main-text required' }, 'Student file'),
            fieldInfo('The Excel or CSV file with one student per row, made from the template.', 'Student file', 'Download the template, fill it in Excel or Google Sheets and save it. The first row must keep the column names.')),
          box)),
      h('div', { class: 'col-12 col-lg-4' },
        h('div', { class: 'bu-tips h-100' },
          h('div', { class: 'text-14 main-text fw-semibold mb-3' }, 'Before you upload'),
          h('ul', { class: 'bu-tip-list' },
            tip('download', 'Start from the ', h('a', { href: TEMPLATE('xlsx'), download: '' }, 'Excel template'), ' (or the ', h('a', { href: TEMPLATE('csv'), download: '' }, 'CSV template'), ') and keep its header row.'),
            tip('check', `The template has ${plural(info.columns.length, 'column')}; ${required} must be filled for every student.`),
            tip('users', 'One student per row. Column order does not matter.'),
            tip('whatsapp', 'Every student needs their own WhatsApp number. Students already registered are added to the class, not registered twice.'),
            tip('settings', 'The columns come from your Student Form. Change them with the gear icon.'),
            info.students.limit != null ? tip('crown', `Your package: ${info.students.count} of ${info.students.limit} students used.`) : null)))),
    h('div', { class: 'row p-0 m-0 g-0 justify-content-end mt-4' },
      h('div', { class: 'col-12 col-md-6 col-lg-3' }, el.check)));
}

function setFile(f) {
  el.fileErr.textContent = '';
  if (!/\.(csv|xlsx)$/i.test(f.name || '')) { el.fileErr.textContent = 'Choose an Excel file (.xlsx) or a CSV file (.csv). Older .xls files: open them in Excel and use Save As -> Excel Workbook.'; return; }
  if (f.size > info.max_bytes) { el.fileErr.textContent = 'The file is larger than 5 MB. Split it into smaller files.'; return; }
  if (!f.size) { el.fileErr.textContent = 'The file is empty. Fill the students under the header row and try again.'; return; }
  chosen = f;
  el.fileName.textContent = f.name;
  el.fileName.title = f.name;
  const kind = /\.xlsx$/i.test(f.name) ? 'Excel' : 'CSV';
  fill(el.fileMeta, h('span', null, size(f.size)), h('span', { class: 'uf-dot' }), h('span', null, kind));
  state('ready');
  el.drop.classList.add('d-none');
  el.card.classList.remove('d-none');
  el.check.disabled = false;
  hideResult();
}

function state(kind) {
  const [cls, text] = { ready: ['is-ready', 'Ready to check'], checked: ['is-saved', 'Checked'] }[kind];
  el.fileState.className = `uf-file-state ${cls}`;
  fill(el.fileState, icon('check', { size: 14 }), h('span', null, text));
}

function clearFile() {
  chosen = null;
  el.card.classList.add('d-none');
  el.drop.classList.remove('d-none');
  el.check.disabled = true;
  hideResult();
}

function hideResult() {
  last = null;
  el.result?.classList.add('d-none');
  if (el.steps) setStep(1);
}

function body(action) {
  const fd = new FormData();
  fd.append('file', chosen);
  fd.append('class_id', el.cls.value);
  fd.append('action', action);
  return fd;
}

function busy(btn, on, text) {
  btn.disabled = on;
  if (on) { btn.dataset.text = btn.textContent; btn.textContent = text; } else if (btn.dataset.text) btn.textContent = btn.dataset.text;
}

async function check(btn) {
  if (!el.cls.value) {
    el.cls.classList.add('is-invalid');
    el.clsErr.textContent = 'Choose the class these students join.';
    el.cls.focus();
    return;
  }
  if (!chosen) return;
  busy(btn, true, 'Checking…');
  try {
    const { data } = await api.post('/tutor/students/bulk', body('check'));
    filter = data.problems ? 'problem' : 'all';
    render(data);
    el.result.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (e) {
    showError(e);
  } finally {
    busy(btn, false);
  }
}

function showError(e) {
  if (e instanceof FeatureLockedError) { gate?.openUpgrade(e.details); return; }
  const fields = e instanceof ApiError ? e.details?.fields : null;
  if (fields?.class_id) { el.cls.classList.add('is-invalid'); el.clsErr.textContent = fields.class_id; }
  if (fields?.file) el.fileErr.textContent = fields.file;
  Toast.error(fields ? Object.values(fields)[0] : e.message);
}

// ---------- 3. result ----------
function resultCard() {
  el.stats = h('div');
  el.found = h('div', { class: 'bu-columns mb-3' });
  el.banner = h('div');
  el.tabs = h('ul', { class: 'nav nav-tabs mb-3', role: 'tablist' });
  el.rows = h('tbody');
  el.cut = h('div', { class: 'text-12 sub-text mt-2' });
  el.note = h('div', { class: 'text-14 sub-text' });
  el.fix = h('button', { type: 'button', class: 'custom-btn-outline d-inline-flex align-items-center justify-content-center gap-2', onclick: downloadProblems,
    title: 'Download only the rows with problems, with a Problems column, to fix and upload again' }, icon('download', { size: 20 }), 'Problem Rows');
  el.recheck = BsModal.button('Check Again', { onclick: (e) => check(e.currentTarget) });
  el.import = h('button', { type: 'button', class: 'custom-btn', onclick: (e) => runImport(e.currentTarget) }, 'Import');
  const th = (text, first) => h('th', { class: `${first ? 'px-4 ' : ''}py-3 bg-table-header text-14 main-text fw-medium` }, text);
  el.result = h('div', { class: 'col-12 component-theme p-4 mb-4 d-none' },
    cardHead('attendance', 'Check and import', 'Every student in the file and what the check found. Fix the rows with problems and check again, or import the ready rows now.'),
    el.stats, el.found, el.banner, el.tabs,
    h('div', { class: 'table-responsive-wrapper' }, h('div', { class: 'table-responsive' },
      h('table', { class: 'table bl-solid m-0 p-0 g-0 ex-fit' },
        h('thead', null, h('tr', { class: 'br-t-5' }, th('Row', true), th('Student'), th('WhatsApp'), th('Result'), th('Details'))),
        el.rows))),
    el.cut,
    h('div', { class: 'bu-import-bar mt-4' }, el.note, h('div', { class: 'bu-import-actions' }, el.fix, el.recheck, el.import)));
  return el.result;
}

const kindOf = (r) => (r.kind === 'problem' ? 'problem' : 'ready');

function render(j) {
  last = j;
  el.result.classList.remove('d-none');
  fill(el.stats, StatCard.row([
    { icon: 'tutes', label: 'Students in the File', value: j.total, caption: j.too_many ? `only the first ${j.checked} are read` : 'rows with a student' },
    { icon: 'user-check', label: 'Ready to Import', value: j.ready, caption: j.existing ? `${j.existing} already registered` : 'will be saved' },
    { icon: 'user-add', label: 'New Students', value: j.new, caption: j.room != null ? `${j.room} more allowed by your package` : 'new accounts' },
    { icon: 'danger', label: 'Rows with Problems', value: j.problems, caption: j.problems ? `${plural(j.problem_count, 'problem')} · will be skipped` : 'nothing to fix' },
  ]));

  fill(el.found, h('span', { class: 'bu-columns-label' }, 'Columns found'),
    j.columns.map((c) => h('span', { class: 'bu-chip', title: c.label }, c.header)),
    j.unknown.length ? h('span', { class: 'bu-columns-note' }, 'Not used (not a column of your form): ', j.unknown.map((u) => h('span', { class: 'bu-chip is-warn ms-1' }, u))) : null);

  const notes = [];
  if (j.missing.length) notes.push(['danger', h('span', null, h('b', null, 'Required columns are missing: '), `${j.missing.join(', ')}. Add them to the header row (download the template to see the names) and check again.`)]);
  if (j.room != null && j.adds > j.room) notes.push(['danger', h('span', null, h('b', null, 'Over your package limit: '), `this file adds ${plural(j.adds, 'student')} and your package allows ${j.room} more. Remove some rows or upgrade your package.`)]);
  if (j.too_many) notes.push(['warning', h('span', null, h('b', null, 'Only the first rows are read: '), `the file has ${j.total} students; up to ${info.max_rows} can be uploaded at once. Put the rest in a second file.`)]);
  fill(el.banner, notes.map(([tone, text]) => h('div', { class: `sbu-missing is-${tone} mb-3` }, icon('danger', { size: 20 }), text)));

  const canImport = j.ready > 0 && !j.missing.length && !(j.room != null && j.adds > j.room);
  el.import.disabled = !canImport;
  el.import.textContent = canImport ? `Import ${plural(j.ready, 'Student')}` : 'Nothing to Import';
  el.fix.classList.toggle('d-none', !j.problems);
  el.note.textContent = canImport
    ? `${j.ready} will join ${selectedClass()}.${j.problems ? ` ${plural(j.problems, 'row')} with problems will be skipped.` : ''} Students sign in with their WhatsApp number.`
    : 'Fix the problems in the file, then press Check Again.';
  el.cut.textContent = '';
  tabs();
  rows();
  state('checked');
  setStep(canImport ? 3 : 2);
}

function selectedClass() {
  return el.cls.selectedOptions[0]?.textContent ?? 'the class';
}

function tabs() {
  const counts = { all: last.checked, problem: last.problems, ready: last.ready };
  fill(el.tabs, [['all', 'All Rows'], ['problem', 'Problems'], ['ready', 'Ready']].map(([id, label]) => h('li', { class: 'nav-item', role: 'presentation' },
    h('button', { type: 'button', role: 'tab', class: `nav-link${filter === id ? ' active' : ''}`, 'aria-selected': String(filter === id), onclick: () => { filter = id; tabs(); rows(); } },
      label, h('span', { class: 'tab-count' }, String(counts[id]))))));
}

function rows() {
  const list = last.rows.filter((r) => filter === 'all' || kindOf(r) === filter);
  const empty = filter === 'problem' ? ['No problems', 'Every row of the file is fine.'] : ['No rows here', 'No row of the file is ready yet. Fix the problems and check again.'];
  fill(el.rows, list.length ? list.map(rowHtml) : h('tr', null, h('td', { colspan: 5 }, EmptyState.empty({ title: empty[0], text: empty[1] }))));
}

function rowHtml(r) {
  const chip = r.kind === 'problem'
    ? h('span', { class: 'table-btn table-btn-danger px-3' }, 'Problem')
    : r.kind === 'existing' ? h('span', { class: 'table-btn table-btn-primary px-3' }, 'Joins Class') : h('span', { class: 'table-btn table-btn-success px-3' }, 'Ready');
  const detail = r.kind === 'problem'
    ? r.errors.map((x) => h('div', { class: 'sbu-err' }, h('span', { class: 'sbu-err-col' }, x.column), h('span', { class: 'bu-row-error text-14' }, x.message), x.value ? h('span', { class: 'sbu-err-val', title: 'Value in the file' }, x.value) : null))
    : h('span', { class: 'text-14 sub-text' }, r.note || 'New student');
  return h('tr', null,
    h('td', { class: 'px-4 py-2 align-middle' }, h('span', { class: 'text-14 main-text' }, String(r.row))),
    h('td', { class: 'py-2 align-middle' }, h('div', { class: 'text-14 main-text fw-medium' }, r.name || '-'), r.email ? h('div', { class: 'text-12 sub-text' }, r.email) : null),
    h('td', { class: 'py-2 align-middle' }, h('span', { class: 'text-14 main-text' }, r.whatsapp || '-')),
    h('td', { class: 'py-2 align-middle' }, chip),
    h('td', { class: 'py-2 align-middle cell-wrap' }, detail));
}

/** The rows with problems as a CSV (same columns + Problems), to fix in Excel and upload again. */
function downloadProblems() {
  const bad = last.rows.filter((r) => r.kind === 'problem');
  const csv = toCsv([[...last.headers, 'Problems'], ...bad.map((r) => [...r.cells, r.errors.map((x) => `${x.column}: ${x.message}`).join(' | ')])]);
  download(`${(chosen?.name ?? 'students').replace(/\.(csv|xlsx)$/i, '')} - problem rows.csv`, csv);
}

async function runImport(btn) {
  const ok = await BsModal.confirm({
    title: `Import ${plural(last.ready, 'student')}?`,
    text: `They join ${selectedClass()}.${last.problems ? ` ${plural(last.problems, 'row')} with problems will be skipped.` : ''} This can't be undone from here.`,
    yes: 'Import', icon: 'upload',
  });
  if (!ok) return;
  busy(btn, true, 'Importing…');
  try {
    const { data } = await api.post('/tutor/students/bulk', body('import'));
    el.steps.querySelectorAll('.bu-step').forEach((s) => { s.classList.add('is-done'); s.classList.remove('is-active'); });
    done(data);
  } catch (e) {
    showError(e);
    busy(btn, false);
  }
}

function done(d) {
  const parts = [d.added ? `${plural(d.added, 'new student')} registered` : '', d.joined ? `${plural(d.joined, 'registered student')} added to the class` : ''].filter(Boolean);
  const failed = d.failed.length
    ? h('div', { class: 'sbu-missing is-danger mt-3 text-start' }, icon('danger', { size: 20 }), h('span', null, h('b', null, `${plural(d.failed.length, 'row')} could not be saved: `), d.failed.map((f) => `row ${f.row} (${f.name}): ${f.message}`).join('; ')))
    : null;
  fill(el.result, EmptyState.empty({
    icon: 'user-check',
    title: parts.length ? `${parts.join(' and ')}.` : 'Nothing was saved.',
    text: `${selectedClass()}${d.skipped ? ` · ${plural(d.skipped, 'row')} with problems skipped` : ''}`,
    action: h('div', { class: 'd-flex flex-wrap gap-3 justify-content-center' },
      h('a', { href: `students?class=${encodeURIComponent(el.cls.value)}`, class: 'custom-btn px-4 text-decoration-none' }, 'View Students'),
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => location.reload() }, 'Upload Another File')),
  }), failed);
  Toast.success(parts.length ? `${parts.join(' and ')}.` : 'Nothing was saved.');
}

// ---------- 4. how to fill the file ----------
function helpCard() {
  const note = (tone, ico, title, lines) => h('div', { class: 'col-12 col-lg-6 col-xxl-4' }, h('div', { class: `sbu-note is-${tone} h-100` },
    h('div', { class: 'sbu-note-title' }, h('span', { class: 'sbu-note-icon' }, icon(ico, { size: 18 })), h('span', null, title)),
    h('ul', { class: 'sbu-note-list' }, lines.map((l) => h('li', null, l)))));
  const th = (text, first) => h('th', { class: `${first ? 'px-4 ' : ''}py-3 bg-table-header text-14 main-text fw-medium` }, text);
  return h('div', { class: 'col-12 component-theme p-4' },
    cardHead('tutes', 'How to fill the file', 'The first row holds the column names of the template. Fill one student per row under it.'),
    h('div', { class: 'row g-3 mb-4' },
      note('info', 'info', 'How it works', [
        'Check File reads every row and shows what is wrong. Nothing is saved yet.',
        'Import saves only the ready rows. Rows with problems are skipped, so you can fix them and upload them again.',
        '"Problem Rows" downloads just those rows with a Problems column to fix.',
      ]),
      note('warning', 'whatsapp', 'WhatsApp numbers', [
        'Write 0771234567, or +44... with the country code for a number from another country.',
        'If Excel removed the first 0 (771234567), that is fine: we put it back.',
        'A number already registered (with you or another tutor) adds that student to the class instead of making a new account.',
      ]),
      note('danger', 'calendar', 'Dates and lists', [
        'Dates: 2008-03-15 or 15/03/2008 (day / month / year).',
        'Gender, Relationship and Title: write the name (Female) or its number (2), see the lists below.',
        'Guardian columns: fill them completely, or leave them all empty.',
      ])),
    h('div', { class: 'text-14 main-text fw-semibold mb-2' }, 'Columns of the file ', h('span', { class: 'bu-chip ms-1' }, String(info.columns.length))),
    h('div', { class: 'table-responsive-wrapper mb-4' }, h('div', { class: 'table-responsive' },
      h('table', { class: 'table bl-solid m-0 p-0 g-0 ex-fit' },
        h('thead', null, h('tr', { class: 'br-t-5' }, th('Column', true), th('Needed'), th('What to write'), th('Example'))),
        h('tbody', null, info.columns.map((c) => h('tr', null,
          h('td', { class: 'px-4 py-2 align-middle' }, h('span', { class: 'text-14 main-text fw-medium sbu-col-name' }, c.header)),
          h('td', { class: 'py-2 align-middle' }, h('span', { class: `table-btn ${c.required ? 'table-btn-danger' : 'table-btn-primary'} px-3` }, c.required ? 'Required' : 'Optional')),
          h('td', { class: 'py-2 align-middle cell-wrap' }, h('span', { class: 'text-14 main-text' }, c.help)),
          h('td', { class: 'py-2 align-middle' }, h('span', { class: 'text-14 sub-text' }, c.example || '-')))))))),
    info.lists.length ? h('div', null,
      h('div', { class: 'text-14 main-text fw-semibold mb-2' }, 'Names you can use'),
      h('div', { class: 'row g-3' }, info.lists.map((l) => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('div', { class: 'bu-ref' },
        h('div', { class: 'bu-ref-head' }, icon('view-details', { size: 20 }), h('span', null, l.header), h('span', { class: 'bu-chip ms-auto' }, String(l.choices.length))),
        h('div', { class: 'bu-ref-body' }, l.choices.map((c) => h('div', { class: 'bu-ref-item d-flex justify-content-between gap-2' },
          h('span', { class: 'text-14 main-text' }, c.label),
          c.value !== c.label ? h('span', { class: 'text-12 sub-text' }, `or ${c.value}`) : null)))))))) : null);
}
