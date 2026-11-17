# Bulk Course Studio — Moodle Plugin Specification (v1)

Status: **Feature-complete for v1 scope. Test coverage added for the commit
engine and Excel parser, a security audit found and fixed one real
stored-XSS plus one functional file-serving gap, and a full marketplace-
acceptance pass added the missing icon/AMD-build/README items the Plugins
directory's automated validation checks for.** Plugin names
(`tool_bulkcoursestudio` / `mod_bulkcoursestudio`) verified consistent
throughout the codebase — see §12. Git/GitHub is being handled by you
directly from here — see §11.

Plugin brand (locked in): **Bulk Course Studio**
- Admin tool: `tool_bulkcoursestudio`
- Companion embed activity: `mod_bulkcoursestudio`

## 1–8. Unchanged from Draft v7

See Draft v7 for purpose, plugin type, core user flows (wizard, Excel,
native forms, progressive materialization, discard), the embed activity,
and the architecture sketch — none of that changed this round. §6 and §9
below supersede v7's versions of those sections.

## 9. This round's work

### 9.1 Test coverage (previously the weakest checklist item)

- `tool_bulkcoursestudio/tests/build_manager_sync_test.php` (new) —
  exercises `sync()`/`commit()`/`publish()`/`discard()` against real core
  course APIs: new courses stay hidden, re-syncing updates rather than
  duplicates, a published course's visibility survives a re-sync,
  `commit()` refuses to write when `preview()` has errors, `publish()`
  rejects an uncommitted build, `discard()` soft-deletes an unpublished
  build's course but leaves a published one alone.
- `tool_bulkcoursestudio/tests/template_parser_test.php` (new) —
  `template_parser::parse()` against workbooks built in memory: course-row
  parsing, skipping the commented example row, missing-sheet/no-course-row
  errors, section mapping, activities attached to the right section, an
  orphan section-number reference being dropped, and the
  embed-URL/local-filename fields.
- Both written and Codechecker/`php -l`-checked but **not executed** — no
  live Moodle codebase (or bundled PhpSpreadsheet) in this environment to
  run PHPUnit against. Each file's own docblock says so.

### 9.2 Security audit

Reviewed every controller for capability/`sesskey` checks, SQL
construction, file-upload handling, and output escaping. Two real
findings, both fixed:

1. **Stored XSS in `mod_bulkcoursestudio/amd/src/launcher.js`.** The
   pop-up player built its iframe by string-concatenating the
   server-resolved embed URL directly into an HTML attribute. For
   YouTube/Vimeo/Google Drive this was safe (the resolved value is always
   a regex-captured id), but `embed_resolver::onedrive_embed_src()`
   returns the admin-pasted URL **verbatim** for OneDrive/SharePoint — so
   a crafted link containing a `"` could break out of the `src` attribute
   and inject markup, executing for every student who opened that
   activity. Fixed at both ends: `launcher.js` now HTML-attribute-escapes
   the embed src before interpolating it, and `onedrive_embed_src()` now
   rejects outright any URL containing a quote character, closing the one
   verbatim-echo path at the source too. Covered by a regression test in
   `tests/embed_resolver_test.php`.
2. **Missing `bulkcoursestudio_pluginfile()` in `mod_bulkcoursestudio/lib.php`.**
   The module uses the standard file-embeddable intro editor, but had no
   pluginfile callback — Moodle has no generic default for a module's own
   file areas, so any image a teacher embedded in the intro text would
   404 for students. Added the standard implementation (login + context +
   filearea checks, then `send_stored_file()`).

Everything else checked out: all controllers have `require_capability()`
at the correct context, `sesskey` is checked on every state-changing POST
action, the one raw-SQL query (`draft_repository::search_committed_builds()`)
is fully parameterized, file uploads go through Moodle's own
`filemanager`/`filepicker` APIs with appropriate `accepted_types`, and
`native_form_registry`'s module-name-to-path construction is gated by a
hardcoded whitelist before any `file_exists()`/`require_once()`, so no
path-traversal risk despite the dynamic include.

**One functional (not security) gap surfaced by the audit, not yet
fixed**: `build_manager::remove_with_choice()` (the hide/delete/cancel
logic from §3.3) has no caller anywhere — no wizard screen exposes a
"delete this section/activity" control. It's implemented and
unit-testable, but currently dead code from the UI's perspective. Flagged
in `tool_bulkcoursestudio`'s README as a known gap, not silently left
undocumented.

### 9.3 Full marketplace-acceptance audit

Went through the actual moodle.org/plugins submission requirements, not
just the code-style/test checklist. Found and fixed:
- **No icon in either plugin.** Added `mod_bulkcoursestudio/pix/monologo.svg`
  and `tool_bulkcoursestudio/pix/icon.svg` (both placeholder geometric
  marks — swap for real artwork before submission if you want a specific
  look; functionally this just needed to exist).
- **No built AMD output.** `amd/src/launcher.js` had no
  `amd/build/launcher.min.js` counterpart, which the directory's automated
  validation checks for. Built one with `terser` (not Moodle's own Grunt/
  Shifter pipeline, which isn't available in this environment) — the
  live-instance checklist flags re-running the real Grunt build on an
  actual install to confirm/replace it.
- **Duplicated, inconsistent license header in `tool_bulkcoursestudio/version.php`.**
  The file had the full 14-line GPL block twice back-to-back, with the
  second copy using `http://` instead of `https://` in the final URL —
  almost certainly a leftover from an earlier hand-edit. Removed the
  duplicate.
- **Both READMEs were badly stale.** `tool_bulkcoursestudio`'s still said
  "functional skeleton — commit engine implemented, wizard UI not yet
  built," several rounds out of date. Rewrote both to reflect current
  status, and added the known-gaps/test/security-fix sections above.
- Verified no missing language strings (every `get_string()` call
  resolves) in either plugin — the one check most likely to cause an
  actual runtime error during review.
- Confirmed `thirdpartylibs.xml` is correctly absent (no vendored
  third-party code), `db/upgrade.php` is correctly absent for a v1 initial
  release, and directory/frankenstyle naming is correct throughout.

Wrote a separate **live-instance verification checklist**
(`docs/live-instance-verification-checklist.md` in this package) covering
everything that genuinely needs a real Moodle install to check — the
native-form embedding end-to-end, deprecated-API spot checks on 5.x, the
real Grunt/ESLint build, and Moodle's own "Plugin validation" tool on
moodle.org itself.

## 6. Marketplace / Review Compliance Checklist (supersedes v7)

For **both** `tool_bulkcoursestudio` and `mod_bulkcoursestudio`:
- [x] Correct plugin type/frankenstyle name, valid `version.php` (and now
      a clean, non-duplicated license header).
- [x] No direct writes to core DB tables — only public APIs.
- [x] Capability definitions in `db/access.php`.
- [x] Language strings fully externalized and verified complete (§9.3).
- [x] Privacy API implementation in both.
- [x] Backup/restore support for `mod_bulkcoursestudio`; documented N/A
      for `tool_bulkcoursestudio`.
- [x] Automated code-style compliance — `phpcs --standard=moodle`: 0
      errors, 0 warnings across every file in both plugins.
- [x] **Icon and AMD build output present** — new this round (§9.3).
- [x] **Independent security review done** — found and fixed a real
      stored-XSS and a missing file-serving callback (§9.2). Not the same
      as a third-party professional pen-test, but a genuine adversarial
      pass, not a self-check.
- [x] **Test coverage of the commit engine and Excel parser** — new this
      round (§9.1), though unexecuted in this environment.
- [ ] No use of deprecated APIs for the declared floor (Moodle 4.5) —
      still not verified against a live instance; see the live-instance
      checklist.
- [ ] No embedded third-party code without `thirdpartylibs.xml` — N/A.
- [ ] Behat/UI-level test coverage of the wizard itself — still absent;
      unit tests now cover the engine and parser directly instead.

## 10. Next Steps

- Work through the live-instance verification checklist
  (`docs/live-instance-verification-checklist.md`) on a real Moodle 4.5
  and, ideally, 5.x install — this is now the single biggest remaining
  gap between "looks right" and "known to work."
- Decide whether `remove_with_choice()` (hide/delete/cancel) gets a real
  UI control, or stays as engine-only functionality for a later round.
- Replace the placeholder `pix/` icons with real artwork if you want a
  specific look for the directory listing.
- Re-run the real Moodle Grunt/Shifter AMD build once on an install with
  the dev toolchain, to confirm or replace the `terser`-built
  `amd/build/launcher.min.js`.
- Decide on per-provider playback-position SDKs (the "modal-open-time"
  limitation) as a v1.1 item, or accept it for v1.

## 11. Repo / Commit Access

You're handling git/GitHub yourself from here — Claude is not pushing to
`emiiyaseyi/bulk_course-upload` or creating commits. This package is
delivered as a zip for you to `git init`/commit on your end, in your
name only, as you prefer.

## 12. Naming Verification (v1)

Confirmed consistent and correct throughout both plugins:
- `version.php` → `$plugin->component = 'tool_bulkcoursestudio';` and
  `'mod_bulkcoursestudio';` respectively.
- Directory names: `tool_bulkcoursestudio/`, `mod_bulkcoursestudio/`.
- PHP namespaces: `tool_bulkcoursestudio`, `\excel`, `\form`, `\privacy`,
  `\task`; `mod_bulkcoursestudio`, `\external`, `\privacy`.
- Lang files: `tool_bulkcoursestudio/lang/en/tool_bulkcoursestudio.php`
  (full frankenstyle name, correct for admin tools) and
  `mod_bulkcoursestudio/lang/en/bulkcoursestudio.php` (module short name,
  correct Moodle convention for activity modules — not the full
  frankenstyle name).
- Capability prefixes: `tool/bulkcoursestudio:manage|bulkupload|
  deletewithdata|schedule`; `mod/bulkcoursestudio:addinstance|view`.

No inconsistencies found.
