<?php
/**
 * External.
 *
 * @package mod_solo
 * @author  Justin Hunt - Poodll.com
 */

global $CFG;

//This is for pre M4.0 and post M4.0 to work on same code base
require_once($CFG->libdir . '/externallib.php');

/*
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
*/

use mod_solo\grades\gradesubmissions;
use mod_solo\utils;
use mod_solo\constants;
use mod_solo\aitranscript;
use mod_solo\textanalyser;
use mod_solo\attempthelper;

/**
 * External class.
 *
 * @package mod_solo
 * @author  Justin Hunt - Poodll.com
 */
class mod_solo_external extends external_api {

    public static function check_grammar($text, $activityid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::check_grammar_parameters(), [
            'text' => $text,
            'activityid' => $activityid]);
        extract($params);

        $mod = $DB->get_record(constants::M_TABLE, ['id' => $activityid], '*', MUST_EXIST);
        if (!$mod) {
            return "";
        }

        $siteconfig = get_config(constants::M_COMPONENT);
        $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
        $textanalyser = new textanalyser($token,$text,$mod->region,$mod->ttslanguage);
        $suggestions = $textanalyser->fetch_grammar_correction();
        if($suggestions==$text || empty($suggestions)){
            return "";
        }

        //if we have suggestions, mark those up and return them
        $direction="r2l";//"l2r";
        list($grammarerrors,$grammarmatches,$insertioncount) = \mod_solo\utils::fetch_grammar_correction_diff($text, $suggestions,$direction);
        $markedupsuggestions = \mod_solo\aitranscriptutils::render_passage($suggestions,'corrections');
        $ret = [];
        $ret['grammarerrors'] = $grammarerrors;
        $ret['grammarmatches'] = $grammarmatches;
        $ret['suggestions'] = $markedupsuggestions;
        $ret['insertioncount'] = $insertioncount;

        return json_encode($ret);

    }

    public static function check_grammar_parameters() {
        return new external_function_parameters([
            'text' => new external_value(PARAM_TEXT),
            'activityid' => new external_value(PARAM_INT)
        ]);
    }

    public static function check_grammar_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function get_grade_submission_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT),
            'cmid' => new external_value(PARAM_INT),
        ]);
    }

    public static function get_grade_submission($userid,  $cmid) {
        $gradesubmissions = new gradesubmissions();
            return ['response' => $gradesubmissions->getSubmissionData($userid,$cmid)];
    }

    public static function get_grade_submission_returns() {
        return new external_function_parameters([
            'response' => new external_multiple_structure(
                new external_single_structure([

                    'id' => new external_value(PARAM_INT, 'ID'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                    'transcriber' => new external_value(PARAM_TEXT, 'Transcriber'),
                    'turns' => new external_value(PARAM_TEXT, 'Turns', VALUE_OPTIONAL),
                    'avturn' => new external_value(PARAM_TEXT, 'AV Turn', VALUE_OPTIONAL),
                    'accuracy' => new external_value(PARAM_TEXT, 'Accuracy', VALUE_OPTIONAL),
                    'chatid' => new external_value(PARAM_INT, 'Chat ID', VALUE_OPTIONAL),
                    'filename' => new external_value(PARAM_TEXT, 'File name', VALUE_OPTIONAL),
                    'transcript' => new external_value(PARAM_TEXT, 'Transcript', VALUE_OPTIONAL),
                    'jsontranscript' => new external_value(PARAM_TEXT, 'JSON transcript', VALUE_OPTIONAL),
                    'selftranscript' => new external_value(PARAM_TEXT, 'Self Transcript', VALUE_OPTIONAL),
                    'words' => new external_value(PARAM_TEXT, 'Words', VALUE_OPTIONAL),
                     'uniquewords' => new external_value(PARAM_TEXT, 'Unique Words', VALUE_OPTIONAL),
                    'longwords' => new external_value(PARAM_TEXT, 'Long Words', VALUE_OPTIONAL),
                    'longestturn' => new external_value(PARAM_TEXT, 'Longest Turn', VALUE_OPTIONAL),
                    'targetwords' => new external_value(PARAM_TEXT, 'Target Words', VALUE_OPTIONAL),
                    'totaltargetwords' => new external_value(PARAM_TEXT, 'Total Target Words', VALUE_OPTIONAL),
                    'autogrammarscore' => new external_value(PARAM_TEXT, 'Grammar score', VALUE_OPTIONAL),
                    'autospellscore' => new external_value(PARAM_TEXT, 'Spelling score', VALUE_OPTIONAL),
                    'aiaccuracy' => new external_value(PARAM_TEXT, 'AI Accuracy', VALUE_OPTIONAL),
                    'grade' => new external_value(PARAM_TEXT, 'Grade', VALUE_OPTIONAL),
                    'remark' => new external_value(PARAM_TEXT, 'Remark', VALUE_OPTIONAL),
                    'feedback' => new external_value(PARAM_TEXT, 'Feedback', VALUE_OPTIONAL),
                ])
            )
        ]);
    }

    /**
     * Describes the parameters for submit_rubric_grade_form webservice.
     * @return external_function_parameters
     */
    public static function submit_rubric_grade_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the course',VALUE_REQUIRED),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create grade form, encoded as a json array',VALUE_REQUIRED),
                'studentid' => new external_value(PARAM_INT, 'The id for the student', VALUE_DEFAULT,0),
                'cmid' => new external_value(PARAM_INT, 'The course module id for the item', VALUE_DEFAULT,0),
            )
        );
    }

    /**
     * Submit the rubric grade form.
     *
     * @param int $contextid The context id for the course.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @param $studentid
     * @param $cmid
     * @return int new grade id.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function submit_rubric_grade_form($contextid, $jsonformdata, $studentid, $cmid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/solo/rubric_grade_form.php');
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/mod/solo/lib.php');

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_rubric_grade_form_parameters(),
            ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);
        require_capability('moodle/course:managegroups', $context);

        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);
        $modulecontext = context_module::instance($cmid);
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $attempthelper = new \mod_solo\attempthelper($cm);
        $attempt= $attempthelper->fetch_latest_complete_attempt($studentid);

        if (!$attempt) { return 0; }

        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id'=>$attempt->solo));
        $gradingdisabled=false;
        $gradinginstance = utils::get_grading_instance($attempt->attemptid, $gradingdisabled,$moduleinstance, $modulecontext);

        $mform = new \rubric_grade_form(null, array('gradinginstance' => $gradinginstance), 'post', '', null, true, $data);

        $validateddata = $mform->get_data();

        if ($validateddata) {
            // Insert rubric
            if (!empty($validateddata->advancedgrading['criteria'])) {
                $thegrade=null;
                if (!$gradingdisabled) {
                    if ($gradinginstance) {
                        $thegrade = $gradinginstance->submit_and_get_grade($validateddata->advancedgrading,
                            $attempt->id);
                    }
                }
            }
            $feedbackobject = new \stdClass();
            $feedbackobject->id = $attempt->id;
            $feedbackobject->feedback = $validateddata->feedback;
            $feedbackobject->manualgraded = 1;
            $feedbackobject->grade = $thegrade;
            $DB->update_record(constants::M_ATTEMPTSTABLE, $feedbackobject);
            $grade = new \stdClass();
            $grade->userid = $studentid;
            $grade->rawgrade = $thegrade;
            \solo_grade_item_update($moduleinstance,$grade);
        } else {
            // Generate a warning.
            throw new \moodle_exception('erroreditgroup', 'group');
        }

        return 1;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     * @since Moodle 3.0
     */
    public static function submit_rubric_grade_form_returns() {
        return new external_value(PARAM_INT, 'grade id');
    }


    //---------------
    /**
     * Describes the parameters for submit_simple_grade_form webservice.
     * @return external_function_parameters
     */
    public static function submit_simple_grade_form_parameters() {
        return new external_function_parameters(
                array(
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course',VALUE_REQUIRED),
                        'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create grade form, encoded as a json array',VALUE_REQUIRED),
                        'studentid' => new external_value(PARAM_INT, 'The id for the student', VALUE_DEFAULT,0),
                        'cmid' => new external_value(PARAM_INT, 'The course module id for the item', VALUE_DEFAULT,0),
                )
        );
    }

    /**
     * Submit the simple grade form.
     *
     * @param int $contextid The context id for the course.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @param $studentid
     * @param $cmid
     * @return int new grade id.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function submit_simple_grade_form($contextid, $jsonformdata, $studentid, $cmid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/solo/simple_grade_form.php');
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/mod/solo/lib.php');

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_simple_grade_form_parameters(),
                ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);
        require_capability('moodle/course:managegroups', $context);

        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $attempthelper = new \mod_solo\attempthelper($cm);
        $attempt= $attempthelper->fetch_latest_complete_attempt($studentid);

        if (!$attempt) { return 0; }

        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id'=>$attempt->solo));

        $mform = new \simple_grade_form(null, array(), 'post', '', null, true, $data);

        $validateddata = $mform->get_data();

        if ($validateddata) {
            $feedbackobject = new \stdClass();
            $feedbackobject->id = $attempt->id;
            $feedbackobject->feedback = $validateddata->feedback;
            $feedbackobject->manualgraded = 1;
            $feedbackobject->grade = $validateddata->grade;
            $DB->update_record('solo_attempts', $feedbackobject);
            $grade = new \stdClass();
            $grade->userid = $studentid;
            $grade->rawgrade = $validateddata->grade;
            \solo_grade_item_update($moduleinstance,$grade);
        } else {
            // Generate a warning.
            throw new \moodle_exception('erroreditgroup', 'group');
        }

        return 1;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     * @since Moodle 3.0
     */
    public static function submit_simple_grade_form_returns() {
        return new external_value(PARAM_INT, 'grade id');
    }



    public static function check_for_results_parameters() {
        return new external_function_parameters([
                'attemptid' => new external_value(PARAM_INT)
        ]);
    }

    public static function check_for_results($attemptid) {
        global $DB, $USER;
        //defaults
        $ret = ['ready'=>false];
        $have_humaneval = false;
        $have_aieval =false;

        $params = self::validate_parameters(self::check_for_results_parameters(),
                array('attemptid'=>$attemptid));

        //fetch attempt information
        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, array('userid' => $USER->id, 'id' => $attemptid));
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $attempt->solo), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(constants::M_MODNAME, $moduleinstance->id, $moduleinstance->course, false, MUST_EXIST);

        if($attempt) {
            $hastranscripts = !empty($attempt->jsontranscript);
            if ($hastranscripts) {
                $have_aieval = true;
            } else {
                $have_aieval= utils::transcripts_are_ready_on_s3($attempt);
            }
        }

        //if no results, that's that. return.
        if($have_aieval || $have_humaneval){
            $ret['ready']=true;
        }
        return json_encode($ret);
    }

    public static function check_for_results_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function submit_step_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT),
            'step' => new external_value(PARAM_INT),
            'action' => new external_value(PARAM_ALPHA),
            'data' => new external_value(PARAM_RAW)
        ]);
    }

    public static function submit_step($cmid,$step,$action,$data) {
          $params = self::validate_parameters(self::submit_step_parameters(),
            array('cmid'=>$cmid,'step'=>$step,'action'=>$action, 'data'=>$data));
        $dataobject=json_decode($data);
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $attempt_helper =  new attempthelper($cm);
        $ret = $attempt_helper->submit_step($step,$dataobject);
        return json_encode($ret);
    }

    public static function submit_step_returns() {
        return new external_value(PARAM_RAW);
    }

       /**
     * Get the parameters and types
     *
     * @return void
     */
    public static function fetch_ai_grade_parameters(): external_function_parameters {
        return new external_function_parameters(
            [   'region' => new external_value(PARAM_TEXT, 'The aws regions'),
               'targetlanguage' => new external_value(PARAM_TEXT, 'The target language'),
               'questiontext' => new external_value(PARAM_TEXT, 'The target language'), 
                'studentresponse' => new external_value(PARAM_TEXT, 'The students response'),
                'maxmarks' => new external_value(PARAM_INT, 'The total possible score'),
                'markscheme' => new external_value(PARAM_TEXT, 'The marks scheme'),
                'feedbackscheme' => new external_value(PARAM_TEXT, 'The AI Prompt'),
                'feedbacklanguage' => new external_value(PARAM_TEXT, 'The language of feedback')]
        );

    }
    /**
     * Similar to clicking the submit button.
     * @param string $region
     * @param string $targetlanguage
     * @param string $questiontext
     * @param string $studentresponse
     * @param integer $maxmarks
     * @param string $markscheme
     * @param string $feedbackscheme
     * @param string $feedbacklanguage
     * @return array $contentobject
     */
    public static function fetch_ai_grade($region,$targetlanguage,$questiontext,$studentresponse, $maxmarks, $markscheme, $feedbackscheme,$feedbacklanguage) {
        $siteconfig = get_config(constants::M_COMPONENT);
        $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
        if (empty($token)) {
            return ["correctedtext"=>"An error occurred","feedback" => ["Invalid API credentials"], "marks" => 0];
        }
        if (empty($targetlanguage)) {
            return ["correctedtext"=>"An error occurred","feedback" => ["Invalid target language"], "marks" => 0];
        }

        // Make sure we have the right data for AI to work with.
        if (!empty($studentresponse) && !empty($feedbackscheme) && $maxmarks > 0) {
        //if (!empty($studentresponse)) {
            $instructions = new \stdClass();
            $instructions->feedbackscheme=$feedbackscheme;
            $instructions->feedbacklanguage=$feedbacklanguage;
            $instructions->markscheme=$markscheme;
            $instructions->maxmarks=$maxmarks;
            $instructions->questiontext=$questiontext;
            $instructions->modeltext='';

            $llmresponse = utils::fetch_ai_grade($token,$region,$targetlanguage,$studentresponse,$instructions);
        //   error_log(print_r($llmresponse, true));
           if(!$llmresponse){
                $contentobject = ["correctedtext"=>"An error occurred","feedback" => ["Invalid response received from server. Could be a network issue, or possibly a Poodll auth issue."], "marks" => 0];
            }else{
                $contentobject =$llmresponse;
            }
        } else {
            $contentobject = ["correctedtext"=>"An error occurred","feedback" => ["Invalid parameters. Check that you have a sample answer,feedback and marks instructions."], "marks" => 0];
        }

        // Return whatever we have got.
        return $contentobject;

    }

    /**
     * Get the structure for retuning grade feedbak and marks
     *
     * @return void
     */
    public static function fetch_ai_grade_returns(): external_single_structure {
        return new external_single_structure([
            'correctedtext' => new external_value(PARAM_TEXT, 'corrected text of student submission', VALUE_DEFAULT),
            'marks' => new external_value(PARAM_FLOAT, 'AI grader awarded marks for student response', VALUE_DEFAULT),
            'feedback' => new external_multiple_structure(new external_value(PARAM_TEXT, 'text feedback for display to student', VALUE_DEFAULT)),
        ]);

    }


    

}
