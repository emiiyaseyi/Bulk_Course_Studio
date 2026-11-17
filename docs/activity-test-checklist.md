# Activity test checklist (run on a real Moodle site)

Nothing in this project has been run on a live Moodle by the developer's tooling, so this is the
quickest way to confirm every activity type behaves as it does in a normal course.

Do this once on a test course built with the wizard (Site administration > Plugins > Admin tools >
Bulk Course Studio > Start a new course). Add one activity of each type to one section.

For **each** activity type below, check all five points:

1. The settings form shows the same sections as "Add an activity" inside a course: General,
   the activity's own settings, Common module settings, **Restrict access**, **Activity completion**,
   **Tags**, **Competencies**.
2. **Save activity** returns to the Activities step (no error page), and the activity is listed.
3. **Edit** reopens it with the values you saved (files and description included).
4. After Preview > Publish, open the course as a normal teacher: the activity is there, in the right
   section, with the right name, and its settings match.
5. Open it as a student (or "Switch role to... Student"): it opens and works.

| Type | Also check |
|---|---|
| Assignment | Due date, submission types, grade settings; advanced grading (rubric) is offered |
| Quiz | Time limit and grade settings save; add a question afterwards |
| Forum | Forum type; subscription mode |
| Page | Content editor with images; page content shows |
| URL | External URL opens |
| File (resource) | Uploaded file downloads for a student |
| Folder | Several files appear |
| SCORM package | The package file uploads; Activity completion offers the SCORM rules (required status: completed or passed, minimum score, all SCOs); the package launches |
| Label | No name field; text shows on the course page |
| Bulk Course Studio | Each video source (link, direct link, upload, copy from link); resume; completion by percent and by time |
| Any other installed type (Book, Lesson, H5P, Choice...) | Same five points |

Also check these wizard-wide behaviours:

- Hide, then Show, then Rename, then Remove an activity from the Activities step.
- Create 4 sections with "How many sections", rename one, and confirm names appear in the course.
- Edit a published course from the dashboard, change one activity, save, and confirm there are no duplicates.
- Excel route: download the template, fill one row per type, upload, and compare the preview with the result.

Report any failure with the activity type, what you clicked, and the exact message (and the web server
error log line if it was a server error).
