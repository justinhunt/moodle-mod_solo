<?php
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

namespace mod_solo\output;

defined('MOODLE_INTERNAL') || die();

use context_module;
use mod_solo\mobile_auth;
use mod_solo\constants;

class mobile {

    public static function mobile_course_view($args) {
        global $DB, $CFG, $OUTPUT, $USER;

        $cmid = $args['cmid'];

        // Verify course context.
        $cm = get_coursemodule_from_id('solo', $cmid);
        if (!$cm) {
            print_error('invalidcoursemodule');
        }
        $course = $DB->get_record('course', ['id' => $cm->course]);
        if (!$course) {
            print_error('coursemisconf');
        }
        require_course_login($course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/solo:view', $context);

        $data = [
            'cmid'    => $cmid,
            'wwwroot' => $CFG->wwwroot,
            'userid' => $USER->id,
        ];

        return [
            'templates'  => [
                [
                    'id'   => 'main',
                    'html' => $OUTPUT->render_from_template('mod_solo/mobile_view_page', $data),
                ],
            ],
            // 'javascript' => file_get_contents($CFG->dirroot . '/mod/solo/library/js/h5p-resizer.js'),
        ];
    }
}
