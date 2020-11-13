<?php
/**
 * Services definition.
 *
 * @package mod_solo
 * @author  Justin Hunt Poodll.com
 */

$functions = array(

        'mod_solo_toggle_topic_selected' => array(
                'classname'   => '\mod_solo\external',
                'methodname'  => 'toggle_topic_selected',
                'description' => 'Select/deselect a topic for a mod',
                'capabilities'=> 'mod/solo:selecttopics',
                'type'        => 'read',
                'ajax'        => true,
        ),
        'mod_solo_get_grade_submission' => array(
            'classname'   => '\mod_solo\external',
            'methodname'  => 'get_grade_submission',
            'description' => 'Gets a solo grade submission',
            'capabilities'=> 'mod/solo:managegrades',
            'type'        => 'write',
            'ajax' => true,
        ),
        'mod_solo_submit_create_grade_form' => array(
            'classname' => '\mod_solo\external',
            'methodname' => 'submit_create_grade_form',
            'description' => 'Creates a grade from submitted form data',
            'ajax' => true,
            'type' => 'write',
            'capabilities' => 'mod/solo:managegrades',
        ),

);
