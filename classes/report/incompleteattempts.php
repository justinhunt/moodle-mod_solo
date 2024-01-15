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

class incompleteattempts extends basereport
{

    protected $report="attempts";
    protected $fields = array('id','idnumber','username','completedsteps','timemodified','deletenow');
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

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = fullname($user);
                if ($withlinks) {
                    $link = new \moodle_url(constants::M_URL . '/reports.php',
                            array('report' => 'userattempts', 'n' => $this->cm->instance, 'id'=>$this->cm->id,'userid' => $record->userid));
                    $ret = \html_writer::link($link, $ret);
                }
                break;

            case 'completedsteps':
                $ret = $record->completedsteps ;
                break;

            case 'timemodified':
                $ret = date("Y-m-d H:i:s", $record->timemodified);
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
        return $record->activityname .'-'.get_string('incompleteattemptsheading',constants::M_COMPONENT);
    }

    public function process_raw_data($formdata){
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();
        $this->headingdata->activityname = $formdata->activityname;

        $emptydata = array();
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $this->cm->instance), '*', MUST_EXIST);
        $totalsteps = utils::fetch_total_step_count($moduleinstance,$this->context);

        //if we need to show just one group
        if($formdata->groupid>0){

                list($groupswhere, $allparams) = $DB->get_in_or_equal($formdata->groupid);

                $sql = 'SELECT at.id,at.userid, at.completedsteps, at.timemodified ';
                $sql .= '  FROM {' . constants::M_ATTEMPTSTABLE . '} at';
                $sql .= ' INNER JOIN {groups_members} gm ON at.userid=gm.userid';
                $sql .= ' WHERE gm.groupid ' . $groupswhere . ' AND at.solo = ?';
                $sql .= ' AND at.completedsteps < ?';
                $sql .= ' ORDER BY at.timemodified DESC';
                $allparams[]=$formdata->soloid;
                $allparams[]=$totalsteps;
                $alldata = $DB->get_records_sql($sql,$allparams);

        //if it's all groups or no groups
        }else {

            $sql = 'SELECT at.id,at.userid, at.completedsteps, at.timemodified ';
                $sql .= '  FROM {' . constants::M_ATTEMPTSTABLE . '} at';
                $sql .= ' WHERE at.solo = :soloid';
                $sql .= ' AND at.completedsteps < :totalsteps';
                $sql .= ' ORDER BY at.timemodified DESC';
                $alldata = $DB->get_records_sql($sql, array('soloid' => $formdata->soloid,'totalsteps'=>$totalsteps));
         }

        if($alldata){
            foreach($alldata as $thedata){
                //do any processing here
                $thedata->completedsteps .= '/' . $totalsteps;
            }
            $this->rawdata= $alldata;
        }else{
            $this->rawdata= $emptydata;
        }
        return true;
    }

}