<?php

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
    public function getGrades(int $courseid, int $coursemoduleid, int $moduleinstance) : array {
        global $DB;

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
                    pa.grade
                from {solo} as p
                    inner join  (select max(mpa.id) as id, mpa.userid, mpa.solo, mpa.grade, mpa.manualgraded 
                            from {solo_attempts} mpa
                            group by mpa.userid, mpa.solo, mpa.grade
                        ) as pa on p.id = pa.solo
                    inner join {course_modules} as cm on cm.course = p.course and cm.id = ?
                    inner join {user} as u on pa.userid = u.id
                    inner join {solo_attemptstats} as pat on pat.attemptid = pa.id and pat.userid = u.id
                    left outer join {solo_ai_result} as par on par.attemptid = pa.id and par.courseid = p.course
                where p.course = ?
                    AND pa.solo = ?
                order by u.lastname";

        return $DB->get_records_sql($sql, [$coursemoduleid, $courseid, $moduleinstance]);
    }
}