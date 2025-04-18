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
 * solo module admin settings and defaults
 *
 * @package    mod
 * @subpackage solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/mod/solo/lib.php');

use mod_solo\constants;
use mod_solo\utils;

if ($ADMIN->fulltree) {


    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apiuser',
        get_string('apiuser', constants::M_COMPONENT), get_string('apiuser_details', constants::M_COMPONENT), '', PARAM_TEXT));

    $cloudpoodllapiuser = get_config(constants::M_COMPONENT, 'apiuser');
    $cloudpoodllapisecret = get_config(constants::M_COMPONENT, 'apisecret');
    $showbelowapisecret = '';
    // if we have an API user and secret we fetch token
    if(!empty($cloudpoodllapiuser) && !empty($cloudpoodllapisecret)) {
        $tokeninfo = utils::fetch_token_for_display($cloudpoodllapiuser, $cloudpoodllapisecret);
        $showbelowapisecret = $tokeninfo;
        // if we have no API user and secret we show a "fetch from elsewhere on site" or "take a free trial" link
    }else{
        $amddata = ['apppath' => $CFG->wwwroot . '/' .constants::M_URL];
        $cpcomponents = ['filter_poodll', 'qtype_cloudpoodll', 'mod_readaloud', 'mod_wordcards', 'mod_minilesson', 'mod_englishcentral', 'mod_pchat',
            'atto_cloudpoodll', 'tinymce_cloudpoodll', 'assignsubmission_cloudpoodll', 'assignfeedback_cloudpoodll'];
        foreach($cpcomponents as $cpcomponent){
            switch($cpcomponent){
                case 'filter_poodll':
                    $apiusersetting = 'cpapiuser';
                    $apisecretsetting = 'cpapisecret';
                    break;
                case 'mod_englishcentral':
                    $apiusersetting = 'poodllapiuser';
                    $apisecretsetting = 'poodllapisecret';
                    break;
                default:
                    $apiusersetting = 'apiuser';
                    $apisecretsetting = 'apisecret';
            }
            $cloudpoodllapiuser = get_config($cpcomponent, $apiusersetting);
            if(!empty($cloudpoodllapiuser)){
                $cloudpoodllapisecret = get_config($cpcomponent, $apisecretsetting);
                if(!empty($cloudpoodllapisecret)){
                    $amddata['apiuser'] = $cloudpoodllapiuser;
                    $amddata['apisecret'] = $cloudpoodllapisecret;
                    break;
                }
            }
        }
        $showbelowapisecret = $OUTPUT->render_from_template( constants::M_COMPONENT . '/managecreds', $amddata);
    }


    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apisecret',
        get_string('apisecret', constants::M_COMPONENT), $showbelowapisecret , '', PARAM_TEXT));

    // Cloud Poodll Server.
    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/cloudpoodllserver',
    get_string('cloudpoodllserver', constants::M_COMPONENT),
        get_string('cloudpoodllserver_details', constants::M_COMPONENT),
        constants::M_DEFAULT_CLOUDPOODLL, PARAM_URL));
   

    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/multipleattempts',
            get_string('multiattempts', constants::M_COMPONENT), get_string('multiattempts_details', constants::M_COMPONENT), 0));

    /*
    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablegallery',
        get_string('enablegallery', constants::M_COMPONENT), get_string('enablegallery_details',constants::M_COMPONENT), 0));
    */
    $regions = \mod_solo\utils::get_region_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/awsregion',
            get_string('awsregion', constants::M_COMPONENT), '', 'useast1', $regions));

    $expiredays = \mod_solo\utils::get_expiredays_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/expiredays',
            get_string('expiredays', constants::M_COMPONENT), '', '365', $expiredays));

    $langoptions = \mod_solo\utils::get_lang_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/ttslanguage',
             get_string('ttslanguage', constants::M_COMPONENT), '',
             constants::M_LANG_ENUS, $langoptions));

    $showopts = \mod_solo\utils::get_show_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/showgrammar',
        get_string('showgrammar', constants::M_COMPONENT), '',
        0, $showopts));

    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/showspelling',
        get_string('showspelling', constants::M_COMPONENT), '',
        0, $showopts));

    // Transcriber options
    $name = 'transcriber';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::TRANSCRIBER_OPEN;
    $options = utils::fetch_options_transcribers();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
            $label, $details, $default, $options));

    $layoutoptions = \mod_solo\utils::get_layout_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/layout',
        get_string('layout', constants::M_COMPONENT), '',
        constants::M_LAYOUT_NARROW, $layoutoptions));

    $settings->add(new admin_setting_confightmleditor(constants::M_COMPONENT . '/speakingtips', get_string('speakingtips', constants::M_COMPONENT),
            get_string('speakingtips_details', constants::M_COMPONENT), get_string('speakingtips_default', constants::M_COMPONENT)));

    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/attemptsperpage',
        get_string('attemptsperpage', constants::M_COMPONENT), get_string('attemptsperpage_details', constants::M_COMPONENT), 10, PARAM_INT));

    $gradingsperpageoptions = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6'];
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/gradingsperpage',
        get_string('gradingsperpage', constants::M_COMPONENT), get_string('gradingsperpage_details', constants::M_COMPONENT),
        3, $gradingsperpageoptions));

    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablelocalpost',
        get_string('enablelocalpost', constants::M_COMPONENT), get_string('enablelocalpost_details', constants::M_COMPONENT), 0));


    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablesetuptab',
            get_string('enablesetuptab', constants::M_COMPONENT), get_string('enablesetuptab_details', constants::M_COMPONENT), 0));

    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/markscheme',
        get_string('markscheme', constants::M_COMPONENT), get_string('markscheme_help', constants::M_COMPONENT), 'Deduct two marks for each grammar mistake.', PARAM_TEXT, 100));

    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/feedbackscheme',
        get_string('feedbackscheme', constants::M_COMPONENT), get_string('feedbackscheme_help', constants::M_COMPONENT), 'Explain each grammar mistake simply.', PARAM_TEXT, 100));

       // $langoptions = \mod_solo\utils::get_lang_options(); // already set above
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/feedbacklanguage',
                get_string('feedbacklanguage', constants::M_COMPONENT), '',
                constants::M_LANG_ENUS, $langoptions));

    // Native Language Setting
    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/setnativelanguage',
        get_string('enablenativelanguage', constants::M_COMPONENT), get_string('enablenativelanguage_details', constants::M_COMPONENT), 1));

}
