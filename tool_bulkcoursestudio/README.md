# tool_bulkcoursestudio

Admin tool half of **Bulk Course Studio**. Lets a Moodle admin build a full
course — metadata, sections, activities, uploaded files, and (via the
companion `mod_bulkcoursestudio` activity) pop-up embedded video links — in
one guided wizard or via a downloadable/re-uploadable Excel template. Both
paths share one preview-before-commit step and one commit engine
(`classes/build_manager.php`) that only calls core Moodle course/module/
completion/file APIs — never direct DB writes — so a built course is
indistinguishable from one assembled by hand.

**Status: feature-complete for v1 scope, Codechecker-clean, with unit test
coverage of the commit engine and the Excel parser.**
`php phpcs_src/bin/phpcs --standard=moodle` reports 0 errors/0 warnings
against this plugin (moodle-cs ruleset).

## What's built

- **Wizard (Flow A)** — `step_coursedetails.php` / `step_format.php` /
  `step_sections.php` / `step_activities.php` / `step_preview.php` /
  `step_commit.php`, each a thin controller over a `moodleform`
  (`classes/form/*.php`) and `draft_repository`.
- **Native per-module settings forms** — `step_activity_settings.php`
  embeds a real `mod_form.php` (via core's `course/modlib.php` contract:
  `can_add_moduleinfo()`, `get_moduleinfo_data()`, `add_moduleinfo()`,
  `update_moduleinfo()`) for the v1-scope activity types
  (`classes/native_form_registry.php`: assign, quiz, forum, page, url,
  resource, folder). Any other installed `mod_*`, and
  `mod_bulkcoursestudio` by design, uses the generic form's "Advanced
  settings (JSON)" fallback instead — the commit path treats both the same.
- **Progressive course materialization** — `build_manager::sync()` creates
  the real (hidden) course and keeps it in sync with the draft tables after
  every wizard step, rather than only at final commit, because a native
  `mod_form` needs a real `$course` (and, when editing, a real `$cm`) to
  construct correctly. `commit()` is now a thin `preview()`-gated wrapper
  around `sync()`, used only by the final Save/Publish/Schedule step.
- **Discard** — `discard_build.php` + `build_manager::discard()` soft-delete
  (to the recycle bin, via `delete_course()`) the hidden course an abandoned
  wizard session leaves behind, for never-published builds only; a
  published build's own course is never touched.
- **Excel (Flow B)** — `classes/excel/template_generator.php` /
  `template_parser.php` build/parse the three-sheet `.xlsx`
  (Course/Sections/Activities), feeding the same draft tables the wizard
  uses. `step_matchfiles.php` is the file-matching screen for any
  File/Folder activity that named a `local_filename`.
- **Edit-existing-course** — `edit_course.php` (search across every
  committed build, any admin) and every dashboard row link straight back
  into the wizard, published or not; re-committing updates the same
  modules (tracked via `committedcmid`) rather than duplicating them.
- **Hide/delete/cancel for a section or activity on a live course** —
  `build_manager::remove_with_choice()` implements this using only core's
  own `set_coursemodule_visible()`/`course_update_section()` (hide) and
  `course_delete_module()`/`course_delete_section()` (delete, gated behind
  `tool/bulkcoursestudio:deletewithdata`). **Not yet wired to any screen** —
  see "Known gaps" below.
- **Feature overview** — `overview.php`, linked from the admin tree and the
  dashboard, lists every screen in both plugins with a direct link to open
  it, plus developer credit.
- **Privacy API, capabilities, scheduled task** (`publish_scheduled`) for
  the Schedule-later flow.

## Known gaps

- **`remove_with_choice()` has no UI entry point.** It's implemented and
  unit-testable, but no wizard screen currently calls it — there is no
  "delete this section/activity" button anywhere yet. Building that
  control (likely inline on `step_sections.php`/`step_activities.php`,
  prompting hide/delete/cancel when the target is already a live course
  module) is the next real feature gap, not a polish item.
- `template_generator`/`template_parser` assume PhpSpreadsheet's bundled
  autoload path resolves on this site (`class_exists()`-guarded with a
  clear error if not) — the exact bundled path has moved between Moodle
  majors.
- No Behat coverage of the wizard UI itself (unit tests cover
  `build_manager`/`template_parser` directly — see "Tests" below).
- Not yet verified against a live Moodle 4.5/5.x install — see the
  project spec's live-instance verification checklist.

## Backup/restore: not applicable, by design

`tool_bulkcoursestudio` has no `backup/moodle2/` directory and needs none.
Its three tables (`_build`, `_section`, `_activity`) hold only in-progress
drafts, scoped to the admin who created them, never to a course — once
`build_manager::commit()`/`sync()` runs, the resulting course lives
entirely in core's own tables and is backed up/restored by core exactly
like any hand-built course.

## Tests

- `tests/build_manager_preview_test.php` — `preview()`'s validation rules
  (missing fullname, bad category, uninstalled format, bad date range,
  unknown/known activity modname, bad/good embed URL, missing-attached-file
  warning).
- `tests/build_manager_sync_test.php` — `sync()`/`commit()`/`publish()`/
  `discard()` against real core course APIs: course creation stays hidden,
  sections/activities are created and re-synced idempotently (update, not
  duplicate), a published course's visibility and data survive a re-sync,
  `commit()` refuses to write when `preview()` has errors, `publish()`
  flips visibility and rejects an uncommitted build, and `discard()`
  soft-deletes an unpublished build's course but leaves a published one
  alone.
- `tests/template_parser_test.php` — `template_parser::parse()` against
  workbooks built in memory: course-row parsing, skipping the commented-out
  example row, missing-sheet and no-course-row errors, section mapping,
  activities attached to the right section, an activity whose section
  number doesn't exist being dropped rather than mis-filed, and the
  embed-URL/local-filename fields for `bulkcoursestudio`/`resource`
  activities.

All three files were written and `php -l`/Codechecker-checked in an
environment with no live Moodle codebase to run PHPUnit against — they
have not actually been executed. Run them on a real install before relying
on them.

See the project spec doc ("Bulk Course Studio — Moodle Plugin
Specification") for the full design, decisions, and the Moodle Plugins
directory compliance checklist this plugin is structured against.

Minimum Moodle version: 4.5 (LTS).
Companion component: `mod_bulkcoursestudio` (optional — this tool still works
without it, just without the embed activity type in its pickers).
