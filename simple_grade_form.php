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

global $CFG;

use mod_solo\constants;
use mod_solo\utils;

require_once("$CFG->libdir/formslib.php");
require_once($CFG->libdir . '/pear/HTML/QuickForm/input.php');


class simple_grade_form extends moodleform {
    // Add elements to form
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $options = utils::get_grade_element_options();
        $mform->addElement('select', 'grade', get_string('grade', constants::M_COMPONENT), $options, ["size" => "5"]);
        $mform->setDefault('grade', 0);

        $mform->addElement('textarea', 'feedback', 'Feedback', 'wrap="virtual" style="width:100%;" rows="10" ');
    }

    // Custom validation should be added here
    function validation($data, $files) {
        return [];
    }
}
