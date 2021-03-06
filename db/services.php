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
        'mod_solo_submit_rubric_grade_form' => array(
            'classname' => '\mod_solo\external',
            'methodname' => 'submit_rubric_grade_form',
            'description' => 'Creates a grade from submitted rubric grade form',
            'ajax' => true,
            'type' => 'write',
            'capabilities' => 'mod/solo:managegrades',
        ),
        'mod_solo_submit_simple_grade_form' => array(
                'classname' => '\mod_solo\external',
                'methodname' => 'submit_simple_grade_form',
                'description' => 'Creates a grade from submitted simple form',
                'ajax' => true,
                'type' => 'write',
                'capabilities' => 'mod/solo:managegrades',
        ),
        'mod_solo_check_for_results' => array(
                'classname' => '\mod_solo\external',
                'methodname' => 'check_for_results',
                'description' => 'returns true or false on presence of transcript',
                'ajax' => true,
                'type' => 'read',
                'capabilities' => 'mod/solo:view'
        )
);
