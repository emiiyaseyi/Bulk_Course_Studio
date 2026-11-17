# Bulk Course Studio

**Build complete Moodle courses in minutes, not hours.** Bulk Course Studio lets an administrator create a whole course (details, sections, activities, uploaded files and embedded videos) through a guided wizard or a single Excel upload, preview the result, and then save it, publish it, or schedule it for later.

> **Status: alpha, release v1.5.** All releases are v1; the number after the dot goes up with each update (v1.1, v1.2, ...). The code is complete for the v1 scope, but it has **not yet been installed or tested on a live Moodle site**. Treat it as a test-site release until the [live-instance checklist](docs/live-instance-verification-checklist.md) has been worked through.

Requires **Moodle 4.5 or later**.

## What's in this repo

This project is two plugins that work together:

| Plugin | Type | Purpose |
|---|---|---|
| [`tool_bulkcoursestudio/`](tool_bulkcoursestudio) | Admin tool | The wizard, Excel import/export and the build engine |
| [`mod_bulkcoursestudio/`](mod_bulkcoursestudio) | Activity module | Embeds a YouTube, Vimeo, Google Drive or OneDrive/SharePoint video that opens in a pop-up player |

The admin tool works on its own. The activity module is optional, and is only needed for the pop-up video embeds.

## Features

### Admin tool (`tool_bulkcoursestudio`)

- **Guided wizard.** A numbered progress bar takes you through course details, format, sections, activities and a preview before anything is saved.
- **Every activity type, with its real settings.** "Add activity" opens a tile chooser of every activity installed on your site. Picking one shows that activity's own form (URL, file upload, embed code, grading and so on), including Moodle's **restrict access**, **activity completion**, **tags** and **competencies** sections.
- **Course tags and competencies.** Set them on the course details step.
- **Excel bulk upload.** Download a three-sheet template (Course, Sections, Activities), fill it in and upload it. It feeds the same draft and preview as the wizard.
- **File matching.** A screen to attach uploaded files to any File or Folder activity that names a local filename.
- **Preview before commit.** Both paths share one preview and one commit engine. The engine refuses to write if the preview has errors.
- **Save, publish or schedule.** New courses are created hidden. You can publish them immediately or schedule publishing through a Moodle scheduled task.
- **Excel extras.** Besides the basics, the template has columns for course tags and competencies, and for each activity: visible or hidden, tags, competencies, open and close dates (restrict access), and completion (none, manual, view, percent of the video watched, or minutes on the activity).
- **Edit existing builds.** Search committed builds and reopen them in the wizard. Re-saving updates the same activities instead of duplicating them.
- **Discard.** Abandoning an unpublished build soft-deletes its hidden course to the recycle bin. A published course is never touched.
- **Core APIs only.** The engine uses Moodle's own course, module, completion and file APIs. It never writes to core database tables directly, so a built course looks the same as one made by hand.
- **Privacy API, capabilities and an overview page** listing every screen.

### Embed activity (`mod_bulkcoursestudio`)

- Works with **YouTube, Vimeo, Loom, Google Drive and OneDrive/SharePoint** links, a **direct link to a video file**, or an **uploaded video file** (MP4, WebM, M4V, MOV, 3GP).
- **Time-on-activity completion:** require learners to keep the video open for N minutes. Works for every source.
- **Watch-percentage completion:** require learners to watch, say, 80% of the video. Works for uploads, direct links, YouTube and Vimeo.
- **Resume playback:** learners restart where they stopped, and their progress is saved.
- **Auto-advance:** when the video ends, a short countdown takes the learner to the next activity.
- **Overview, Resources and Transcript tabs** on the activity page.
- Uploaded videos are limited to **100 MB**, with a warning shown on the form; use a link for longer recordings.
- Teachers can attach **resource files** (slides, worksheets) that students download from the activity page.
- Shows a launch button on the course page and plays the video in a **pop-up modal**.
- Supports **view-based completion** and the **watch-percentage** rule.
- Includes full **backup and restore** support and the Privacy API.

## Installation

### From a ZIP (recommended for testing)

1. Build the ZIPs with `python tools/build_zips.py`. It bumps each plugin's version number, so Moodle treats a reinstall as an upgrade, and writes both ZIPs to `dist/` with the root folder named `bulkcoursestudio`.
2. In Moodle, go to **Site administration → Plugins → Install plugins** and upload `tool_bulkcoursestudio` first. Then upload `mod_bulkcoursestudio` if you want video embeds.
3. Follow the on-screen upgrade prompts.

### By copying files

1. Copy `tool_bulkcoursestudio/` to `<moodle>/admin/tool/bulkcoursestudio/`.
2. Copy `mod_bulkcoursestudio/` to `<moodle>/mod/bulkcoursestudio/`.
3. Visit **Site administration → Notifications** to run the install.

## Usage

1. Open **Site administration → Courses → Bulk Course Studio**.
2. Start the wizard, or download the Excel template, fill it in and upload it.
3. Review the preview, then save, publish or schedule the course.

## Permissions

| Capability | Allows |
|---|---|
| `tool/bulkcoursestudio:manage` | Using the wizard and dashboard |
| `tool/bulkcoursestudio:bulkupload` | Excel template download and upload |
| `tool/bulkcoursestudio:schedule` | Scheduling a publish date |
| `tool/bulkcoursestudio:deletewithdata` | Deleting content together with its data |
| `mod/bulkcoursestudio:addinstance` | Adding the embed activity to a course |
| `mod/bulkcoursestudio:view` | Viewing the embed activity |

## Security notes

A review of the code found and fixed two issues:

- A stored XSS risk in the pop-up player, where a crafted OneDrive link could inject markup. The URL is now escaped in the JavaScript and rejected in PHP if it contains a quote character.
- A missing file-serving callback, which meant images in the activity's intro text would not display for students.

This was an internal review, not an independent penetration test.

## Known gaps

- **Not tested on a live Moodle site.** The unit tests for the build engine, Excel parser and embed resolver exist but have not been run. PHP and JavaScript syntax checks do pass.
- **Sections** can't be hidden or removed from the wizard yet; activities can (hide, show, or delete).
- **No Behat (UI) tests** for the wizard.
- **The AMD JavaScript build was made with `terser`, not Moodle's Grunt.** Re-run the real Grunt build on a development install.
- **Placeholder icons** in both plugins.
- **Deprecated-API checks** against Moodle 4.5 and 5.x are still outstanding.
- **OneDrive/SharePoint:** a normal share link cannot be shown inside Moodle (Microsoft blocks it). Use Share, then Embed, and paste the embed link, or upload the video.
- **Document completion** (scroll to the end of a PDF) is not built yet.
- **Percentage tracking** does not work for Google Drive, OneDrive or Loom links, because those players do not share playback progress. Use view completion for them.

## Documentation

- [`docs/bulk-course-builder-spec.md`](docs/bulk-course-builder-spec.md): the project specification and status.
- [`docs/live-instance-verification-checklist.md`](docs/live-instance-verification-checklist.md): the checks to run on a real Moodle install.
- [`docs/development-log.md`](docs/development-log.md): a reconstructed record of how the project was built. It is not a verbatim transcript.
- Each plugin folder has its own `README.md` with more detail.

## License

GNU GPL v3 or later, as required for Moodle plugins.

## Author

**Adeniran O. Michael**

- GitHub: [emiiyaseyi](https://github.com/emiiyaseyi)
- LinkedIn: [michaeladeniran](https://www.linkedin.com/in/michaeladeniran)
- Email: michaeladeniransnr@gmail.com
