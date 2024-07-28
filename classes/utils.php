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
 * Grade Now for solo plugin
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_solo;

defined('MOODLE_INTERNAL') || die();

use \mod_solo\constants;

require_once($CFG->dirroot . '/mod/solo/lib.php');


/**
 * Functions used generally across this mod
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils{

    const CLOUDPOODLL = 'https://cloud.poodll.com';
    //const CLOUDPOODLL = 'https://vbox.poodll.com/cphost';

    //are we willing and able to transcribe submissions?
    public static function can_transcribe($instance) {

        //we default to true
        //but it only takes one no ....
        $ret = true;

        //The regions that can transcribe
        switch($instance->region){
            default:
                $ret = true;
        }

        //if user disables ai, we do not transcribe
        if (!$instance->enableai) {
            $ret = false;
        }

        return $ret;
    }

    public static function can_streaming_transcribe($instance){

        $ret = false;

        //The instance languages
        switch($instance->ttslanguage){
            case constants::M_LANG_ENAU:
            case constants::M_LANG_ENGB:
            case constants::M_LANG_ENUS:
            case constants::M_LANG_ESUS:
            case constants::M_LANG_FRFR:
            case constants::M_LANG_FRCA:
                $ret =true;
                break;
            default:
                $ret = false;
        }

        //The supported regions
        if($ret) {
            switch ($instance->region) {
                case "useast1":
                case "useast2":
                case "uswest2":
                case "sydney":
                case "dublin":
                case "ottawa":
                    $ret =true;
                    break;
                default:
                    $ret = false;
            }
        }

        return $ret;
    }

    //streaming results are not the same format as non streaming, we massage the streaming to look like a non streaming
    //to our code that will go on to process it.
    public static function parse_streaming_results($streaming_results){
        $results = json_decode($streaming_results);
        $alltranscript = '';
        $allitems=[];
        foreach($results as $result){
            foreach($result as $completion) {
                foreach ($completion->Alternatives as $alternative) {
                    $alltranscript .= $alternative->Transcript . ' ';
                    foreach ($alternative->Items as $item) {
                        $processeditem = new \stdClass();
                        $processeditem->alternatives = [['content' => $item->Content, 'confidence' => "1.0000"]];
                        $processeditem->end_time = "" . round($item->EndTime,3);
                        $processeditem->start_time = "" . round($item->StartTime,3);
                        $processeditem->type = $item->Type;
                        $allitems[] = $processeditem;
                    }
                }
            }
        }
        $ret = new \stdClass();
        $ret->jobName="streaming";
        $ret->accountId="streaming";
        $ret->results =[];
        $ret->status='COMPLETED';
        $ret->results['transcripts']=[['transcript'=>$alltranscript]];
        $ret->results['items']=$allitems;

        return json_encode($ret);
    }


    //check if curl return from transcript url is valid
    public static function is_valid_transcript($transcript){
        if(strpos($transcript,"<Error><Code>AccessDenied</Code>")>0){
            return false;
        }
        return true;
    }

    public static function transcripts_are_ready_on_s3($attempt) {
        //if the audio filename is empty or wrong, its hopeless ...just return false
        if(!$attempt->filename || empty($attempt->filename)){
            return false;
        }
        $transcripturl = $attempt->filename . '.txt';
        $postdata = array();
        //fetch transcripts, and bail out of they are not ready or wrong
        $transcript = self::curl_fetch($transcripturl,$postdata);
        return self::is_valid_transcript($transcript);
    }

    public static function retrieve_transcripts_from_s3($attempt){
        global $DB;

        //if the audio filename is empty or wrong, its hopeless ...just return false
        if(!$attempt->filename || empty($attempt->filename)){
            return false;
        }

        $jsontranscripturl = $attempt->filename . '.json';
        $vtttranscripturl = $attempt->filename . '.vtt';
        $transcripturl = $attempt->filename . '.txt';
        $postdata = array();
        //fetch transcripts, and bail out of they are not ready or wrong
        $jsontranscript = self::curl_fetch($jsontranscripturl,$postdata);
        if(!self::is_valid_transcript($jsontranscript)){return false;}

        $vtttranscript = self::curl_fetch($vtttranscripturl,$postdata);
        if(!self::is_valid_transcript($vtttranscript)){return false;}

        $transcript = self::curl_fetch($transcripturl,$postdata);
        if(!self::is_valid_transcript($transcript)){return false;}

        //if we got here, we have transcripts and we do not need to come back
        //jsontranscript and vtttranscript will both be truthy even if empty, but transcript will not ... it will falsey
        //so we allow emtpy transcript even though it sucks 15/01/2024 J
        if($jsontranscript && $vtttranscript && $transcript!==null && $transcript !==false) {
            $updateattempt = new \stdClass();
            $updateattempt->id=$attempt->id;
            $updateattempt->jsontranscript = $jsontranscript;
            $updateattempt->vtttranscript = $vtttranscript;
            $updateattempt->transcript = $transcript;
            $success = $DB->update_record(constants::M_ATTEMPTSTABLE, $updateattempt);

            if($success){
                $attempt->jsontranscript = $jsontranscript;
                $attempt->vtttranscript = $vtttranscript;
                $attempt->transcript = $transcript;

                //update auto transcript stats
                self::update_stats_for_autotranscript($attempt);

                //return attempt
                return $attempt;
            }
        }
        return false;
    }

    //fetch stats, one way or the other
    public static function update_stats_for_autotranscript($attempt) {
        global $DB;
        if($attempt->selftranscript && $attempt->transcript){
            //do some stats work

        }
        return true;
    }



    //fetch lang server url, services incl. 'transcribe' , 'lm', 'lt', 'spellcheck'
    public static function fetch_lang_server_url($region,$service='transcribe'){
        switch($region) {
            case 'useast1':
                $ret = 'https://useast.ls.poodll.com/';
                break;
            default:
                $ret = 'https://' . $region . '.ls.poodll.com/';
        }
        return $ret . $service;
    }

    //fetch self transcript parts
    public static function fetch_selftranscript_parts($attempt) {
        $sc= $attempt->selftranscript;
        if(!empty($sc)){
            $items = preg_split('/[!?.]+(?![0-9])/', $sc);
            $items = array_filter($items);
            return $items;
        }else{
            return array();
        }
    }

    public static function fetch_sentence_stats($text,$stats, $language){

        //count sentences
        $items = preg_split('/[!?.]+(?![0-9])/', $text);
        $items = array_filter($items);
        $sentencecount = count($items);

        //longest sentence length
        //average sentence length
        $longestsentence=1;
        $averagesentence=1;
        $totallengths = 0;
        foreach($items as $sentence){
            $length = self::mb_count_words($sentence,$language);
            if($length>$longestsentence){
                $longestsentence =$length;
            }
            $totallengths+=$length;
        }
        if($totallengths>0 && $sentencecount>0){
            $averagesentence=round($totallengths / $sentencecount);
        }

        //return values
        $stats->avturn = $averagesentence;
        $stats->longestturn = $longestsentence;
        return $stats;
    }

    public static function is_english($language){
        $ret = strpos($language,'en')===0;
        return $ret;
    }

    //TO DO - remove this function, it is now in textanalyser
    public static function fetch_word_stats($text,$language, $stats) {

        //prepare data
        $is_english=self::is_english($language);
        $items = \core_text::strtolower($text);
        $items = self::mb_count_words($items,$language,1);
        $items = array_unique($items);

        //unique words
        $uniquewords = count($items);

        //long words
        $longwords = 0;
        foreach ($items as $item) {
            if($is_english) {
                if (self::count_syllables($item) > 2) {
                    $longwords++;
                }
            }else{
                if (\core_text::strlen($item) > 5) {
                    $longwords++;
                }
            }
        }

        //return results
        $stats->uniquewords= $uniquewords;
        $stats->longwords= $longwords;
        return $stats;
    }

    public static function mb_count_words($string, $language, $format=0)
    {

        //wordcount will be different for different languages
        switch($language){
            //arabic
            case constants::M_LANG_ARAE:
            case constants::M_LANG_ARSA:
                //remove double spaces and count spaces remaining to estimate words
                $string= preg_replace('!\s+!', ' ', $string);
                switch($format){

                    case 1:
                        $ret = explode(' ', $string);
                        break;
                    case 0:
                    default:
                        $ret = substr_count($string, ' ') + 1;
                }

                break;
            //others
            default:
                $words = diff::fetchWordArray($string);
                $wordcount = count($words);
                //$wordcount = str_word_count($string,$format);
                switch($format){

                    case 1:
                        $ret = $words;
                        break;
                    case 0:
                    default:
                       $ret = $wordcount;
                }

        }

        return $ret;
    }

    /**
     * count_syllables
     *
     * based on: https://github.com/e-rasvet/sassessment/blob/master/lib.php
     */
    public static function count_syllables($word) {
        // https://github.com/vanderlee/phpSyllable (multilang)
        // https://github.com/DaveChild/Text-Statistics (English only)
        // https://pear.php.net/manual/en/package.text.text-statistics.intro.php
        // https://pear.php.net/package/Text_Statistics/docs/latest/__filesource/fsource_Text_Statistics__Text_Statistics-1.0.1TextWord.php.html
        $str = strtoupper($word);
        $oldlen = strlen($str);
        if ($oldlen < 2) {
            $count = 1;
        } else {
            $count = 0;

            // detect syllables for double-vowels
            $vowels = array('AA','AE','AI','AO','AU',
                    'EA','EE','EI','EO','EU',
                    'IA','IE','II','IO','IU',
                    'OA','OE','OI','OO','OU',
                    'UA','UE','UI','UO','UU');
            $str = str_replace($vowels, '', $str);
            $newlen = strlen($str);
            $count += (($oldlen - $newlen) / 2);

            // detect syllables for single-vowels
            $vowels = array('A','E','I','O','U');
            $str = str_replace($vowels, '', $str);
            $oldlen = $newlen;
            $newlen = strlen($str);
            $count += ($oldlen - $newlen);

            // adjust count for special last char
            switch (substr($str, -1)) {
                case 'E': $count--; break;
                case 'Y': $count++; break;
            };
        }
        return $count;
    }


    public static function fetch_targetwords($targetwords){
        $targetwordsarray =array();
        //if no target words just exit
        if(empty($targetwords)){
            return $targetwordsarray;
        }
        //split on PHP_EOL or comma
        $separator = "/(,|" . PHP_EOL . ")/"; // Regular expression to match a comma or PHP_EOL
        $result = preg_split($separator, $targetwords, -1, PREG_SPLIT_NO_EMPTY);
        if($result && count($result)>0){
            //remove duplicates and reindex array so there are no gaps
            $targetwordsarray = array_values(array_unique($result));
        }

        return $targetwordsarray;
    }

    /*
     * 2023/5/13 TO DO: remove unneeded AI transcript constructer and edited self-transcript ... it can not be empty or edited
     */
    public static function process_attempt($moduleinstance, $attempt, $contextid, $cmid, $trace=false){
        global $DB;

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $attempthelper = new \mod_solo\attempthelper($cm);
        $requiresgrading = ($attempt->grade==0 && $attempt->manualgraded==0);

        //if we do not have a self transcript, and yet we require a self transcript,
        // and if we are calling from cron, we send the task back
        //this will happen if crons runs while the student is still typing and after the transcript has finished processing
        $transcribestep = self::fetch_step_no($moduleinstance, constants::STEP_SELFTRANSCRIBE);
        if(empty($attempt->selftranscript) && $transcribestep !==false){
            if($trace) {
                $trace->output("Self Transcript not ready yet. quitting");
                return false;
            }
        }

        //if we do not have automatic transcripts, try to fetch them
        $recordstep = self::fetch_step_no($moduleinstance, constants::M_STEP_RECORD);
        $hastranscripts = !empty($attempt->jsontranscript);
        //if we have no record step, this is a written assignment
        if(!$hastranscripts && $recordstep === false) {
            //fake some ai data so we dont need to rewrite the whole world
            $DB->update_record(constants::M_ATTEMPTSTABLE,
                array('id' => $attempt->id, 'transcript' => $attempt->selftranscript,'jsontranscript'=>'{}'));
       //if we have a record step but no transcripts th
        }elseif(!$hastranscripts) {
            $attempt_with_transcripts = self::retrieve_transcripts_from_s3($attempt);
            $hastranscripts = $attempt_with_transcripts !== false;

            //if we are calling from cron, just return here
            if (!$hastranscripts && $trace) {
                $trace->output("Transcript not ready yet");
                return false;
            }

            //if we fetched the transcript, and this activity has no manual self transcript, use the auto transcript as manual
            if ($transcribestep === false && $hastranscripts) {
                $attempt->selftranscript = $attempt_with_transcripts->transcript;
                $DB->update_record(constants::M_ATTEMPTSTABLE, array('id' => $attempt->id, 'selftranscript' => $attempt->selftranscript));
            }
            $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, array('id' => $attempt->id));
        }

        //this should run down the aitranscript constructor and do the diffs if the passage arrives late or on time, but not redo
        //this line caused an error if the user entered a blank transcript. Do we need to check for empty?
        //if($hastranscripts && !empty($attempt->selftranscript)){
        if($hastranscripts){
            $autotranscript=$attempt->transcript;
            $aitranscript = new \mod_solo\aitranscript($attempt->id,
                $contextid, $attempt->selftranscript,
                $attempt->transcript,
                $attempt->jsontranscript);
            // $attempt = $attempthelper->fetch_latest_complete_attempt();
        }

        //get token, for web service calls
        $siteconfig = get_config(constants::M_COMPONENT);
        $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);

        //get target words
        $targetwords = utils::fetch_targetwords($attempt->topictargetwords);

        //for relevance, the default is to use the model answer embedding
        //but that's not helful with an open ended question e.g "what would you do with a million dollars?"
        //so we use the speaking topic
        $agoptions = json_decode($moduleinstance->autogradeoptions);
        $targettopic=false;
        if($agoptions->relevancegrade==constants::RELEVANCE_QUESTION){
            $targettopic = strip_tags($moduleinstance->speakingtopic);
        }

        //get text analyser
        $passage = $attempt->selftranscript;
        $userlanguage=false;
        $textanalyser = new textanalyser($token,$passage,$moduleinstance->region,
            $moduleinstance->ttslanguage,$moduleinstance->modelttsembedding,$userlanguage,$targettopic);

        //update statistics and grammar correction if we need to
        if($hastranscripts) {
            //if we don't already have stats calculate them
            if(!$DB->get_records(constants::M_STATSTABLE,['attemptid'=>$attempt->id])){

                $stats = $textanalyser->process_all_stats($targetwords);
                if($stats){
                    //for historical reasons some Solo stats field names are weird
                    //but text analyser returns nice names, here we turn them into weird ones.
                    $stats->turns=$stats->sentences; unset($stats->sentences);
                    $stats->avturn=$stats->sentenceavg; unset($stats->sentenceavg);
                    $stats->longestturn=$stats->sentencelongest; unset($stats->sentencelongest);
                    $stats->uniquewords=$stats->wordsunique; unset($stats->wordsunique);
                    $stats->longwords=$stats->wordslong; unset($stats->wordslong);
                    //also calculate WPM
                    $duration = textanalyser::fetch_duration_from_transcript($attempt->jsontranscript);
                    if($stats->words && $duration) {
                        $stats->wpm = round(( $stats->words / $duration ) * 60,0);
                    }else{
                        $stats->wpm=0;
                    }

                    //then we save them
                    self::save_stats($stats, $attempt);
                }

                //recalculate AI data, if the selftranscription is altered AND we have a jsontranscript
                if($attempt->jsontranscript){
                    $aitranscript = new \mod_solo\aitranscript($attempt->id, $contextid,$passage,$attempt->transcript, $attempt->jsontranscript);
                    $aitranscript->recalculate($passage,$attempt->transcript, $attempt->jsontranscript);
                }
            }
        }


        //Do we need aI data
        $studentresponse=$attempt->selftranscript;
        $maxmarks=100;
        $aigraderesults=false;
        //if we do not already have an ai grade, and we have all the info we need to fetch one, lets do it
        if ($attempt->aigrade ==null && !empty($studentresponse) && !empty($moduleinstance->feedbackscheme) && !empty($moduleinstance->markscheme)) {
            $instructions = new \stdClass();
            $instructions->feedbackscheme = $moduleinstance->feedbackscheme;
            $instructions->feedbacklanguage = $moduleinstance->feedbacklanguage;
            $instructions->markscheme = $moduleinstance->markscheme;
            $instructions->maxmarks = $maxmarks;
            $instructions->questiontext = strip_tags($moduleinstance->speakingtopic);
            $instructions->modeltext = '';
            $aigraderesults = self::fetch_ai_grade($token, $moduleinstance->region, $moduleinstance->ttslanguage, $studentresponse, $instructions);
            if($aigraderesults && isset($aigraderesults->marks) && isset($aigraderesults->feedback)){
                if($aigraderesults->feedback!==null){
                    $aigraderesults->feedback=json_encode($aigraderesults->feedback);
                }
                $DB->update_record(constants::M_ATTEMPTSTABLE,
                    array('id'=>$attempt->id,
                        'aigrade'=>$aigraderesults->marks,
                        'aifeedback'=>$aigraderesults->feedback));
            }
        }

        //Process grammar correction (it won't fetch again if it has it already)
        if($aigraderesults && isset($aigraderesults->correctedtext)){
            self::process_grammar_correction($moduleinstance,$attempt,$aigraderesults->correctedtext);
        }else{
            self::process_grammar_correction($moduleinstance,$attempt);
        }

        //if we have an ungraded activity, lets grade it
        if($hastranscripts && $requiresgrading) {
            $stats=self::fetch_stats($attempt,$moduleinstance);
            utils::autograde_attempt($attempt->id, $stats);
            $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, array('id' => $attempt->id));
        }

        return $attempt;
    }


     /*
      * Process grammar correction details as returned by text analyser
      */
    public static function process_grammar_correction($moduleinstance,$attempt,$corrections=false){
        global $DB;

        if(!empty($attempt->grammarcorrection)){
            return $attempt->grammarcorrection;
        }

        //If this is English then lets see if we can get a grammar correction
       // if(!empty($attempt->selftranscript) && self::is_english($moduleinstance)){
        if(!empty($attempt->selftranscript)){
            if(empty($attempt->grammarcorrection)) {
                $siteconfig = get_config(constants::M_COMPONENT);
                $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
                //get text analyser
                $textanalyser = new textanalyser($token, $attempt->selftranscript,$moduleinstance->region,$moduleinstance->ttslanguage);
                $gcdata= $textanalyser->process_grammar_correction($attempt->selftranscript,$corrections);
                $grammarcorrection = $gcdata['gcorrections'];
                $gcerrors= $gcdata['gcerrors'];
                $gcmatches= $gcdata['gcmatches'];
                $gcerrorcount= $gcdata['gcerrorcount'];

                if ($grammarcorrection) {
                    //set grammar correction (GC)
                    $attempt->grammarcorrection = $grammarcorrection;
                    $DB->update_record(constants::M_ATTEMPTSTABLE,
                        array('id'=>$attempt->id,
                            'grammarcorrection'=>$attempt->grammarcorrection));

                    if(self::is_json($gcerrors)&& self::is_json($gcmatches)) {
                        $stats = $DB->get_record(constants::M_STATSTABLE,
                            array('solo' => $attempt->solo, 'attemptid' => $attempt->id, 'userid' => $attempt->userid));
                        if($stats) {
                            $DB->update_record(constants::M_STATSTABLE,
                                array('id' => $stats->id,
                                    'gcerrorcount' => $gcerrorcount,
                                    'gcerrors' => $gcerrors,
                                    'gcmatches' => $gcmatches));
                        }
                    }
                }
            }
        }
        return $attempt->grammarcorrection;
    }

    /*
      * TO DO -  remove this
      */
    public static function process_relevance($moduleinstance,$attempt,$stats){
        global $DB;

        //if there is a relevance and its not null, then return that
        if(isset($stats->relevance)&&$stats->relevance!=null){
            return $stats->relevance;
        }
        $relevance=false;//default is blank
        if(!empty($attempt->selftranscript)){
            $siteconfig = get_config(constants::M_COMPONENT);
            $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
            $textanalyser = new textanalyser($token,$attempt->selftranscript,$moduleinstance->region,$moduleinstance->ttslanguage);
            $modelembedding = !empty($moduleinstance->modelttsembedding) ? $moduleinstance->modelttsembedding : $moduleinstance->modeltts;
            $relevance = $textanalyser->fetch_relevance($modelembedding);
        }
        if ($relevance!==false) {
            return $relevance;
        }else{
            return 0;
        }
    }

    /*
          * TO DO -  remove this
          */
    public static function process_cefr_level($moduleinstance,$attempt,$stats){
        global $DB;

        //if there is a cefrlevel and its not null, then return that
        if(isset($stats->cefrlevel)&&$stats->cefrlevel!=null){
            return $stats->cefrlevel;
        }
        $cefrlevel=false;//default is blank
        if(!empty($attempt->selftranscript)){
            $siteconfig = get_config(constants::M_COMPONENT);
            $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
            $textanalyser = new textanalyser($token,$attempt->selftranscript,$moduleinstance->region,$moduleinstance->ttslanguage);
            $cefrlevel = $textanalyser->fetch_cefr_level();
        }
        if ($cefrlevel!==false) {
            return $cefrlevel;
        }else{
            return "";
        }
    }

    /*
      * TO DO - remove this function, it is now in textanalyser
      */
    public static function process_idea_count($moduleinstance,$attempt,$stats){
        global $DB;

        //if there is an ideacount and its not null, then return that
        if(isset($stats->ideacount)&&$stats->ideacount!=null){
            return $stats->ideacount;
        }
        $ideacount=false;
        if(!empty($attempt->selftranscript)){
            $siteconfig = get_config(constants::M_COMPONENT);
            $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
            $textanalyser = new textanalyser($token,$attempt->selftranscript,$moduleinstance->region,$moduleinstance->ttslanguage);
            $ideacount = $textanalyser->fetch_idea_count();
        }
        if ($ideacount!==false) {
            return $ideacount;
        }else{
            return 0;
        }

    }

    public static function fetch_grammar_correction_diff($selftranscript,$correction,$direction='l2r'){


        //turn the passage and transcript into an array of words
        $alternatives = diff::fetchAlternativesArray('');
        $wildcards = diff::fetchWildcardsArray($alternatives);

        //the direction of diff depends on which text we want to mark up. Because we only highlight
        //this is because if we show the pre-text (eg student typed text) we can not highlight corrections .. they are not there
        //if we show post-text (eg corrections) we can not highlight mistakes .. they are not there
        //the diffs tell us where the diffs are with relation to text A
        if($direction=='l2r') {
            $passagebits = diff::fetchWordArray($selftranscript);
            $transcriptbits = diff::fetchWordArray($correction);
        }else {
            $passagebits = diff::fetchWordArray($correction);
            $transcriptbits = diff::fetchWordArray($selftranscript);
        }

        //fetch sequences of transcript/passage matched words
        // then prepare an array of "differences"
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        //rough estimate of insertions
        $insertioncount = $transcriptcount - $passagecount;
        if($insertioncount<0){$insertioncount=0;}

        $language = constants::M_LANG_ENUS;
        $sequences = diff::fetchSequences($passagebits,$transcriptbits,$alternatives,$language);

        //fetch diffs
        $diffs = diff::fetchDiffs($sequences, $passagecount,$transcriptcount);
        $diffs = diff::applyWildcards($diffs,$passagebits,$wildcards);


        //from the array of differences build error data, match data, markers, scores and metrics
        $errors = new \stdClass();
        $matches = new \stdClass();
        $currentword=0;
        $lastunmodified=0;
        //loop through diffs
        foreach($diffs as $diff){
            $currentword++;
            switch($diff[0]){
                case Diff::UNMATCHED:
                    //we collect error info so we can count and display them on passage
                    $error = new \stdClass();
                    $error->word=$passagebits[$currentword-1];
                    $error->wordnumber=$currentword;
                    $errors->{$currentword}=$error;
                    break;

                case Diff::MATCHED:
                    //we collect match info so we can play audio from selected word
                    $match = new \stdClass();
                    $match->word=$passagebits[$currentword-1];
                    $match->pposition=$currentword;
                    $match->tposition = $diff[1];
                    $match->audiostart=0;//not meaningful when processing corrections
                    $match->audioend=0;//not meaningful when processing corrections
                    $match->altmatch=$diff[2];//not meaningful when processing corrections
                    $matches->{$currentword}=$match;
                    $lastunmodified = $currentword;
                    break;

                default:
                    //do nothing
                    //should never get here

            }
        }
        $sessionendword = $lastunmodified;

        //discard errors that happen after session end word.
        $errorcount = 0;
        $finalerrors = new \stdClass();
        foreach($errors as $key=>$error) {
            if ($key < $sessionendword) {
                $finalerrors->{$key} = $error;
                $errorcount++;
            }
        }
        //finalise and serialise session errors
        $sessionerrors = json_encode($finalerrors);
        $sessionmatches = json_encode($matches);

        return [$sessionerrors,$sessionmatches,$insertioncount];

    }

    /*
      * TO DO -  remove this
      */

    //we leave it up to the grading logic how/if it adds the ai grades to gradebook
    public static function calc_grammarspell_stats($selftranscript, $region, $language, $stats){
        //init stats with defaults
        $stats->autospell="";
        $stats->autogrammar="";
        $stats->autospellscore=100;
        $stats->autogrammarscore=100;
        $stats->autospellerrors = 0;
        $stats->autogrammarerrors=0;


        //if we have no words for whatever reason the calc will not work
        if(!$stats->words || $stats->words<1) {
            //update spelling and grammar stats in DB
            return $stats;
        }

        //get lanserver lang string
        switch($language){
            case constants::M_LANG_ARSA:
            case constants::M_LANG_ARAE:
                $uselanguage = 'ar';
                break;
            default:
                $uselanguage = $language;
        }

        //fetch grammar stats
        $lt_url = utils::fetch_lang_server_url($region,'lt');
        $postdata =array('text'=> $selftranscript,'language'=>$uselanguage);
        $autogrammar = utils::curl_fetch($lt_url,$postdata,'post');
        //default grammar score
        $autogrammarscore =100;

        //fetch spell stats
        $spellcheck_url = utils::fetch_lang_server_url($region,'spellcheck');
        $spelltranscript = diff::cleanText($selftranscript);
        $postdata =array('passage'=>$spelltranscript,'lang'=>$uselanguage);
        $autospell = utils::curl_fetch($spellcheck_url,$postdata,'post');
        //default spell score
        $autospellscore =100;



        //calc grammar score
        if(self::is_json($autogrammar)) {
            //work out grammar
            $grammarobj = json_decode($autogrammar);
            $incorrect = count($grammarobj->matches);
            $stats->autogrammarerrors= $incorrect;
            $raw = $stats->words - ($incorrect * 3);
            if ($raw < 1) {
                $autogrammarscore = 0;
            } else {
                $autogrammarscore = round($raw / $stats->words, 2) * 100;
            }

            $stats->autogrammar = $autogrammar;
            $stats->autogrammarscore = $autogrammarscore;
        }

        //calculate spell score
        if(self::is_json($autospell)) {

            //work out spelling
            $spellobj = json_decode($autospell);
            $correct = 0;
            if($spellobj->status) {
                $spellarray = $spellobj->data->results;
                foreach ($spellarray as $val) {
                    if ($val) {
                        $correct++;
                    }else{
                        $stats->autospellerrors++;
                    }
                }

                if ($correct > 0) {
                    $autospellscore = round($correct / $stats->words, 2) * 100;
                } else {
                    $autospellscore = 0;
                }
            }
        }

        //update spelling and grammar stats in data object and return
        $stats->autospell=$autospell;
        $stats->autogrammar=$autogrammar;
        $stats->autospellscore=$autospellscore;
        $stats->autogrammarscore=$autogrammarscore;
        return $stats;
    }


    //fetch stats, one way or the other
    public static function fetch_stats($attempt,$moduleinstance=false) {
        global $DB;
        //if we have stats in the database, lets use those
        $stats = $DB->get_record(constants::M_STATSTABLE,array('attemptid'=>$attempt->id));

        //target words ratio - for visual chart
        if($stats){
            if($stats->totaltargetwords > 0) {
                $stats->targetwordsratio = round($stats->targetwords / $stats->totaltargetwords, 2) * 100;
            }else{
                $stats->targetwordsratio = 0;
            }
        }

        //AI inaccuracy -  for visual chart
        if($stats) {
            $stats->aiinaccuracy = 100 - $stats->aiaccuracy;
        }

        //0 aiaccuracy means absolutely nothing was matched
        //-1 means we do not have ai data
        if($stats && $stats->aiaccuracy < 0){
            $stats->aiaccuracy='--';
        }
        return $stats;
    }

    //save / update stats
    public static function save_stats($stats, $attempt){
        global $DB;
        $stats->solo=$attempt->solo;
        $stats->attemptid=$attempt->id;
        $stats->userid=$attempt->userid;
        $stats->timemodified=time();

        $oldstats =$DB->get_record(constants::M_STATSTABLE,
                array('solo'=>$attempt->solo,'attemptid'=>$attempt->id,'userid'=>$attempt->userid));
        if($oldstats){
            $stats->id = $oldstats->id;
            $DB->update_record(constants::M_STATSTABLE,$stats);
        }else{
            $stats->timecreated=time();
            $stats->createdby=$attempt->userid;
            $DB->insert_record(constants::M_STATSTABLE,$stats);
        }
        return;
    }

    /*
      * TO DO -  remove this
      */
    //calculate stats of transcript (no db code)
    public static function calculate_stats($usetranscript, $attempt, $language){
        $stats= new \stdClass();
        $stats->turns=0;
        $stats->words=0;
        $stats->avturn=0;
        $stats->longestturn=0;
        $stats->targetwords=0;
        $stats->totaltargetwords=0;
        $stats->aiaccuracy=-1;

        if(!$usetranscript || empty($usetranscript)){
            return $stats;
        }

        $items = preg_split('/[!?.]+(?![0-9])/', $usetranscript);
        $transcriptarray = array_filter($items);
        $totalturnlengths=0;
        $jsontranscript = '';

        foreach($transcriptarray as $sentence){
            //wordcount will be different for different languages
            $wordcount = self::mb_count_words($sentence,$language);

            if($wordcount===0){continue;}
            $jsontranscript .= $sentence . ' ' ;
            $stats->turns++;
            $stats->words+= $wordcount;
            $totalturnlengths += $wordcount;
            if($stats->longestturn < $wordcount){$stats->longestturn = $wordcount;}
        }
        if(!$stats->turns){
            return false;
        }
        $stats->avturn= round($totalturnlengths  / $stats->turns);
        $targetwords = utils::fetch_targetwords($attempt->topictargetwords);
        $stats->totaltargetwords = count($targetwords);


        $searchpassage = strtolower($jsontranscript);
        foreach($targetwords as $theword){
            $searchword = self::cleanText($theword);
            if(empty($searchword) || empty($searchpassage)){
                $usecount=0;
            }else {
                $usecount = substr_count($searchpassage, $searchword);
            }
            if($usecount){$stats->targetwords++;}
        }
        return $stats;
    }


    //clear AI data
    // we might do this if the user re-records
    public static function update_stat_aiaccuracy($attemptid, $accuracy) {
        global $DB;

        $stats = $DB->get_record(constants::M_STATSTABLE,array('attemptid'=>$attemptid));
        if($stats) {
            if($stats->aiaccuracy!==$accuracy) {
                $stats->aiaccuracy = $accuracy;
                $DB->update_record(constants::M_STATSTABLE, $stats);

                //update grades in this case
                self::autograde_attempt($attemptid,$stats);

            }
        }
    }

    public static function autograde_attempt($attemptid,$stats=false){
        global $DB;

        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, array('id'=>$attemptid));
        //if no attempt can be found, all is lost.
        if(!$attempt) {
            return;
        }
        //if this was human graded do not mess with it
        if($attempt->manualgraded){
            return;
        }
        //we will need our module instance too
        $moduleinstance=$DB->get_record(constants::M_TABLE,array('id'=>$attempt->solo));
        if(!$moduleinstance) {
            return;
        }
        //if autograding we should not be here
        if(!$moduleinstance->enableautograde){
            return;
        }

        //if its already been autograded ... why are we doing it again?
        //it can also be called from utils::update_stat_aiaccuracy
        // TO DO - prevent that happening
        /*
        if($attempt->autogradelog !== null && self::is_json($attempt->autogradelog)){
            return;
        }
        */

        //we might need AI table data too
        $airesult = $DB->get_record(constants::M_AITABLE,array('attemptid'=>$attemptid));

        //figure out the autograde
        $agoptions = json_decode($moduleinstance->autogradeoptions);

        //autograde log
        $ag_log=[];

        //basescore
        $basescore = $agoptions->gradebasescore;

        //wordcount value
        $thewordcount = $agoptions->gradewordcount== 'totalunique' ? $stats->uniquewords : $stats->words;
        $gradewordgoal = $moduleinstance->gradewordgoal;
        if($gradewordgoal<1){$gradewordgoal=1;}//what kind of person would set to 0 anyway?
        $wordratio = round(($thewordcount / $gradewordgoal),2);
        if($wordratio>1){$wordratio=1;}
        $ag_log[]="Words% = Total words($agoptions->gradewordcount)/ Words goal";
        $ag_log[]="Words% = $thewordcount / $gradewordgoal = " . (100 * $wordratio) . "%";

        //ratio to apply to start ratio
        switch($agoptions->graderatioitem){
            case 'accuracy':
                if($airesult){
                    $accuracyratio = $stats->aiaccuracy;
                }
                break;
            case '--':
            default:
                $accuracyratio = 100;
                $ag_log[]="Accuracy%: is not considered. Defaulting to 100%";
                break;

        }
        $ag_log[]="Accuracy% = % AI transcript matches manual transcript";
        if(!is_number($accuracyratio) && !is_numeric($accuracyratio)){
            $accuracyratio=100;
            $ag_log[]="Accuracy%: is not considered. Defaulting to 100%";
        }
         
         $ag_log[]="Accuracy% = " . $accuracyratio . "%";
        $accuracyratio=$accuracyratio*.01;

        //Ratio for relevance
        $relevanceratio=1;
        if(isset($agoptions->relevancegrade)){

            switch($agoptions->relevancegrade){
                case constants::RELEVANCE_QUESTION:
                    $margin = 10;
                    $ag_log[]="Relevance% = % Submission is on topic. Margin of $margin%";
                    //we assume a relevance of 80% (given the vagaries of AI) is a good answer,
                    $calculatedrelevance = round($stats->relevance / 100,2);
                    $relevanceratio= round(min($stats->relevance +$margin,100) / 100,2);
                    $ag_log[]="Relevance% = (" . (100 * $calculatedrelevance) . " + $margin )% => " . (100 * $relevanceratio) . "%";
                    break;

                case constants::RELEVANCE_MODEL:
                    $margin = 10;
                    $ag_log[]="Relevance% = % Submission is similar to model answer. Margin of $margin%";
                    //we assume a relevance of 85% (given the vagaries of AI) is a good answer,
                    $calculatedrelevance = round($stats->relevance / 100,2);
                    $relevanceratio= round(min($stats->relevance +$margin,100) / 100,2);
                    $ag_log[]="Relevance% = (" . (100 * $calculatedrelevance) . " + $margin )% => " . (100 * $relevanceratio) . "%";
                    break;

                default:
                    $ag_log[]="Relevance%: is not considered. Defaulting to 100%";
                    $ag_log[]="Relevance% = 100%";
                    $relevanceratio=1;
            }
        }
        

        //AI Grade
        if ($agoptions->aigradeitem==constants::AIGRADE_USE & $attempt->aigrade!==null) {
            $ag_log[]="AI Grade% = is calculated from the following guideline: \"$moduleinstance->markscheme\"";
            $ag_log[]="AI Grade% = is $attempt->aigrade%";
            $aigraderatio = $attempt->aigrade * .01;
        }else{
            $ag_log[]="AI Grade%: is not used. Defaulting to 100%";
            $ag_log[]="AI Grade% = 100%";
            $aigraderatio  = 1;
        }

        //Begin with wordratio
        $autograde= $wordratio;

        //apply basescore (default 100%)
        //eg word score = 80% and base score = 80%
        //.80 x 80 = 64
        $autograde = $autograde * $basescore;

        //apply use ratio (default aiaccuracy / speaking clarity same thing)
        //eg we reduce score according to accuracy. in this case 50%
        // 64 x 50 x .01 = 32
        $autograde = $autograde * $accuracyratio;

        //apply ai grade ratio
        $autograde = $autograde * $aigraderatio;

        //apply relevance ratio
        $autograde = $autograde * $relevanceratio;

        //apply bonuses
        $bonustotal=0;
        for($bonusno =1;$bonusno<=4;$bonusno++){
            switch($agoptions->{'bonus' . $bonusno}){

                case 'bigword':
                    $bonusscore=$stats->longwords;
                    break;
                case 'targetwordspoken':
                    $bonusscore=$stats->targetwords;
                    break;
                case 'sentence':
                    $bonusscore=$stats->turns;
                    break;
                case 'ideacount':
                    $bonusscore=$stats->ideacount;
                    break;
                case '--':
                default:
                    $bonusscore=0;
                    break;

            }
            if($bonusscore>0) {
                $ag_log[] = "Bonuses for " . $agoptions->{'bonus' . $bonusno}. ": +" . ($bonusscore * $agoptions->{'bonuspoints' . $bonusno});
            }
            $bonustotal+= $agoptions->{'bonuspoints' . $bonusno}  * $bonusscore;
            $autograde += $agoptions->{'bonuspoints' . $bonusno}  * $bonusscore;
        }


        //sanitize result
        $autograde = round($autograde,0);
        if($autograde > 100){
            $autograde=100;
        }else if($autograde < 0){
            $autograde=0;
        }

        //update attempts table
        $attempt->grade = round($autograde,0);
        $ag_log[]="Autograde = 100 * (Words[$wordratio] * Accuracy[$accuracyratio] * AI Grade[$aigraderatio])  + bonustotal[$bonustotal] => $attempt->grade%";
        $attempt->autogradelog=json_encode($ag_log);
        $DB->update_record(constants::M_ATTEMPTSTABLE, $attempt);

        //update gradebook
        $grade = new \stdClass();
        $grade->userid = $attempt->userid;
        $grade->rawgrade = $autograde;
        \solo_grade_item_update($moduleinstance,$grade);

        //fire an event to tell the world this happened
        $cm=\get_coursemodule_from_instance(constants::M_MODNAME,$moduleinstance->id);
        $context = \context_module::instance($cm->id);
        $event=\mod_solo\event\attempt_autograded::create_from_attempt($attempt,$context);
        $event->trigger();
    }

    //remove stats
    public static function remove_stats($attempt){
        global $DB;

        $oldstats =$DB->get_record(constants::M_STATSTABLE,
                array('solo'=>$attempt->solo,'attemptid'=>$attempt->id,'userid'=>$attempt->userid));
        if($oldstats) {
            $DB->delete_records(constants::M_STATSTABLE, array('id'=>$oldstats->id));
        }
    }

    //clear AI data
    // we might do this if the user re-records
    public static function clear_ai_data($activityid, $attemptid){
        global $DB;
        $record = new \stdClass();
        $record->id=$attemptid;
        $record->transcript='';
        $record->jsontranscript='';
        $record->vtttranscript='';

        //Remove AI data from attempts table
        $DB->update_record(constants::M_ATTEMPTSTABLE,$record);

        //update stats table
        self::update_stat_aiaccuracy($attemptid,-1);

        //Delete AI record
        $DB->delete_records(constants::M_AITABLE,array('attemptid'=>$attemptid, 'moduleid'=>$activityid));
    }

    //register an adhoc task to pick up transcripts
    public static function register_aws_task($activityid, $attemptid,$modulecontextid, $cmid){
        $s3_task = new \mod_solo\task\solo_s3_adhoc();
        $s3_task->set_component('mod_solo');

        $customdata = new \stdClass();
        $customdata->activityid = $activityid;
        $customdata->cmid = $cmid;
        $customdata->attemptid = $attemptid;
        $customdata->modulecontextid = $modulecontextid;
        $customdata->taskcreationtime = time();

        $s3_task->set_custom_data($customdata);
        // queue it
        \core\task\manager::queue_adhoc_task($s3_task);
        return true;
    }


    /*
   * Clean word of things that might prevent a match
    * i) lowercase it
    * ii) remove html characters
    * iii) replace any line ends with spaces (so we can "split" later)
    * iv) remove punctuation
   *
   */
    public static function cleanText($thetext){
        //lowercaseify
        $thetext=strtolower($thetext);

        //remove any html
        $thetext = strip_tags($thetext);

        //replace all line ends with empty strings
        $thetext = preg_replace('#\R+#', '', $thetext);

        //remove punctuation
        //see https://stackoverflow.com/questions/5233734/how-to-strip-punctuation-in-php
        // $thetext = preg_replace("#[[:punct:]]#", "", $thetext);
        //https://stackoverflow.com/questions/5689918/php-strip-punctuation
        $thetext = preg_replace("/[[:punct:]]+/", "", $thetext);

        //remove bad chars
        $b_open="“";
        $b_close="”";
        $b_sopen='‘';
        $b_sclose='’';
        $bads= array($b_open,$b_close,$b_sopen,$b_sclose);
        foreach($bads as $bad){
            $thetext=str_replace($bad,'',$thetext);
        }

        //remove double spaces
        //split on spaces into words
        $textbits = explode(' ',$thetext);
        //remove any empty elements
        $textbits = array_filter($textbits, function($value) { return $value !== ''; });
        $thetext = implode(' ',$textbits);
        return $thetext;
    }

    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    public static function curl_fetch($url,$postdata=false, $method='get')
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        if($method=='post') {
            $result = $curl->post($url, $postdata);
        }else{
            $result = $curl->get($url, $postdata);
        }
        return $result;
    }

    //This is called from the settings page and we do not want to make calls out to cloud.poodll.com on settings
    //page load, for performance and stability issues. So if the cache is empty and/or no token, we just show a
    //"refresh token" links
    public static function fetch_token_for_display($apiuser,$apisecret){
       global $CFG;

       //First check that we have an API id and secret
        //refresh token
        $refresh = \html_writer::link($CFG->wwwroot . constants::M_URL . '/refreshtoken.php',
                get_string('refreshtoken',constants::M_COMPONENT)) . '<br>';

        $message = '';
        $apiuser = self::super_trim($apiuser);
        $apisecret = self::super_trim($apisecret);
        if(empty($apiuser)){
           $message .= get_string('noapiuser',constants::M_COMPONENT) . '<br>';
       }
        if(empty($apisecret)){
            $message .= get_string('noapisecret',constants::M_COMPONENT);
        }

        if(!empty($message)){
            return $refresh . $message;
        }

        //Fetch from cache and process the results and display
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //if we have no token object the creds were wrong ... or something
        if(!($tokenobject)){
            $message = get_string('notokenincache',constants::M_COMPONENT);
            //if we have an object but its no good, creds werer wrong ..or something
        }elseif(!property_exists($tokenobject,'token') || empty($tokenobject->token)){
            $message = get_string('credentialsinvalid',constants::M_COMPONENT);
        //if we do not have subs, then we are on a very old token or something is wrong, just get out of here.
        }elseif(!property_exists($tokenobject,'subs')){
            $message = 'No subscriptions found at all';
        }
        if(!empty($message)){
            return $refresh . $message;
        }

        //we have enough info to display a report. Lets go.
        foreach ($tokenobject->subs as $sub){
            $sub->expiredate = date('d/m/Y',$sub->expiredate);
            $message .= get_string('displaysubs',constants::M_COMPONENT, $sub) . '<br>';
        }
        //Is app authorised
        if(in_array(constants::M_COMPONENT,$tokenobject->apps)){
            $message .= get_string('appauthorised',constants::M_COMPONENT) . '<br>';
        }else{
            $message .= get_string('appnotauthorised',constants::M_COMPONENT) . '<br>';
        }

        return $refresh . $message;

    }

    //We need a Poodll token to make all this recording and transcripts happen
    public static function fetch_token($apiuser, $apisecret, $force=false)
    {

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');
        $tokenuser = $cache->get('recentpoodlluser');
        $apiuser = self::super_trim($apiuser);
        $apisecret = self::super_trim($apisecret);
        $now = time();

        //if we got a token and its less than expiry time
        // use the cached one
        if($tokenobject && $tokenuser && $tokenuser==$apiuser && !$force){
            if($tokenobject->validuntil == 0 || $tokenobject->validuntil > $now){
               // $hoursleft= ($tokenobject->validuntil-$now) / (60*60);
                return $tokenobject->token;
            }
        }

        // Send the request & save response to $resp

        $token_url = self::CLOUDPOODLL . "/local/cpapi/poodlltoken.php";
        $postdata = array(
            'username' => $apiuser,
            'password' => $apisecret,
            'service'=>'cloud_poodll'
        );
        $token_response = self::curl_fetch($token_url,$postdata);
        if ($token_response) {
            $resp_object = json_decode($token_response);
            if($resp_object && property_exists($resp_object,'token')) {
                $token = $resp_object->token;
                //store the expiry timestamp and adjust it for diffs between our server times
                if($resp_object->validuntil) {
                    $validuntil = $resp_object->validuntil - ($resp_object->poodlltime - $now);
                    //we refresh one hour out, to prevent any overlap
                    $validuntil = $validuntil - (1 * HOURSECS);
                }else{
                    $validuntil = 0;
                }

                $tillrefreshhoursleft= ($validuntil-$now) / (60*60);


                //cache the token
                $tokenobject = new \stdClass();
                $tokenobject->token = $token;
                $tokenobject->validuntil = $validuntil;
                $tokenobject->subs=false;
                $tokenobject->apps=false;
                $tokenobject->sites=false;
                if(property_exists($resp_object,'subs')){
                    $tokenobject->subs = $resp_object->subs;
                }
                if(property_exists($resp_object,'apps')){
                    $tokenobject->apps = $resp_object->apps;
                }
                if(property_exists($resp_object,'sites')){
                    $tokenobject->sites = $resp_object->sites;
                }

                $cache->set('recentpoodlltoken', $tokenobject);
                $cache->set('recentpoodlluser', $apiuser);

            }else{
                $token = '';
                if($resp_object && property_exists($resp_object,'error')) {
                    //ERROR = $resp_object->error
                }
            }
        }else{
            $token='';
        }
        return $token;
    }

    //check token and tokenobject(from cache)
    //return error message or blank if its all ok
    public static function fetch_token_error($token){
        global $CFG;

        //check token authenticated
        if(empty($token)) {
            $message = get_string('novalidcredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $message;
        }

        // Fetch from cache and process the results and display.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //we should not get here if there is no token, but lets gracefully die, [v unlikely]
        if (!($tokenobject)) {
            $message = get_string('notokenincache', constants::M_COMPONENT);
            return $message;
        }

        //We have an object but its no good, creds were wrong ..or something. [v unlikely]
        if (!property_exists($tokenobject, 'token') || empty($tokenobject->token)) {
            $message = get_string('credentialsinvalid', constants::M_COMPONENT);
            return $message;
        }
        // if we do not have subs.
        if (!property_exists($tokenobject, 'subs')) {
            $message = get_string('nosubscriptions', constants::M_COMPONENT);
            return $message;
        }
        // Is app authorised?
        if (!property_exists($tokenobject, 'apps') || !in_array(constants::M_COMPONENT, $tokenobject->apps)) {
            $message = get_string('appnotauthorised', constants::M_COMPONENT);
            return $message;
        }

        //just return empty if there is no error.
        return '';
    }


    public static function fetch_media_urls($contextid, $filearea,$itemid){
        //get question audio div (not so easy)
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,  constants::M_COMPONENT,$filearea,$itemid);
        $urls=[];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if($filename=='.'){continue;}
            $filepath = '/';
            $mediaurl = \moodle_url::make_pluginfile_url($contextid, constants::M_COMPONENT,
                    $filearea, $itemid,
                    $filepath, $filename);
            $urls[]= $mediaurl->__toString();

        }
        return $urls;
    }

    public static function fetch_duration_from_transcript($jsontranscript){
        $transcript = json_decode($jsontranscript);
        $titems=$transcript->results->items;
        $twords=array();
        foreach($titems as $titem){
            if($titem->type == 'pronunciation'){
                $twords[] = $titem;
            }
        }
        $lastindex = count($twords);
        if($lastindex>0){
            return $twords[$lastindex-1]->end_time;
        }else{
            return 0;
        }
    }

    public static function get_skin_options(){
        $rec_options = array( constants::SKIN_PLAIN => get_string("skinplain", constants::M_COMPONENT),
                constants::SKIN_BMR => get_string("skinbmr", constants::M_COMPONENT),
                constants::SKIN_123 => get_string("skin123", constants::M_COMPONENT),
                constants::SKIN_FRESH => get_string("skinfresh", constants::M_COMPONENT),
                constants::SKIN_ONCE => get_string("skinonce", constants::M_COMPONENT),
                constants::SKIN_UPLOAD => get_string("skinupload", constants::M_COMPONENT),
                constants::SKIN_SOLO => get_string("skinsolo", constants::M_COMPONENT));
        return $rec_options;
    }

    public static function get_recorders_options(){
        $rec_options = array( constants::REC_AUDIO => get_string("recorderaudio", constants::M_COMPONENT),
               // constants::REC_VIDEO  => get_string("recordervideo", constants::M_COMPONENT)
        );
        return $rec_options;
    }

    public static function is_textonlysubmission($moduleinstance){
        if((int)$moduleinstance->step2!==constants::M_STEP_RECORD &&
            (int)$moduleinstance->step3!==constants::M_STEP_RECORD &&
            (int)$moduleinstance->step4!==constants::M_STEP_RECORD){
            return true;
        }else{
            return false;
        }
    }

    public static function fetch_total_step_count($moduleinstance,$context){
        for($step=2;$step<6;$step++){

            switch($moduleinstance->{'step' . $step}){
                case constants::STEP_NONE:
                    return $step-1;
                case constants::STEP_MODEL:
                    if(self::has_modelanswer_media($moduleinstance, $context)===false){
                        return $step-1;
                    }
                    break;
            }
        }
        //This would mean all steps are set
        return 5;
    }

    public static function has_modelanswer_media($moduleinstance, $context){
        if(!empty(self::super_trim($moduleinstance->modelytid))) {return true;}
        if(!empty(self::super_trim($moduleinstance->modeliframe))) {return true;}
        if(!empty(self::super_trim($moduleinstance->modeltts))) {return true;}
        $itemid=0;
        $filearea='modelmedia';
        $mediaurls = utils::fetch_media_urls($context->id,$filearea,$itemid);
        if($mediaurls && count($mediaurls)>0) {
            return true;
        }
        return false;
    }

    public static function get_steplabel($step){
        switch($step){
            case constants::STEP_PREPARE:
                return get_string('userselections', constants::M_COMPONENT);
            case constants::STEP_MEDIARECORDING:
                return get_string('audiorecording', constants::M_COMPONENT);
            case constants::STEP_SELFTRANSCRIBE:
                return get_string('selftranscribe', constants::M_COMPONENT);
            case constants::STEP_MODEL:
                return get_string('modelanswer', constants::M_COMPONENT);
            default:
                return '';
        }
    }

    public static function get_grade_element_options(){
        $options = [];
        for($x=0;$x<101;$x++){
            $options[$x]=$x;
        }
        return $options;
    }

    public static function get_suggestionsgrade_options(){
        return array(
            constants::SUGGEST_GRADE_NONE => get_string("suggestionsgrade_none",constants::M_COMPONENT),
            constants::SUGGEST_GRADE_USE => get_string("suggestionsgrade_use",constants::M_COMPONENT)
        );
    }

    public static function get_word_count_options(){
        return array(
                "totalunique" => get_string("totalunique",constants::M_COMPONENT),
                "totalwords" => get_string("totalwords",constants::M_COMPONENT),
        );
    }

  public static function get_region_options(){
      return array(
        "useast1" => get_string("useast1",constants::M_COMPONENT),
          "tokyo" => get_string("tokyo",constants::M_COMPONENT),
          "sydney" => get_string("sydney",constants::M_COMPONENT),
          "dublin" => get_string("dublin",constants::M_COMPONENT),
          "ottawa" => get_string("ottawa",constants::M_COMPONENT),
          "frankfurt" => get_string("frankfurt",constants::M_COMPONENT),
          "london" => get_string("london",constants::M_COMPONENT),
          "saopaulo" => get_string("saopaulo",constants::M_COMPONENT),
          "singapore" => get_string("singapore",constants::M_COMPONENT),
          "mumbai" => get_string("mumbai",constants::M_COMPONENT),
           "capetown" => get_string("capetown",constants::M_COMPONENT),
          "bahrain" => get_string("bahrain",constants::M_COMPONENT)
      );
  }

  public static function get_expiredays_options(){
      return array(
          "1"=>"1",
          "3"=>"3",
          "7"=>"7",
          "30"=>"30",
          "90"=>"90",
          "180"=>"180",
          "365"=>"365",
          "730"=>"730",
          "9999"=>get_string('forever',constants::M_COMPONENT)
      );
  }


    public static function fetch_options_transcribers() {
        $options =
                array(constants::TRANSCRIBER_OPEN => get_string("transcriber_open", constants::M_COMPONENT));
        return $options;
    }

    public static function get_layout_options() {
        return array(
            constants::M_LAYOUT_NARROW => get_string('layout_narrow', constants::M_COMPONENT),
            constants::M_LAYOUT_STANDARD => get_string('layout_standard', constants::M_COMPONENT)
        );

    }

    public static function get_show_options(){
        return array(
            0 => get_string('showopts_no', constants::M_COMPONENT),
            1 => get_string('showopts_yes', constants::M_COMPONENT)
        );
    }

    public static function get_ttsspeed_options() {
        return array(
            constants::TTSSPEED_MEDIUM => get_string("mediumspeed", constants::M_COMPONENT),
            constants::TTSSPEED_SLOW => get_string("slowspeed", constants::M_COMPONENT),
            constants::TTSSPEED_XSLOW => get_string("extraslowspeed", constants::M_COMPONENT)
        );
    }
    public static function get_lang_options() {

        //we decided to limit this to what we can process and use langtool for:
        //https://dev.languagetool.org/languages

        return array(
                constants::M_LANG_ARAE => get_string('ar-ae', constants::M_COMPONENT),
                constants::M_LANG_ARSA => get_string('ar-sa', constants::M_COMPONENT),
                constants::M_LANG_EUES => get_string('eu-es',constants::M_COMPONENT),
                constants::M_LANG_BGBG => get_string('bg-bg', constants::M_COMPONENT),
                constants::M_LANG_HRHR => get_string('hr-hr', constants::M_COMPONENT),
                constants::M_LANG_ZHCN => get_string('zh-cn', constants::M_COMPONENT),
                constants::M_LANG_CSCZ => get_string('cs-cz', constants::M_COMPONENT),
                constants::M_LANG_DADK => get_string('da-dk', constants::M_COMPONENT),
                constants::M_LANG_NLNL => get_string('nl-nl', constants::M_COMPONENT),
                constants::M_LANG_NLBE => get_string('nl-be', constants::M_COMPONENT),
                constants::M_LANG_ENUS => get_string('en-us', constants::M_COMPONENT),
                constants::M_LANG_ENGB => get_string('en-gb', constants::M_COMPONENT),
                constants::M_LANG_ENAU => get_string('en-au', constants::M_COMPONENT),
                constants::M_LANG_ENIN => get_string('en-in', constants::M_COMPONENT),
                constants::M_LANG_ENIE => get_string('en-ie', constants::M_COMPONENT),
                constants::M_LANG_ENNZ => get_string('en-nz', constants::M_COMPONENT),
                constants::M_LANG_ENZA => get_string('en-za', constants::M_COMPONENT),
                constants::M_LANG_ENWL => get_string('en-wl', constants::M_COMPONENT),
                constants::M_LANG_ENAB => get_string('en-ab', constants::M_COMPONENT),
                constants::M_LANG_FAIR => get_string('fa-ir', constants::M_COMPONENT),
                constants::M_LANG_FILPH => get_string('fil-ph', constants::M_COMPONENT),
                constants::M_LANG_FIFI => get_string('fi-fi',constants::M_COMPONENT),
                constants::M_LANG_FRCA => get_string('fr-ca', constants::M_COMPONENT),
                constants::M_LANG_FRFR => get_string('fr-fr', constants::M_COMPONENT),
                constants::M_LANG_DEDE => get_string('de-de', constants::M_COMPONENT),
                constants::M_LANG_DECH => get_string('de-ch', constants::M_COMPONENT),
                constants::M_LANG_DEAT => get_string('de-at', constants::M_COMPONENT),
                constants::M_LANG_ELGR => get_string('el-gr', constants::M_COMPONENT),
                constants::M_LANG_HIIN => get_string('hi-in', constants::M_COMPONENT),
                constants::M_LANG_HEIL => get_string('he-il', constants::M_COMPONENT),
                constants::M_LANG_HUHU => get_string('hu-hu',constants::M_COMPONENT),
                constants::M_LANG_IDID => get_string('id-id', constants::M_COMPONENT),
                constants::M_LANG_ISIS => get_string('is-is', constants::M_COMPONENT),
                constants::M_LANG_ITIT => get_string('it-it', constants::M_COMPONENT),
                constants::M_LANG_JAJP => get_string('ja-jp', constants::M_COMPONENT),
                constants::M_LANG_KOKR => get_string('ko-kr', constants::M_COMPONENT),
                constants::M_LANG_LTLT => get_string('lt-lt', constants::M_COMPONENT),
                constants::M_LANG_LVLV => get_string('lv-lv', constants::M_COMPONENT),
                constants::M_LANG_MINZ => get_string('mi-nz',constants::M_COMPONENT),
                constants::M_LANG_MSMY => get_string('ms-my', constants::M_COMPONENT),
                constants::M_LANG_MKMK => get_string('mk-mk', constants::M_COMPONENT),
                constants::M_LANG_NONO => get_string('no-no', constants::M_COMPONENT),
                constants::M_LANG_PLPL => get_string('pl-pl', constants::M_COMPONENT),
                constants::M_LANG_PTBR => get_string('pt-br', constants::M_COMPONENT),
                constants::M_LANG_PTPT => get_string('pt-pt', constants::M_COMPONENT),
                constants::M_LANG_RORO => get_string('ro-ro', constants::M_COMPONENT),
                constants::M_LANG_RURU => get_string('ru-ru', constants::M_COMPONENT),
                constants::M_LANG_ESUS => get_string('es-us', constants::M_COMPONENT),
                constants::M_LANG_ESES => get_string('es-es', constants::M_COMPONENT),
                constants::M_LANG_SKSK => get_string('sk-sk', constants::M_COMPONENT),
                constants::M_LANG_SLSI => get_string('sl-si', constants::M_COMPONENT),
                constants::M_LANG_SRRS => get_string('sr-rs', constants::M_COMPONENT),
                constants::M_LANG_SVSE => get_string('sv-se', constants::M_COMPONENT),
                constants::M_LANG_TAIN => get_string('ta-in', constants::M_COMPONENT),
                constants::M_LANG_TEIN => get_string('te-in', constants::M_COMPONENT),
                constants::M_LANG_TRTR => get_string('tr-tr', constants::M_COMPONENT),
                constants::M_LANG_UKUA => get_string('uk-ua',constants::M_COMPONENT),


        );
    }

    public static function get_aifeedback_lang_options() {
        $otherlangs = array(
            constants::M_LANG_ASIN => get_string('as-in', constants::M_COMPONENT),
            constants::M_LANG_AWAW => get_string('aw-aw', constants::M_COMPONENT),
            constants::M_LANG_BNIN => get_string('bn-in', constants::M_COMPONENT),
            constants::M_LANG_BHIN => get_string('bh-in', constants::M_COMPONENT),
            constants::M_LANG_GUIN => get_string('gu-in', constants::M_COMPONENT),
            constants::M_LANG_KNIN => get_string('kn-in', constants::M_COMPONENT),
            constants::M_LANG_MLIN => get_string('ml-in', constants::M_COMPONENT),
            constants::M_LANG_MRIN => get_string('mr-in', constants::M_COMPONENT),
            constants::M_LANG_MWIN => get_string('mw-in', constants::M_COMPONENT),
            constants::M_LANG_ORIN => get_string('or-in', constants::M_COMPONENT),
            constants::M_LANG_PAING => get_string('pa-ing', constants::M_COMPONENT),
            constants::M_LANG_PAIN => get_string('pa-in', constants::M_COMPONENT),
            constants::M_LANG_SAIN => get_string('sa-in', constants::M_COMPONENT),
            constants::M_LANG_URIN => get_string('ur-in', constants::M_COMPONENT),
        );
        return self::get_lang_options() + $otherlangs;
    }

    public static function fetch_topic_levels(){
        return array(
                constants::M_TOPICLEVEL_COURSE=>get_string('topiclevelcourse',constants::M_COMPONENT),
                constants::M_TOPICLEVEL_CUSTOM=>get_string('topiclevelcustom',constants::M_COMPONENT)
        );

    }

    public static function get_conversationlength_options(){
        return array(
                '0'=>get_string('notimelimit',constants::M_COMPONENT),
                '1'=>get_string('xminutes',constants::M_COMPONENT,1),
                '2'=>get_string('xminutes',constants::M_COMPONENT,2),
                '3'=>get_string('xminutes',constants::M_COMPONENT,3),
                '4'=>get_string('xminutes',constants::M_COMPONENT,4),
                '5'=>get_string('xminutes',constants::M_COMPONENT,5),
                '6'=>get_string('xminutes',constants::M_COMPONENT,6),
                '7'=>get_string('xminutes',constants::M_COMPONENT,7),
                '8'=>get_string('xminutes',constants::M_COMPONENT,8),
                '9'=>get_string('xminutes',constants::M_COMPONENT,9),
                '10'=>get_string('xminutes',constants::M_COMPONENT,10)
        );

    }

    public static function fetch_fonticon($fonticon, $size='fa-2x'){
        if(empty($fonticon)){return '';}
        if(strlen($fonticon)<5){return $fonticon;}
        return '<i class="fa ' . $fonticon . ' ' . $size . '"></i>';
    }

    //grading stuff
    public static function fetch_bonus_grade_options(){
        return array(
                '--'=>'--',
                'bigword'=>get_string('bigword',constants::M_COMPONENT),
                //'spellingmistake'=>get_string('spellingmistake',constants::M_COMPONENT),
                //'grammarmistake'=>get_string('grammarmistake',constants::M_COMPONENT),
                'targetwordspoken'=>get_string('targetwordspoken',constants::M_COMPONENT),
                'sentence'=>get_string('sentence',constants::M_COMPONENT),
                'ideacount'=>get_string('ideacount',constants::M_COMPONENT)
        );
    }

    public static function fetch_factor_options(){
        return array(
            '*'=>'*',
            '+'=>'+'
        );
    }

    public static function fetch_ratio_grade_options(){
        return array(
                '--'=>'--',
             //   'spelling'=>get_string('stats_autospellscore',constants::M_COMPONENT),
             //   'grammar'=>get_string('stats_autogrammarscore',constants::M_COMPONENT),
                'accuracy'=>get_string('stats_aiaccuracy',constants::M_COMPONENT),
        );
    }

    public static function fetch_ai_grade_options(){
        return array(
            constants::AIGRADE_NONE=>'--',
            constants::AIGRADE_USE=>get_string('stats_aigrade',constants::M_COMPONENT),
        );
    }

    public static function fetch_relevance_options(){
        return array(
            constants::RELEVANCE_NONE=>'--',
            constants::RELEVANCE_MODEL=>get_string('relevance_model',constants::M_COMPONENT),
            constants::RELEVANCE_QUESTION=>get_string('relevance_question',constants::M_COMPONENT)
        );
    }

    public static function get_relevancegrade_options(){
        return array(
            constants::RELEVANCE_NONE => get_string("relevance_none",constants::M_COMPONENT),
            constants::RELEVANCE_BROAD => get_string("relevance_broad",constants::M_COMPONENT),
            constants::RELEVANCE_QUITE => get_string("relevance_quite",constants::M_COMPONENT),
            constants::RELEVANCE_VERY => get_string("relevance_very",constants::M_COMPONENT),
            constants::RELEVANCE_EXTREME => get_string("relevance_extreme",constants::M_COMPONENT)
        );
    }

    /*
     * 2023/05/13 To DO - delete this function
     */
    public static function fetch_spellingerrors($stats, $transcript) {
        $spellingerrors=array();
        $usetranscript = diff::cleanText($transcript);
        //sanity check
        if(empty($usetranscript) ||!self::is_json($stats->autospell)){
            return $spellingerrors;
        }

        //return errors
        $spellobj = json_decode($stats->autospell);
        if($spellobj->status) {
            $spellarray = $spellobj->data->results;
            $wordarray = explode(' ', $usetranscript);
            for($index=0;$index<count($spellarray); $index++) {
                if (!$spellarray[$index]) {
                    $spellingerrors[]=$wordarray[$index];
                }
            }
        }
        return $spellingerrors;

    }

    /*
     * 2023/05/13 To DO - delete this function
     */
    public static function fetch_grammarerrors($stats, $transcript) {
        $usetranscript = diff::cleanText($transcript);
        //sanity check
        if(empty($usetranscript) ||!self::is_json($stats->autogrammar)){
            return [];
        }

        //return errors
        $grammarobj = json_decode($stats->autogrammar);
        return $grammarobj->matches;

    }


    /**
     * fetch a summary of rubric grade for thje student
     *
     * @param \context_module| $modulecontext
     * @param \stdClass| $moduleinstance
     * @param \stdClass| $attempt
     * @return string rubric results
     */
    public static function display_studentgrade($modulecontext, $moduleinstance, $attempt, $gradinginfo, $starrating=false){
        global  $PAGE;

        $gradingitem = null;
        $gradebookgrade = null;
        if (isset($gradinginfo->items[0])) {
            $gradingitem = $gradinginfo->items[0];
            $gradebookgrade = $gradingitem->grades[$attempt->userid];
        }

        $gradefordisplay = null;
        $gradeddate = null;
        $grader = null;
        $gradingmanager = \get_grading_manager($modulecontext, constants::M_COMPONENT, 'solo');
        $gradingdisabled = false;
        $gradeid =$attempt->id;

        $method = $gradingmanager->get_active_method();
        if($method=='rubric') {
            if ($controller = $gradingmanager->get_active_controller()) {
                $menu = \mod_pchat\utils::make_grades_menu($moduleinstance->grade);
                $controller->set_grade_range($menu, $moduleinstance->grade > 0);
                $gradefordisplay = $controller->render_grade($PAGE,
                        $gradeid,
                        $gradingitem,
                        $gradebookgrade->str_long_grade,
                        $gradingdisabled);
            } else {
                $gradefordisplay = 'no grade available';
            }
        }else{
            //star rating
            if($starrating){
                $onlyhalf=false;
                switch(true){
                    case $attempt->grade > 89:
                        $message = get_string('rating_excellent',constants::M_COMPONENT);
                        $stars=5;
                        break;
                    case $attempt->grade > 79:
                        $message = get_string('rating_excellent',constants::M_COMPONENT);
                        $onlyhalf=true;
                        $stars=5;
                        break;
                    case $attempt->grade > 69:
                        $message = get_string('rating_verygood',constants::M_COMPONENT);
                        $stars=4;
                        break;
                    case $attempt->grade > 59:
                        $message = get_string('rating_verygood',constants::M_COMPONENT);
                        $onlyhalf=true;
                        $stars=4;
                        break;
                    case $attempt->grade > 49:
                        $message = get_string('rating_good',constants::M_COMPONENT);
                        $stars=3;
                        break;
                    case $attempt->grade > 39:
                        $message = get_string('rating_good',constants::M_COMPONENT);
                        $onlyhalf=true;
                        $stars=3;
                        break;
                    case $attempt->grade > 29:
                        $message = get_string('rating_fair',constants::M_COMPONENT);
                        $stars=2;
                        break;
                    case $attempt->grade > 19:
                        $message = get_string('rating_fair',constants::M_COMPONENT);
                        $onlyhalf=true;
                        $stars=2;
                        break;
                    case $attempt->grade > 9:
                        $message = get_string('rating_fair',constants::M_COMPONENT);
                        $stars=1;
                        break;            
                    default:
                        $message = get_string('rating_poor',constants::M_COMPONENT);
                        $onlyhalf=true;
                        $stars=1;
                }
                $displaystars ='';
                for($i=0;$i<5;$i++){
                    if($i<$stars){
                        if($onlyhalf && $i==$stars-1){
                            $displaystars .= '<div class="mod_solo_reports_star_half"></div>';
                        }else{
                            $displaystars .= '<div class="mod_solo_reports_star_on"></div>';
                        }
                    }else{
                        $displaystars .= '<div class="mod_solo_reports_star_off"></div>';
                    }
                }
                $gradefordisplay = \html_writer::div(
                    \html_writer::div($message . '<div class="mod_solo_reports_stars_content">' . $displaystars . '</div>','mod_solo_evalstars'),
                    'mod_solo_reports_stars_container'
                );
            }else {
                $gradefordisplay = get_string('gradelabel', constants::M_COMPONENT, $attempt->grade);
            }
        }
        return $gradefordisplay;
    }



    /**
     * Get an instance of a grading form if advanced grading is enabled.
     * This is specific to the assignment, marker and student.
     *
     * @param int $userid - The student userid
     * @param stdClass|false $grade - The grade record
     * @param bool $gradingdisabled
     * @return mixed gradingform_instance|null $gradinginstance
     */
    public static function get_grading_instance($gradeid, $gradingdisabled,$moduleinstance, $context) {
        global $CFG, $USER;

        $raterid = $USER->id;

        $grademenu = \mod_pchat\utils::make_grades_menu($moduleinstance->grade);
        $allowgradedecimals = $moduleinstance->grade > 0;

        $advancedgradingwarning = false;

        //necessary for M3.3
        require_once($CFG->dirroot .'/grade/grading/lib.php');

        $gradingmanager = \get_grading_manager($context, constants::M_COMPONENT, 'solo');
        $gradinginstance = null;
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if ($gradeid && $gradeid > 0) {
                    $itemid = $gradeid;
                }
                if ($gradingdisabled && $itemid) {
                    $gradinginstance = $controller->get_current_instance($raterid, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $gradinginstance = $controller->get_or_create_instance($instanceid,
                        $raterid,
                        $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }
        if ($gradinginstance) {
            $gradinginstance->get_controller()->set_grade_range($grademenu, $allowgradedecimals);
        }
        return $gradinginstance;
    }

    //see if this is truly json or some error
    public static function is_json($string) {
        if (!$string) {
            return false;
        }
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public static function fetch_options_steps(){
        $ret = array(constants::M_STEP_NONE=>get_string('step_none',constants::M_COMPONENT),
            constants::M_STEP_RECORD=>get_string('step_record',constants::M_COMPONENT),
            constants::M_STEP_TRANSCRIBE=>get_string('step_transcribe',constants::M_COMPONENT),
            constants::M_STEP_MODEL=>get_string('step_model',constants::M_COMPONENT));
        return $ret;
    }

    public static function fetch_options_sequences(){
        $ret = array();
       // $ret[constants::M_SEQ_PRTM] = get_string('seq_PRTM',constants::M_COMPONENT);
        $ret[constants::M_SEQ_PRM] =get_string('seq_PRM',constants::M_COMPONENT);
        $ret[constants::M_SEQ_PTRM]=get_string('seq_PTRM',constants::M_COMPONENT);
        $ret[constants::M_SEQ_PTM]=get_string('seq_PTM',constants::M_COMPONENT);
       // $ret[constants::M_SEQ_PRMT]=get_string('seq_PRMT',constants::M_COMPONENT);
        return $ret;
    }
    public static function fetch_step_no($moduleinstance, $type){
        $steps = [2,3,4,5];
        foreach($steps as $step){
            if($moduleinstance->{'step' . $step}==$type){
                return $step;
            }
        }
        return false;
    }

    public static function get_tts_voices($langcode='en-US',$showall=true) {
        $alllang = array(
            constants::M_LANG_ARAE => ['Hala'=>'Hala','Zayd'=>'Zayd'],
            constants::M_LANG_ARSA => ['Zeina'=>'Zeina','ar-XA-Wavenet-B'=>'Amir_g','ar-XA-Wavenet-A'=>'Salma_g'],
            constants::M_LANG_BGBG => ['bg-BG-Standard-A' => 'Mila_g'],//nikolai
            constants::M_LANG_HRHR => ['hr-HR-Whisper-alloy'=>'Marko','hr-HR-Whisper-shimmer'=>'Ivana'],
            constants::M_LANG_CSCZ => ['cs-CZ-Wavenet-A' => 'Zuzana_g', 'cs-CZ-Standard-A' => 'Karolina_g'],
            constants::M_LANG_ZHCN => ['Zhiyu'=>'Zhiyu'],
            constants::M_LANG_DADK => ["Naja"=>"Naja","Mads"=>"Mads"],
            constants::M_LANG_NLNL => ["Ruben"=>"Ruben","Lotte"=>"Lotte","Laura"=>"Laura"],
            constants::M_LANG_NLBE => ["nl-BE-Wavenet-B"=>"Marc_g","nl-BE-Wavenet-A"=>"Marie_g"],
            //constants::M_LANG_DECH => [],
            constants::M_LANG_ENUS => ['Joey'=>'Joey','Justin'=>'Justin','Kevin'=>'Kevin','Matthew'=>'Matthew','Ivy'=>'Ivy',
                'Joanna'=>'Joanna','Kendra'=>'Kendra','Kimberly'=>'Kimberly','Salli'=>'Salli',
                'en-US-Whisper-alloy'=>'Ricky','en-US-Whisper-onyx'=>'Ed','en-US-Whisper-nova'=>'Tiffany','en-US-Whisper-shimmer'=>'Tammy'],
            constants::M_LANG_ENGB => ['Brian'=>'Brian','Amy'=>'Amy', 'Emma'=>'Emma'],
            constants::M_LANG_ENAU => ['Russell'=>'Russell','Nicole'=>'Nicole','Olivia'=>'Olivia'],
            constants::M_LANG_ENNZ => ['Aria'=>'Aria'],
            constants::M_LANG_ENZA => ['Ayanda'=>'Ayanda'],
            constants::M_LANG_ENIN => ['Aditi'=>'Aditi', 'Raveena'=>'Raveena', 'Kajal'=>'Kajal'],
            // constants::M_LANG_ENIE => [],
            constants::M_LANG_ENWL => ["Geraint"=>"Geraint"],
            // constants::M_LANG_ENAB => [],

            constants::M_LANG_FILPH => ['fil-PH-Wavenet-A'=>'Darna_g','fil-PH-Wavenet-B'=>'Reyna_g','fil-PH-Wavenet-C'=>'Bayani_g','fil-PH-Wavenet-D'=>'Ernesto_g'],
            constants::M_LANG_FIFI => ['Suvi'=>'Suvi','fi-FI-Wavenet-A'=>'Kaarina_g'],
            //constants::M_LANG_FAIR => [],
            constants::M_LANG_FRCA => ['Chantal'=>'Chantal', 'Gabrielle'=>'Gabrielle','Liam'=>'Liam'],
            constants::M_LANG_ELGR => ['el-GR-Wavenet-A' => 'Sophia_g', 'el-GR-Standard-A' => 'Isabella_g'],
            constants::M_LANG_FRFR => ['Mathieu'=>'Mathieu','Celine'=>'Celine', 'Lea'=>'Lea'],
            constants::M_LANG_DEDE => ['Hans'=>'Hans','Marlene'=>'Marlene', 'Vicki'=>'Vicki','Daniel'=>'Daniel'],
            constants::M_LANG_DEAT => ['Hannah'=>'Hannah'],
            constants::M_LANG_HEIL => ['he-IL-Wavenet-A'=>'Sarah_g','he-IL-Wavenet-B'=>'Noah_g'],
            constants::M_LANG_HIIN => ["Aditi"=>"Aditi"],
            constants::M_LANG_HUHU => ['hu-HU-Wavenet-A'=>'Eszter_g'],
            constants::M_LANG_ISIS => ['Dora' => 'Dora', 'Karl' => 'Karl'],
            constants::M_LANG_IDID => ['id-ID-Wavenet-A'=>'Guntur_g','id-ID-Wavenet-B'=>'Bhoomik_g'],
            constants::M_LANG_ITIT => ['Carla'=>'Carla',  'Bianca'=>'Bianca', 'Giorgio'=>'Giorgio'],
            constants::M_LANG_JAJP => ['Takumi'=>'Takumi','Mizuki'=>'Mizuki','Kazuha'=>'Kazuha','Tomoko'=>'Tomoko'],
            constants::M_LANG_KOKR => ['Seoyeon'=>'Seoyeon'],
            constants::M_LANG_LVLV => ['lv-LV-Standard-A' => 'Janis_g'],
            constants::M_LANG_LTLT => ['lt-LT-Standard-A' => 'Matas_g'],
            constants::M_LANG_MKMK => ['mk-MK-Whisper-alloy'=>'Trajko','mk-MK-Whisper-shimmer'=>'Marija'],
            constants::M_LANG_MSMY => ['ms-MY-Whisper-alloy'=>'Afsah','ms-MY-Whisper-shimmer'=>'Siti'],
            constants::M_LANG_MINZ => ['mi-NZ-Whisper-alloy'=>'Tane','mi-NZ-Whisper-shimmer'=>'Aroha'],
            constants::M_LANG_NONO => ['Liv'=>'Liv','Ida'=>'Ida','nb-NO-Wavenet-B'=>'Lars_g','nb-NO-Wavenet-A'=>'Hedda_g','nb-NO-Wavenet-D'=>'Anders_g'],
            constants::M_LANG_PLPL => ['Ewa'=>'Ewa','Maja'=>'Maja','Jacek'=>'Jacek','Jan'=>'Jan'],
            constants::M_LANG_PTBR => ['Ricardo'=>'Ricardo', 'Vitoria'=>'Vitoria','Camila'=>'Camila'],
            constants::M_LANG_PTPT => ["Ines"=>"Ines",'Cristiano'=>'Cristiano'],
            constants::M_LANG_RORO => ['Carmen'=>'Carmen','ro-RO-Wavenet-A'=>'Sorina_g'],
            constants::M_LANG_RURU => ["Tatyana"=>"Tatyana","Maxim"=>"Maxim"],
            constants::M_LANG_ESUS => ['Miguel'=>'Miguel','Penelope'=>'Penelope','Lupe'=>'Lupe'],
            constants::M_LANG_ESES => [ 'Enrique'=>'Enrique', 'Conchita'=>'Conchita', 'Lucia'=>'Lucia'],
            constants::M_LANG_SVSE => ['Astrid'=>'Astrid','Elin'=>'Elin'],
            constants::M_LANG_SKSK => ['sk-SK-Wavenet-A' => 'Laura_g', 'sk-SK-Standard-A' => 'Natalia_g'],
            constants::M_LANG_SLSI => ['sl-SI-Whisper-alloy'=>'Vid','sl-SI-Whisper-shimmer'=>'Pia'],
            constants::M_LANG_SRRS => ['sr-RS-Standard-A' => 'Milena_g'],
            constants::M_LANG_TAIN => ['ta-IN-Wavenet-A'=>'Dyuthi_g','ta-IN-Wavenet-B'=>'Bhoomik_g'],
            constants::M_LANG_TEIN => ['te-IN-Standard-A'=>'Anandi_g','te-IN-Standard-B'=>'Kai_g'],
            constants::M_LANG_TRTR => ['Filiz'=>'Filiz'],
            constants::M_LANG_UKUA => ['uk-UA-Wavenet-A'=>'Katya_g'],
        );
        if (array_key_exists($langcode, $alllang) && !$showall) {
            return $alllang[$langcode];
        } else if ($showall) {
            $usearray = [];

            //add current language first
            foreach ($alllang[$langcode] as $v => $thevoice) {
                $usearray[$v] = get_string(strtolower($langcode), constants::M_COMPONENT) . ': ' . $thevoice;
            }
            //then all the rest
            foreach ($alllang as $lang => $voices) {
                if ($lang == $langcode) {
                    continue;
                }
                foreach ($voices as $v => $thevoice) {
                    $usearray[$v] = get_string(strtolower($lang), constants::M_COMPONENT) . ': ' . $thevoice;
                }
            }
            return $usearray;
        } else {
            return $alllang[constants::M_LANG_ENUS];
        }
    }

    /**
     * The html part of the recorder (js is in the fetch_activity_amd)
     * PARAM $media one of audio, video
     * PARAM $recordertype something like "upload" or "fresh" or "bmr"
     */
    public static function fetch_recorder_data($cm, $moduleinstance, $media, $token){
        global $CFG, $USER;

        $config = get_config(constants::M_COMPONENT);
        $rec = new \stdClass();

        $rec->timelimit = $moduleinstance->maxconvlength * 60;
        $rec->recorderskin = $moduleinstance->recorderskin;
        $rec->recordertype = $moduleinstance->recordertype;


        $rec->widgetid = \html_writer::random_id(constants::M_WIDGETID);

        switch ($moduleinstance->transcriber){
            case constants::TRANSCRIBER_OPEN:
            case constants::TRANSCRIBER_NONE:
            default:
            $can_transcribe = self::can_transcribe($moduleinstance);
            $rec->transcribe = $can_transcribe ? $moduleinstance->transcriber : "0";
            $rec->subtitle=$rec->transcribe;
            $rec->speechevents="0";
        }
        
        //get width and height
        //set width and height
        switch($rec->recordertype) {
            case constants::REC_AUDIO:
                //fresh
                if($rec->recorderskin==constants::SKIN_FRESH){
                    $rec->width = "400";
                    $rec->height = "300";


                }elseif($rec->recorderskin==constants::SKIN_PLAIN){
                    $rec->width = "360";
                    $rec->height = "190";

                }elseif($rec->recorderskin==constants::SKIN_UPLOAD){
                    $rec->width = "360";
                    $rec->height = "150";
                }elseif($rec->recorderskin==constants::SKIN_SOLO){
                    $rec->width = "330";
                    $rec->height = "250";

                    //bmr 123 once standard
                }else {
                    $rec->width = "360";
                    $rec->height = "240";
                }
                $rec->iframeclass= constants::CLASS_AUDIOREC_IFRAME;
                break;
            case constants::REC_VIDEO:
            default:
                //bmr 123 once
                if($rec->recorderskin==constants::SKIN_BMR) {
                    $rec->width = "360";
                    $rec->height = "450";
                }elseif($rec->recorderskin==constants::SKIN_123){
                    $rec->width = "450";//"360";
                    $rec->height = "550";//"410";
                }elseif($rec->recorderskin==constants::SKIN_ONCE ){
                    $rec->width = "350";
                    $rec->height = "290";
                }elseif($rec->recorderskin==constants::SKIN_UPLOAD){
                    $rec->width = "350";
                    $rec->height = "310";
                    //standard
                }else {
                    $rec->width = "360";
                    $rec->height = "410";
                }
                $rec->iframeclass= constants::CLASS_VIDEOREC_IFRAME;
        }


        //we encode any hints
        $hints = new \stdClass();
        //pass localposturl as hint, but if its permanent we will add it as a top level param
        if($config->enablelocalpost) {
            $hints->localposturl = $CFG->wwwroot . '/' . constants::M_URL . '/poodlllocalpost.php';
        }
        $rec->hints = base64_encode(json_encode($hints));
        $rec->id=constants::M_RECORDERID;
        $rec->parent=$CFG->wwwroot;
        $rec->owner=hash('md5',$USER->username);
        if($config->enablelocalpost){
            $rec->localloading='always';
            $rec->localposturl= $CFG->wwwroot . '/' . constants::M_URL . '/poodlllocalpost.php';
        }else {
            $rec->localloading = 'auto';
        }
        $rec->localloader= constants::M_URL . '/poodllloader.html';
        $rec->media=$media;
        $rec->appid=constants::M_COMPONENT;
        $rec->updatecontrol=constants::M_WIDGETID . constants::RECORDINGURLFIELD;
        $rec->transcode="1";
        $rec->language=$moduleinstance->ttslanguage;
        $rec->expiredays=$moduleinstance->expiredays;
        $rec->region=$moduleinstance->region;
        $rec->fallback='warning';
        $rec->token=$token;

        //here we set up any info we need to pass into javascript
        //importantly we tell it the div id of the recorder
       // $recopts =Array();
      //  $recopts['recorderid']=$rec->widgetid;

        $rec->transcriber=$moduleinstance->transcriber;
        $rec->expiretime=300;//max expire time is 300 seconds
        $rec->cmid=$cm->id;

        //these need to be returned and echo'ed to the page
        return $rec;

    }

    /*
     * 2023/05/13 - Delete this
     */

    //fetch the grammar correction suggestions
    public static function fetch_grammar_correction($token,$region,$ttslanguage,$passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'request_grammar_correction';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $correction = $payloadobject->returnMessage;
            //clean up the correction a little
            if(\core_text::strlen($correction) > 0){
                $correction = self::super_trim($correction);
                $charone = substr($correction,0,1);
                if(preg_match('/^[.,:!?;-]/',$charone)){
                    $correction = substr($correction,1);
                }
            }

            return $correction;
        } else {
            return false;
        }
    }

    /*
     * 2023/05/13 - Delete this
     */

    //fetch the relevance
    public static function fetch_relevance($token, $moduleinstance,$passage) {
        global $USER;

        //default to 100% relevant if no TTS model or if it's not English
        if(!self::is_english($moduleinstance->ttslanguage) || empty($moduleinstance->modeltts)){
            return 1;
        }

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'get_semantic_sim';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['subject'] = !empty($moduleinstance->modelttsembedding) ? $moduleinstance->modelttsembedding : $moduleinstance->modeltts;
        $params['language'] = $moduleinstance->ttslanguage;
        $params['region'] = $moduleinstance->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params,'post');
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then return the value
        } else if ($payloadobject->returnCode === 0) {
            $relevance = $payloadobject->returnMessage;
            if(is_numeric($relevance)){
                $relevance=(int)round($relevance * 100,0);
            }else{
                $relevance = false;
            }
            return $relevance;
        } else {
            return false;
        }
    }

    /*
    * 2023/05/13 - Delete this
    */
    //fetch the CEFR Level
    public static function fetch_cefr_level($token,$region,$ttslanguage,$passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'predict_cefr';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then return the value
        } else if ($payloadobject->returnCode === 0) {
            $cefr = $payloadobject->returnMessage;
            //make pretty sure its a CEFR level
            if(\core_text::strlen($cefr) !== 2){
                $cefr=false;
            }

            return $cefr;
        } else {
            return false;
        }
    }

    /*
    * 2023/05/13 - Delete this
    */
    //fetch embedding
    public static function fetch_embedding($token,$moduleinstance,$passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'get_embedding';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $moduleinstance->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $moduleinstance->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then process  it
        } else if ($payloadobject->returnCode === 0) {
            $return_data = $payloadobject->returnMessage;
            //clean up the correction a little
            if(!self::is_json($return_data)){
                $embedding=false;
            }else{
                $data_object = json_decode($return_data);
                if(is_array($data_object)&&$data_object[0]->object=='embedding') {
                    $embedding = json_encode($data_object[0]->embedding);
                }else{
                    $embedding=false;
                }
            }
            return $embedding;
        } else {
            return false;
        }
    }

    //fetch the Idea Count
    public static function fetch_idea_count($token,$moduleinstance,$passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'count_unique_ideas';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $moduleinstance->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $moduleinstance->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $ideacount = $payloadobject->returnMessage;
            //clean up the correction a little
            if(!is_number($ideacount)){
                $ideacount=false;
            }

            return $ideacount;
        } else {
            return false;
        }
    }

    //fetch slightly slower version of speech
    public static function fetch_speech_ssml($text, $ttsspeed){

        switch($ttsspeed){
            case constants::TTSSPEED_SLOW:
                $speed='slow';
                break;
            case constants::TTSSPEED_XSLOW:
                $speed='x-slow';
                break;
            case constants::TTSSPEED_MEDIUM:
            default:
                $speed='medium';
        }

        //deal with SSML reserved characters
        $text = str_replace('&','&amp;',$text);
        $text = str_replace("'",'&apos;',$text);
        $text = str_replace('"','&quot;',$text);
        $text = str_replace('<','&lt;',$text);
        $text = str_replace('>','&gt;',$text);

        $speedtemplate='<speak><break time="1000ms"></break><prosody rate="@@speed@@">@@text@@</prosody></speak>';
        $speedtemplate = str_replace('@@text@@',$text,$speedtemplate);
        $speedtemplate = str_replace('@@speed@@',$speed,$speedtemplate);

        return $speedtemplate;
    }


    //fetch the MP3 URL of the text we want read aloud
    public static function fetch_polly_url($token,$region,$speaktext,$texttype, $voice) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_fetch_polly_url';

        //log.debug(params);
        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['text'] = urlencode($speaktext);
        $params['texttype'] = $texttype;
        $params['voice'] = $voice;
        $params['appid'] = constants::M_COMPONENT;
        $params['owner'] = hash('md5',$USER->username);
        $params['region'] = $region;
        $params['engine'] = self::can_speak_neural($voice, $region)?'neural' : 'standard';
        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $pollyurl = $payloadobject->returnMessage;
            return $pollyurl;
        } else {
            return false;
        }
    }

    //can speak neural?
    public static function can_speak_neural($voice,$region){
        //check if the region is supported
        switch($region){
            case "useast1":
            case "tokyo":
            case "sydney":
            case "dublin":
            case "ottawa":
            case "frankfurt":
            case "london":
            case "singapore":
            case "capetown":
                //ok
                break;
            default:
                return false;
        }

        //check if the voice is supported
        if(in_array($voice,constants::M_NEURALVOICES)){
            return true;
        }else{
            return false;
        }
    }

    public static function process_modelanswer_stats($moduleinstance){
        if(empty($moduleinstance->modelanswer)) {
            return $moduleinstance;
        }
        $siteconfig = get_config(constants::M_COMPONENT);
        $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
        $textanalyser = new textanalyser($token,$moduleinstance->modelanswer,$moduleinstance->region,$moduleinstance->ttslanguage);
        $embedding = $textanalyser->fetch_embedding();
        $ideacount = $textanalyser->fetch_idea_count();
        if($embedding){
            //the modelanswer field was originally modeltts, so the field name is out of date.
            // TO DO: change it
            $moduleinstance->modelttsembedding = $embedding;
        }
        if($ideacount){
            //the modelanswer field was originally modeltts, so the field name is out of date.
            // TO DO: change it
            $moduleinstance->modelttsideacount = $ideacount;
        }
        return $moduleinstance;
    }

    public static function sequence_to_steps($moduleinstance){
        switch($moduleinstance->activitysteps){


            case constants::M_SEQ_PTRM:
                $moduleinstance->step2=constants::M_STEP_TRANSCRIBE;
                $moduleinstance->step3=constants::M_STEP_RECORD;
                $moduleinstance->step4=constants::M_STEP_MODEL;
                $moduleinstance->step5=constants::M_STEP_NONE;
                break;
            case constants::M_SEQ_PRMT:
                $moduleinstance->step2=constants::M_STEP_RECORD;
                $moduleinstance->step3=constants::M_STEP_MODEL;
                $moduleinstance->step4=constants::M_STEP_TRANSCRIBE;
                $moduleinstance->step5=constants::M_STEP_NONE;
                break;
            case constants::M_SEQ_PTM:
                $moduleinstance->step2=constants::M_STEP_TRANSCRIBE;
                $moduleinstance->step3=constants::M_STEP_MODEL;
                $moduleinstance->step4=constants::M_STEP_NONE;
                $moduleinstance->step5=constants::M_STEP_NONE;
                break;
            case constants::M_SEQ_PRTM:
                $moduleinstance->step2=constants::M_STEP_RECORD;
                $moduleinstance->step3=constants::M_STEP_TRANSCRIBE;
                $moduleinstance->step4=constants::M_STEP_MODEL;
                $moduleinstance->step5=constants::M_STEP_NONE;
                break;
            case constants::M_SEQ_PRM:
            default:
                $moduleinstance->step2=constants::M_STEP_RECORD;
                $moduleinstance->step3=constants::M_STEP_MODEL;
                $moduleinstance->step4=constants::M_STEP_NONE;
                $moduleinstance->step5=constants::M_STEP_NONE;
                break;
        }
        unset($moduleinstance->activitysteps);
        return $moduleinstance;
    }

    public static function steps_to_sequence($moduleinstance){
        //this just uses function sequence_to_steps to figure out the sequence (activitysteps)
        $sequences = [constants::M_SEQ_PRM,constants::M_SEQ_PTRM,constants::M_SEQ_PRMT,constants::M_SEQ_PRTM,constants::M_SEQ_PTM];
        foreach ($sequences as $sequence){
            $fakemodule = new \stdClass();
            $fakemodule->activitysteps=$sequence;
            $fakemodule = self::sequence_to_steps($fakemodule);
            if($fakemodule->step2 == $moduleinstance->step2
                && $fakemodule->step3 == $moduleinstance->step3
                && $fakemodule->step4 == $moduleinstance->step4
                && $fakemodule->step5 == $moduleinstance->step5){
                $moduleinstance->activitysteps = $sequence;
                return $moduleinstance;
            }
        }
        //if we got here just default to PRM
        $moduleinstance->activitysteps = constants::M_SEQ_PRM;
        return $moduleinstance;
    }

    public static function add_mform_elements($mform, $context,$setuptab=false) {
        global $CFG,$PAGE;
        $config = get_config(constants::M_COMPONENT);
          $dateoptions = array('optional' => true);
        //if this is setup tab we need to add a field to tell it the id of the activity
        if($setuptab) {
            $mform->addElement('hidden', 'n');
            $mform->setType('n', PARAM_INT);
        }

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
        //we do not support this in tabs
        if(!$setuptab) {
            $label = get_string('moduleintro');
            $mform->addElement('editor', 'introeditor', $label, array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, 'context' => $context, 'subdirs' => true));
            $mform->setType('introeditor', PARAM_RAW); // no XSS prevention here, users must be trusted
            $mform->addElement('advcheckbox', 'showdescription', get_string('showdescription'));
            $mform->addHelpButton('showdescription', 'showdescription');
        }


        // Speaking topic text
        $mform->addElement('textarea', 'speakingtopic', get_string('speakingtopic', constants::M_COMPONENT, '1'),  array('rows'=>'3', 'cols'=>'80'));
        $mform->setType('speakingtopic', PARAM_TEXT);
        $mform->addHelpButton('speakingtopic', 'speakingtopic', constants::M_MODNAME);
        //$mform->addRule('speakingtopic', get_string('required'), 'required', null, 'client');

        //add tips field
        $edoptions = solo_editor_no_files_options($context);
        $opts = array('rows'=>'2', 'columns'=>'80');
        $mform->addElement('editor','tips_editor',get_string('tips', constants::M_COMPONENT),$opts,$edoptions);
        $mform->setDefault('tips_editor',array('text'=>$config->speakingtips, 'format'=>FORMAT_HTML));
        $mform->setType('tips_editor',PARAM_RAW);

        //Sequence of activities
        $options = self::fetch_options_sequences();
        $mform->addElement('select','activitysteps',get_string('activitysteps', constants::M_COMPONENT), $options,array());
        $mform->setDefault('activitysteps',constants::M_SEQ_PRM);

        //Disable copy pasting on transcribe text box
        $mform->addElement('selectyesno', 'nopasting', get_string('nopasting', constants::M_MODNAME));
        $mform->disabledIf('nopasting', 'activitysteps', 'neq', constants::M_SEQ_PTRM);
        $mform->setType('nopasting', PARAM_INT);
        $mform->setDefault('nopasting',0);
        $mform->addHelpButton('nopasting', 'nopasting', constants::M_MODNAME);

        //Enable multiple attempts (or not)
        $mform->addElement('advcheckbox', 'multiattempts', get_string('multiattempts', constants::M_COMPONENT), get_string('multiattempts_details', constants::M_COMPONENT));
        $mform->setDefault('multipleattempts',$config->multipleattempts);

        //allow post attempt edit
        $mform->addElement('hidden','postattemptedit');
        $mform->setDefault('postattemptedit',false);
        $mform->setType('postattemptedit',PARAM_BOOL);

        //Preload automatic transcript
        $mform->addElement('hidden', 'preloadtranscript', 1);
        $mform->setType('preloadtranscript',PARAM_BOOL);
        /*
        $mform->addElement('selectyesno', 'preloadtranscript', get_string('preloadtranscript', constants::M_MODNAME));
        $mform->setType('preloadtranscript', PARAM_INT);
        $mform->setDefault('preloadtranscript',1);
        $mform->addHelpButton('preloadtranscript', 'preloadtranscript', constants::M_MODNAME);
        */

        //display media options for speaking prompt
//--------------------------------------------------------
      self::prepare_content_toggle('topic',$mform,$context);
//--------------------------------------------------------


	    $name = 'activityopenscloses';
        $label = get_string($name, constants::M_COMPONENT);
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, false);
        //-----------------------------------------------------------------------------

        $name = 'viewstart';
        $label = get_string($name, constants::M_COMPONENT);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name,constants::M_COMPONENT);
        

        $name = 'viewend';
        $label = get_string($name, constants::M_COMPONENT);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name ,constants::M_COMPONENT);

        // Speaking Targets
        $mform->addElement('header', 'speakingtargetsheader', get_string('speakingtargetsheader', constants::M_COMPONENT));

        //time limits
        $options = utils::get_conversationlength_options();
        //the size attribute doesn't work because the attributes are applied on the div container holding the select
        $mform->addElement('select','convlength',get_string('convlength', constants::M_COMPONENT), $options,array());
        $mform->setDefault('convlength',constants::DEF_CONVLENGTH);

        //the size attribute doesn't work because the attributes are applied on the div container holding the select
        $mform->addElement('select','maxconvlength',get_string('maxconvlength', constants::M_COMPONENT), $options,array());
        $mform->setDefault('maxconvlength',constants::DEF_CONVLENGTH);
       

        //targetwords
        $mform->addElement('static','targetwordsexplanation','',get_string('targetwordsexplanation',constants::M_COMPONENT));
        $mform->addElement('textarea', 'targetwords', get_string('topictargetwords', constants::M_COMPONENT), 'wrap="virtual" rows="5" cols="50"');
        $mform->setType('targetwords', PARAM_TEXT);
        //$mform->addRule('targetwords', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('targetwords', 'targetwords', constants::M_MODNAME);

        //Total words goal
        $mform->addElement('text', 'gradewordgoal', get_string('gradewordgoal', constants::M_COMPONENT), array('size'=>20));
        $mform->setType('gradewordgoal', PARAM_INT);
        $mform->setDefault('gradewordgoal',60);
        $mform->addHelpButton('gradewordgoal', 'gradewordgoal', constants::M_MODNAME);

        //Enable Grammar Suggestions
        $mform->addElement('hidden', 'enablesuggestions', 1);
        $mform->setType('enablesuggestions',PARAM_BOOL);
        /*
        $mform->addElement('selectyesno', 'enablesuggestions', get_string('enablesuggestions', constants::M_MODNAME));
        $mform->setType('enablesuggestions', PARAM_INT);
        $mform->setDefault('enablesuggestions',1);
        $mform->addHelpButton('enablesuggestions', 'enablesuggestions', constants::M_MODNAME);
        */

        //Grammar options
        $mform->addElement('hidden', 'showgrammar', 0);
        $mform->setType('showgrammar',PARAM_BOOL);
        /*
        $grammaroptions = \mod_solo\utils::get_show_options();
        $mform->addElement('select', 'showgrammar', get_string('showgrammar', constants::M_COMPONENT), $grammaroptions);
        $mform->setDefault('showgrammar',$config->showgrammar);
        */


        //Spelling options
        $mform->addElement('hidden', 'showspelling', 0);
        $mform->setType('showspelling',PARAM_BOOL);
        /*
        $spellingoptions = \mod_solo\utils::get_show_options();
        $mform->addElement('select', 'showspelling', get_string('showspelling', constants::M_COMPONENT), $spellingoptions);
        $mform->setDefault('showspelling',$config->showspelling);
        */

        //TTS on pre-audio transcribe
        $mform->addElement('hidden', 'enabletts', 0);
        $mform->setType('enabletts',PARAM_BOOL);
        /*
        $mform->addElement('selectyesno', 'enabletts', get_string('enabletts', constants::M_MODNAME));
        $mform->setType('enabletts', PARAM_INT);
        $mform->setDefault('enabletts',1);
        $mform->addHelpButton('enabletts', 'enabletts', constants::M_MODNAME);
        */

        //Model Answer
        $mform->addElement('header', 'modelanswerheader', get_string('modelanswerheader', constants::M_COMPONENT));
        $mform->addElement('static','modelanswerinstructions','', "<div>" . get_string('modelanswerinstructions', constants::M_COMPONENT) . "</div>");
        $mform->addElement('textarea', 'modelanswer', get_string('modelanswer', constants::M_COMPONENT), array('wrap' => 'virtual', 'style' => 'width: 100%;'));
        $mform->setType('modelanswer', PARAM_RAW);
        $mform->addHelpButton('modelanswer', 'modelanswer', constants::M_MODNAME);
        self::prepare_content_toggle('model',$mform,$context);

        // Language and Recording
        $mform->addElement('header', 'languageandrecordingheader', get_string('languageandrecordingheader', constants::M_COMPONENT));

        $options = utils::get_recorders_options();
        $mform->addElement('select','recordertype',get_string('recordertype', constants::M_COMPONENT), $options,array());
        $mform->setDefault('recordertype',constants::REC_AUDIO);

        $options = utils::get_skin_options();
        $mform->addElement('select','recorderskin',get_string('recorderskin', constants::M_COMPONENT), $options,array());
        $mform->setDefault('recorderskin',constants::SKIN_SOLO);

        //Enable Manual Transcription [lets force this ]
        $mform->addElement('hidden', 'enabletranscription', 1);
        $mform->setType('enabletranscription',PARAM_BOOL);
        //$mform->addElement('advcheckbox', 'enabletranscription', get_string('enabletranscription', constants::M_COMPONENT), get_string('enabletranscription_details', constants::M_COMPONENT));
        //$mform->setDefault('enabletranscription',$config->enabletranscription);


        //Enable AI
        //Enable AI [lets force this ]
        $mform->addElement('hidden', 'enableai', 1);
        $mform->setType('enableai',PARAM_BOOL);
        // $mform->addElement('advcheckbox', 'enableai', get_string('enableai', constants::M_COMPONENT), get_string('enableai_details', constants::M_COMPONENT));
        // $mform->setDefault('enableai',$config->enableai);

        //tts options
        $langoptions = \mod_solo\utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT), $langoptions);
        $mform->setDefault('ttslanguage',$config->ttslanguage);


        //transcriber options
        $name = 'transcriber';
        $label = get_string($name, constants::M_COMPONENT);
        $options = \mod_solo\utils::fetch_options_transcribers();
        $mform->addElement('select', $name, $label, $options);
        $mform->setDefault($name,constants::TRANSCRIBER_OPEN);// $config->{$name});

        //region
        $regionoptions = \mod_solo\utils::get_region_options();
        $mform->addElement('select', 'region', get_string('region', constants::M_COMPONENT), $regionoptions);
        $mform->setDefault('region',$config->awsregion);

        //expiredays
        $expiredaysoptions = \mod_solo\utils::get_expiredays_options();
        $mform->addElement('select', 'expiredays', get_string('expiredays', constants::M_COMPONENT), $expiredaysoptions);
        $mform->setDefault('expiredays',$config->expiredays);

        // Attempts and autograding
        $mform->addElement('header', 'autogradingheader', get_string('autogradingheader', constants::M_COMPONENT));

       // $mform->addElement('advcheckbox', 'postattemptedit', get_string('postattemptedit', constants::M_COMPONENT), get_string('postattemptedit_details', constants::M_COMPONENT));
       // $mform->setDefault('postattemptedit',false);

        //To auto grade or not to autograde
        $mform->addElement('hidden', 'enableautograde', 1);
        $mform->setType('enableautograde',PARAM_BOOL);
       // $mform->addElement('advcheckbox', 'enableautograde', get_string('enableautograde', constants::M_COMPONENT), get_string('enableautograde_details', constants::M_COMPONENT));
       // $mform->setDefault('enableautograde',$config->enableautograde);


        //auto grading options
        $wordcountoptions = utils::get_word_count_options(); //unique word total ;all word total
        $startgradeoptions = utils::get_grade_element_options(); //0-100
        $bonusgradeoptions = utils::fetch_bonus_grade_options(); //targetwordspoken=+3;bigword=+3
        $ratiogradeoptions = utils::fetch_ratio_grade_options(); //accuracy (aka speaking clarity)
        $aigradeoptions = utils::fetch_ai_grade_options(); //aigrade or ---
        $relevanceoptions = utils::fetch_relevance_options(); //relevance_model or relevance_question or ---
        $points_per = get_string("ag_pointsper",constants::M_COMPONENT);
        $over_target_words = get_string("ag_overgradewordgoal",constants::M_COMPONENT);

        //auto grading base elements (word count)
        $aggroup1=array();
        $aggroup1[] =& $mform->createElement('static', 'stext0', '',get_string('gradeequals', constants::M_COMPONENT). '( ');
        $aggroup1[] =& $mform->createElement('select', 'gradewordcount', '', $wordcountoptions);
        $aggroup1[] =& $mform->createElement('static', 'statictext00', '',$over_target_words );
        $aggroup1[] =& $mform->createElement('select', 'gradebasescore', '', $startgradeoptions);
        $aggroup1[] =& $mform->createElement('static', 'stext1', '','%');
        $mform->setDefault('gradewordcount','totalwords');
        $mform->setDefault('gradebasescore',100);
        $mform->addGroup($aggroup1, 'aggroup', get_string('aggroup', constants::M_COMPONENT),'',false);
        $mform->addHelpButton('aggroup', 'aggroup', constants::M_MODNAME);

        //ai grade
        $aggroup2=array();
        $aggroup2[] =& $mform->createElement('static', 'stext2', '',' x &nbsp;');
        $aggroup2[] =& $mform->createElement('select', 'aigradeitem', '', $aigradeoptions);
        $mform->setDefault('aigradeitem',constants::AIGRADE_USE);
        $aggroup2[] =& $mform->createElement('static', 'stext11', '','%  x &nbsp;');

        //relevance item
        $aggroup2[] =& $mform->createElement('select', 'relevancegrade', '', $relevanceoptions);
        $mform->setDefault('relevancegrade',constants::RELEVANCE_QUESTION);
        $aggroup2[] =& $mform->createElement('static', 'stext11', '','%');
        $mform->addGroup($aggroup2, 'aigroup2', '','',false);


        //grade ratio (AKA speaking clarity)
        $aggroup3=array();
        $aggroup3[] =& $mform->createElement('static', 'stext2', '',' x &nbsp;');
        $aggroup3[] =& $mform->createElement('select', 'graderatioitem', '', $ratiogradeoptions);
        $mform->setDefault('graderatioitem','--');
        $aggroup3[] =& $mform->createElement('static', 'stext11', '','% )');
        $aggroup3[] =& $mform->createElement('static', 'stext12', '',' + ' . get_string("bonusgrade",constants::M_COMPONENT));
        $mform->addGroup($aggroup3, 'aggroup3', '','',false);


        //relevance
        /*
        $relevanceoptions = utils::get_relevancegrade_options();
        $mform->addElement('select', 'relevancegrade',get_string('relevancegrade', constants::M_COMPONENT), $relevanceoptions);
        $mform->addHelpButton('relevancegrade', 'relevancegrade', constants::M_MODNAME);
        $mform->addElement('static', 'stext32','', get_string('relevancegrade_details', constants::M_COMPONENT));
        */

        //suggestions
        /*
        $suggestionsoptions = utils::get_suggestionsgrade_options();
        $mform->addElement('select', 'suggestionsgrade',get_string('suggestionsgrade', constants::M_COMPONENT), $suggestionsoptions);
        $mform->addHelpButton('suggestionsgrade', 'suggestionsgrade', constants::M_MODNAME);
        $mform->addElement('static', 'stext42','', get_string('suggestionsgrade_details', constants::M_COMPONENT));
        */

    //AI grading options
    //how to give marks to student
    $mform->addElement('textarea', 'markscheme', get_string('markscheme', constants::M_COMPONENT),
    ['maxlen' => 50, 'rows' => 6, 'size' => 30]);
     $mform->setType('markscheme', PARAM_RAW);
     $mform->setDefault('markscheme', get_config(constants::M_COMPONENT, 'markscheme'));
     $mform->addHelpButton('markscheme', 'markscheme', constants::M_COMPONENT);
 
     // how to give feedback to student
     $mform->addElement('textarea', 'feedbackscheme', get_string('feedbackscheme', constants::M_COMPONENT),
         ['maxlen' => 50, 'rows' => 5, 'size' => 30]);
    $mform->setType('feedbackscheme', PARAM_RAW);
    $mform->setDefault('feedbackscheme', get_config(constants::M_COMPONENT, 'feedbackscheme'));
    $mform->addHelpButton('feedbackscheme', 'feedbackscheme', constants::M_COMPONENT);
     
     //feedback options
     $langoptions = \mod_solo\utils::get_aifeedback_lang_options();
     $mform->addElement('select', 'feedbacklanguage', get_string('feedbacklanguage', constants::M_COMPONENT), $langoptions);
     $mform->setDefault('feedbacklanguage',$config->feedbacklanguage);


        //bonus points
        for ($bonusno=1;$bonusno<=4;$bonusno++){
            $bg = array();
            $bg[] =& $mform->createElement('static', 'stext2'. $bonusno, '',' ');
            $bg[] =& $mform->createElement('select', 'bonuspoints' . $bonusno,'', $startgradeoptions);
            $mform->setDefault('bonuspoints' . $bonusno,3);
            $bg[] =& $mform->createElement('static', 'stext22' . $bonusno, '',$points_per);
            $bg[] =& $mform->createElement('select', 'bonus' . $bonusno, '', $bonusgradeoptions);
            if($bonusno==1) {
                $mform->setDefault('bonus' . $bonusno, 'targetwordspoken');
            }else{
                $mform->setDefault('bonus' . $bonusno, '--');
            }
            $grouptitle = $bonusno==1 ?  get_string('bonusgrade', constants::M_COMPONENT) : "";
            $mform->addGroup($bg, 'bonusgroup' . $bonusno,$grouptitle, '', false);
        }

        //grade options
        //for now we hard code this to latest attempt
        $mform->addElement('hidden', 'gradeoptions',constants::M_GRADELATEST);
        $mform->setType('gradeoptions', PARAM_INT);

        //preview AI grade options
        $mform->addElement('header', 'prompttester', get_string('prompttester', constants::M_COMPONENT));
        $mform->addElement('static','prompttesterinstructions','', "<div>" . get_string('sampleanswerinstructions', constants::M_COMPONENT) . "</div>");
        $mform->addElement('textarea', 'sampleanswer', get_string('sampleanswer', constants::M_COMPONENT),
            ['maxlen' => 50, 'rows' => 6, 'size' => 30]);
        $mform->setType('sampleanswer', PARAM_RAW);
        $mform->setDefault('sampleanswer', '');
        $mform->addHelpButton('sampleanswer', 'sampleanswer', constants::M_COMPONENT);
        $mform->addElement('static', 'sampleanswereval', '',  '<a class="'. constants::M_COMPONENT . '_sampleanswerbtn btn btn-secondary"
                id="id_sampleanswerbtn">'
            . get_string('sampleanswerevaluate', constants::M_COMPONENT) . '</a>' .
             '<div class="' . constants::M_COMPONENT . '_sampleanswereval" id="id_sampleanswereval"></div>');
        
        // Load any JS for the prompt tester.
        $props=[];
        $props['questiontextid']='#id_speakingtopic';
        $props['previewbtnid']='#id_sampleanswerbtn';
        $props['sampleanswerid']='#id_sampleanswer';
        $props['sampleanswerevalid']='#id_sampleanswereval';
        $props['maxmarksid']='';
        $props['markschemeid']='#id_markscheme';
        $props['feedbackschemeid']='#id_feedbackscheme';
        $props['regionid']='#id_region';
        $props['targetlanguageid']='#id_ttslanguage';
        $props['feedbacklanguageid']='#id_feedbacklanguage';
        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/aigradepreview', 'init', [$props]);
    } //end of add_mform_elements



    public static function prepare_content_toggle($contentprefix, $mform, $context){
        global $CFG;

        //display media options for speaking prompt
        $cp = $contentprefix;
        $m35 = $CFG->version >= 2018051700;

        $togglearray=array();
        $togglearray[] =& $mform->createElement('advcheckbox',$cp . 'addmedia',get_string('addmedia',constants::M_COMPONENT),'');
        $togglearray[] =& $mform->createElement('advcheckbox',$cp . 'addiframe',get_string('addiframe',constants::M_COMPONENT),'');
        $togglearray[] =& $mform->createElement('advcheckbox',$cp . 'addttsaudio',get_string('addttsaudio',constants::M_COMPONENT),'');
        $togglearray[] =& $mform->createElement('advcheckbox',$cp . 'addytclip',get_string('addytclip',constants::M_COMPONENT),'');
        $mform->addGroup($togglearray, $cp . 'togglearray', get_string('mediaoptions', constants::M_COMPONENT), array(' '), false);

        //We assume they want to use some media
        $mform->setDefault($cp . 'addmedia', 1);


        //Speaking topic upload
        $filemanageroptions = solo_filemanager_options($context);
        $mform->addElement('filemanager',
            $cp . 'media',//topicmedia , modelmedia
            get_string('content_media',constants::M_COMPONENT),
            null,
            $filemanageroptions
        );
        $mform->addHelpButton($cp . 'media', 'content_media', constants::M_MODNAME);
        if($m35){
            $mform->hideIf($cp . 'media',$cp .  'addmedia', 'neq', 1);
        }else {
            $mform->disabledIf($cp . 'media',$cp .  'addmedia', 'neq', 1);
        }

        //Speaking topic iframe
        $mform->addElement('text', $cp . 'iframe', get_string('content_iframe', constants::M_COMPONENT), array('size'=>100));
        $mform->setType($cp . 'iframe', PARAM_RAW);
        $mform->addHelpButton($cp . 'iframe', 'content_iframe', constants::M_MODNAME);
        if($m35){
            $mform->hideIf($cp . 'iframe',$cp . 'addiframe','neq', 1);
        }else {
            $mform->disabledIf( $cp . 'iframe',$cp . 'addiframe','neq', 1);
        }

        //Speaking topic TTS
        switch($cp){
            case 'topic':
            case 'model':
                $mform->addElement('textarea', $cp . 'tts', get_string('content_tts', constants::M_COMPONENT), array('wrap' => 'virtual', 'style' => 'width: 100%;'));
                $mform->setType($cp . 'tts', PARAM_RAW);
                $mform->addHelpButton($cp . 'tts', 'content_tts', constants::M_MODNAME);
                if($m35){
                    $mform->hideIf($cp . 'tts',$cp .  'addttsaudio', 'neq', 1);
                }else {
                    $mform->disabledIf($cp . 'tts', $cp . 'addttsaudio', 'neq', 1);
                }
                break;
        }


        $voiceoptions = utils::get_tts_voices();
        $mform->addElement('select', $cp . 'ttsvoice', get_string('content_ttsvoice',constants::M_COMPONENT), $voiceoptions);
        $mform->setDefault($cp . 'ttsvoice','Amy');
        if($m35){
            $mform->hideIf($cp . 'ttsvoice', $cp . 'addttsaudio', 'neq', 1);
        }else {
            $mform->disabledIf($cp . 'ttsvoice', $cp . 'addttsaudio', 'neq', 1);
        }

        $speedoptions = \mod_solo\utils::get_ttsspeed_options();
        $mform->addElement('select', $cp .'ttsspeed', get_string('content_ttsspeed', constants::M_COMPONENT), $speedoptions);
        $mform->setDefault($cp .'ttsspeed', constants::TTSSPEED_SLOW);
       // $mform->addHelpButton($cp . 'ttsspeed', $cp . 'ttsspeed', constants::M_COMPONENT);
        if($m35){
            $mform->hideIf($cp . 'ttsspeed', $cp . 'addttsaudio', 'neq', 1);
        }else {
            $mform->disabledIf($cp . 'ttsspeed', $cp . 'addttsaudio', 'neq', 1);
        }


        //Question YouTube Clip
        $ytarray=array();
        $ytarray[] =& $mform->createElement('text', $cp . 'ytid', get_string('content_ytid', constants::M_COMPONENT),  array('size'=>15, 'placeholder'=>"Video ID/URL"));
        $ytarray[] =& $mform->createElement('text', $cp . 'ytstart', get_string('content_ytstart', constants::M_COMPONENT),  array('size'=>3,'placeholder'=>"Start"));
        $ytarray[] =& $mform->createElement('html','s - ');
        $ytarray[] =& $mform->createElement('text', $cp . 'ytend', get_string('content_ytend', constants::M_COMPONENT),  array('size'=>3,'placeholder'=>"End"));
        $ytarray[] =& $mform->createElement('html','s');

        $mform->addGroup($ytarray, $cp .'ytarray' , get_string('ytclipdetails', constants::M_COMPONENT), array(' '), false);
        $mform->setType($cp . 'ytid', PARAM_RAW);
        $mform->setType($cp . 'ytstart', PARAM_INT);
        $mform->setType($cp . 'ytend', PARAM_INT);

        if($m35){
            $mform->hideIf($cp .'ytarray', $cp . 'addytclip', 'neq', 1);
        }else {
            $mform->disabledIf($cp .'ytarray',$cp . 'addytclip', 'neq', 1);
        }
    }

    public static function prepare_file_and_json_stuff($moduleinstance, $modulecontext){
        $filemanageroptions = solo_filemanager_options($modulecontext);
        $ednofileoptions = solo_editor_no_files_options($modulecontext);
        $editors  = solo_get_editornames();
        $filemanagers  = solo_get_filemanagernames();

        $itemid = 0;
        foreach($editors as $editor){
            $form_data = file_prepare_standard_editor((object)$moduleinstance,$editor, $ednofileoptions, $modulecontext,constants::M_COMPONENT,$editor, $itemid);
        }
        foreach($filemanagers as $fm){
            $draftitemid = file_get_submitted_draft_itemid($fm);
            file_prepare_draft_area($draftitemid, $modulecontext->id, constants::M_COMPONENT,
                    $fm, $itemid,
                    $filemanageroptions);
            $moduleinstance->{$fm} = $draftitemid;
        }

        //autograde options
        if(isset($moduleinstance->autogradeoptions)) {
            $agoptions = json_decode($moduleinstance->autogradeoptions);
            $moduleinstance->graderatioitem = $agoptions->graderatioitem;
            $moduleinstance->gradewordcount = $agoptions->gradewordcount;
            $moduleinstance->gradebasescore = $agoptions->gradebasescore;
            if(isset($agoptions->relevancegrade)) {
                $moduleinstance->relevancegrade = $agoptions->relevancegrade;
            }else{
                $moduleinstance->relevancegrade=constants::RELEVANCE_NONE;
            }
            if(isset($agoptions->aigradeitem)) {
                $moduleinstance->aigradeitem = $agoptions->aigradeitem;
            }else{
                $moduleinstance->aigradeitem=constants::AIGRADE_NONE;
            }

            for ($bonusno=1;$bonusno<=4;$bonusno++) {
                $moduleinstance->{'bonuspoints' . $bonusno}  = $agoptions->{'bonuspoints' . $bonusno} ;
                $moduleinstance->{'bonus' . $bonusno} = $agoptions->{'bonus' . $bonusno};
            }
        }

        //make sure the media upload fields are in the correct state

        $fs = get_file_storage();
        $itemid=0;
        $mediasets = ['topic','model'];
        foreach($mediasets as $prefix){

            $files = $fs->get_area_files($modulecontext->id, constants::M_COMPONENT,
                    $prefix. 'media', $itemid);
            if ($files) {
                $moduleinstance->{$prefix.'addmedia'} = 1;
            } else {
                $moduleinstance->{$prefix.'addmedia'} = 0;
            }
            if (!empty($moduleinstance->{$prefix.'tts'})) {
                $moduleinstance->{$prefix.'addttsaudio'} = 1;
            } else {
                $moduleinstance->{$prefix.'addttsaudio'} = 0;
            }
            if (!empty($moduleinstance->{$prefix.'iframe'})) {
                $moduleinstance->{$prefix.'addiframe'} = 1;
            } else {
                $moduleinstance->{$prefix.'addiframe'} = 0;
            }
            if (!empty($moduleinstance->{$prefix.'ytid'})) {
                $moduleinstance->{$prefix.'addytclip'} = 1;
            } else {
                $moduleinstance->{$prefix.'addytclip'} = 0;
            }
        }

        return $moduleinstance;

  }//end of prepare_file_and_json_stuff

    public static function super_trim($str){
        if($str==null){
            return '';
        }else{
            $str = trim($str);
            return $str;
        }
    }


      //fetch the AI Grade
      public static function fetch_ai_grade($token,$region,$ttslanguage,$studentresponse, $instructions) {
        global $USER;
        $instructions_json=json_encode($instructions);
        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'autograde_text';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $instructions_json;
        $params['language'] = $ttslanguage;
        $params['subject'] = $studentresponse;
        $params['region'] = $region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets return
        } else if ($payloadobject->returnCode === 0) {
            $autograderesponse = $payloadobject->returnMessage;
            //clean up the correction a little
            if(\core_text::strlen($autograderesponse) > 0 && self::is_json($autograderesponse)){
                $autogradeobj = json_decode($autograderesponse);
                if(isset($autogradeobj->feedback) && $autogradeobj->feedback==null){
                    unset($autogradeobj->feedback);
                }
                if(isset($autogradeobj->marks) && $autogradeobj->marks==null){
                    unset($autogradeobj->marks);
                }
                return $autogradeobj;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     *
     * Convert string json returned from LLM call to an object,
     * if it is not valid json apend as string to new object
     *
     * @param string $feedback
     * @return \stdClass
     */
    public static function process_aigrade_feedback(string $feedback) {
        if (preg_match('/\{[^{}]*\}/', $feedback, $matches)) {
            // Array $matches[1] contains the captured text inside the braces.
            $feedback = $matches[0];
        }
        $contentobject = json_decode($feedback);
        if (json_last_error() === JSON_ERROR_NONE) {
            $contentobject->feedback = trim($contentobject->feedback);
            $contentobject->feedback = preg_replace(array('/\[\[/', '/\]\]/'), '"', $contentobject->feedback);
          //  $disclaimer = get_config('qtype_aitext', 'disclaimer');
          //  $disclaimer = str_replace("[[model]]", $this->model, $disclaimer);
          //  $contentobject->feedback .= ' '.$this->llm_translate($disclaimer);
        } else {
            $contentobject = (object) [
                                        "feedback" => $feedback,
                                        "marks" => null,
                                        ];
        }
        return $contentobject;
    }


}
