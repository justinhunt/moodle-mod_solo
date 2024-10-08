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

use dml_exception;
use mod_solo\constants;
use mod_solo\utils;

/**
 * Class grades
 *
 * Defines a listing of student grades for this course and module.
 *
 * @package mod_solo\grades
 */
class gradesubmissions {

    /**
     * Gets full submission data for a student's entry.
     *
     * @param int $userid
     * @param int $cmid
     * @return array
     * @throws dml_exception
     */
    public function getsubmissiondata($userid, $cmid) {
        global $DB;
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $modulecontext = \context_module::instance($cm->id);
        $totalsteps = utils::fetch_total_step_count($moduleinstance, $modulecontext);

        $sql = "SELECT pa.id AS attemptid,
                    u.lastname,
                    u.firstname,
                    p.name,
                    p.transcriber,
                    p.id,
                    pa.filename,
                    pa.selftranscript,
                    pa.transcript,
                    pa.jsontranscript,
                    pat.turns,
                    pat.words,
                    pat.avturn,
                    pat.uniquewords,
                    pat.longwords,
                    pat.longestturn,
                    pat.targetwords,
                    pat.totaltargetwords,
                    pat.autogrammarscore,
                    pat.autospellscore,
                    pat.aiaccuracy,
                    pa.manualgraded,
                    pa.grade,
                    pa.feedback,
                    pa.userid
                FROM {solo} AS p
                    INNER JOIN {solo_attempts} pa ON p.id = pa.solo
                    INNER JOIN {course_modules} AS cm ON cm.course = p.course AND cm.id = ?
                    INNER JOIN {user} AS u ON pa.userid = u.id
                    INNER JOIN {solo_attemptstats} AS pat ON pat.attemptid = pa.id AND pat.userid = u.id
                    left outer join {solo_ai_result} AS par ON par.attemptid = pa.id AND par.courseid = p.course
                WHERE pa.userid = ?
                    AND pa.solo = ?
                    AND pa.completedsteps = ?
                order by pa.id DESC";

        $alldata = $DB->get_records_sql($sql, [$cmid, $userid, $moduleinstance->id, $totalsteps]);
        if ($alldata) {
            return [reset($alldata)];
        } else {
            return [];
        }

    }

    /**
     * Returns a listing of students who should be graded based on the user clicked.
     *
     * @param int $attempt
     * @return array
     * @throws dml_exception
     */
    public function getstudentstograde($moduleinstance, $groupid, $modulecontext) {
        global $DB;

        $totalsteps = utils::fetch_total_step_count($moduleinstance, $modulecontext);

        // fetch all finished attempts
        if ($groupid > 0) {
            list($groupswhere, $groupparams) = $DB->get_in_or_equal($groupid);
            $sql = "SELECT pa.id AS id, pa.userid as userid
                    FROM {solo_attempts} pa                    
                     INNER JOIN {groups_members} gm ON pa.userid=gm.userid
                     WHERE pa.solo = ? AND pa.completedsteps = " . $totalsteps .
                     " AND gm.groupid $groupswhere 
                      ORDER BY pa.id DESC";
            $results = $DB->get_records_sql($sql, array_merge([$moduleinstance->id], $groupparams));
        } else {
            $sql = "SELECT pa.id AS id, pa.userid AS userid
                    FROM {solo_attempts} pa
                    WHERE pa.solo = ? AND pa.completedsteps = " . $totalsteps .
                    " ORDER BY pa.id DESC";
            $results = $DB->get_records_sql($sql, [$moduleinstance->id]);
        }

        // if we do not have results just return
        if (!$results) {
            return $results;
        }

        // we ony take the most recent attempt
        $latestresults = [];
        $userattempttotals = [];
        foreach ($results as $thedata) {
            if (array_key_exists($thedata->userid, $userattempttotals)) {
                $userattempttotals[$thedata->userid] = $userattempttotals[$thedata->userid] + 1;
                continue;
            }
            $userattempttotals[$thedata->userid] = 1;
            $latestresults[] = $thedata;
        }

        // if we looped and did not get 3 lets just return what we got
        return $latestresults;

    }//end of function
    /**
     * Returns a listing of students who should be graded based on the user clicked.
     *
     * @param int $attempt
     * @return array
     * @throws dml_exception
     */
    public function getpageofstudents($students, $studentid=0, $perpage=1) {
        $currentpagemembers = [];
        $pages = [];
        $studentpage = -1;
        // build array of 3 student pages
        foreach ($students as $student) {
            if (count($currentpagemembers) >= $perpage) {
                $pages[] = $currentpagemembers;
                $currentpagemembers = [];
            }
            $currentpagemembers[] = $student->userid;
            if ($studentid > 0 && $student->userid == $studentid ) {
                $studentpage = count($pages);
            }
        }
        if (count($currentpagemembers) > 0) {
            $pages[] = $currentpagemembers;
        }
        // return page details
        $ret = [$pages, $studentpage];
        return $ret;
    }
}//end of class
