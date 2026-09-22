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

use mod_solo_external;

/**
 * Storing the transcript the in page streaming recorder sends with a recording.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempthelper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class streaming_submit_test extends \advanced_testcase {
    /** @var string A recording url of the kind the uploader gives back. */
    const URL = 'https://s3.amazonaws.com/poodll-audioprocessing-out-us-east-1/CP/test/a.mp3';

    /**
     * A streaming activity with the given sequence, and a student at its record step.
     *
     * @param int $sequence
     * @param bool $streaming
     * @param string|null $typed The student's typed script, for PTRM.
     * @return array [cm, attemptid, record step number]
     */
    protected function at_record_step(int $sequence, bool $streaming = true, ?string $typed = null): array {
        global $DB, $USER;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $course->id, 'enableai' => 1, 'ttslanguage' => 'en-US',
            'activitysteps' => $sequence, 'streamingrecord' => $streaming ? 1 : 0]);
        $cm = get_coursemodule_from_instance('solo', $solo->id, $course->id, false, MUST_EXIST);
        $this->setUser($gen->create_and_enrol($course, 'student'));
        if ($sequence === constants::M_SEQ_RM) {
            return [$cm, 0, 1];
        }
        $this->submit($cm, 1, ['attemptid' => 0]);
        $attemptid = $DB->get_field(constants::M_ATTEMPTSTABLE, 'id', ['userid' => $USER->id, 'solo' => $cm->instance]);
        if ($sequence === constants::M_SEQ_PTRM) {
            $this->submit($cm, 2, ['attemptid' => $attemptid, 'selftranscript' => $typed]);
            return [$cm, $attemptid, 3];
        }
        return [$cm, $attemptid, 2];
    }

    /**
     * Submit a step as the current user.
     *
     * @param \stdClass $cm
     * @param int $step
     * @param array $data
     * @return \stdClass The web service's answer.
     */
    protected function submit(\stdClass $cm, int $step, array $data): \stdClass {
        $_POST['sesskey'] = sesskey();
        return json_decode(mod_solo_external::submit_step($cm->id, $step, 'submitstep', json_encode($data)));
    }

    /**
     * Submit the record step with the given streaming fields.
     *
     * @param \stdClass $cm
     * @param int $attemptid
     * @param int $step
     * @param array $streaming
     * @return \stdClass The attempt afterwards.
     */
    protected function record(\stdClass $cm, int $attemptid, int $step, array $streaming): \stdClass {
        global $DB, $USER;
        $this->submit($cm, $step, ['attemptid' => $attemptid, 'filename' => self::URL] + $streaming);
        return $DB->get_record(constants::M_ATTEMPTSTABLE, ['userid' => $USER->id, 'solo' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * A streamed word list.
     *
     * @param int $n How many words.
     * @return string JSON
     */
    protected function words(int $n): string {
        $words = [];
        for ($i = 0; $i < $n; $i++) {
            $words[] = ['content' => 'word' . $i, 'start_time' => $i * 0.5, 'end_time' => $i * 0.5 + 0.4, 'confidence' => 0.9];
        }
        return json_encode($words);
    }

    public function test_stream_is_stored_for_each_sequence(): void {
        $this->resetAfterTest();
        foreach ([constants::M_SEQ_PRM, constants::M_SEQ_RM, constants::M_SEQ_PTRM] as $sequence) {
            [$cm, $attemptid, $step] = $this->at_record_step($sequence, true, 'I typed this.');
            $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => 'Word0 word1 word2.',
                'streamingtranscript' => $this->words(3), 'streamingstatus' => 'complete']);
            $this->assertSame('Word0 word1 word2.', $attempt->transcript);
            $this->assertCount(3, json_decode($attempt->jsontranscript)->results->items);
            $this->assertSame('', $attempt->vtttranscript);
            $this->assertEqualsWithDelta(1.4, textanalyser::fetch_duration_from_transcript($attempt->jsontranscript), 0.001);
            // The graded text: the transcript, unless the student typed their own.
            $expected = $sequence === constants::M_SEQ_PTRM ? 'I typed this.' : 'Word0 word1 word2.';
            $this->assertSame($expected, $attempt->selftranscript);
            $this->assertTrue(json_decode(mod_solo_external::check_for_results($attempt->id))->ready);
        }
    }

    public function test_silence_is_a_result(): void {
        $this->resetAfterTest();
        [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_PRM);
        $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => '', 'streamingtranscript' => '[]',
            'streamingstatus' => 'complete']);
        $this->assertSame('', $attempt->transcript);
        $this->assertNotEmpty($attempt->jsontranscript);
        $this->assertTrue(json_decode(mod_solo_external::check_for_results($attempt->id))->ready);
    }

    public function test_failed_stream_stores_nothing(): void {
        $this->resetAfterTest();
        foreach (['none', 'timeout', 'error', 'closed', ''] as $status) {
            [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_PRM);
            $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => 'partial',
                'streamingtranscript' => $this->words(1), 'streamingstatus' => $status]);
            $this->assertEmpty($attempt->jsontranscript, $status);
            $this->assertSame(self::URL, $attempt->filename, $status);
        }
    }

    public function test_input_is_cleaned(): void {
        $this->resetAfterTest();
        [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_RM);
        $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => ' The <b>birch</b> canoe.',
            'streamingtranscript' => '{not json', 'streamingstatus' => 'unconfirmed']);
        $this->assertSame('The birch canoe.', $attempt->transcript);
        $this->assertCount(0, json_decode($attempt->jsontranscript)->results->items);

        [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_PRM);
        $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => str_repeat('a ', 60000),
            'streamingtranscript' => '[]', 'streamingstatus' => 'complete']);
        $this->assertEmpty($attempt->jsontranscript);
    }

    public function test_ignored_when_the_activity_does_not_stream(): void {
        $this->resetAfterTest();
        [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_PRM, false);
        $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => 'Hello.',
            'streamingtranscript' => $this->words(1), 'streamingstatus' => 'complete']);
        $this->assertEmpty($attempt->transcript);
        $this->assertEmpty($attempt->jsontranscript);
    }

    public function test_rerecording_clears_the_old_grading(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, $attemptid, $step] = $this->at_record_step(constants::M_SEQ_PRM);
        $attempt = $this->record($cm, $attemptid, $step, ['streamingtext' => 'First.',
            'streamingtranscript' => $this->words(1), 'streamingstatus' => 'complete']);
        $DB->update_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id, 'grade' => 80, 'aigrade' => 70,
            'aifeedback' => 'good', 'grammarcorrection' => 'First!']);

        $this->submit($cm, $step, ['attemptid' => $attempt->id, 'filename' => self::URL . '?2', 'streamingtext' => 'Second.',
            'streamingtranscript' => $this->words(2), 'streamingstatus' => 'complete']);
        $after = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id]);
        $this->assertSame('Second.', $after->selftranscript);
        $this->assertEquals(0, $after->grade);
        $this->assertNull($after->aigrade);
        $this->assertNull($after->aifeedback);
        $this->assertNull($after->grammarcorrection);

        // A teacher's grade stays.
        $DB->update_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id, 'grade' => 90, 'manualgraded' => 1]);
        $this->submit($cm, $step, ['attemptid' => $attempt->id, 'filename' => self::URL . '?3', 'streamingtext' => 'Third.',
            'streamingtranscript' => $this->words(2), 'streamingstatus' => 'complete']);
        $this->assertEquals(90, $DB->get_field(constants::M_ATTEMPTSTABLE, 'grade', ['id' => $attempt->id]));
    }
}
