<?php

namespace mod_solo\grades;

use dml_exception;
use mod_solo\constants;

/**
 * Class grades
 *
 * Defines a listing of student grades for this course and module.
 *
 * @package mod_solo\grades
 */
class gradesubmissions {
    /**
     * Gets assignment data for a specific student.
     *
     * @param int $courseid Course ID of chat.
     * @param int $studentid Moodle student ID
     * @param int $moduleinstance
     * @return array
     * @throws dml_exception
     */
    public function getGradeData(int $courseid, int $studentid, int $moduleinstance): array {
        global $DB;

        $sql = "select pa.id, u.lastname, u.firstname, p.name, p.transcriber, pat.words, pat.avturn, pat.longestturn, pat.targetwords, 
              pat.totaltargetwords, pat.autogrammarscore,pat.autospellscore, pat.aiaccuracy, pat.uniquewords, pat.longwords
                from {" . constants::M_TABLE . "} as p
                    inner join  (select max(mpa.id) as id, mpa.userid, mpa.solo
                            from {" . constants::M_ATTEMPTSTABLE . "} mpa
                            group by mpa.userid, mpa.solo
                        ) as pa on p.id = pa.solo
                    inner join {course_modules} as cm on cm.course = p.course and cm.id = ?
                    inner join {user} as u on pa.userid = u.id
                    inner join {" . constants::M_STATSTABLE . "} as pat on pat.attemptid = pa.id and pat.userid = u.id
                    left outer join {" . constants::M_AITABLE . "} as par on par.attemptid = pa.id and par.courseid = p.course
                where u.id = ?
                    AND pa.solo = ?
                    AND p.course = ?
                order by u.lastname";

        return $DB->get_records_sql($sql, [$studentid, $moduleinstance, $courseid]);
    }

    /**
     * Gets full submission data for a student's entry.
     *
     * @param int $userid
     * @param int $cmid
     * @return array
     * @throws dml_exception
     */
    public function getSubmissionData(int $userid, int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
        $sql = "select pa.id,
                   u.lastname,
                   u.firstname,
                   p.name,
                   p.transcriber,
                   p.id,
                   pa.solo,
                   pat.solo,
                   ca.filename,
                   ca.selftranscript,
                    ca.transcript,
                    ca.jsontranscript,
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
                    ca.grade as grade,
                    pa.feedback
            from {" . constants::M_TABLE . "} as p
                inner join (select max(mpa.id) as id, mpa.userid, mpa.solo, mpa.feedback
                 from {" . constants::M_ATTEMPTSTABLE . "} mpa group by  mpa.userid, mpa.solo, mpa.feedback) as pa
            on p.id = pa.solo
                inner join {course_modules} as cm on cm.course = p.course
                inner join {user} as u on pa.userid = u.id
                inner join {" . constants::M_STATSTABLE . "} as pat on pat.attemptid = pa.id and pat.userid = u.id
                left outer join  {" . constants::M_AITABLE . "} as par on par.attemptid = pa.id and par.courseid = p.course
                left outer join {" . constants::M_ATTEMPTSTABLE . "} as ca on ca.solo = pa.solo and ca.userid = u.id
            where u.id = ?
            and cm.id = ?
            and p.id = ?;";

        return $DB->get_records_sql($sql, [$userid, $cmid,$moduleinstance->id]);
    }

    /**
     * Returns a listing of students who should be graded based on the user clicked.
     *
     * @param int $attempt
     * @return array
     * @throws dml_exception
     */
    public function getStudentsToGrade($attempt,$moduleinstance) {
        global $DB;

        //fetch all finished attempts
        $sql = "select userid as students
                    from {solo_attempts} pa
                    where pa.solo = ? AND pa.completedsteps = " . constants::STEP_SELFTRANSCRIBE .
                    " order by pa.id ASC";

        $results = $DB->get_records_sql($sql, [$attempt, $moduleinstance->id]);
        //if we do not have results just return
        if(!$results){return $results;}

        //if we have results, try to get 3 of them including the selected one
        if(count($results<4)){
            return $results;
        }else{
            $ret = [];
            $returning=0;
            foreach($results as $result){
                if($result->id == $attempt){
                    $returning=1;
                    $ret[] = $result;
                }elseif($returning>0 && $returning<3){
                    $ret[] = $result;
                }
                if($returning==3){
                    return $ret;
                }
            }
            //if we looped and did not get 3 lets just return what we got
            return $ret;
        }//end of if
    }//end of function
}//end of class