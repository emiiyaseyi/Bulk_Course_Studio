// This file is part of Moodle - http://moodle.org/

/**
 * Binds the activity's launch button to open the embedded Vimeo/YouTube/
 * Drive/OneDrive player in a pop-up modal, rather than rendering it inline
 * on the activity page (spec §4). Uses core's ModalFactory so the modal
 * itself follows Moodle's own accessibility/focus-trap behavior rather than
 * a bespoke overlay.
 *
 * Progress is reported back to mod_bulkcoursestudio_report_progress on a
 * timer while the modal is open and once more when it closes, feeding the
 * optional "minimum seconds watched" completion rule.
 *
 * @module     mod_bulkcoursestudio/launcher
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/notification',
], function($, ModalFactory, ModalEvents, Ajax, Notification) {

    // How often, in milliseconds, to report progress while the modal is
    // open. Kept coarse on purpose — this is "has the student kept the
    // video open", not frame-accurate playback tracking, since none of the
    // four providers expose a reliable cross-origin playback-position API
    // without extra per-provider SDK integration (a v2 candidate, noted in
    // the project spec as out of scope for v1).
    var PROGRESS_INTERVAL_MS = 10000;

    /**
     * @param {Number} cmid course module id, needed by the server to check
     *                      capability + look up completionminseconds
     * @param {String} embedSrc the already-resolved iframe src (built
     *                           server-side via embed_resolver::to_embed_src()
     *                           and passed in from view.php — kept out of
     *                           this module so the URL-rewriting rules live
     *                           in exactly one place, in PHP)
     * @param {String} mediaType 'iframe' for an embedded link, 'video' for an
     *                           uploaded video file played with the browser's own player
     */
    var init = function(cmid, embedSrc, mediaType) {
        var button = document.querySelector('[data-bulkcoursestudio-launch="' + cmid + '"]');
        if (!button) {
            return;
        }

        button.addEventListener('click', function() {
            openModal(cmid, embedSrc, mediaType);
        });
    };

    /**
     * Escape a string for safe interpolation inside an HTML attribute
     * value. Needed because embedSrc, while resolved server-side, is
     * returned verbatim (not rebuilt from a regex-captured id) for the
     * OneDrive/SharePoint provider — a crafted embed-shaped URL containing
     * a double quote could otherwise break out of the iframe's src
     * attribute and inject markup into every viewer's page.
     *
     * @param {String} value
     * @return {String}
     */
    var escapeHtmlAttribute = function(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    var openModal = function(cmid, embedSrc, mediaType) {
        if (!embedSrc) {
            Notification.alert(
                '',
                'This video link could not be opened. Ask your teacher to check the embed URL.'
            );
            return;
        }

        var secondsOpen = 0;
        var progressTimer = null;
        var safeEmbedSrc = escapeHtmlAttribute(embedSrc);

        ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            large: true,
            body: mediaType === 'video' ?
                '<video controls autoplay playsinline src="' + safeEmbedSrc + '" ' +
                'style="width:100%;max-height:75vh;background:#000;"></video>' :
                '<div class="bulkcoursestudio-embed-wrap" style="position:relative;padding-top:56.25%;">' +
                '<iframe src="' + safeEmbedSrc + '" allow="autoplay; fullscreen" allowfullscreen ' +
                'style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe>' +
                '</div>',
        }).then(function(modal) {
            modal.show();

            // Coarse "time spent with the modal open" proxy — see
            // PROGRESS_INTERVAL_MS note above for why this isn't true
            // playback-position tracking in v1.
            progressTimer = setInterval(function() {
                secondsOpen += (PROGRESS_INTERVAL_MS / 1000);
                reportProgress(cmid, secondsOpen);
            }, PROGRESS_INTERVAL_MS);

            modal.getRoot().on(ModalEvents.hidden, function() {
                if (progressTimer) {
                    clearInterval(progressTimer);
                }
                reportProgress(cmid, secondsOpen);
                modal.destroy();
            });

            return modal;
        }).catch(Notification.exception);
    };

    var reportProgress = function(cmid, secondsWatched) {
        Ajax.call([{
            methodname: 'mod_bulkcoursestudio_report_progress',
            args: {
                cmid: cmid,
                secondswatched: Math.round(secondsWatched),
            },
        }])[0].catch(function(error) {
            // Non-fatal: a dropped progress ping shouldn't interrupt playback.
            window.console.warn('bulkcoursestudio: progress report failed', error);
        });
    };

    return {
        init: init,
    };
});
