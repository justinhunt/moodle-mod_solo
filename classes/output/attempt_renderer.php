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

namespace mod_solo\output;


defined('MOODLE_INTERNAL') || die();

use mod_solo\constants;
use mod_solo\utils;
use mod_solo\diff;
use mod_solo\textanalyser;

/**
 * A custom renderer class that extends the plugin_renderer_base.
 *
 * @package mod_solo
 * @copyright COPYRIGHTNOTICE
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_renderer extends \plugin_renderer_base {

    /**
     * Show the introduction text is as set in the activity description
     */
    public function show_intro($readaloud, $cm) {
        $ret = "";
        if (utils::super_trim(strip_tags($readaloud->intro))) {
            $ret .= $this->output->box_start(constants::M_INTRO_CONTAINER . ' ' . constants::M_CLASS . '_center ');
            $ret .= format_module_intro('readaloud', $readaloud, $cm->id);
            $ret .= $this->output->box_end();
        }
        return $ret;
    }

    /**
     * Return HTML to display add first page links
     * @param lesson $lesson
     * @return string
     */
    public function add_edit_page_links($solo, $latestattempt, $thisstep, $cm, $context) {
        global $CFG;

        // instructions /intro if less then Moodle 4.0 show
        if($CFG->version < 2022041900) {
            $introcontent = $this->show_intro($solo, $cm);
        }else {
            $introcontent = '';
        }

        $parts = [];
        $buttonclass = 'btn ' . constants::M_COMPONENT .'_menubutton ' . constants::M_COMPONENT;

        // Set the attempt id
        $attemptid = 0;
        // TODO reverted to non form processing, so id should be id, not like it says below. Confirm its working right
        // because of the way attemot/data are managed in form handler (manageattempts.php) the true attemptid is at 'attemptid' not 'id'
        if($latestattempt){$attemptid = $latestattempt->id;
        }

         // stepdata
        $stepdata = new \stdClass();
        // get total steps
        $stepdata->totalsteps = 0;// we should always have one step
        $steps = [1, 2, 3, 4, 5];
        foreach($steps as $step) {
            if ($solo->{'step' . $step} != constants::M_STEP_NONE) {
                $stepdata->totalsteps++;
            }
        }

        $stepdata->completedsteps = (($latestattempt && $latestattempt->completedsteps) ? $latestattempt->completedsteps : 0);
        $stepdata->currentstep = 1 + $stepdata->completedsteps;
        // this is not exactly "percentagecomplete", it actually is the number of lines between steps, eg 1 -- 2 -- 3 -- 4
        $stepdata->percentcomplete = $stepdata->completedsteps == 0 ? 0 : round(($stepdata->completedsteps + 1 / ($stepdata->totalsteps)) * 100, 0);
        if($stepdata->percentcomplete > 100){$stepdata->percentcomplete = 100;
        }

        // init buttons
        $buttons = [];
        $allsteps = $stepdata->totalsteps + 1;// we show an icon for finished step
        for ($step = 1; $step <= $allsteps; $step++){
            // foreach($steps as $step) {
            $button = new \stdClass();
            if($step <= $stepdata->currentstep ) {
                $button->class = 'activeordone';
            }else{
                $button->class = '';
            }
            $buttons[] = $button;
        }

         return $this->output->render_from_template( constants::M_COMPONENT . '/activityintrobuttons', ['introcontent' => $introcontent, 'stepdata' => $stepdata, 'buttons' => $buttons]);
    }

    function show_userattemptsummary($moduleinstance, $attempt) {
        $userheader = true;
        return $this->show_summary($moduleinstance, $attempt, $userheader);
    }

    public function show_placeholdereval($attemptid) {
        $data = new \stdClass();
        $data->attemptid = $attemptid;
        return $this->output->render_from_template( constants::M_COMPONENT . '/summaryplaceholdereval', $data);
    }

    function show_teachereval($graderesults, $feedback, $evaluator) {
        $data = new \stdClass();
        $data->graderesults = $graderesults;
        $data->feedback = $feedback;
        $data->evaluator = $evaluator;

        // Check if the resulting string is numeric
        if(strpos($graderesults, '%') !== false){
            // Remove the percentage sign
            $numericstr = str_replace('%', '', $graderesults);
            // Parse the string as an integer
            if(is_numeric($numericstr)) {
                $percentage = (int)$numericstr;
                $data->donutchart = true;
                $data->filled = $percentage;
                $data->unfilled = 100 - $percentage;
            }
        }
        // Hacky check for rubric and show different layout depending on that.
        if (\core_text::strpos($graderesults, 'rubric') !== false) {
            return $this->output->render_from_template( constants::M_COMPONENT . '/summaryrubriceval', $data);
        } else {
            return $this->output->render_from_template( constants::M_COMPONENT . '/summaryteachereval', $data);
        }

    }

    function show_spellingerrors($spellingerrors) {
        $data = new \stdClass();
        if(count($spellingerrors)) {
            $data->spellingerrors = $spellingerrors;
            $data->spellingerrorslabel = get_string('possiblespellingerrors', constants::M_COMPONENT);
        }else{
            $data->spellingerrorslabel = get_string('nospellingerrors', constants::M_COMPONENT);
        }
        return $this->output->render_from_template( constants::M_COMPONENT . '/summaryspellingeval', $data);
    }
    function show_grammarerrors($grammarerrors) {
        $data = new \stdClass();
        if(count($grammarerrors)) {
            $data->grammarerrors = $grammarerrors;
            $data->grammarerrorslabel = get_string('possiblegrammarerrors', constants::M_COMPONENT);
        }else{
            $data->grammarerrorslabel = get_string('nogrammarerrors', constants::M_COMPONENT);
        }
        return $this->output->render_from_template( constants::M_COMPONENT . '/summarygrammareval', $data);
    }

    function show_summary($moduleinstance, $attempt, $userheader=false) {
        $attempt->targetwords = utils::fetch_targetwords($attempt->topictargetwords);
        $attempt->hastargetwords = !empty($attempt->targetwords);
        $attempt->convlength = $moduleinstance->convlength;
        $attempt->speakingtopic = $moduleinstance->speakingtopic;
        // we don't want to show target speaking time if its not a speaking activity
        $attempt->textonlysubmission = utils::is_textonlysubmission($moduleinstance);

        if($userheader){
            $ret = $this->output->render_from_template( constants::M_COMPONENT . '/summaryuserattemptheader', $attempt);
        }else{
            $ret = $this->output->render_from_template( constants::M_COMPONENT . '/summaryheader', $attempt);
        }

        $ret .= $this->output->render_from_template( constants::M_COMPONENT . '/summarychoices', $attempt);

        return $ret;
    }

    function show_waitingforteacher() {
        return $this->output->render_from_template( constants::M_COMPONENT . '/waitingforteacher', []);
    }

    function show_summarypassageandstats($moduleinstance, $attempt, $aidata, $stats, $autotranscriptready, $selftranscribe) {
        // mark up our passage for review
        // if we have ai we need all the js and markup, otherwise we just need the formated transcript
        $ret = '';
        // spelling and grammar data
        $tdata = ['a' => $attempt, 's' => $stats, 'audiofilename' => $attempt->filename, 'autotranscriptready' => $autotranscriptready];

        // if user doesn't edit transcript don't bother explaining how transcript matching works
        if($moduleinstance->step1 == constants::M_STEP_TRANSCRIBE ||
            $moduleinstance->step2 == constants::M_STEP_TRANSCRIBE ||
            $moduleinstance->step3 == constants::M_STEP_TRANSCRIBE ||
            $moduleinstance->step4 == constants::M_STEP_TRANSCRIBE){
            $tdata['studenteditstranscript'] = true;
        }

        // If no audio recording (this is a text submission) then we don't want to show WPM / Target Time etc
        $tdata['textonlysubmission'] = utils::is_textonlysubmission($moduleinstance);

        // If this is audio, show an audio recorder, if this is video, show a video recorder
        $tdata['isaudiosubmission']  = $moduleinstance->recordertype == constants::REC_AUDIO;
        $tdata['isvideosubmission'] = $moduleinstance->recordertype == constants::REC_VIDEO;

        $tdata['spellingerrors'] = textanalyser::fetch_spellingerrors($stats, $attempt->selftranscript);
        $tdata['grammarerrors'] = textanalyser::fetch_grammarerrors($stats, $attempt->selftranscript);
        if($tdata['spellingerrors']){$tdata['hasspellingerrors'] = true;
        }
        if($tdata['grammarerrors']){$tdata['hasgrammarerrors'] = true;
        }
        if($selftranscribe){$tdata['selftranscribe'] = true;
        }
        if($moduleinstance->showgrammar){$tdata['showgrammar'] = true;
        }
        if($moduleinstance->showspelling){$tdata['showspelling'] = true;
        }

        // if you have no transcript then it will error on render, so we use a space by default
        // it should never really be blank however, and theuser arrived in a strange way probably. This just avoids an ugly error
        $simpleselftranscript = ' ';
        if(!empty($attempt->selftranscript)){
            $simpleselftranscript = $attempt->selftranscript;
        }

        if($aidata) {

            $markedpassage = \mod_solo\aitranscriptutils::render_passage($simpleselftranscript);
            $jsoptshtml = \mod_solo\aitranscriptutils::prepare_passage_amd($attempt, $aidata);
            $markedpassage .= $jsoptshtml;

            // add nav to marrked Passage
            /*
            $navdata=[];
            $navdata['clarity_errors']=$aidata->errorcount ? '(' .$aidata->errorcount. ')':'';;
            $navdata['spelling_errors']=$tdata['spellingerrors'] ? '(' .count($tdata['spellingerrors']). ')':'';
            $navdata['grammar_errors']=$tdata['grammarerrors'] ? '(' .count($tdata['grammarerrors']). ')':'';
            $resultsnav = $this->output->render_from_template( constants::M_COMPONENT . '/summarytranscriptnav', $navdata);
            $markedpassage = $resultsnav . $markedpassage;
            */
        }else{
            $markedpassage = $this->output->render_from_template( constants::M_COMPONENT . '/summarytranscript', $tdata);
        }
        $tdata['markedpassage'] = $markedpassage;

        // if we have a correction, send that out too
        if(!empty($attempt->grammarcorrection)){
            if(diff::cleanText($simpleselftranscript) !== diff::cleanText($attempt->grammarcorrection)) {
                $direction = 'r2l';
                list($grammarerrors, $grammarmatches, $insertioncount) = utils::fetch_grammar_correction_diff($simpleselftranscript, $attempt->grammarcorrection, $direction);
                $jsoptshtml = \mod_solo\aitranscriptutils::prepare_corrections_amd($grammarerrors, $grammarmatches, $insertioncount);
                $markedupcorrections = \mod_solo\aitranscriptutils::render_passage($attempt->grammarcorrection, 'corrections');
                $markedupcorrections .= $jsoptshtml;
                $tdata['grammarcorrection'] = $markedupcorrections;
            }

        }

        if(!empty($attempt->aifeedback)&&utils::is_json($attempt->aifeedback)){
            $tdata['hasaifeedback'] = true;
            $tdata['aifeedback'] = json_decode($attempt->aifeedback);
            // For right to left languages we want to add the RTL direction and right justify.
            $feedbacklanguage = utils::fetch_feedback_language($moduleinstance, $attempt);
            if(utils::is_rtl($feedbacklanguage)){
                $tdata['rtl'] = constants::M_CLASS . '_rtl';
            }
        }

        // send data to template
        $ret .= $this->output->render_from_template( constants::M_COMPONENT . '/summaryresults', $tdata);
        return $ret;
    }

    function show_autogradelog($autogradelog) {
        if(empty($autogradelog)) {return '';
        }
        if(!utils::is_json($autogradelog)) {return '';
        }

        $data = new \stdClass();
        $data->autogradelog = json_decode($autogradelog);
        return $this->output->render_from_template( constants::M_COMPONENT . '/summaryautogradelog', $data);
    }


    function show_myreports($moduleinstance, $cm) {

        $myprogress = new \single_button(
                new \moodle_url(constants::M_URL . '/myreports.php',
                        ['report' => 'myprogress', 'id' => $cm->id, 'n' => $moduleinstance->id, 'format' => 'linechart']),
                get_string('myprogressreport', constants::M_COMPONENT), 'get');
        $buttons[] = $this->render($myprogress);

        $myattempts = new \single_button(
                new \moodle_url(constants::M_URL . '/myreports.php',
                        ['report' => 'myattempts', 'id' => $cm->id, 'n' => $moduleinstance->id, 'format' => 'tabular']),
                get_string('myattempts', constants::M_COMPONENT), 'get');
        $buttons[] = $this->render($myattempts);

        $buttonshtml = \html_writer::div(  implode("&nbsp;&nbsp;", $buttons),  constants::M_CLASS . '_listbuttons');
        $data = new \stdClass();
        $data->reportbuttons = $buttonshtml;
        return $this->output->render_from_template( constants::M_COMPONENT . '/summarymyreports', $data);
    }



    function setup_datatables($tableid) {
        global $USER;

        $tableprops = [];
        $columns = [];
        // for cols .. .'attemptname', 'attempttype','timemodified', 'edit','delete'
        $columns[0] = null;
        $columns[1] = null;
        $columns[2] = null;
        $columns[3] = null;
        $columns[4] = null;
        $tableprops['columns'] = $columns;

        // default ordering
        $order = [];
        // $order[0] =array(3, "desc");
        // $tableprops['order']=$order;

        // here we set up any info we need to pass into javascript
        $opts = [];
        $opts['tableid'] = $tableid;
        $opts['tableprops'] = $tableprops;
        $this->page->requires->js_call_amd("mod_solo/datatables", 'init', [$opts]);
        $this->page->requires->css( new \moodle_url('https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css'));
    }

    function fetch_recorder_amd($cm) {
        global $USER;

        $widgetid = constants::M_WIDGETID;
        // any html we want to return to be sent to the page
        $rethtml = '';

        // here we set up any info we need to pass into javascript
        $recopts = [];
        $recopts['recorderid'] = $widgetid . '_recorderdiv';
        $recopts['cloudpoodllurl'] = utils::get_cloud_poodll_server();

        // this inits the M.mod_solo thingy, after the page has loaded.
        // we put the opts in html on the page because moodle/AMD doesn't like lots of opts in js
        $jsonstring = json_encode($recopts);
        $optshtml = \html_writer::tag('input', '', ['id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        // the recorder div
        $rethtml = $rethtml . $optshtml;

        $opts = ['cmid' => $cm->id, 'widgetid' => $widgetid];
        $this->page->requires->js_call_amd("mod_solo/recordercontroller", 'init', [$opts]);

        // these need to be returned and echo'ed to the page
        return $rethtml;
    }

}
