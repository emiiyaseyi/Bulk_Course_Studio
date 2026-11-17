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

/**
 * Opens the video in a pop-up player, tracks how much of it the learner
 * has watched (uploaded/direct files, YouTube and Vimeo), resumes where
 * they left off, and moves on to the next activity when it ends.
 *
 * Progress is saved through mod_bulkcoursestudio_report_progress; the
 * server turns it into the "watched at least N%" completion rule.
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

    // How often, at most, progress is sent to the server while playing.
    var REPORT_EVERY_MS = 5000;
    // Seconds shown on the "coming up next" countdown.
    var NEXT_COUNTDOWN = 5;

    var ORIGINS = {
        youtube: 'https://www.youtube.com',
        vimeo: 'https://player.vimeo.com',
    };

    /**
     * Escape a value for use inside an HTML attribute.
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

    /**
     * Entry point, called from view.php.
     *
     * @param {Object} config cmid, src, type (video|iframe), provider,
     *                        resume (seconds), nexturl, nextname, autonext
     */
    var init = function(config) {
        var button = document.querySelector('[data-bulkcoursestudio-launch="' + config.cmid + '"]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            openModal(config);
        });
    };

    /**
     * Keeps track of how far the learner has watched and reports it.
     *
     * @param {Object} config
     * @return {Object}
     */
    var createTracker = function(config) {
        var state = {duration: 0, current: 0, furthest: 0, lastSent: 0, timespent: 0, enabled: config.tracked || config.timed};

        var send = function() {
            if (!state.enabled) {
                return;
            }
            state.lastSent = Date.now();
            var percent = state.duration ? Math.min(100, Math.floor(state.furthest / state.duration * 100)) : 0;
            Ajax.call([{
                methodname: 'mod_bulkcoursestudio_report_progress',
                args: {
                    cmid: config.cmid,
                    currenttime: Math.floor(state.current),
                    duration: Math.floor(state.duration),
                    percent: percent,
                    provider: config.provider || '',
                    timespent: Math.floor(state.timespent),
                },
            }])[0].catch(function() {
                // A dropped progress ping must never interrupt playback.
                return null;
            });
        };

        var update = function(current, duration) {
            if (isFinite(duration) && duration > 0) {
                state.duration = duration;
            }
            if (isFinite(current)) {
                state.current = current;
                if (current > state.furthest) {
                    state.furthest = current;
                }
            }
            if (Date.now() - state.lastSent >= REPORT_EVERY_MS) {
                send();
            }
        };

        var finish = function() {
            if (state.duration) {
                state.current = state.duration;
                state.furthest = state.duration;
            }
            send();
        };

        // Counts seconds the player is open while this browser tab is visible.
        var tick = function() {
            if (document.visibilityState === 'visible') {
                state.timespent++;
            }
            if (Date.now() - state.lastSent >= REPORT_EVERY_MS) {
                send();
            }
        };

        return {state: state, update: update, send: send, finish: finish, tick: tick};
    };

    /**
     * Show the "coming up next" countdown and move on when it ends.
     *
     * @param {Object} config
     * @param {jQuery} modalBody
     */
    var showNext = function(config, modalBody) {
        if (!config.autonext || !config.nexturl) {
            return;
        }
        var left = NEXT_COUNTDOWN;
        var overlay = $('<div class="bulkcoursestudio-next text-center p-3"></div>');
        var label = $('<div class="h5 mb-1"></div>').text(config.strings.next);
        var name = $('<div class="h4"></div>').text(config.nextname || '');
        var counter = $('<div class="mb-2"></div>');
        var cancel = $('<button type="button" class="btn btn-secondary"></button>').text(config.strings.cancel);
        overlay.append(label, name, counter, cancel);
        modalBody.append(overlay);

        var timer = setInterval(function() {
            left--;
            counter.text(left);
            if (left <= 0) {
                clearInterval(timer);
                window.location.href = config.nexturl;
            }
        }, 1000);
        counter.text(left);
        cancel.on('click', function() {
            clearInterval(timer);
            overlay.remove();
        });
    };

    /**
     * Wire up an HTML5 video element.
     *
     * @param {HTMLVideoElement} video
     * @param {Object} config
     * @param {Object} tracker
     * @param {Function} onEnded
     */
    var trackHtml5 = function(video, config, tracker, onEnded) {
        var resumed = false;
        video.addEventListener('loadedmetadata', function() {
            tracker.update(0, video.duration);
            if (!resumed && config.resume > 0 && config.resume < video.duration - 3) {
                video.currentTime = config.resume;
            }
            resumed = true;
        });
        video.addEventListener('timeupdate', function() {
            tracker.update(video.currentTime, video.duration);
        });
        video.addEventListener('ended', function() {
            tracker.finish();
            onEnded();
        });
    };

    /**
     * Track a YouTube or Vimeo iframe through the player's postMessage
     * protocol. Only messages from that provider's own origin, sent by this
     * exact iframe, are trusted.
     *
     * @param {HTMLIFrameElement} iframe
     * @param {Object} config
     * @param {Object} tracker
     * @param {Function} onEnded
     * @return {Function} cleanup
     */
    var trackIframe = function(iframe, config, tracker, onEnded) {
        var origin = ORIGINS[config.provider];
        var resumed = false;
        var listening = false;
        var attempts = 0;
        var poll = null;

        var post = function(message) {
            iframe.contentWindow.postMessage(
                config.provider === 'youtube' ? JSON.stringify(message) : JSON.stringify(message),
                origin
            );
        };

        var resume = function() {
            if (resumed || !(config.resume > 0) || !(tracker.state.duration > config.resume + 3)) {
                return;
            }
            resumed = true;
            if (config.provider === 'youtube') {
                post({event: 'command', func: 'seekTo', args: [config.resume, true]});
            } else {
                post({method: 'setCurrentTime', value: config.resume});
            }
        };

        var listen = function() {
            if (config.provider === 'youtube') {
                post({event: 'listening', id: config.cmid, channel: 'widget'});
            } else if (!listening) {
                post({method: 'addEventListener', value: 'timeupdate'});
                post({method: 'addEventListener', value: 'ended'});
            }
        };

        var onMessage = function(event) {
            if (event.origin !== origin || event.source !== iframe.contentWindow) {
                return;
            }
            var data = event.data;
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e) {
                    return;
                }
            }
            if (!data || typeof data !== 'object') {
                return;
            }
            listening = true;

            if (config.provider === 'youtube') {
                var info = data.info || {};
                if ((data.event === 'infoDelivery' || data.event === 'initialDelivery') && typeof info === 'object') {
                    if (typeof info.currentTime === 'number') {
                        tracker.update(info.currentTime, info.duration || tracker.state.duration);
                        resume();
                    }
                }
                if (data.event === 'onStateChange' && info === 0) {
                    tracker.finish();
                    onEnded();
                }
            } else {
                if (data.event === 'ready') {
                    listen();
                } else if (data.event === 'timeupdate' && data.data) {
                    tracker.update(data.data.seconds, data.data.duration);
                    resume();
                } else if (data.event === 'ended') {
                    tracker.finish();
                    onEnded();
                }
            }
        };

        window.addEventListener('message', onMessage);
        iframe.addEventListener('load', function() {
            listen();
            // YouTube only starts talking once it hears us; keep nudging briefly.
            poll = setInterval(function() {
                attempts++;
                if (listening || attempts > 20) {
                    clearInterval(poll);
                    return;
                }
                listen();
            }, 500);
        });

        return function() {
            window.removeEventListener('message', onMessage);
            if (poll) {
                clearInterval(poll);
            }
        };
    };

    /**
     * Open the pop-up player.
     *
     * @param {Object} config
     */
    var openModal = function(config) {
        if (!config.src) {
            Notification.alert('', config.strings.unresolvable);
            return;
        }

        var src = config.src;
        var isVideo = config.type === 'video';
        if (!isVideo && config.provider === 'youtube') {
            src += (src.indexOf('?') < 0 ? '?' : '&') + 'enablejsapi=1&origin=' + encodeURIComponent(window.location.origin);
        }
        var safeSrc = escapeHtmlAttribute(src);

        var body = isVideo ?
            '<video controls autoplay playsinline src="' + safeSrc + '" ' +
            'style="width:100%;max-height:75vh;background:#000;"></video>' :
            '<div class="bulkcoursestudio-embed-wrap" style="position:relative;padding-top:56.25%;">' +
            '<iframe src="' + safeSrc + '" allow="autoplay; fullscreen; encrypted-media" allowfullscreen ' +
            'style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe></div>';

        var tracker = createTracker(config);
        var cleanup = null;
        var clock = null;

        ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            large: true,
            body: body,
        }).then(function(modal) {
            var root = modal.getRoot();
            var modalBody = root.find('.modal-body');
            var onEnded = function() {
                showNext(config, modalBody);
            };

            if (isVideo && config.fallbacksrc) {
                // Streaming from Google Drive can be refused (quota, sharing).
                // Fall back to the normal preview, keeping the time-based rule.
                root.find('video').on('error', function() {
                    tracker.state.enabled = !!config.timed;
                    modalBody.html('<div class="bulkcoursestudio-embed-wrap" style="position:relative;padding-top:56.25%;">' +
                        '<iframe src="' + escapeHtmlAttribute(config.fallbacksrc) + '" allow="autoplay; fullscreen" allowfullscreen ' +
                        'style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe></div>');
                });
            }

            if (config.tracked) {
                if (isVideo) {
                    trackHtml5(root.find('video')[0], config, tracker, onEnded);
                } else {
                    cleanup = trackIframe(root.find('iframe')[0], config, tracker, onEnded);
                }
            }

            modal.show();

            if (tracker.state.enabled) {
                clock = setInterval(tracker.tick, 1000);
            }

            root.on(ModalEvents.hidden, function() {
                if (clock) {
                    clearInterval(clock);
                }
                if (cleanup) {
                    cleanup();
                }
                tracker.send();
                modal.destroy();
            });

            return modal;
        }).catch(Notification.exception);
    };

    return {
        init: init,
    };
});
