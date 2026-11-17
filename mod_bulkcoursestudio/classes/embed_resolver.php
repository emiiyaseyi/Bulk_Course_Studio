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
 * Identifies which provider a pasted URL belongs to, and builds the
 * corresponding embeddable iframe/oEmbed src for the pop-up player.
 *
 * YouTube and Vimeo have real oEmbed endpoints; Google Drive and OneDrive do
 * not, so those two are handled via their documented "preview"/embed URL
 * conventions instead, and both require "anyone with the link can view"
 * sharing — see get_sharing_notice() for the string shown to the admin.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_resolver {
    /** @var string YouTube provider identifier. */
    const PROVIDER_YOUTUBE = 'youtube';
    /** @var string Vimeo provider identifier. */
    const PROVIDER_VIMEO = 'vimeo';
    /** @var string Google Drive provider identifier. */
    const PROVIDER_GDRIVE = 'gdrive';
    /** @var string OneDrive provider identifier. */
    const PROVIDER_ONEDRIVE = 'onedrive';

    /**
     * Identify which known provider a pasted URL belongs to.
     *
     * @param string $url the admin-pasted URL
     * @return string one of the PROVIDER_* constants, or '' if unrecognised
     */
    public static function detect_provider(string $url): string {
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://(www\.)?(youtube\.com|youtu\.be)/#i', $url)) {
            return self::PROVIDER_YOUTUBE;
        }
        if (preg_match('#^https?://(www\.)?vimeo\.com/#i', $url)) {
            return self::PROVIDER_VIMEO;
        }
        if (preg_match('#^https?://(drive|docs)\.google\.com/#i', $url)) {
            return self::PROVIDER_GDRIVE;
        }
        if (preg_match('#^https?://(onedrive\.live\.com|.*\.sharepoint\.com)/#i', $url)) {
            return self::PROVIDER_ONEDRIVE;
        }
        return '';
    }

    /**
     * Build the iframe src to use inside the pop-up player for this URL.
     *
     * Each sub-method only recognises the URL shapes documented inline; if a
     * provider's URL doesn't match any known shape, these return '' rather
     * than guessing, so the caller can show a validation error instead of
     * silently rendering a broken iframe.
     *
     * @param string $url the admin-pasted URL
     * @param string $provider one of the PROVIDER_* constants
     * @return string an embeddable iframe src, or '' if $url could not be
     *                 resolved for that provider
     */
    public static function to_embed_src(string $url, string $provider): string {
        switch ($provider) {
            case self::PROVIDER_YOUTUBE:
                return self::youtube_embed_src($url);
            case self::PROVIDER_VIMEO:
                return self::vimeo_embed_src($url);
            case self::PROVIDER_GDRIVE:
                return self::gdrive_embed_src($url);
            case self::PROVIDER_ONEDRIVE:
                return self::onedrive_embed_src($url);
            default:
                return '';
        }
    }

    /**
     * youtube.com/watch?v={id}, youtu.be/{id}, youtube.com/shorts/{id},
     * and youtube.com/embed/{id} already-embed URLs all resolve to the
     * standard player embed URL.
     */
    private static function youtube_embed_src(string $url): string {
        $id = '';

        if (preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#i', $url, $m)) {
            $id = $m[1];
        } else if (preg_match('#[?&]v=([A-Za-z0-9_-]{6,})#i', $url, $m)) {
            $id = $m[1];
        } else if (preg_match('#youtube\.com/(?:shorts|embed)/([A-Za-z0-9_-]{6,})#i', $url, $m)) {
            $id = $m[1];
        }

        if ($id === '') {
            return '';
        }

        return 'https://www.youtube.com/embed/' . $id;
    }

    /**
     * vimeo.com/{id} (and vimeo.com/channels/.../{id},
     * vimeo.com/groups/.../videos/{id}) resolve to the dedicated player
     * domain, which is Vimeo's documented embeddable URL.
     */
    private static function vimeo_embed_src(string $url): string {
        if (!preg_match('#vimeo\.com/(?:[^/]+/)*?(\d+)(?:[/?]|$)#i', $url, $m)) {
            return '';
        }

        return 'https://player.vimeo.com/video/' . $m[1];
    }

    /**
     * Google Drive file links of the form
     * .../file/d/{id}/view?... (or no trailing path at all) rewrite to the
     * documented .../file/d/{id}/preview embed form. We deliberately only
     * support single-file share links, not folders — a folder has no single
     * embeddable preview.
     */
    private static function gdrive_embed_src(string $url): string {
        if (!preg_match('#drive\.google\.com/file/d/([A-Za-z0-9_-]+)#i', $url, $m)) {
            // The open?id={id} form is the other common share-link shape.
            if (preg_match('#drive\.google\.com/open\?id=([A-Za-z0-9_-]+)#i', $url, $m2)) {
                return 'https://drive.google.com/file/d/' . $m2[1] . '/preview';
            }
            return '';
        }

        return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
    }

    /**
     * OneDrive/SharePoint have no predictable "rewrite the URL" embed form
     * the way YouTube/Vimeo/Drive do — the only reliable embed link is the
     * one OneDrive itself generates from the file's own "Embed" share
     * option (a onedrive.live.com/embed?... or
     * {tenant}.sharepoint.com/:v:/... link with an embed action already
     * baked in). We validate that the admin pasted an embed-shaped link
     * rather than trying to rewrite a plain share link ourselves, since a
     * wrong guess here fails silently inside the iframe with no useful
     * error.
     *
     * Unlike the other three providers, this is the one case that returns
     * the admin-pasted URL back verbatim rather than a value rebuilt from
     * a regex-captured id — so, defensively, a URL containing a quote
     * character is rejected outright here rather than trusting every
     * caller to HTML-attribute-escape it correctly (the JS caller does,
     * but this guard holds even if a future caller forgets to).
     */
    private static function onedrive_embed_src(string $url): string {
        if (strpos($url, '"') !== false || strpos($url, "'") !== false) {
            return '';
        }
        if (preg_match('#onedrive\.live\.com/embed#i', $url)) {
            return $url;
        }
        if (preg_match('#\.sharepoint\.com/.*(action=embedview|/:v:/|_layouts/15/embed\.aspx)#i', $url)) {
            return $url;
        }
        return '';
    }

    /**
     * A short notice to show the admin when they pick Drive/OneDrive,
     * since both require "anyone with the link can view" sharing to embed
     * — unlike YouTube/Vimeo, which just need the content to be public or
     * unlisted.
     */
    public static function get_sharing_notice(string $provider): string {
        if (in_array($provider, [self::PROVIDER_GDRIVE, self::PROVIDER_ONEDRIVE], true)) {
            return get_string('embednotice:sharingrequired', 'mod_bulkcoursestudio');
        }
        return '';
    }
}
