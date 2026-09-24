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
 * Adding punctuation to a transcript the browser recognised.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class punctuation_test extends \advanced_testcase {
    /** @var string Speech as the browser's own recogniser returns it: no punctuation, no capitals. */
    const BROWSER_TEXT = 'they you know for the height of a big problem and you must always be careful with the beer '
        . "cuz it's such a big problem you know so that's what I think about the beer and the hiker";

    public function test_needs_punctuation(): void {
        $this->assertTrue(utils::needs_punctuation(self::BROWSER_TEXT));
        // An apostrophe is not sentence punctuation: the browser gets those right.
        $this->assertTrue(utils::needs_punctuation("it's a problem so i said so"));

        // The cloud recognisers punctuate as they go, so their transcripts are left alone.
        $this->assertFalse(utils::needs_punctuation('The birch canoe slid on the smooth planks.'));
        $this->assertFalse(utils::needs_punctuation(''));
        $this->assertFalse(utils::needs_punctuation('   '));
        // The service caps its answer, so a very long one would come back cut short. Do not ask.
        $this->assertFalse(utils::needs_punctuation(str_repeat('word ', utils::PUNCTUATION_MAXWORDS + 10)));
    }

    public function test_same_words(): void {
        $punctuated = 'They, you know, for the height of a big problem, and you must always be careful with the beer, '
            . "cuz it's such a big problem, you know. So that's what I think about the beer and the hiker.";
        $this->assertTrue(utils::same_words(self::BROWSER_TEXT, $punctuated));
        $this->assertTrue(utils::same_words("it's such a big problem", "It's such a big problem."));

        // Anything that is not the same words is not a punctuated copy of what the student said.
        $this->assertFalse(utils::same_words(self::BROWSER_TEXT, 'They, you know, for the height of a big problem.'));
        $this->assertFalse(utils::same_words('i said the beer', 'I said the bear.'));
        $this->assertFalse(utils::same_words('i said the beer', 'I said the beer, really.'));
        $this->assertFalse(utils::same_words('i said the beer', ''));
    }

    public function test_an_already_punctuated_attempt_is_left_alone(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $course->id, 'ttslanguage' => 'en-US']);
        $student = $gen->create_and_enrol($course, 'student');
        $id = $DB->insert_record(constants::M_ATTEMPTSTABLE, (object) ['solo' => $solo->id, 'userid' => $student->id,
            'transcript' => 'The birch canoe slid on the smooth planks.',
            'selftranscript' => 'The birch canoe slid on the smooth planks.',
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $student->id, 'modifiedby' => $student->id]);
        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $id], '*', MUST_EXIST);
        $module = $DB->get_record(constants::M_TABLE, ['id' => $solo->id], '*', MUST_EXIST);

        // Nothing to do, so no call to the service and nothing changes.
        $after = utils::punctuate_attempt_transcript($attempt, $module);
        $this->assertSame($attempt->transcript, $after->transcript);
        $this->assertSame($attempt->transcript, $DB->get_field(constants::M_ATTEMPTSTABLE, 'transcript', ['id' => $id]));
    }
}
