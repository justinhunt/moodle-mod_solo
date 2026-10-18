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

/**
 * Tests for the grades report listing: one row per student, from their most recent attempt.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grades::class)]
final class grades_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass the solo course module */
    protected $cm;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;
    /** @var \stdClass */
    protected $student3;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('solo', $solo->id, $this->course->id, false, MUST_EXIST);
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->student3 = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * Add an attempt with its stats row, as a finished attempt would have.
     *
     * @param \stdClass $user
     * @param int $grade
     * @return int the attempt id
     */
    protected function add_attempt(\stdClass $user, int $grade): int {
        global $DB;
        $attemptid = $DB->insert_record(constants::M_ATTEMPTSTABLE, [
            'solo' => $this->cm->instance,
            'userid' => $user->id,
            'grade' => $grade,
            'completedsteps' => 2,
            'timemodified' => time(),
        ]);
        $stats = ['userid' => $user->id, 'attemptid' => $attemptid, 'words' => 10 + $grade];
        $zerofields = ['turns', 'uniquewords', 'longwords', 'avturn', 'longestturn', 'targetwords',
            'totaltargetwords', 'aiaccuracy', 'autogrammarscore', 'autogrammarerrors', 'autospellscore', 'autospellerrors'];
        foreach ($zerofields as $field) {
            $stats[$field] = 0;
        }
        $DB->insert_record(constants::M_STATSTABLE, $stats);
        return $attemptid;
    }

    /**
     * Fetch the grades listing for the test activity, keyed by user id.
     *
     * @param int $groupid
     * @return array
     */
    protected function fetch_grades(int $groupid = 0): array {
        $rows = (new grades())->getgrades($this->course->id, $this->cm->id, $this->cm->instance, $groupid);
        $byuser = [];
        foreach ($rows as $row) {
            $byuser[$row->userid] = $row;
        }
        $this->assertCount(count($rows), $byuser, 'A student is listed more than once');
        return $byuser;
    }

    public function test_no_attempts_gives_an_empty_listing(): void {
        $this->assertSame([], (new grades())->getgrades($this->course->id, $this->cm->id, $this->cm->instance, 0));
    }

    public function test_each_student_is_listed_once_with_their_latest_attempt(): void {
        $this->add_attempt($this->student1, 40);
        $this->add_attempt($this->student1, 50);
        $latest = $this->add_attempt($this->student1, 60);
        $this->add_attempt($this->student2, 70);

        $grades = $this->fetch_grades();

        $this->assertCount(2, $grades);
        $this->assertEquals($latest, $grades[$this->student1->id]->attemptid);
        $this->assertEquals(60, $grades[$this->student1->id]->grade);
        $this->assertEquals(70, $grades[$this->student2->id]->grade);
    }

    public function test_total_attempts_counts_every_attempt_of_the_student(): void {
        $this->add_attempt($this->student1, 40);
        $this->add_attempt($this->student1, 50);
        $this->add_attempt($this->student1, 60);
        $this->add_attempt($this->student2, 70);

        $grades = $this->fetch_grades();

        $this->assertEquals(3, $grades[$this->student1->id]->totalattempts);
        $this->assertEquals(1, $grades[$this->student2->id]->totalattempts);
    }

    public function test_stats_and_student_details_are_included(): void {
        $this->add_attempt($this->student1, 40);

        $row = $this->fetch_grades()[$this->student1->id];

        $this->assertEquals(50, $row->words);
        $this->assertEquals($this->student1->firstname, $row->firstname);
        $this->assertEquals($this->student1->lastname, $row->lastname);
    }

    public function test_attempts_in_other_activities_are_not_listed(): void {
        $other = $this->getDataGenerator()->create_module('solo', ['course' => $this->course->id]);
        $this->add_attempt($this->student1, 40);

        $rows = (new grades())->getgrades($this->course->id, $this->cm->id, $other->id, 0);

        $this->assertSame([], $rows);
    }

    public function test_group_filter_lists_only_group_members(): void {
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->student1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->student3->id]);
        $this->add_attempt($this->student1, 40);
        $this->add_attempt($this->student1, 45);
        $this->add_attempt($this->student2, 50);
        $this->add_attempt($this->student3, 60);

        $grades = $this->fetch_grades($group->id);

        $this->assertEqualsCanonicalizing([$this->student1->id, $this->student3->id], array_keys($grades));
        $this->assertEquals(45, $grades[$this->student1->id]->grade);
        $this->assertEquals(2, $grades[$this->student1->id]->totalattempts);
    }
}
