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
 * Access checks on the attempt write path and the web services.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempthelper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(mod_solo_external::class)]
final class security_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass the solo course module, prepare -> record -> model */
    protected $cm;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('solo', $solo->id, $this->course->id, false, MUST_EXIST);
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * Call submit_step as the current user and decode the result.
     */
    protected function submit_step(int $step, array $data): \stdClass {
        $_POST['sesskey'] = sesskey();
        $result = mod_solo_external::submit_step($this->cm->id, $step, 'submitstep', json_encode($data));
        return json_decode($result);
    }

    /**
     * Start an attempt as the current user by submitting the first (prepare) step.
     */
    protected function start_attempt(): \stdClass {
        global $DB, $USER;
        $ret = $this->submit_step(1, ['attemptid' => 0, 'activitytype' => constants::STEP_PREPARE]);
        $this->assertTrue($ret->success, $ret->message ?? '');
        $conditions = ['userid' => $USER->id, 'solo' => $this->cm->instance];
        return $DB->get_record(constants::M_ATTEMPTSTABLE, $conditions, '*', MUST_EXIST);
    }

    public function test_submit_step_ignores_fields_the_form_does_not_send(): void {
        global $DB;
        $this->setUser($this->student1);

        $ret = $this->submit_step(1, [
            'attemptid' => 0,
            'activitytype' => constants::STEP_PREPARE,
            'grade' => 100,
            'aigrade' => 100,
            'manualgraded' => 1,
            'transcript' => 'injected',
            'selftranscript' => 'injected',
            'userid' => $this->student2->id,
        ]);
        $this->assertTrue($ret->success);

        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, ['solo' => $this->cm->instance], '*', MUST_EXIST);
        $this->assertEquals($this->student1->id, $attempt->userid);
        $this->assertEmpty($attempt->grade);
        $this->assertEmpty($attempt->aigrade);
        $this->assertEmpty($attempt->manualgraded);
        $this->assertEmpty($attempt->transcript);
        $this->assertEmpty($attempt->selftranscript);
        $this->assertEquals(1, $attempt->completedsteps);
    }

    public function test_submit_step_rejects_another_users_attempt(): void {
        global $DB;
        $this->setUser($this->student1);
        $attempt = $this->start_attempt();

        $this->setUser($this->student2);
        $ret = $this->submit_step(1, ['attemptid' => $attempt->id]);
        $this->assertFalse($ret->success);

        $after = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertEquals($this->student1->id, $after->userid);
    }

    public function test_submit_step_takes_the_step_type_from_the_activity(): void {
        global $DB;
        $this->setUser($this->student1);
        $attempt = $this->start_attempt();

        // Step 2 is the record step. Claiming it is a transcribe step must not let a transcript through.
        $ret = $this->submit_step(2, [
            'attemptid' => $attempt->id,
            'activitytype' => constants::STEP_SELFTRANSCRIBE,
            'selftranscript' => 'injected',
            'filename' => 'https://s3.amazonaws.com/bucket/audio.mp3',
        ]);
        $this->assertTrue($ret->success);
        $after = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertEmpty($after->selftranscript);
        $this->assertEquals('https://s3.amazonaws.com/bucket/audio.mp3', $after->filename);
    }

    public function test_submit_step_rejects_skipping_steps_and_missing_steps(): void {
        $this->setUser($this->student1);

        $ret = $this->submit_step(3, ['attemptid' => 0]);
        $this->assertFalse($ret->success);

        // The default sequence has three steps, so there is no step 4.
        $attempt = $this->start_attempt();
        $ret = $this->submit_step(4, ['attemptid' => $attempt->id]);
        $this->assertFalse($ret->success);
    }

    public function test_submit_step_only_accepts_https_media_urls(): void {
        $this->setUser($this->student1);
        $attempt = $this->start_attempt();

        foreach (['http://127.0.0.1/audio', 'file:///etc/passwd', '', 'javascript:alert(1)'] as $bad) {
            $ret = $this->submit_step(2, ['attemptid' => $attempt->id, 'filename' => $bad]);
            $this->assertFalse($ret->success, $bad);
        }
        $ret = $this->submit_step(2, ['attemptid' => $attempt->id, 'filename' => 'https://s3.amazonaws.com/b/a.mp3']);
        $this->assertTrue($ret->success);
    }

    public function test_submit_step_requires_access_to_the_activity(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->expectException(\require_login_exception::class);
        $this->submit_step(1, ['attemptid' => 0]);
    }

    public function test_check_for_results_on_another_users_attempt(): void {
        $this->setUser($this->student1);
        $attempt = $this->start_attempt();

        $this->setUser($this->student2);
        $ret = json_decode(mod_solo_external::check_for_results($attempt->id));
        $this->assertFalse($ret->ready);

        // A missing attempt used to fatal on a null dereference.
        $ret = json_decode(mod_solo_external::check_for_results($attempt->id + 1000));
        $this->assertFalse($ret->ready);
    }

    public function test_get_grade_submission_needs_grading_capability(): void {
        $this->setUser($this->student1);
        $this->expectException(\required_capability_exception::class);
        mod_solo_external::get_grade_submission($this->student2->id, $this->cm->id);
    }

    public function test_fetch_ai_grade_needs_manage_activities(): void {
        $this->setUser($this->student1);
        $context = \context_module::instance($this->cm->id);
        $this->expectException(\required_capability_exception::class);
        mod_solo_external::fetch_ai_grade(
            'useast1',
            'en-US',
            '',
            'answer',
            100,
            'marks',
            'feedback',
            'en-US',
            $context->id
        );
    }

    public function test_check_grammar_requires_access_to_the_activity(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        mod_solo_external::check_grammar('some text', $this->cm->instance);
    }

    public function test_delete_attempt_is_scoped_to_the_activity(): void {
        global $DB;
        $this->setUser($this->student1);
        $attempt = $this->start_attempt();

        $other = $this->getDataGenerator()->create_module('solo', ['course' => $this->course->id]);
        $othercm = get_coursemodule_from_instance('solo', $other->id, $this->course->id, false, MUST_EXIST);
        $helper = new attempthelper($othercm);

        $this->assertFalse($helper->delete_attempt($attempt->id));
        $this->assertTrue($DB->record_exists(constants::M_ATTEMPTSTABLE, ['id' => $attempt->id]));
    }
}
