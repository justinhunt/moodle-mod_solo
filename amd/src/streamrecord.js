// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The record step's in page streaming recorder (templates/streamrecorder.mustache).
 *
 * Drives the minimal skin's four visual modes (start, recording, uploading, playback) from ttrecorder's events,
 * and fills the step form's hidden fields once both halves of a recording are in: the audio url, which is only
 * final when the upload has finished, and the transcript, which arrives when the stream has ended.
 *
 * @module     mod_solo/streamrecord
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/str', 'core/modal', 'mod_solo/ttrecorder'],
    function($, log, str, Modal, ttrecorder) {
    "use strict";

    // How long to wait for the transcript once the upload has finished. The streamer itself gives up 3s after stop,
    // so this only fires if something went wrong on the way.
    var SPEECH_BACKSTOP_MS = 8000;
    // Where the chosen microphone is remembered, per browser.
    var DEVICE_KEY = 'mod_solo_streamrec_deviceid';

    return {

        clone: function() {
            return $.extend(true, {}, this);
        },

        /**
         * Set up the recorder.
         *
         * @param {Object} opts uniqueid (the recorder template's), widgetid (the step form's hidden fields),
         *        nextbuttonid (the step's Next button)
         */
        init: function(opts) {
            var dd = this.clone();
            dd.uniqueid = opts.uniqueid;
            dd.container = $('#ttrec_container_' + dd.uniqueid);
            // Only once the template is on the page; ttrecorder reads everything from it.
            if (dd.container.length === 0 || dd.container.data('streamrecinit')) {
                return;
            }
            dd.container.data('streamrecinit', true);

            dd.fields = {
                filename: $('#' + opts.widgetid + '_filename'),
                transcript: $('#' + opts.widgetid + '_streamingtranscript'),
                text: $('#' + opts.widgetid + '_streamingtext'),
                status: $('#' + opts.widgetid + '_streamingstatus')
            };
            dd.nextbutton = $('#' + opts.nextbuttonid);
            dd.button = $('#' + dd.uniqueid + '_recorderbutton');
            dd.counter = dd.container.find('.mod_solo_streamrec_counter');
            dd.initialtime = dd.counter.text();
            dd.percent = dd.container.find('.mod_solo_streamrec_percent');
            dd.statusarea = dd.container.find('.mod_solo_streamrec_status');
            dd.player = $('#' + dd.uniqueid + '_player_mod_solo_hiddenaudioplayer');
            dd.bloburl = null;

            dd.load_strings();
            dd.reset();

            dd.ttr = ttrecorder.clone();
            dd.ttr.init({
                uniqueid: dd.uniqueid,
                stt_guided: false,
                callback: function(message) {
                    dd.handle(message);
                }
            });
            dd.ttr.deviceid = dd.load_device();
            dd.register_events();
        },

        load_strings: function() {
            var dd = this;
            dd.strings = {};
            var keys = ['streamrecord', 'streamstop', 'streamuploaded', 'streamuploadfailed', 'streamsettings',
                'streammicrophone', 'streammicdefault', 'streamdownload', 'streamrecording'];
            str.get_strings(keys.map(function(key) {
                return {key: key, component: 'mod_solo'};
            })).then(function(results) {
                keys.forEach(function(key, i) {
                    dd.strings[key] = results[i];
                });
                return results;
            }).catch(log.debug);
        },

        register_events: function() {
            var dd = this;
            dd.container.find('.mod_solo_streamrec_restart').on('click', function(e) {
                e.preventDefault();
                dd.restart();
            });
            dd.container.find('.mod_solo_streamrec_settings').on('click', function(e) {
                e.preventDefault();
                if (dd.container.attr('data-mode') === 'playback' && dd.bloburl) {
                    dd.show_download();
                } else if (dd.container.attr('data-mode') === 'start') {
                    dd.show_devices();
                }
            });
        },

        handle: function(message) {
            var dd = this;
            switch (message.type) {
                case 'recordingstarted':
                    dd.reset();
                    dd.set_mode('recording');
                    dd.button.attr('aria-label', dd.strings.streamstop || '');
                    dd.say(dd.strings.streamrecording || '');
                    break;

                case 'recordingstopped':
                    dd.button.attr('aria-label', dd.strings.streamrecord || '');
                    dd.percent.text('0%');
                    dd.set_mode('uploading');
                    dd.say('');
                    break;

                case 'mediasaved':
                    // The upload has only started, but the local copy can be played straight away.
                    dd.bloburl = message.bloburl;
                    if (dd.player.length) {
                        dd.player.attr('src', message.bloburl);
                        dd.player[0].load();
                    }
                    break;

                case 'mediauploadprogress':
                    dd.percent.text(message.percent + '%');
                    break;

                case 'mediauploaded':
                    dd.mediaurl = message.mediaurl;
                    dd.set_mode('playback');
                    dd.say(dd.strings.streamuploaded || '');
                    // The transcript normally arrives before this. If it has not, give it a little longer.
                    if (dd.speech === null) {
                        dd.backstop = setTimeout(function() {
                            if (dd.speech === null) {
                                log.debug('Solo streamrecord: no transcript arrived, going on without one');
                                dd.speech = {text: '', words: [], status: 'none'};
                                dd.maybe_ready();
                            }
                        }, SPEECH_BACKSTOP_MS);
                    }
                    dd.maybe_ready();
                    break;

                case 'mediauploadfailed':
                    dd.reset();
                    dd.set_mode('start');
                    dd.say(dd.strings.streamuploadfailed || '', true);
                    break;

                case 'speech':
                    dd.speech = {
                        text: message.capturedspeech || '',
                        words: message.speechresults || [],
                        // 'terminated' means the service confirmed it had sent everything.
                        status: message.streamstatus === 'terminated' ? 'complete' : (message.streamstatus || 'unknown')
                    };
                    dd.maybe_ready();
                    break;
            }
        },

        // Both halves are in: fill the step form and let the student move on.
        maybe_ready: function() {
            var dd = this;
            if (!dd.mediaurl || dd.speech === null) {
                return;
            }
            clearTimeout(dd.backstop);
            dd.fields.transcript.val(JSON.stringify(dd.speech.words));
            dd.fields.text.val(dd.speech.text);
            dd.fields.status.val(dd.speech.status);
            // Last, because the Next button treats a filename as "there is a recording".
            dd.fields.filename.val(dd.mediaurl);
            dd.nextbutton.prop('disabled', false);
        },

        reset: function() {
            var dd = this;
            clearTimeout(dd.backstop);
            dd.mediaurl = null;
            dd.speech = null;
            Object.keys(dd.fields).forEach(function(name) {
                dd.fields[name].val('');
            });
            dd.percent.text('');
        },

        restart: function() {
            var dd = this;
            if (dd.player.length) {
                dd.player[0].pause();
            }
            dd.reset();
            dd.counter.text(dd.initialtime);
            dd.set_mode('start');
            dd.say('');
            dd.button.trigger('focus');
        },

        set_mode: function(mode) {
            var dd = this;
            dd.container.attr('data-mode', mode);
            // No moving on while a recording is under way or being saved.
            dd.nextbutton.prop('disabled', mode === 'recording' || mode === 'uploading');
            dd.button.prop('disabled', mode === 'uploading');
        },

        // Status messages are read out but not shown, like the minimal skin. An error is shown too.
        say: function(text, iserror) {
            this.statusarea.toggleClass('mod_solo_streamrec_error', !!iserror).text(text);
        },

        load_device: function() {
            try {
                return window.localStorage.getItem(DEVICE_KEY) || '';
            } catch (e) {
                return '';
            }
        },

        save_device: function(deviceid) {
            this.ttr.deviceid = deviceid;
            try {
                if (deviceid) {
                    window.localStorage.setItem(DEVICE_KEY, deviceid);
                } else {
                    window.localStorage.removeItem(DEVICE_KEY);
                }
            } catch (e) {
                // Not remembered, the choice still applies to this page.
            }
        },

        // Choose a microphone. Device names are only shown once the browser has been allowed to use the microphone.
        show_devices: function() {
            var dd = this;
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                return;
            }
            var selectid = dd.uniqueid + '_deviceselect';
            navigator.mediaDevices.enumerateDevices().then(function(devices) {
                // Built with jQuery so labels are escaped, then handed to the modal as a string.
                var select = $('<select class="form-select"></select>').attr('id', selectid);
                var addoption = function(value, label) {
                    var option = $('<option></option>').attr('value', value).text(label);
                    if (value === (dd.ttr.deviceid || '')) {
                        option.attr('selected', 'selected');
                    }
                    select.append(option);
                };
                addoption('', dd.strings.streammicdefault || '');
                var n = 0;
                devices.forEach(function(device) {
                    if (device.kind !== 'audioinput' || device.deviceId === 'default' || device.deviceId === '') {
                        return;
                    }
                    n++;
                    addoption(device.deviceId, device.label || ((dd.strings.streammicrophone || '') + ' ' + n));
                });
                var body = $('<div></div>')
                    .append($('<label class="form-label"></label>').attr('for', selectid).text(dd.strings.streammicrophone || ''))
                    .append(select);
                return Modal.create({title: dd.strings.streamsettings || '', body: body.html(), show: true,
                    removeOnClose: true});
            }).then(function(modal) {
                if (modal) {
                    modal.getRoot().on('change', '#' + selectid, function() {
                        dd.save_device($(this).val());
                    });
                }
                return modal;
            }).catch(log.debug);
        },

        show_download: function() {
            var dd = this;
            var link = $('<a class="btn btn-primary" download="recording.wav"></a>').attr('href', dd.bloburl)
                .text(dd.strings.streamdownload || '');
            Modal.create({title: dd.strings.streamsettings || '', body: $('<div></div>').append(link).html(), show: true,
                removeOnClose: true}).catch(log.debug);
        }
    };
});
