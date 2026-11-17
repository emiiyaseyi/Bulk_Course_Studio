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
 * Unit tests for embed_resolver — pure URL-parsing logic, no DB/course
 * fixtures needed, so these run fast and cover every documented URL shape
 * per provider plus the deliberately-unresolvable cases that should
 * surface as a validation error upstream rather than a broken iframe.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_bulkcoursestudio\embed_resolver
 */
final class embed_resolver_test extends \basic_testcase {
    public function test_detect_provider_youtube(): void {
        $this->assertSame(
            embed_resolver::PROVIDER_YOUTUBE,
            embed_resolver::detect_provider('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
        $this->assertSame(embed_resolver::PROVIDER_YOUTUBE, embed_resolver::detect_provider('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_detect_provider_vimeo(): void {
        $this->assertSame(embed_resolver::PROVIDER_VIMEO, embed_resolver::detect_provider('https://vimeo.com/76979871'));
    }

    public function test_detect_provider_gdrive(): void {
        $this->assertSame(
            embed_resolver::PROVIDER_GDRIVE,
            embed_resolver::detect_provider('https://drive.google.com/file/d/1AbCdEfGhIjKlMn/view')
        );
    }

    public function test_detect_provider_onedrive(): void {
        $this->assertSame(
            embed_resolver::PROVIDER_ONEDRIVE,
            embed_resolver::detect_provider('https://onedrive.live.com/embed?cid=ABC&resid=ABC')
        );
        $this->assertSame(
            embed_resolver::PROVIDER_ONEDRIVE,
            embed_resolver::detect_provider('https://contoso.sharepoint.com/:v:/g/personal/x/abc?action=embedview')
        );
    }

    public function test_detect_provider_unrecognised(): void {
        $this->assertSame('', embed_resolver::detect_provider('https://example.com/some/video.mp4'));
        $this->assertSame('', embed_resolver::detect_provider(''));
    }

    public function test_youtube_embed_src_watch_url(): void {
        $src = embed_resolver::to_embed_src('https://www.youtube.com/watch?v=dQw4w9WgXcQ', embed_resolver::PROVIDER_YOUTUBE);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $src);
    }

    public function test_youtube_embed_src_short_url(): void {
        $src = embed_resolver::to_embed_src('https://youtu.be/dQw4w9WgXcQ?si=xyz', embed_resolver::PROVIDER_YOUTUBE);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $src);
    }

    public function test_youtube_embed_src_shorts_url(): void {
        $src = embed_resolver::to_embed_src('https://www.youtube.com/shorts/dQw4w9WgXcQ', embed_resolver::PROVIDER_YOUTUBE);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $src);
    }

    public function test_youtube_embed_src_unresolvable_returns_empty(): void {
        $this->assertSame('', embed_resolver::to_embed_src('https://www.youtube.com/', embed_resolver::PROVIDER_YOUTUBE));
    }

    public function test_vimeo_embed_src(): void {
        $src = embed_resolver::to_embed_src('https://vimeo.com/76979871', embed_resolver::PROVIDER_VIMEO);
        $this->assertSame('https://player.vimeo.com/video/76979871', $src);
    }

    public function test_vimeo_embed_src_channel_url(): void {
        $src = embed_resolver::to_embed_src('https://vimeo.com/channels/staffpicks/76979871', embed_resolver::PROVIDER_VIMEO);
        $this->assertSame('https://player.vimeo.com/video/76979871', $src);
    }

    public function test_gdrive_embed_src_file_view_url(): void {
        $src = embed_resolver::to_embed_src(
            'https://drive.google.com/file/d/1AbCdEfGhIjKlMn/view?usp=sharing',
            embed_resolver::PROVIDER_GDRIVE
        );
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjKlMn/preview', $src);
    }

    public function test_gdrive_embed_src_open_id_url(): void {
        $src = embed_resolver::to_embed_src(
            'https://drive.google.com/open?id=1AbCdEfGhIjKlMn',
            embed_resolver::PROVIDER_GDRIVE
        );
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjKlMn/preview', $src);
    }

    public function test_onedrive_embed_src_requires_embed_shaped_link(): void {
        $embedlink = 'https://onedrive.live.com/embed?cid=ABC&resid=ABC123';
        $this->assertSame($embedlink, embed_resolver::to_embed_src($embedlink, embed_resolver::PROVIDER_ONEDRIVE));

        // A plain (non-embed) OneDrive share link is deliberately NOT
        // rewritten — there's no safe guess, so this must return ''.
        $sharelink = 'https://onedrive.live.com/?cid=ABC&id=ABC123';
        $this->assertSame('', embed_resolver::to_embed_src($sharelink, embed_resolver::PROVIDER_ONEDRIVE));
    }

    public function test_onedrive_embed_src_rejects_a_url_containing_a_quote_character(): void {
        // Regression test for a stored-XSS fix: this provider is the one
        // case where to_embed_src() returns the admin-pasted URL verbatim,
        // so a crafted embed-shaped link containing a double quote must be
        // rejected here rather than trusted to the caller's own escaping.
        $malicious = 'https://onedrive.live.com/embed?resid=X&authkey=Y" onload="alert(1)';
        $this->assertSame('', embed_resolver::to_embed_src($malicious, embed_resolver::PROVIDER_ONEDRIVE));

        $maliciouswithsinglequote = "https://onedrive.live.com/embed?resid=X&authkey=Y' onload='alert(1)";
        $this->assertSame('', embed_resolver::to_embed_src($maliciouswithsinglequote, embed_resolver::PROVIDER_ONEDRIVE));
    }

    public function test_a_pasted_iframe_embed_code_is_reduced_to_its_src(): void {
        $code = '<iframe src="https://contoso-my.sharepoint.com/personal/a/_layouts/15/embed.aspx?UniqueId=1&amp;x=2" '
            . 'width="853" height="480" frameborder="0" allowfullscreen></iframe>';
        $this->assertSame(
            'https://contoso-my.sharepoint.com/personal/a/_layouts/15/embed.aspx?UniqueId=1&x=2',
            embed_resolver::extract_url($code)
        );
        $this->assertSame('https://youtu.be/abcdefghijk', embed_resolver::extract_url('  https://youtu.be/abcdefghijk '));
        $this->assertSame(
            embed_resolver::PROVIDER_ONEDRIVE,
            embed_resolver::detect_provider(embed_resolver::extract_url($code))
        );
    }

    public function test_loom_links_resolve_to_the_embed_form(): void {
        $this->assertSame(embed_resolver::PROVIDER_LOOM, embed_resolver::detect_provider('https://www.loom.com/share/abc123DEF'));
        $this->assertSame(
            'https://www.loom.com/embed/abc123DEF',
            embed_resolver::to_embed_src('https://www.loom.com/share/abc123DEF?sid=x', embed_resolver::PROVIDER_LOOM)
        );
    }

    public function test_direct_video_urls_are_recognised_safely(): void {
        $this->assertTrue(embed_resolver::is_direct_video_url('https://example.com/videos/lesson1.mp4'));
        $this->assertTrue(embed_resolver::is_direct_video_url('https://example.com/a.webm?token=1'));
        $this->assertFalse(embed_resolver::is_direct_video_url('https://example.com/watch?v=1'));
        $this->assertFalse(embed_resolver::is_direct_video_url('javascript:alert(1)'));
        $this->assertFalse(embed_resolver::is_direct_video_url('https://example.com/a.mp4" onload="x'));
    }

    public function test_progress_tracking_is_supported_only_where_the_player_reports_it(): void {
        foreach ([embed_resolver::PROVIDER_YOUTUBE, embed_resolver::PROVIDER_VIMEO, 'upload', 'direct'] as $provider) {
            $this->assertTrue(embed_resolver::supports_progress($provider));
        }
        foreach ([embed_resolver::PROVIDER_GDRIVE, embed_resolver::PROVIDER_ONEDRIVE, embed_resolver::PROVIDER_LOOM, ''] as $provider) {
            $this->assertFalse(embed_resolver::supports_progress($provider));
        }
    }

    public function test_sharing_notice_shown_only_for_drive_and_onedrive(): void {
        $this->assertNotSame('', embed_resolver::get_sharing_notice(embed_resolver::PROVIDER_GDRIVE));
        $this->assertNotSame('', embed_resolver::get_sharing_notice(embed_resolver::PROVIDER_ONEDRIVE));
        $this->assertSame('', embed_resolver::get_sharing_notice(embed_resolver::PROVIDER_YOUTUBE));
        $this->assertSame('', embed_resolver::get_sharing_notice(embed_resolver::PROVIDER_VIMEO));
    }
}
