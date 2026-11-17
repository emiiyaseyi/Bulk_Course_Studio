# Bulk Course Studio — Live Moodle Instance Verification Checklist

This can't be run in the Claude environment (no Moodle install available
there). Run it yourself against a real Moodle 4.5 LTS site and, ideally, a
current 5.x site, before submitting to the Plugins directory. Items are
ordered roughly by how likely they are to surface a real problem.

## 1. Install and smoke-test

- [ ] Copy `tool_bulkcoursestudio` to `admin/tool/bulkcoursestudio`,
      `mod_bulkcoursestudio` to `mod/bulkcoursestudio`. Visit
      Site administration → Notifications to trigger the install.
- [ ] Confirm both install with no errors, and that
      `tool/bulkcoursestudio:manage` etc. appear under
      Site administration → Users → Permissions → Define roles.
- [ ] As an admin, open **Site administration → Courses → Bulk Course
      Studio** and confirm the dashboard loads, and that **"See all
      features"** (the new overview page) lists every screen with working
      links.

## 2. Wizard end-to-end (Flow A)

- [ ] Start a new build, fill in course details with a course-image
      upload, pick a format, add two sections, and add one activity of
      each v1-scope type: Assignment, Quiz, Forum, Page, URL, File,
      Folder, and the Bulk Course Studio embed activity.
- [ ] **This is the highest-value check in this whole list**: for each of
      the seven native-form types, confirm `step_activity_settings.php`
      actually renders that module's real settings form (not a 500 error,
      not a blank page) and that saving it works for both "add new" and
      "edit existing" (the "add" mode's `$data` shape was reconstructed
      from `course/modedit.php`'s convention without being run against a
      live site — see the project spec §9 — so this is the one piece most
      likely to need a fix here).
- [ ] After adding a File/Folder activity in the wizard, confirm its own
      `filemanager` field still works even though `step_activities.php`
      now redirects file-bearing types into the native form — check
      whether `resource`/`folder`'s native `mod_form` exposes its own file
      field correctly inside the wizard's embedded copy.
- [ ] Preview the build; confirm every section/activity shows, and that no
      unexpected errors appear.
- [ ] Save as draft, confirm the real (hidden) course now exists and is
      not visible on the course list to a student.
- [ ] Re-open the draft from the dashboard, change the course fullname,
      save again — confirm it updates the same course rather than creating
      a second one.
- [ ] Click "Discard" on an in-progress (never-published) draft — confirm
      its hidden course moves to the recycle bin (Site admin → Courses →
      recycle bin, if enabled) rather than staying as a permanent hidden
      orphan.
- [ ] Publish a different draft; confirm the course becomes visible, then
      try "Discard" again on an already-published build from the
      dashboard — confirm it refuses to delete the real course (only
      removes this tool's own tracking row).

## 3. Excel flow (Flow B)

- [ ] Download the template, fill in a course + two sections + one
      File-type activity naming a `local_filename`, save, re-upload.
- [ ] Confirm it routes to the file-matching screen, upload a
      correctly-named file, confirm it matches and the course builds with
      that file attached.
- [ ] Re-upload a template with a `local_filename` left unmatched; confirm
      preview shows a warning (not a hard error) and the build still
      completes.

## 4. mod_bulkcoursestudio

- [ ] Add a Bulk Course Studio activity directly (not via the wizard) to
      a normal course, with each of the four providers: a YouTube link, a
      Vimeo link, a Google Drive "anyone with the link" share link, and a
      real OneDrive/SharePoint **embed-shaped** link (generated from that
      file's own "Embed" option, not a plain share link).
- [ ] As a student, open the activity, click the launch button, confirm
      the modal opens and plays for all four providers.
- [ ] Leave the modal open past the configured
      "minimum seconds watched" threshold (if enabled) and confirm
      completion is marked; confirm closing the modal also reports final
      progress.
- [ ] Embed an image directly in the activity's intro/description text
      (via the editor's image button) and confirm it actually loads for a
      student — this exercises the new `bulkcoursestudio_pluginfile()`
      callback added this round; before that fix it would have 404'd.
- [ ] Back up a course containing this activity (with user data included),
      restore it into a new course, and confirm both the activity and the
      student's watch-progress carry over.
- [ ] Run "Reset course" with "Delete bulk course studio progress" ticked;
      confirm progress rows are cleared and the activity itself survives.

## 5. Deprecated-API / cross-version spot checks

- [ ] On the 5.x site specifically: confirm `course_delete_section()`'s
      4-argument call in `build_manager::remove_with_choice()` still works
      — it is currently unreachable from any UI (see the gap noted in
      `tool_bulkcoursestudio`'s README), so this can only be checked by
      calling it directly, e.g. via `php admin/cli/...` or a quick
      throwaway script, not by clicking through the UI.
- [ ] Watch the PHP error log (not just the browser) while clicking
      through the whole wizard once on the 5.x site specifically, since a
      deprecation notice doesn't always surface visibly in the UI.
- [ ] Confirm `core_component::get_plugin_list('format')` and
      `('mod')` (used throughout `build_manager`/`preview()`) return what
      you expect on 5.x — these APIs are stable, but worth a quick sanity
      check given how central they are to this plugin.

## 6. Permissions / negative tests

- [ ] As a user with the `teacher` archetype (no
      `tool/bulkcoursestudio:manage`), confirm every admin-tool URL
      (`index.php`, `step_coursedetails.php`, etc.) refuses access rather
      than erroring oddly.
- [ ] As a `manager`-archetype user without
      `tool/bulkcoursestudio:deletewithdata`, confirm a delete action
      (once wired to a screen — currently a known gap) is refused while
      hide still works.
- [ ] Try discarding a build you did not create — confirm it's refused
      (this one intentionally differs from edit access, which is shared
      across all managers by design).

## 7. Code-quality tools this environment couldn't run

- [ ] `grunt` / the AMD Shifter build for `amd/src/launcher.js` — a
      hand-built minified file (`amd/build/launcher.min.js` +
      `.min.js.map`) was committed this round via `terser` rather than
      Moodle's own Grunt pipeline; re-run `grunt amd` (or `grunt` at the
      Moodle root with this plugin's path) once on a real install with
      the Moodle dev toolchain to confirm Shifter produces an equivalent
      (or better) build, and commit that output instead if it differs.
- [ ] `grunt eslint` / `.eslintrc` compliance for `launcher.js` — not run
      here (no Moodle ESLint config available).
- [ ] Moodle's "Plugin validation" tool on moodle.org/plugins itself,
      once you create a draft submission — it runs its own automated
      checks (file structure, version.php fields, language-pack
      completeness) beyond what `phpcs --standard=moodle` covers locally.
