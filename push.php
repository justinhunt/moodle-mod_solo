<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Push config for Poodll Solo
 *
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/formslib.php');

use \mod_solo\constants;
use \mod_solo\utils;
const PUSHABLES=['none'=>'None','recorderskin'=>'Recorder Skin','ttslanguage'=>'TTS Language','ttsvoice'=>'TTS Voice','activitysteps'=>'Activity Steps'];
const PUSH_NONE='none';




/**
 * Push Form
 *
 * @abstract
 * @copyright  2023 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class pushform extends \moodleform {

    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'pushformheading', get_string("pushformheading", constants::M_COMPONENT));
        $mform->addElement('select', 'action', get_string('pushaction', constants::M_COMPONENT),PUSHABLES,PUSH_NONE);
        if(is_siteadmin()){
            $mform->addElement('advcheckbox', 'sitelevel', get_string('pushsitelevel', constants::M_COMPONENT));
        }else{
            $mform->addElement('hidden', 'sitelevel', 0);
            $mform->setType('sitelevel',PARAM_INT);
        }
        $mform->addElement('advcheckbox', 'samename', get_string('pushsamename', constants::M_COMPONENT));

        //add the action buttons
        $this->add_action_buttons(get_string('cancel'), get_string('save'),);

    }
}




$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // activity instance ID
$action = optional_param('action', PUSH_NONE, PARAM_TEXT);
$sitelevel =optional_param('sitelevel',0,PARAM_INT);
$samename =optional_param('samename',0,PARAM_INT);


if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
    $id = $cm->id;
} else {
    print_error('You must specify a course_module ID or an instance ID');
}



$PAGE->set_url(constants::M_URL . '/push.php',
	array('id' => $cm->id));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

//make sure they have permissions
require_capability('mod/solo:manage', $modulecontext);
$siteadmin = is_siteadmin();

if($sitelevel && !$siteadmin){
    print_error('You must be a site administrator to push sitewide settings');
}

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);



if($action!==PUSH_NONE && !array_key_exists($action,PUSHABLES)){
    print_error('Invalid push action');
}

$pushform = new pushform();
$data = $pushform->get_data();

if($action!==PUSH_NONE && $data) {
    //set conditions for the push
    $conditions = [];
    if ($sitelevel==0) {
        $conditions['course'] = $course->id;
    }
    if ($samename) {
        $conditions['name'] = $moduleinstance->name;
    }

    //do the push
    $count =$DB->count_records(constants::M_TABLE,  $conditions);
    switch ($action) {
        case 'activitysteps':
            $recordsupdated= $DB->set_field(constants::M_TABLE, 'step2', $moduleinstance->step2, $conditions);
            $recordsupdated= $DB->set_field(constants::M_TABLE, 'step3', $moduleinstance->step3, $conditions);
            $recordsupdated= $DB->set_field(constants::M_TABLE, 'step4', $moduleinstance->step4, $conditions);
            $recordsupdated= $DB->set_field(constants::M_TABLE, 'step5', $moduleinstance->step5, $conditions);
            redirect($PAGE->url, get_string('pushdone', constants::M_COMPONENT,$count), 10);
            break;
        default:
            $recordsupdated= $DB->set_field(constants::M_TABLE, $action, $moduleinstance->{$action}, $conditions);
            redirect($PAGE->url, get_string('pushdone', constants::M_COMPONENT,$count), 10);
            break;
    }

}

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');
$mode = "push";

//This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);


echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('pushpage', constants::M_COMPONENT));
echo $renderer->box_start(constants::M_CLASS . '_pushinstructions');
echo get_string('pushinstructions', constants::M_COMPONENT);
echo $renderer->box_end();

if($data){
    $pushform->set_data($data);
}else{
    $pushform->set_data(['id'=>$id,'action'=>PUSH_NONE,'sitelevel'=>0,'samename'=>0]);
}
$pushform->display();
echo $renderer->footer();
return;
