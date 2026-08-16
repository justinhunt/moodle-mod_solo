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

/**
 * A Free Trial Jumper
 *
 *
 * @package    mod_solo
 * @copyright  Justin Hunt (justin@poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

use mod_solo\constants;

require_login(0, false);
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url(constants::M_URL . '/fetchcbpage.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('freetrial', constants::M_COMPONENT));
$PAGE->set_heading(get_string('freetrial', constants::M_COMPONENT));

require_capability('moodle/site:config', $systemcontext);

// The checkout details. These go to JS as JSON, never interpolated into a script by the template,
// because names and emails can contain quotes.
$cbdata = [
    'site' => constants::M_CB_SITE,
    'priceid' => constants::M_CB_TRIAL_PRICEID,
    'wwwroot' => $CFG->wwwroot,
    'firstname' => $USER->firstname,
    'lastname' => $USER->lastname,
    'email' => $USER->email,
    'country' => $USER->country,
];
$PAGE->requires->js_call_amd(constants::M_COMPONENT . '/cbfreetrial', 'init', [$cbdata]);

$templatedata = [
    'wwwroot' => $CFG->wwwroot,
    'settingsurl' => $CFG->wwwroot . constants::M_PLUGINSETTINGS,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(constants::M_COMPONENT . '/fetchcbpage', $templatedata);
echo $OUTPUT->footer();
