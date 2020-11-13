<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:32
 */

namespace mod_solo\attempt;

use \mod_solo\constants;

class userselectionsform extends baseform
{
    public $type = constants::STEP_USERSELECTIONS;
    public $typestring = constants::T_USERSELECTIONS;
    public function custom_definition() {
        $this->topics = $this->_customdata['topics'];
        $this->moduleinstance = $this->_customdata['moduleinstance'];
        $this->users = $this->_customdata['users'];
        $this->targetwords = $this->_customdata['targetwords'];

        //we set the title and instructions
        $this->add_title(get_string('attempt_partone', constants::M_COMPONENT));
        $this->add_instructions(get_string('attempt_partone_instructions', constants::M_COMPONENT));

        //user combo
        $name='interlocutors';
        $label=get_string('chooseusers',constants::M_COMPONENT);
       // $this->add_usercombo_field($name,$label);
        $this->add_userselector_field($name,$label);

        //Selected Topic
        $name='topicid';
        $label=get_string('choosetopic',constants::M_COMPONENT);
        $this->add_fontawesomecombo_field($name,$label);

        //add words
        $this->add_targetwords_fields();

        //Conversation length
        $this->add_conversationlength_field();
    }
    public function custom_definition_after_data() {

       $this->set_targetwords();

    }
    public function get_savebutton_text(){
        return get_string('next', constants::M_COMPONENT);
    }

}