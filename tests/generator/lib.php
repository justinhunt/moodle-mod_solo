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

/**
 * Data generator for mod_solo.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_solo_generator extends testing_module_generator {
    /**
     * Create a solo instance. The defaults are just enough for solo_add_instance to run without the settings form.
     *
     * @param array|stdClass $record
     * @param array|null $options
     * @return stdClass the course module record
     */
    public function create_instance($record = null, ?array $options = null) {
        global $CFG;
        // The solo_add_instance function uses form constants, which the settings form would normally have loaded.
        require_once($CFG->libdir . '/formslib.php');
        $record = (object) (array) $record;
        $defaults = [
            'viewstart' => 0,
            'activitysteps' => \mod_solo\constants::M_SEQ_PRM,
            'graderatioitem' => 0,
            'gradewordcount' => 0,
            'gradebasescore' => 0,
            'aigradeitem' => 0,
            'relevancegrade' => 0,
        ];
        for ($bonusno = 1; $bonusno <= 4; $bonusno++) {
            $defaults['bonuspoints' . $bonusno] = 0;
            $defaults['bonus' . $bonusno] = '--';
        }
        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }
        return parent::create_instance($record, (array) $options);
    }
}
