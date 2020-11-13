<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 20:52
 */

namespace mod_solo\report;

use \mod_solo\constants;
use \mod_solo\utils;

class detailedattempts extends basereport
{

    protected $report="detailedattempts";
    protected $fields = array('id','idnumber', 'username','audiofile','topicname','partners','turns','words','ATL','LTL','TW','QS','ACC','grade','selftranscript','transcript','timemodified','view','deletenow');
    protected $headingdata = null;
    protected $qcache=array();
    protected $ucache=array();


    public function fetch_formatted_field($field,$record,$withlinks)
    {
        global $DB, $CFG, $OUTPUT;
        switch ($field) {
            case 'id':
                $ret = $record->id;
                if ($withlinks) {
                    $link = new \moodle_url(constants::M_URL . '/reports.php',
                            array('format'=>'html','report' => 'singleattempt', 'id' => $this->cm->id, 'attemptid' => $record->id));
                    $ret = \html_writer::link($link, $ret);
                }
                break;

            case 'idnumber':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = $user->idnumber;
                break;

            case 'grade':

                $ret = $record->grade==null ? '' : $record->grade;
                break;

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = fullname($user);
                if ($withlinks) {
                    $link = new \moodle_url(constants::M_URL . '/reports.php',
                            array('report' => 'userattempts', 'n' => $this->cm->instance, 'id'=>$this->cm->id,'userid' => $record->userid));
                    $ret = \html_writer::link($link, $ret);
                }
                break;

            case 'transcript':
                if(!empty($record->transcript)){
                    $ret = $record->transcript;
                    if ($withlinks) {
                        $ret="[transcript]";
                    }
                }else{
                    $ret="";
                }
                break;

            case 'selftranscript':
                if(!empty($record->selftranscript)){

                    if ($withlinks) {
                        $ret="[self-transcript]";
                    }else{
                        $parts = json_decode($record->selftranscript);
                        $ret='';
                        foreach($parts as $part)
                        {
                            $ret.=' ' . $part->part;
                        }                    }
                }else{
                    $ret="";
                }
                break;

            case 'topicname':
                $ret = $record->topicname;
                break;

            case 'partners':
                //we need to work out usernames and stuff.
                //just return blank if we have none, right from the start
                if(empty($record->interlocutors)){
                    $ret='';
                    break;
                }
                $partners = explode(',',$record->interlocutors);
                $users = array();
                foreach ($partners as $partner){
                    $users[] = fullname($this->fetch_cache('user', $partner));
                }
                //this is bad. We use the targetwords tags for users. It just seemed like a good idea
                if ($withlinks) {
                    $tdata = array('targetwords' => $users);
                    $ret =$targetwordcontent = $OUTPUT->render_from_template(constants::M_COMPONENT . '/targetwords', $tdata);
                }else{
                    $ret =implode(',' , $users);
                }

                break;

            case 'words':
                $ret = $record->words;
                break;

            case 'turns':
                $ret = $record->turns;
                break;

            case 'ATL':
                $ret = $record->avturn;
                break;

            case 'LTL':
                $ret = $record->longestturn;
                break;

            case 'TW':

                // $ret = $record->targetwords . '/' . $record->totaltargetwords;
                $ret = $record->targetwords;

                break;

            case 'QS':
                $ret = $record->questions ;
                break;

            case 'ACC':
                if($record->aiaccuracy<0) {
                    $ret = '';
                }else{
                    $ret = $record->aiaccuracy;
                }

                break;

            case 'audiofile':
                if ($withlinks && !empty($record->filename)) {


                    $ret = \html_writer::tag('audio','',
                            array('controls'=>'1','src'=>$record->filename));
                    //hidden player works but less useful right now
                    /*
                    $ret = \html_writer::div('<i class="fa fa-play-circle fa-2x"></i>',
                            constants::M_HIDDEN_PLAYER_BUTTON, array('data-audiosource' => $record->filename));
                    */

                } else {
                    $ret = $record->filename;
                }
                break;

            case 'timemodified':
                $ret = date("Y-m-d H:i:s", $record->timemodified);
                break;

            case 'view':
                if ($withlinks && has_capability('mod/solo:manageattempts', $this->context)) {
                    $url = new \moodle_url(constants::M_URL . '/reports.php',
                            array('format'=>'html','report' => 'singleattempt', 'id' => $this->cm->id, 'attemptid' => $record->id));
                    $btn = new \single_button($url, get_string('view'), 'post');
                    $ret = $OUTPUT->render($btn);
                }else {
                    $ret = '';
                }
                break;

            case 'deletenow':
                if ($withlinks && has_capability('mod/solo:manageattempts', $this->context)) {
                    $url = new \moodle_url(constants::M_URL . '/attempt/manageattempts.php',
                        array('action' => 'delete', 'id' => $this->cm->id, 'attemptid' => $record->id, 'source' => $this->report));
                    $btn = new \single_button($url, get_string('delete'), 'post');
                    $btn->add_confirm_action(get_string('deleteattemptconfirm', constants::M_COMPONENT));
                    $ret = $OUTPUT->render($btn);
                }else {
                    $ret = '';
                }
                break;

            default:
                if (property_exists($record, $field)) {
                    $ret = $record->{$field};
                } else {
                    $ret = '';
                }
        }
        return $ret;
    }

    public function fetch_formatted_heading(){
        $record = $this->headingdata;
        $ret='';
        if(!$record){return $ret;}
        return $record->activityname .'-'.get_string('detailedattemptsheading',constants::M_COMPONENT);

    }

    public function process_raw_data($formdata){
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();
        $this->headingdata->activityname = $formdata->activityname;

        $emptydata = array();
        $sql = 'SELECT at.id, at.grade, at.userid, at.topicname, at.interlocutors,at.filename, st.turns, st.words,
        st.avturn, st.longestturn, st.targetwords, st.totaltargetwords,st.questions,st.aiaccuracy,at.selftranscript,at.transcript, at.timemodified ';
        $sql .= '  FROM {' . constants::M_ATTEMPTSTABLE . '} at INNER JOIN {' . constants::M_STATSTABLE .  '} st ON at.id = st.attemptid ';
        $sql .= ' WHERE at.solo = :soloid';
        $sql .= ' ORDER BY at.userid ASC';
        $alldata = $DB->get_records_sql($sql,array('soloid'=>$formdata->soloid));

        if($alldata){
            foreach($alldata as $thedata){
                //do any processing here
            }
            $this->rawdata= $alldata;
        }else{
            $this->rawdata= $emptydata;
        }
        return true;
    }

}