# Bulk Course Studio (v1)

Two Moodle plugins developed together:

- `tool_bulkcoursestudio/` — the admin tool (wizard + Excel bulk course
  creation). See its own `README.md` inside that directory.
- `mod_bulkcoursestudio/` — the companion embed activity (YouTube, Vimeo,
  Google Drive, OneDrive/SharePoint). See its own `README.md` inside that
  directory.

## Docs

- `docs/bulk-course-builder-spec.md` — full project specification,
  architecture, and status (v1).
- `docs/live-instance-verification-checklist.md` — manual checklist to
  run on a real Moodle install before submitting to the Plugins
  directory (this environment has no live Moodle instance to run it
  against).
- `docs/development-log.md` — chronological record of this Claude
  session's requests and changes.

## Getting started in VS Code

1. Unzip this package into a new folder.
2. `git init`, then commit as yourself:
   ```
   git add .
   git commit -m "Bulk Course Studio v1"
   ```
3. Push to your own GitHub repo (e.g. `emiiyaseyi/bulk_course-upload`).
4. To install into a Moodle site for testing: copy
   `tool_bulkcoursestudio/` to `<moodle>/admin/tool/bulkcoursestudio/`
   and `mod_bulkcoursestudio/` to `<moodle>/mod/bulkcoursestudio/`, then
   visit *Site administration → Notifications* to trigger install.

## Status

Feature-complete for v1 scope. Codechecker-clean (`phpcs
--standard=moodle`: 0 errors, 0 warnings). Test coverage written for the
commit engine and Excel parser (not yet executed — no live Moodle
environment was available to run PHPUnit against). One real stored-XSS
and one missing file-serving callback were found in a security audit and
fixed. See `docs/bulk-course-builder-spec.md` for full detail, and the
live-instance checklist for what still needs verifying on a real install.
