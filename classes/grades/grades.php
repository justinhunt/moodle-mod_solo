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

/**
 * Class grades
 *
 * Defines a listing of student grades for this course and module.
 *
 * @package mod_solo\grades
 */
class grades {
    /**
     * Gets listing of grades for students.
     *
     * @param int $courseid Course ID of chat.
     * @param int $coursemoduleid
     * @param int $moduleinstance Module instance ID for given chat.
     * @return array
     * @throws dml_exception
     */
    public function getgrades($courseid, $coursemoduleid, $moduleinstance, $groupid) {
        global $DB;
        $results = [];
        if($groupid > 0){
            list($groupswhere, $groupparams) = $DB->get_in_or_equal($groupid);
            $sql = "select pa.id as attemptid,
                    u.lastname,
                    u.firstname,
                    p.name,
                    p.transcriber,
                    pat.words,
                    pat.targetwords,
                    pat.totaltargetwords,
                    pat.turns,
                    pat.avturn,
                    par.accuracy,
                    pa.solo,
                    pat.aiaccuracy,
                    pa.manualgraded,
                    pa.grade,
                    pa.userid,
                    pa.timemodified
                from {solo} as p
                    inner join {solo_attempts} pa on p.id = pa.solo
                    inner join {course_modules} as cm on cm.course = p.course and cm.id = ?
                    inner join {groups_members} gm ON pa.userid=gm.userid
                    inner join {user} as u on pa.userid = u.id
                    inner join {solo_attemptstats} as pat on pat.attemptid = pa.id and pat.userid = u.id
                    left outer join {solo_ai_result} as par on par.attemptid = pa.id and par.courseid = p.course
                where p.course = ?
                    AND pa.solo = ?
                    AND gm.groupid $groupswhere 
                order by pa.id DESC";

            $alldata = $DB->get_records_sql($sql, array_merge([$coursemoduleid, $courseid, $moduleinstance] , $groupparams));

            // not groups
        }else {
            $sql = "select pa.id as attemptid,
                    u.lastname,
                    u.firstname,
                    p.name,
                    p.transcriber,
                    pat.words,
                    pat.targetwords,
                    pat.totaltargetwords,
                    pat.turns,
                    pat.avturn,
                    par.accuracy,
                    pa.solo,
                    pat.aiaccuracy,
                    pa.manualgraded,
                    pa.grade,
                    pa.userid,
                    pa.timemodified
                from {solo} as p
                    inner join {solo_attempts} pa on p.id = pa.solo
                    inner join {course_modules} as cm on cm.course = p.course and cm.id = ?
                    inner join {user} as u on pa.userid = u.id
                    inner join {solo_attemptstats} as pat on pat.attemptid = pa.id and pat.userid = u.id
                    left outer join {solo_ai_result} as par on par.attemptid = pa.id and par.courseid = p.course
                where p.course = ?
                    AND pa.solo = ?
                order by pa.id DESC";

            $alldata = $DB->get_records_sql($sql, [$coursemoduleid, $courseid, $moduleinstance]);
        }

        // loop through data getting most recent attempt
        if ($alldata) {
            $results = [];
            $userattempttotals = [];
            foreach ($alldata as $thedata) {

                // we ony take the most recent attempt
                if (array_key_exists($thedata->userid, $userattempttotals)) {
                    $userattempttotals[$thedata->userid] = $userattempttotals[$thedata->userid] + 1;
                    continue;
                }
                $userattempttotals[$thedata->userid] = 1;

                $results[] = $thedata;
            }
            foreach ($results as $thedata) {
                $thedata->totalattempts = $userattempttotals[$thedata->userid];
            }
        }
        return $results;
    }
}
