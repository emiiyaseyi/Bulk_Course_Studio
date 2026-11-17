<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_bulkcoursestudio;

/**
 * Copies a video from a public link (Google Drive, a OneDrive/SharePoint
 * "Anyone with the link" share, or a direct file link) into this activity's
 * own file area. Once copied it behaves exactly like an uploaded video, so
 * it plays in the browser's own player and watch percentage is tracked.
 *
 * Downloads go through Moodle's own curl class, so the site's blocked-host
 * and port security settings still apply (no fetching internal addresses).
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_importer {
    /** @var int Largest video copied in: 100 MB. */
    const MAX_BYTES = 104857600;

    /**
     * The address that actually returns the video bytes for a pasted link.
     *
     * @param string $url pasted link
     * @return string download address
     */
    public static function download_url(string $url): string {
        $url = trim($url);

        $driveid = embed_resolver::drive_file_id($url);
        if ($driveid !== '') {
            // The usercontent host plus confirm=t skips Google's "can't scan
            // for viruses" page that appears for larger files.
            return 'https://drive.usercontent.google.com/download?id=' . $driveid . '&export=download&confirm=t';
        }

        if (preg_match('#^https?://[^/]*\.sharepoint\.com/#i', $url)) {
            if (preg_match('#/_layouts/15/embed\.aspx#i', $url)) {
                return preg_replace('#/_layouts/15/embed\.aspx#i', '/_layouts/15/download.aspx', $url);
            }
            if (!preg_match('/[?&]download=1/', $url)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'download=1';
            }
            return $url;
        }

        if (preg_match('#^https?://onedrive\.live\.com/(embed|redir|view\.aspx)#i', $url)) {
            return preg_replace('#^(https?://onedrive\.live\.com/)(embed|redir|view\.aspx)#i', '$1download', $url);
        }

        return $url;
    }

    /**
     * Quick check, used when the form is saved, that the link really returns
     * a video that is small enough. Reads only the first few kilobytes.
     *
     * @param string $url pasted link
     * @return string '' when fine, otherwise a language string key
     */
    public static function probe(string $url): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (!preg_match('#^https?://#i', $url)) {
            return 'importfailed:notalink';
        }

        $curl = new \curl();
        $body = $curl->get(self::download_url($url), null, [
            'CURLOPT_RANGE' => '0-2047',
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 6,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ]);
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($body === false || $curl->get_errno() || $status < 200 || $status >= 400) {
            return 'importfailed:unreachable';
        }
        if (self::looks_like_web_page((string) $body, (string) ($info['content_type'] ?? ''))) {
            return 'importfailed:notpublic';
        }
        return '';
    }

    /**
     * Download the video into the activity's file area, replacing any video
     * already stored there.
     *
     * @param string $url pasted link
     * @param \context_module $context the activity's context
     * @throws \moodle_exception when the video cannot be copied
     */
    public static function fetch(string $url, \context_module $context): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $tempdir = make_request_directory();
        $tempfile = $tempdir . '/download.tmp';

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 6,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_NOPROGRESS' => false,
            // Stop as soon as the download passes the size limit.
            'CURLOPT_PROGRESSFUNCTION' => function ($handle, $total, $downloaded) {
                return $downloaded > self::MAX_BYTES ? 1 : 0;
            },
        ]);
        $ok = $curl->download_one(
            self::download_url($url),
            null,
            ['filepath' => $tempfile, 'timeout' => 900, 'followlocation' => true, 'maxredirs' => 6]
        );

        $size = file_exists($tempfile) ? filesize($tempfile) : 0;
        if (!$ok || $curl->get_errno() || $size <= 0) {
            @unlink($tempfile);
            throw new \moodle_exception('importfailed:unreachable', 'mod_bulkcoursestudio');
        }
        if ($size > self::MAX_BYTES) {
            @unlink($tempfile);
            throw new \moodle_exception('importfailed:toolarge', 'mod_bulkcoursestudio', '', display_size(self::MAX_BYTES));
        }

        $head = (string) file_get_contents($tempfile, false, null, 0, 2048);
        $mime = (string) mime_content_type($tempfile);
        if (self::looks_like_web_page($head, $mime) || strpos($mime, 'video/') !== 0) {
            @unlink($tempfile);
            throw new \moodle_exception('importfailed:notpublic', 'mod_bulkcoursestudio');
        }

        $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-m4v' => 'm4v'];
        $filename = 'video.' . ($extensions[$mime] ?? 'mp4');

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_bulkcoursestudio', 'mediafile', 0);
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_bulkcoursestudio',
            'filearea' => 'mediafile',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $tempfile);
        @unlink($tempfile);
    }

    /**
     * Whether what came back is an HTML page (a sign-in or error page)
     * rather than a video.
     *
     * @param string $body first bytes of the response
     * @param string $contenttype the response's content type
     * @return bool
     */
    private static function looks_like_web_page(string $body, string $contenttype): bool {
        if (stripos($contenttype, 'text/html') !== false) {
            return true;
        }
        return (bool) preg_match('/^\s*(<!doctype html|<html)/i', $body);
    }
}
