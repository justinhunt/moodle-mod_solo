<?php
/**
 * Created by PhpStorm.
 * User: justin
 * Date: 17/08/29
 * Time: 16:12
 */

namespace mod_solo;

defined('MOODLE_INTERNAL') || die();


require_once($CFG->libdir . '/completionlib.php');

class attempthelper
{
    protected $cm;
    protected $context;
    protected $mod;
    protected $attempts;
    protected $course;


    public function __construct($cm) {
        global $DB;
        $this->cm = $cm;
        $this->mod = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    }

    public function fetch_media_url($filearea,$attempt){
        //get question audio div (not so easy)
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id,  constants::M_COMPONENT,$filearea,$attempt->id);
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if($filename=='.'){continue;}
            $filepath = '/';
            $mediaurl = \moodle_url::make_pluginfile_url($this->context->id, constants::M_COMPONENT,
                $filearea, $attempt->id,
                $filepath, $filename);
            return $mediaurl->__toString();

        }
        //We always take the first file and if we have none, thats not good.
        return "";
       // return "$this->context->id pp $filearea pp $attempt->id";
    }

    public function fetch_attempts($userid=false)
    {
        global $DB,$USER;

        if(!$userid){
            $userid= $USER->id;
        }
        if (!$this->attempts) {
            $this->attempts = $DB->get_records(constants::M_ATTEMPTSTABLE, [constants::M_MODNAME => $this->mod->id, 'userid'=>$userid],'timemodified DESC');
        }
        if($this->attempts){
            return $this->attempts;
        }else{
            return [];
        }
    }

    public function fetch_latest_complete_attempt($userid=false){
        global $DB, $USER;

        if(!$userid){
            $userid = $USER->id;
        }
        $totalsteps = utils::fetch_total_step_count($this->mod,$this->context);
        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
                array(constants::M_MODNAME => $this->mod->id,'userid'=>$userid),
                'id DESC');

        if($attempts){

            foreach ($attempts as $attempt){
                if($attempt->completedsteps>=$totalsteps){
                    return $attempt;
                }
            }
        }else{
            return false;
        }
    }

    public function fetch_latest_attempt($userid=false){
        global $DB, $USER;

        if(!$userid){
            $userid = $USER->id;
        }

        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
                array(constants::M_MODNAME => $this->mod->id,'userid'=>$userid),
                'id DESC');
        if($attempts){
            $attempt = array_shift($attempts);
            return $attempt;
        }else{
            return false;
        }
    }

    public function fetch_attempt_number($attempt){
        global $DB;

        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
            array(constants::M_MODNAME => $this->mod->id,'userid'=>$attempt->userid),
            'id DESC');
        $count = 0;
        foreach ($attempts as $a){
            $count++;
            if($a->id == $attempt->id){
                return $count;
            }
        }
        return 0;
    }

    //Delete an attempt
    public function delete_attempt($attemptid) {
        global $DB;

        // Only ever delete attempts that belong to this activity.
        if (!$DB->record_exists(constants::M_ATTEMPTSTABLE, ['id' => $attemptid, constants::M_MODNAME => $this->mod->id])) {
            return false;
        }

        //delete stats for this attempt
        $DB->delete_records(constants::M_STATSTABLE, array('attemptid'=>$attemptid));
        //delete AI data for this attempt
        $DB->delete_records(constants::M_AITABLE, array('attemptid'=>$attemptid));
        //delete Attempt
        $DB->delete_records(constants::M_ATTEMPTSTABLE, array('id'=>$attemptid));
        return true;
    }

    /**
     * The type of a step (prepare, record, transcribe, model) as configured on this activity.
     *
     * @param int $step The step number, 1 based.
     * @return int|false The step type, or false if the activity has no such step.
     */
    public function fetch_step_type($step) {
        if ($step < 1 || $step > 5) {
            return false;
        }
        $steptype = (int) $this->mod->{'step' . $step};
        return $steptype === constants::M_STEP_NONE ? false : $steptype;
    }

    /**
     * Clean the media url a recorder hands back. The server later fetches transcripts from next to it, so it must
     * be a plain https url and nothing else.
     *
     * @param mixed $url The url from the browser.
     * @return string The url, or an empty string if it is not acceptable.
     */
    public static function clean_media_url($url) {
        if (!is_string($url)) {
            return '';
        }
        $url = trim($url);
        if ($url === '' || clean_param($url, PARAM_URL) !== $url || strpos($url, 'https://') !== 0) {
            return '';
        }
        return $url;
    }


    public function fetch_attempts_for_js(){

        $attempts = $this->fetch_attempts();
        return $attempts;
    }

    public function submit_step($step, $data){
        global $USER, $DB;

        $ret = new \stdClass();

        if ($data ) {
            // The step type comes from the activity settings, never from the browser.
            $steptype = $this->fetch_step_type($step);
            if ($steptype === false) {
                $ret->message = 'invalid step:' . $step;
                $ret->success = false;
                return $ret;
            }

            // Only the fields a step form legitimately sends are copied onto the attempt. Everything else on the
            // attempt (grades, transcripts, ownership) is set server side.
            $newattempt = new \stdClass();
            $newattempt->solo = $this->mod->id;
            $newattempt->userid = $USER->id;
            $newattempt->modifiedby=$USER->id;
            $newattempt->timemodified=time();
            switch ($steptype) {
                case constants::STEP_MEDIARECORDING:
                    $filename = isset($data->filename) ? self::clean_media_url($data->filename) : '';
                    if ($filename === '') {
                        $ret->message = 'invalid recording url';
                        $ret->success = false;
                        return $ret;
                    }
                    $newattempt->filename = $filename;
                    // The transcript the in page streaming recorder made while the student spoke. Only taken from
                    // activities that stream, and only if the stream ended properly (see fetch_streamed_transcript).
                    if (utils::can_stream_record($this->mod)) {
                        $streamed = utils::fetch_streamed_transcript($data);
                        if ($streamed) {
                            $newattempt->transcript = $streamed->transcript;
                            $newattempt->jsontranscript = $streamed->jsontranscript;
                            $newattempt->vtttranscript = '';
                        }
                    }
                    break;
                case constants::STEP_SELFTRANSCRIBE:
                    if (isset($data->selftranscript) && is_string($data->selftranscript)) {
                        $newattempt->selftranscript = $data->selftranscript;
                    }
                    break;
            }

            //are we in edit or new mode
            $attempt = false;
            $attemptid = isset($data->attemptid) ? clean_param($data->attemptid, PARAM_INT) : 0;
            if ($attemptid) {
                // Students may only ever work on their own attempts.
                $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE,
                    ['id' => $attemptid, constants::M_MODNAME => $this->mod->id, 'userid' => $USER->id]);
                if(!$attempt){
                    $ret->message = 'could not find attempt of id:' . $attemptid;
                    $ret->success = false;
                    return $ret;
                }
                //This would force a step, if we needed to
                $lateststep = $attempt->completedsteps;
                $edit = true;
            } else {
                $lateststep = constants::STEP_NONE;
                $edit = false;
            }

            // Steps are done in order. Going back to an earlier step is fine, skipping ahead is not.
            if ($step > $lateststep + 1) {
                $ret->message = 'step ' . $step . ' can not be submitted before step ' . ($lateststep + 1);
                $ret->success = false;
                return $ret;
            }

            //first insert a new attempt if we need to
            //that will give us a attemptid, we need that for saving files
            if($edit) {
                $newattempt->id = $attempt->id;
            }else{
                $newattempt->timecreated=time();
                $newattempt->createdby=$USER->id;
                $newattempt->topictargetwords = $this->mod->targetwords;

                //try to insert it
                if (!$newattempt->id = $DB->insert_record(constants::M_ATTEMPTSTABLE,$newattempt)){
                    $ret->message = "Could not insert solo attempt!";
                    $ret->success = false;
                    return $ret;
                }
            }

            //type specific settings
            switch($steptype) {
                case constants::STEP_PREPARE:
                    break;

                case constants::STEP_MEDIARECORDING:

                    $rerecording = $attempt && $newattempt->filename
                        && $attempt->filename != $newattempt->filename;
                    $transcribestep = utils::fetch_step_no($this->mod,constants::M_STEP_TRANSCRIBE);
                    $recordstep = utils::fetch_step_no($this->mod,constants::M_STEP_RECORD);
                    $audio_before_transcription = $recordstep < $transcribestep && $transcribestep!==false;

                    //if rerecording we want to clear old AI data out
                    //as well as self transcript and force us back to self transcript
                    // Note this is also true for the first recording, when the old filename is empty.
                    if($rerecording) {
                        utils::clear_ai_data($this->mod->id, $newattempt->id);
                        // Everything worked out from the old recording goes, or processing would keep it: it only
                        // fills these in when they are empty. A teacher's grade stays.
                        utils::remove_stats($newattempt);
                        $newattempt->aigrade = null;
                        $newattempt->aifeedback = null;
                        $newattempt->grammarcorrection = null;
                        if (empty($attempt->manualgraded)) {
                            $newattempt->grade = 0;
                        }
                        if($audio_before_transcription){
                            $newattempt->selftranscript = "";
                        }
                        $newattempt->completedsteps = $step;
                    }

                    // A streamed transcript is the text to grade when there is no step for the student to type it.
                    // (With a server side transcript, process_attempt does this when it fetches it.)
                    if (isset($newattempt->jsontranscript) && $transcribestep === false) {
                        $newattempt->selftranscript = $newattempt->transcript;
                    }
                    //if rerecording, or we are in "new" mode (first recording) we register our AWS task
                    if($rerecording || !$edit){
                        utils::register_aws_task($this->mod->id, $newattempt->id, $this->context->id, $this->cm->id);
                    }


                    break;
                case constants::STEP_SELFTRANSCRIBE:
                    //do nothing much at this point
                    break;
                case constants::STEP_MODEL:
                default:
            }

            //Set the last completed stage
            if($lateststep < $step){
                $newattempt->completedsteps = $step;
            }

            //now update the db
            if (!$DB->update_record(constants::M_ATTEMPTSTABLE,$newattempt)){
                $ret->message = "Could not update solo attempt!";
                $ret->success = false;
                return $ret;
            }

            //raise step submitted event
            \mod_solo\event\step_submitted::create_from_attempt($newattempt, $this->context, $lateststep)->trigger();

            //if we just finished the last step then lets indicate this activity complete in the Moodle sense.
            $totalsteps= utils::fetch_total_step_count($this->mod,$this->context);

            if($step==$totalsteps){
                //notify completion handler that we are finished
                $completion=new \completion_info($this->course);
                if($completion->is_enabled($this->cm) && $this->mod->completionallsteps) {
                    $completion->update_state($this->cm,COMPLETION_COMPLETE);
                }
                //raise step submitted event
                \mod_solo\event\attempt_submitted::create_from_attempt($newattempt, $this->context)->trigger();
            }

            //go back to top page
            $ret->message = "Updated solo attempt!";
            $ret->success = true;
            return $ret;
        }

        //should not really get here , but lets return anyway
        return $ret;

    }

}//end of class