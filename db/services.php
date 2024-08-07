<?php
/**
 * Services definition.
 *
 * @package mod_solo
 * @author  Justin Hunt Poodll.com
 */

$functions = array(
        'mod_solo_check_grammar' => array(
                'classname'   => 'mod_solo_external',
                'methodname'  => 'check_grammar',
                'description' => 'check grammar',
                'capabilities'=> 'mod/solo:view',
                'type'        => 'read',
                'ajax'        => true,
        ),
        'mod_solo_get_grade_submission' => array(
            'classname'   => 'mod_solo_external',
            'methodname'  => 'get_grade_submission',
            'description' => 'Gets a solo grade submission',
            'capabilities'=> 'mod/solo:manage',
            'type'        => 'write',
            'ajax' => true,
        ),
        'mod_solo_submit_rubric_grade_form' => array(
            'classname' => 'mod_solo_external',
            'methodname' => 'submit_rubric_grade_form',
            'description' => 'Creates a grade from submitted rubric grade form',
            'ajax' => true,
            'type' => 'write',
            'capabilities' => 'mod/solo:manage',
        ),
        'mod_solo_submit_simple_grade_form' => array(
                'classname' => 'mod_solo_external',
                'methodname' => 'submit_simple_grade_form',
                'description' => 'Creates a grade from submitted simple form',
                'ajax' => true,
                'type' => 'write',
                'capabilities' => 'mod/solo:manage',
        ),
        'mod_solo_check_for_results' => array(
                'classname' => 'mod_solo_external',
                'methodname' => 'check_for_results',
                'description' => 'returns true or false on presence of transcript',
                'ajax' => true,
                'type' => 'read',
                'capabilities' => 'mod/solo:view'
        ),
        'mod_solo_submit_step' => array(
            'classname' => 'mod_solo_external',
            'methodname' => 'submit_step',
            'description' => 'submits a step of the attempt',
            'ajax' => true,
            'type' => 'write',
            'capabilities' => 'mod/solo:view'
        ),
        'mod_solo_fetch_ai_grade' => array(
            'classname' => 'mod_solo_external',
            'methodname' => 'fetch_ai_grade',
            'description' => 'fetches the AI grade for a given prompt and response',
            'ajax' => true,
            'type' => 'write',
            'capabilities' => 'mod/solo:manage'
        )
);