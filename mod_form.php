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
 * The main solo configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/solo/classes/attempt/userselectionsform.php');

use \mod_solo\constants;
use \mod_solo\utils;

/**
 * Module instance settings form
 */
class mod_solo_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
    	global $CFG, $COURSE;

        $mform = $this->_form;
        $config = get_config(constants::M_COMPONENT);

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('soloname', constants::M_COMPONENT), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'soloname', constants::M_COMPONENT);

         // Adding the standard "intro" and "introformat" fields
        if($CFG->version < 2015051100){
        	$this->add_intro_editor();
        }else{
        	$this->standard_intro_elements();
		}

        //Enable multiple attempts (or not)
        $mform->addElement('advcheckbox', 'multiattempts', get_string('multiattempts', constants::M_COMPONENT), get_string('multiattempts_details', constants::M_COMPONENT));
        $mform->setDefault('multipleattempts',true);

        //allow post attempt edit
        $mform->addElement('advcheckbox', 'postattemptedit', get_string('postattemptedit', constants::M_COMPONENT), get_string('postattemptedit_details', constants::M_COMPONENT));
        $mform->setDefault('postattemptedit',false);



        $options = utils::get_recorders_options();
        $mform->addElement('select','recordertype',get_string('recordertype', constants::M_COMPONENT), $options,array("size"=>"5"));
        $mform->setDefault('recordertype',constants::REC_AUDIO);


        $options = utils::get_skin_options();
        $mform->addElement('select','recorderskin',get_string('recorderskin', constants::M_COMPONENT), $options,array("size"=>"5"));
        $mform->setDefault('recorderskin',constants::SKIN_ONCE);


        //time limits
        $options = utils::get_conversationlength_options();
        //the size attribute doesn't work because the attributes are applied on the div container holding the select
        $mform->addElement('select','convlength',get_string('convlength', constants::M_COMPONENT), $options,array("size"=>"5"));
        $mform->setDefault('convlength',constants::DEF_CONVLENGTH);

        //the size attribute doesn't work because the attributes are applied on the div container holding the select
        $mform->addElement('select','maxconvlength',get_string('maxconvlength', constants::M_COMPONENT), $options,array("size"=>"5"));
        $mform->setDefault('maxconvlength',constants::DEF_CONVLENGTH);

        // Speaking topic text
        $mform->addElement('textarea', 'speakingtopic', get_string('speakingtopic', constants::M_COMPONENT, '1'),  array('rows'=>'3', 'cols'=>'80'));
        $mform->setType('speakingtopic', PARAM_TEXT);

        //Speaking topic upload
        $filemanageroptions = solo_filemanager_options($this->context);
        $mform->addElement('filemanager',
                'topicmedia',
                get_string('topicmedia',constants::M_COMPONENT),
                null,
                $filemanageroptions
        );

        //Speaking topic iframe
        $mform->addElement('text', 'topiciframe', get_string('topiciframe', constants::M_COMPONENT), array('size'=>100));
        $mform->setType('topiciframe', PARAM_RAW);

        //targetwords
        $mform->addElement('static','targetwordsexplanation','',get_string('targetwordsexplanation',constants::M_COMPONENT));
        $mform->addElement('textarea', 'targetwords', get_string('topictargetwords', constants::M_COMPONENT), 'wrap="virtual" rows="12" cols="50"');
        $mform->setType('targetwords', PARAM_TEXT);
        $mform->addRule('targetwords', get_string('required'), 'required', null, 'client');


        //add tips field
        $edoptions = solo_editor_no_files_options($this->context);
        $opts = array('rows'=>'5', 'columns'=>'80');
        $mform->addElement('editor','tips_editor',get_string('tips', constants::M_COMPONENT),$opts,$edoptions);
        $mform->setDefault('tips_editor',array('text'=>$config->speakingtips, 'format'=>FORMAT_HTML));
        $mform->setType('tips_editor',PARAM_RAW);

        //Enable Manual Transcription
        $mform->addElement('advcheckbox', 'enabletranscription', get_string('enabletranscription', constants::M_COMPONENT), get_string('enabletranscription_details', constants::M_COMPONENT));
        $mform->setDefault('enabletranscription',$config->enabletranscription);


        //Enable AI
        $mform->addElement('advcheckbox', 'enableai', get_string('enableai', constants::M_COMPONENT), get_string('enableai_details', constants::M_COMPONENT));
        $mform->setDefault('enableai',$config->enableai);

        //tts options
        $langoptions = \mod_solo\utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT), $langoptions);
        $mform->setDefault('ttslanguage',$config->ttslanguage);


        //transcriber options
        $name = 'transcriber';
        $label = get_string($name, constants::M_COMPONENT);
        $options = \mod_solo\utils::fetch_options_transcribers();
        $mform->addElement('select', $name, $label, $options);
        $mform->setDefault($name,constants::TRANSCRIBER_AMAZONTRANSCRIBE);// $config->{$name});

        //region
        $regionoptions = \mod_solo\utils::get_region_options();
        $mform->addElement('select', 'region', get_string('region', constants::M_COMPONENT), $regionoptions);
        $mform->setDefault('region',$config->awsregion);

        //expiredays
        $expiredaysoptions = \mod_solo\utils::get_expiredays_options();
        $mform->addElement('select', 'expiredays', get_string('expiredays', constants::M_COMPONENT), $expiredaysoptions);
        $mform->setDefault('expiredays',$config->expiredays);

        // Grade.
        $this->standard_grading_coursemodule_elements();

        //To auto grade or not to autograde
        $mform->addElement('advcheckbox', 'enableautograde', get_string('enableautograde', constants::M_COMPONENT), get_string('enableautograde_details', constants::M_COMPONENT));
        $mform->setDefault('enableautograde',$config->enableautograde);

        //Target word-count
        $mform->addElement('text', 'targetwordcount', get_string('targetwordcount', constants::M_COMPONENT), array('size'=>20));
        $mform->setType('targetwordcount', PARAM_INT);
        $mform->setDefault('targetwordcount',200);
        $mform->addHelpButton('targetwordcount', 'targetwordcount', constants::M_MODNAME);

        //auto grading options
        $aggroup=array();
        $wordcountoptions = utils::get_word_count_options();
        $startgradeoptions = utils::get_grade_element_options();
        $bonusgradeoptions = utils::fetch_bonus_grade_options();
        $ratiogradeoptions = utils::fetch_ratio_grade_options();
        $plusminusoptions = array('plus'=>'+','minus'=>'-');
        $points_per = get_string("ag_pointsper",constants::M_COMPONENT);
        $over_target_words = get_string("ag_overtargetwords",constants::M_COMPONENT);

        $aggroup[] =& $mform->createElement('static', 'statictext00', '','( ');
        $aggroup[] =& $mform->createElement('select', 'gradewordcount', '', $wordcountoptions);
        $aggroup[] =& $mform->createElement('static', 'statictext0', '',$over_target_words );
        $aggroup[] =& $mform->createElement('select', 'graderatiostart', '', $startgradeoptions);
        $mform->setDefault('graderatiostart',80);


        $aggroup[] =& $mform->createElement('static', 'statictext1', '','% x ');
        $aggroup[] =& $mform->createElement('select', 'graderatio', '', $ratiogradeoptions);
        $mform->setDefault('graderatio','clarity');
        $aggroup[] =& $mform->createElement('static', 'statictext2', '','% ');
        $mform->addGroup($aggroup, 'aggroup', get_string('aggroup', constants::M_COMPONENT),
                '', false);
        $mform->addHelpButton('aggroup', 'aggroup', constants::M_MODNAME);

        $bggroup[] =& $mform->createElement('select', 'gradebonus1direction', '', $plusminusoptions);
        $bggroup[] =& $mform->createElement('static', 'statictext3', '',' ');
        $bggroup[] =& $mform->createElement('select', 'gradebonuspoints1','', $startgradeoptions);
        $mform->setDefault('gradebonuspoints1',5);
        $bggroup[] =& $mform->createElement('static', 'statictext3', '',$points_per);
        $bggroup[] =& $mform->createElement('select', 'gradebonus1', '', $bonusgradeoptions);
        $mform->setDefault('gradebonus1','targetwordspoken');
        $mform->addGroup($bggroup, 'bggroup', '', '', false);

        $cggroup[] =& $mform->createElement('select', 'gradebonus2direction', '', $plusminusoptions);
        $mform->setDefault('gradebonus2direction','minus');
        $cggroup[] =& $mform->createElement('static', 'statictext4', '',' ');
        $cggroup[] =& $mform->createElement('select', 'gradebonuspoints2','', $startgradeoptions);
        $mform->setDefault('gradebonuspoints2',3);
        $cggroup[] =& $mform->createElement('static', 'statictext5', '',$points_per);
        $cggroup[] =& $mform->createElement('select', 'gradebonus2', '', $bonusgradeoptions);
        $mform->setDefault('gradebonus2','spellingmistake');
        $mform->addGroup($cggroup, 'cggroup', '', '', false);

        $dggroup[] =& $mform->createElement('select', 'gradebonus3direction', '', $plusminusoptions);
        $mform->setDefault('gradebonus3direction','plus');
        $dggroup[] =& $mform->createElement('static', 'statictext6', '',' ');
        $dggroup[] =& $mform->createElement('select', 'gradebonuspoints3','', $startgradeoptions);
        $mform->setDefault('gradebonuspoints3',0);
        $dggroup[] =& $mform->createElement('static', 'statictext7', '',$points_per);
        $dggroup[] =& $mform->createElement('select', 'gradebonus3', '', $bonusgradeoptions);
        $mform->setDefault('gradebonus3','--');
        $mform->addGroup($dggroup, 'dggroup', '', '', false);

        $eggroup[] =& $mform->createElement('select', 'gradebonus4direction', '', $plusminusoptions);
        $mform->setDefault('gradebonus4direction','plus');
        $eggroup[] =& $mform->createElement('static', 'statictext8', '',' ');
        $eggroup[] =& $mform->createElement('select', 'gradebonuspoints4','', $startgradeoptions);
        $mform->setDefault('gradebonuspoints4',0);
        $eggroup[] =& $mform->createElement('static', 'statictext9', '',$points_per);
        $eggroup[] =& $mform->createElement('select', 'gradebonus4', '', $bonusgradeoptions);
        $mform->setDefault('gradebonus4','--');
        $mform->addGroup($eggroup, 'eggroup', '', '', false);


        //grade options
        //for now we hard code this to latest attempt
        $mform->addElement('hidden', 'gradeoptions',constants::M_GRADELATEST);
        $mform->setType('gradeoptions', PARAM_INT);

        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }

	public function data_preprocessing(&$form_data) {


		$filemanageroptions = solo_filemanager_options($this->context);
		$ednofileoptions = solo_editor_no_files_options($this->context);
		$editors  = solo_get_editornames();
        $filemanagers  = solo_get_filemanagernames();
		 if ($this->current->instance) {
			$itemid = 0;
			foreach($editors as $editor){
				$form_data = file_prepare_standard_editor((object)$form_data,$editor, $ednofileoptions, $this->context,constants::M_COMPONENT,$editor, $itemid);
			}
             foreach($filemanagers as $fm){
                 $draftitemid = file_get_submitted_draft_itemid($fm);
                 file_prepare_draft_area($draftitemid, $this->context->id, constants::M_COMPONENT,
                         $fm, $itemid,
                         $filemanageroptions);
                 $form_data->{$fm} = $draftitemid;
             }
		}
		//autograde options
        if(isset($form_data->autogradeoptions)) {
            $ag_options = json_decode($form_data->autogradeoptions);
            $form_data->graderatio = $ag_options->graderatio;
            $form_data->gradewordcount = $ag_options->gradewordcount;
            $form_data->graderatiostart = $ag_options->graderatiostart;

            $form_data->gradebonus1direction = $ag_options->gradebonus1direction;
            $form_data->gradebonuspoints1 = $ag_options->gradebonuspoints1;
            $form_data->gradebonus1 = $ag_options->gradebonus1;

            $form_data->gradebonus2direction = $ag_options->gradebonus2direction;
            $form_data->gradebonuspoints2 = $ag_options->gradebonuspoints2;
            $form_data->gradebonus2 = $ag_options->gradebonus2;

            $form_data->gradebonus3direction = $ag_options->gradebonus3direction;
            $form_data->gradebonuspoints3 = $ag_options->gradebonuspoints3;
            $form_data->gradebonus3 = $ag_options->gradebonus3;

            $form_data->gradebonus4direction = $ag_options->gradebonus4direction;
            $form_data->gradebonuspoints4 = $ag_options->gradebonuspoints4;
            $form_data->gradebonus4 = $ag_options->gradebonus4;
        }

	}

    /**
     * Add elements for setting the custom completion rules.
     *
     * @category completion
     * @return array List of added element names, or names of wrapping group elements.
     */
    public function add_completion_rules() {

        $mform = $this->_form;
        //time limits
        $yesno_options = array(0 => get_string("no", constants::M_COMPONENT),
                1 => get_string("yes", constants::M_COMPONENT));
        //the size attribute doesn't work because the attributes are applied on the div container holding the select
        $mform->addElement('select','completionallsteps',get_string('completionallsteps', constants::M_COMPONENT), $yesno_options,array("size"=>"5"));
        $mform->setDefault('convlength',constants::DEF_CONVLENGTH);
        $mform->addHelpButton('completionallsteps', 'completionallsteps', constants::M_MODNAME);
        return ['completionallsteps'];
    }

    /**
     * Called during validation to see whether some module-specific completion rules are selected.
     *
     * @param array $data Input data not yet validated.
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['completionallsteps']) && $data['completionallsteps'] != 0);
    }


}
