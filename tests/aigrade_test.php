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

namespace mod_solo;

/**
 * Storing what the AI grader returns.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class aigrade_test extends \advanced_testcase {
    /**
     * An attempt to store an AI grade on.
     *
     * @return \stdClass
     */
    protected function attempt(): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $id = $DB->insert_record(constants::M_ATTEMPTSTABLE, (object) ['solo' => $solo->id, 'userid' => $student->id,
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $student->id, 'modifiedby' => $student->id]);
        return $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The stored attempt.
     *
     * @param \stdClass $attempt
     * @return \stdClass
     */
    protected function stored(\stdClass $attempt): \stdClass {
        global $DB;
        return $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id], '*', MUST_EXIST);
    }

    public function test_marks_and_feedback_are_stored(): void {
        $this->resetAfterTest();
        $attempt = $this->attempt();
        utils::store_ai_grade($attempt, (object) ['marks' => 80, 'feedback' => ['Good.', 'Try again.']]);
        $stored = $this->stored($attempt);
        $this->assertEquals(80, $stored->aigrade);
        $this->assertSame(['Good.', 'Try again.'], json_decode($stored->aifeedback, true));
    }

    public function test_marks_without_feedback_are_still_stored(): void {
        $this->resetAfterTest();
        // The grader can answer with a mark and no feedback. The whole answer used to be thrown away, so the mark
        // never reached the attempt and the grade formula counted the AI part as 100%.
        $attempt = $this->attempt();
        utils::store_ai_grade($attempt, (object) ['marks' => 65, 'correctedtext' => 'Hello.']);
        $stored = $this->stored($attempt);
        $this->assertEquals(65, $stored->aigrade);
        $this->assertNull($stored->aifeedback);
        // And on the attempt we were handed, for the code that carries on with it.
        $this->assertEquals(65, $attempt->aigrade);
    }

    public function test_feedback_without_marks_is_still_stored(): void {
        $this->resetAfterTest();
        $attempt = $this->attempt();
        utils::store_ai_grade($attempt, (object) ['feedback' => ['Nearly.']]);
        $stored = $this->stored($attempt);
        $this->assertNull($stored->aigrade);
        $this->assertSame(['Nearly.'], json_decode($stored->aifeedback, true));
    }

    public function test_nothing_usable_stores_nothing(): void {
        $this->resetAfterTest();
        foreach ([false, (object) [], (object) ['correctedtext' => 'Hello.'], (object) ['marks' => 'not a number']] as $results) {
            $attempt = $this->attempt();
            utils::store_ai_grade($attempt, $results);
            $stored = $this->stored($attempt);
            $this->assertNull($stored->aigrade);
            $this->assertNull($stored->aifeedback);
        }
    }

    public function test_feedback_that_is_already_json_is_not_encoded_twice(): void {
        $this->resetAfterTest();
        $attempt = $this->attempt();
        utils::store_ai_grade($attempt, (object) ['marks' => 50, 'feedback' => '["Already json."]']);
        $this->assertSame(['Already json.'], json_decode($this->stored($attempt)->aifeedback, true));
    }
}
