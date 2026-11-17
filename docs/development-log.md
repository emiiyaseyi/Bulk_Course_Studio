# Bulk Course Studio — Development Log (Claude session history)

This is a compiled record of the work done on Bulk Course Studio in this
Claude session — not a literal chat export (no export tool exists in this
toolset), but a faithful, chronological account of every request made and
every change produced, so the history travels with the repo.

## Session scope

Two plugins for Moodle, developed together as a suite:
- `tool_bulkcoursestudio` — admin tool for bulk/wizard course creation
  (wizard flow, Excel/spreadsheet flow, native per-activity settings
  forms, progressive materialization, discard/publish lifecycle).
- `mod_bulkcoursestudio` — companion embed activity (YouTube, Vimeo,
  Google Drive, OneDrive/SharePoint video embedding with watch-progress
  tracking).

Target: eventual submission to the Moodle Plugins directory
(moodle.org/plugins).

## Timeline

### Earlier rounds (prior session, summarized for continuity)
- Core architecture established: `draft_repository` (own draft tables
  only) vs `build_manager` (the only class calling core Moodle
  course/module APIs).
- `build_manager::sync()` (idempotent, called after every wizard step),
  `commit()` (thin `preview()`-gated wrapper), `discard()` (soft-deletes
  an unpublished build's hidden course, never touches a published one).
- Native per-module settings-form embedding via
  `classes/native_form_registry.php`, scoped to assign, quiz, forum,
  page, url, resource, folder — using core's `course/modlib.php` contract.
- User confirmed: "So the plug-in and mod are fully ready now" → "Fix
  all" → chose "Build native forms now" when asked how to proceed.

### This session

**Request: features-overview page + developer details.**
> "Let's add an overview page which list alll the features of the
> plug-in and mod with clickable link to access them. And also add
> developer details. You have my details — LinkedIn, GitHub, Email,
> Phone number."

Followed by the actual details, with an explicit exclusion:
> "https://www.linkedin.com/in/michaeladeniran
> https://github.com/emiiyaseyi
> michaeladeniransnr@gmail.com
> Leave out my phone number"

→ Built an overview/features page listing every screen in both plugins
with working links, plus a developer-details section using LinkedIn,
GitHub and email only — no phone number included anywhere, per the
explicit instruction.

**Request: Codechecker pass.** (implicit, as part of general "fix all")
→ Ran `phpcs --standard=moodle` (Moodle's official coding-standard
Codechecker ruleset, via PHP_CodeSniffer + moodlehq/moodle-cs +
PHPCSExtra/PHPCSUtils, built from pinned GitHub tags since Packagist is
blocked in this environment) across both plugins repeatedly until 0
errors / 0 warnings were reached and stayed stable across every
subsequent file addition.

**Follow-up: "Yes"** (confirming to proceed with the three remaining
checklist items: test coverage, security audit, live-instance
verification prep).

**AskUserQuestion: "Which of the three remaining items should I tackle
next?"** → User answered **"All."**

**Mid-turn addition:**
> "Also do a full audit against Moodle plug-in upload and acceptance."

Queued (not dropped) while test-writing was in progress, per the user's
standing instruction to decide priority only when genuinely ambiguous and
otherwise keep working.

**Test coverage delivered:**
- `tool_bulkcoursestudio/tests/build_manager_sync_test.php` — `sync()`,
  `commit()`, `publish()`, `discard()` against real core course APIs.
- `tool_bulkcoursestudio/tests/template_parser_test.php` — Excel template
  parsing, including malformed/edge-case rows.
- Both written and lint-checked (`php -l`, Codechecker) but explicitly
  documented as **not executed** — no live Moodle codebase or bundled
  PhpSpreadsheet exists in this environment to run PHPUnit against.

**Security audit delivered** — two real findings, both fixed:
1. Stored XSS in `mod_bulkcoursestudio/amd/src/launcher.js`: the
   OneDrive/SharePoint embed URL path returns admin input verbatim and
   was concatenated unescaped into an iframe `src` attribute. Fixed with
   HTML-attribute escaping client-side (`launcher.js`) and a
   quote-character rejection server-side (`embed_resolver.php`), plus a
   regression test.
2. Missing `bulkcoursestudio_pluginfile()` callback in
   `mod_bulkcoursestudio/lib.php`: intro-embedded images would 404 for
   students. Added the standard Moodle pluginfile implementation.
   Also surfaced (flagged, not silently fixed, since it's a scope
   decision): `build_manager::remove_with_choice()` has no UI caller
   anywhere — implemented and testable, but currently dead code from the
   admin's perspective.

**"Proceed"** — sent mid-way through the README rewrite; work continued
without interruption.

**Full marketplace-acceptance audit delivered** (the item requested
mid-turn above) — checked against the actual moodle.org/plugins
submission requirements, not just code style:
- Added missing icons: `mod_bulkcoursestudio/pix/monologo.svg`,
  `tool_bulkcoursestudio/pix/icon.svg`.
- Added missing AMD build output: `amd/build/launcher.min.js` +
  `.min.js.map`, hand-built with `terser` since Moodle's own Grunt/
  Shifter pipeline isn't available in this environment.
- Found and removed a duplicated, inconsistent GPL license header in
  `tool_bulkcoursestudio/version.php` (the full boilerplate appeared
  twice, with the second copy using `http://` instead of `https://`).
- Rewrote both plugins' `README.md` files, which were several rounds
  stale (still describing a "functional skeleton").
- Verified complete language-string coverage (every `get_string()` call
  resolves) in both plugins.
- Confirmed `thirdpartylibs.xml` correctly absent, `db/upgrade.php`
  correctly absent for a v1 initial release, and all frankenstyle/
  directory naming correct.
- Produced the live-instance verification checklist
  (`docs/live-instance-verification-checklist.md` in this package) for
  everything that genuinely requires a real Moodle install to check.

**Most recent request:**
> "Change tge draft to v1 and confirm that the name I selected for the
> tool and mod are actually used
>
> Then zip everything including the documentation, read me and all out
> chat history here so that I can move them to my GitHub and connect it
> to vscode"

→ Verified plugin-name consistency across `version.php` component
declarations, directory names, PHP namespaces, lang filenames (per
Moodle's convention — the mod's lang file is correctly named after its
short name `bulkcoursestudio`, not the full frankenstyle name), and
capability-string prefixes. No inconsistencies found — see
`docs/bulk-course-builder-spec.md` §12.
→ Retitled the project specification from "Draft v8 for Review" to "v1".
→ Assembled this package: both plugin directories, the spec, the
live-instance checklist, both plugins' own READMEs (inside their
directories), and this development log — for you to push to your own
GitHub repository and open in VS Code.

## Toolchain notes (for anyone picking this repo up)

- PHP Codechecker was built from pinned-tag GitHub clones (Packagist is
  blocked in this sandboxed environment; GitHub cloning and npm/pip are
  not): `PHPCSStandards/PHP_CodeSniffer` 3.13.6, `moodlehq/moodle-cs`,
  `PHPCSStandards/PHPCSExtra` 1.5.0, `PHPCSStandards/PHPCSUtils` 1.2.3.
  On a normal dev machine with Composer/Packagist access, installing
  `moodlehq/moodle-cs` via Composer is the standard route instead.
- The AMD build (`amd/build/launcher.min.js`) was hand-built with
  `terser`, not Moodle's real Grunt/Shifter pipeline. Re-run the real
  build once on an install with the Moodle dev toolchain (see the
  live-instance checklist, §7).
- No PHPUnit run has happened against this code — there is no live
  Moodle codebase in this environment. The tests are written and
  lint-checked only. Running them for the first time against a real
  Moodle install is the single highest-priority next step.
