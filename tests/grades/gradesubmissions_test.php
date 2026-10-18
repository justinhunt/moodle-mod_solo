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

namespace mod_solo\grades;

use mod_solo\constants;
use mod_solo\utils;

/**
 * Tests for the grading page data: which students to grade, paging them, and a student's submission.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gradesubmissions::class)]
final class gradesubmissions_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass the solo course module */
    protected $cm;
    /** @var \stdClass the solo instance record */
    protected $moduleinstance;
    /** @var \context_module */
    protected $context;
    /** @var int number of steps a finished attempt has completed */
    protected $totalsteps;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;
    /** @var \stdClass */
    protected $student3;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('solo', $solo->id, $this->course->id, false, MUST_EXIST);
        $this->moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $solo->id], '*', MUST_EXIST);
        $this->context = \context_module::instance($this->cm->id);
        $this->totalsteps = utils::fetch_total_step_count($this->moduleinstance, $this->context);
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->student3 = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * Add an attempt with its stats row.
     *
     * @param \stdClass $user
     * @param bool $finished whether the attempt has completed every step
     * @param string $transcript
     * @return int the attempt id
     */
    protected function add_attempt(\stdClass $user, bool $finished = true, string $transcript = ''): int {
        global $DB;
        $attemptid = $DB->insert_record(constants::M_ATTEMPTSTABLE, [
            'solo' => $this->cm->instance,
            'userid' => $user->id,
            'transcript' => $transcript,
            'completedsteps' => $finished ? $this->totalsteps : $this->totalsteps - 1,
            'timemodified' => time(),
        ]);
        $stats = ['userid' => $user->id, 'attemptid' => $attemptid];
        $zerofields = ['turns', 'words', 'uniquewords', 'longwords', 'avturn', 'longestturn', 'targetwords',
            'totaltargetwords', 'aiaccuracy', 'autogrammarscore', 'autogrammarerrors', 'autospellscore', 'autospellerrors'];
        foreach ($zerofields as $field) {
            $stats[$field] = 0;
        }
        $DB->insert_record(constants::M_STATSTABLE, $stats);
        return $attemptid;
    }

    /**
     * Fetch the students to grade, as attempt id => user id.
     *
     * @param int $groupid
     * @return array
     */
    protected function students_to_grade(int $groupid = 0): array {
        $students = (new gradesubmissions())->getstudentstograde($this->moduleinstance, $groupid, $this->context);
        $result = [];
        foreach ($students as $student) {
            $result[$student->id] = $student->userid;
        }
        return $result;
    }

    public function test_the_activity_has_steps_to_complete(): void {
        // The other tests rely on an unfinished attempt being different from a finished one.
        $this->assertGreaterThan(0, $this->totalsteps);
    }

    public function test_no_attempts_means_no_students_to_grade(): void {
        $this->assertEmpty($this->students_to_grade());
    }

    public function test_students_to_grade_lists_each_student_once_by_latest_finished_attempt(): void {
        $this->add_attempt($this->student1);
        $latest1 = $this->add_attempt($this->student1);
        $latest2 = $this->add_attempt($this->student2);

        $this->assertSame([$latest2 => $this->student2->id, $latest1 => $this->student1->id], $this->students_to_grade());
    }

    public function test_unfinished_attempts_are_not_graded(): void {
        $finished = $this->add_attempt($this->student1);
        $this->add_attempt($this->student1, false);
        $this->add_attempt($this->student2, false);

        $this->assertSame([$finished => $this->student1->id], $this->students_to_grade());
    }

    public function test_group_filter_lists_only_group_members(): void {
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->student2->id]);
        $this->add_attempt($this->student1);
        $attempt2 = $this->add_attempt($this->student2);

        $this->assertSame([$attempt2 => $this->student2->id], $this->students_to_grade($group->id));
    }

    public function test_page_of_students_splits_into_pages_and_finds_the_student(): void {
        $students = [];
        foreach ([11, 12, 13] as $userid) {
            $students[] = (object) ['userid' => $userid];
        }
        $helper = new gradesubmissions();

        $this->assertSame([[[11, 12], [13]], 1], $helper->getpageofstudents($students, 13, 2));
        $this->assertSame([[[11, 12], [13]], 0], $helper->getpageofstudents($students, 12, 2));
        $this->assertSame([[[11], [12], [13]], -1], $helper->getpageofstudents($students));
        $this->assertSame([[], -1], $helper->getpageofstudents([], 11, 2));
    }

    public function test_submission_data_is_the_latest_finished_attempt(): void {
        $this->add_attempt($this->student1, true, 'first try');
        $latest = $this->add_attempt($this->student1, true, 'second try');
        $this->add_attempt($this->student1, false, 'not finished');
        $this->add_attempt($this->student2, true, 'someone else');

        $data = (new gradesubmissions())->getsubmissiondata($this->student1->id, $this->cm->id);

        $this->assertCount(1, $data);
        $this->assertEquals($latest, $data[0]->attemptid);
        $this->assertEquals('second try', $data[0]->transcript);
    }

    public function test_submission_data_is_empty_without_a_finished_attempt(): void {
        $this->add_attempt($this->student1, false);

        $this->assertSame([], (new gradesubmissions())->getsubmissiondata($this->student1->id, $this->cm->id));
    }
}
