# mod_bulkcoursestudio

Companion activity module for **Bulk Course Studio** (`tool_bulkcoursestudio`).
Embeds a Vimeo, YouTube, Google Drive, or OneDrive video link. On the course
page it appears as a normal activity with a launch button/thumbnail; clicking
it opens the video in a pop-up modal (`amd/src/launcher.js`) rather than
rendering it inline in the page — this is the one thing a generic Page/URL
activity can't do, which is why this module exists as its own component
rather than a feature bolted onto an existing one (see the project spec,
§4, for the full trade-off).

Supports view-based completion and an optional "minimum seconds watched"
custom completion rule, fed by per-user progress rows reported back from the
modal player.

**Status: feature-complete for v1 scope, Codechecker-clean.**
`php phpcs_src/bin/phpcs --standard=moodle` reports 0 errors/0 warnings
against this plugin (moodle-cs ruleset). Implemented:
- `classes/embed_resolver.php` — real URL → embeddable-iframe-src resolution
  for YouTube, Vimeo, and Google Drive (regex-based, documented per
  provider); OneDrive/SharePoint validates that the admin pasted one of
  Microsoft's own already-embed-shaped links (there's no reliable
  "rewrite a plain share link" trick for OneDrive the way there is for the
  other three).
- `view.php` — renders the launch button (or an error notice if the URL
  can't be resolved, plus a sharing-requirements notice for Drive/OneDrive
  shown only to the editing teacher), fires view-based completion via core's
  `completion_info`, and passes the resolved embed src to the JS.
- `amd/src/launcher.js` — opens the resolved embed in a real `core/modal_factory`
  modal (not a stub), reports coarse watch-time progress to
  `mod_bulkcoursestudio_report_progress` on a timer and on close.
- `classes/external/report_progress.php` + `db/services.php` — the AJAX
  endpoint the launcher calls; persists `bulkcoursestudio_progress` and
  marks completion via `completion_info::update_state()` once
  `completionminseconds` is reached — never a direct write to completion
  tables.
- `backup/moodle2/` — full backup/restore support: the instance row and,
  when the backup includes user data, each learner's watch-progress row,
  via the same `backup_nested_element`/`restore_path_element` pattern core's
  own single-instance activities (e.g. `mod_page`) use. Course backup,
  course copy, and course import all work on this module without any
  special-casing. `lib.php` also implements `bulkcoursestudio_reset_userdata()`
  and the matching reset-form hooks, so "Reset course" clears watch-progress
  the same way it clears quiz attempts or forum posts.
- `lib.php:bulkcoursestudio_pluginfile()` — serves files embedded in this
  module's own intro text (the standard intro editor's file-embedding
  support), required because Moodle has no generic default handler for a
  module's own file areas.
- `pix/monologo.svg` and `amd/build/launcher.min.js` (+ source map) — the
  icon and built AMD output the Moodle Plugins directory's automated
  validation checks for.

**Security fix this round:** `amd/src/launcher.js` built the pop-up
player's iframe by string-concatenating the server-resolved embed URL
directly into an HTML attribute. For YouTube/Vimeo/Google Drive this is
safe (the resolved value is always a regex-captured id, never raw admin
input), but for OneDrive/SharePoint `embed_resolver` returns the
admin-pasted URL verbatim — so a crafted link containing a double quote
could break out of the `src` attribute and inject markup, executing for
every student who opened that activity. Fixed two ways: the JS now
HTML-attribute-escapes the embed src before interpolating it, and
`embed_resolver::onedrive_embed_src()` now rejects outright any URL
containing a quote character, so the one verbatim-echo path is closed at
the source as well as at the point of use. Covered by a regression test
in `tests/embed_resolver_test.php`.

Known v1 limitation, intentionally not hidden: progress is "time the modal
was open," not true video playback position — none of the four providers
expose a reliable cross-origin playback-position API without a per-provider
SDK (YouTube IFrame API, Vimeo Player.js, etc.), which is a reasonable v2
addition once the rest of the plugin is stable, not a v1 blocker.

Still outstanding: provider thumbnails on the view page (YouTube/Vimeo both
expose predictable thumbnail URLs; Drive/OneDrive don't), and Behat
coverage for the actual pop-up player interaction.

**Tests:** `tests/embed_resolver_test.php` covers every documented URL
shape per provider (YouTube watch/short/shorts links, Vimeo plain and
channel links, Google Drive file/view and open?id= links, OneDrive's two
embed-shaped link forms) plus the deliberately-unresolvable cases — a
plain OneDrive share link, an unrecognised domain — that must return `''`
rather than guess. Pure-logic `basic_testcase` tests; no DB/course
fixtures needed.

Minimum Moodle version: 4.5 (LTS). Depends on no third-party libraries —
if a dedicated player library (e.g. Plyr, as `mod_edwiservideoactivity` uses)
is added later for a consistent player skin, it'll need its own
`thirdpartylibs.xml` declaration per Moodle Plugins directory requirements.
