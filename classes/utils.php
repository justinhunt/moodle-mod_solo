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


/**
 * Functions used generally across this mod
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils{

    //fetch the latest compeleted state
    public static function fetch_latest_finishedattempt($solo,$userid=false) {
        global $DB, $USER;
        if(!$userid){
            $userid = $USER->id;
        }
        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
                array('solo'=>$solo->id,'userid'=>$userid,'completedsteps'=>constants::STEP_SELFREVIEW),'timemodified DESC','*',0,1);
        if($attempts){
            $attempt=  array_shift($attempts);
        }else {
            $attempt = false;
        }
        return $attempt;
    }

    //Fetch latest attempt regardless of its completed state
    public static function fetch_latest_attempt($solo,$userid=false) {
        global $DB, $USER;
        if(!$userid){
            $userid = $USER->id;
        }
        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
                array('solo'=>$solo->id,'userid'=>$userid),'timemodified DESC','*',0,1);
        if($attempts){
            $attempt=  array_shift($attempts);
        }else {
            $attempt = false;
        }
        return $attempt;
    }


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



    public static function extract_simple_transcript($selftranscript){
        if(!$selftranscript || empty($selftranscript)){
            return '';
        }else{
            $transcriptarray=json_decode($selftranscript);
            $ret = '';
            foreach($transcriptarray as $turn){
                $ret .= $turn->part . ' ' ;
            }
            return $ret;
        }
    }

    //check if curl return from transcript url is valid
    public static function is_valid_transcript($transcript){
        if(strpos($transcript,"<Error><Code>AccessDenied</Code>")>0){
            return false;
        }
        return true;
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
        if($jsontranscript && $vtttranscript && $transcript) {
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

    //fetch interlocutor array to string
    public static function interlocutors_array_to_string($interlocutors) {
        //the incoming data is an array, and we need to csv it.
        if($interlocutors) {
            if(is_array($interlocutors)) {
                $ret = implode(',', $interlocutors);
            }else{
                $ret = $interlocutors;
            }
        }else{
            $ret ='';
        }
        return $ret;
    }

    //fetch interlocutor names
    public static function fetch_interlocutor_names($attempt) {
        global $DB;
        //user names
        $userids= explode(',',$attempt->interlocutors);
        $usernames = array();
        foreach($userids as $userid){
            $user = $DB->get_record('user',array('id'=>$userid));
            if($user){
                $usernames[] =fullname($user);
            }

        }
        return $usernames;
    }
    //fetch self transcript parts
    public static function fetch_selftranscript_parts($attempt) {
        global $DB;
        //user names
        $sc= $attempt->selftranscript;
        if(!empty($sc)){
            $sc_object = json_decode($sc);
            $parts= array();
            foreach($sc_object as $turn){
                $parts[]=$turn->part;
            }
            return $parts;
        }else{
            return array();
        }
    }

    public static function fetch_targetwords($attempt){
        $targetwords = explode(PHP_EOL,$attempt->topictargetwords);
        $mywords = explode(PHP_EOL,$attempt->mywords);
        $alltargetwords = array_unique(array_merge($targetwords, $mywords));
        return $alltargetwords;
    }


    //fetch stats, one way or the other
    public static function fetch_stats($attempt) {
        global $DB;
        //if we have stats in the database, lets use those
        $stats = $DB->get_record(constants::M_STATSTABLE,array('attemptid'=>$attempt->id));
        if(!$stats){
            $stats = self::calculate_stats($attempt->selftranscript, $attempt);
            //if that worked, and why wouldn't it, lets save them too.
            if ($stats) {
                self::save_stats($stats, $attempt);
            }
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

    //calculate stats of transcript (no db code)
    public static function calculate_stats($usetranscript, $attempt){
        $stats= new \stdClass();
        $stats->turns=0;
        $stats->words=0;
        $stats->avturn=0;
        $stats->longestturn=0;
        $stats->targetwords=0;
        $stats->totaltargetwords=0;
        $stats->questions=0;
        $stats->aiaccuracy=-1;

        if(!$usetranscript || empty($usetranscript)){
            return false;
        }

        $transcriptarray=json_decode($usetranscript);
        $totalturnlengths=0;
        $jsontranscript = '';

        foreach($transcriptarray as $turn){
            $part = $turn->part;
            $wordcount = str_word_count($part,0);
            if($wordcount===0){continue;}
            $jsontranscript .= $turn->part . ' ' ;
            $stats->turns++;
            $stats->words+= $wordcount;
            $totalturnlengths += $wordcount;
            if($stats->longestturn < $wordcount){$stats->longestturn = $wordcount;}
            $stats->questions+= substr_count($turn->part,"?");
        }
        if(!$stats->turns){
            return false;
        }
        $stats->avturn= round($totalturnlengths  / $stats->turns);
        $topictargetwords = utils::fetch_targetwords($attempt);
        $mywords = explode(PHP_EOL,$attempt->mywords);
        $targetwords = array_unique(array_merge($topictargetwords, $mywords));
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

        $record = $DB->get_record(constants::M_STATSTABLE,array('attemptid'=>$attemptid));
        if($record) {
            $record->aiaccuracy=$accuracy;
            $DB->update_record(constants::M_STATSTABLE, $record);
        }
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
    public static function register_aws_task($activityid, $attemptid,$modulecontextid){
        $s3_task = new \mod_solo\task\solo_s3_adhoc();
        $s3_task->set_component('mod_solo');

        $customdata = new \stdClass();
        $customdata->activityid = $activityid;
        $customdata->attemptid = $attemptid;
        $customdata->modulecontextid = $modulecontextid;
        $customdata->taskcreationtime = time();

        $s3_task->set_custom_data($customdata);
        // queue it
        \core\task\manager::queue_adhoc_task($s3_task);
        return true;
    }


    public static function toggle_topic_selected($topicid, $activityid) {
        global $DB;

        // Require view and make sure the user did not previously mark as seen.
        $params = ['moduleid' => $activityid, 'topicid' => $topicid];
        $selected = $DB->record_exists(constants::M_SELECTEDTOPIC_TABLE, $params);

        if($selected){
            $DB->delete_records(constants::M_SELECTEDTOPIC_TABLE, $params);
        }else{
            $entry = new \stdClass();
            $entry->topicid=$topicid;
            $entry->moduleid=$activityid;
            $entry->timemodified=time();

            $DB->insert_record(constants::M_SELECTEDTOPIC_TABLE, $entry);
        }
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
    public static function curl_fetch($url,$postdata=false)
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        $result = $curl->get($url, $postdata);
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
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
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
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
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
        $token_url ="https://cloud.poodll.com/local/cpapi/poodlltoken.php";
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
          "mumbai" => get_string("mumbai",constants::M_COMPONENT)
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
                array(constants::TRANSCRIBER_AMAZONTRANSCRIBE => get_string("transcriber_amazontranscribe", constants::M_COMPONENT),
                        constants::TRANSCRIBER_AMAZONSTREAMING => get_string("transcriber_amazonstreaming", constants::M_COMPONENT),
                        constants::TRANSCRIBER_GOOGLECLOUDSPEECH => get_string("transcriber_googlecloud", constants::M_COMPONENT));
        return $options;
    }

    public static function get_lang_options() {
        return array(
                constants::M_LANG_ARAE => get_string('ar-ae', constants::M_COMPONENT),
                constants::M_LANG_ARSA => get_string('ar-sa', constants::M_COMPONENT),
                constants::M_LANG_DADK => get_string('da-dk', constants::M_COMPONENT),
                constants::M_LANG_DEDE => get_string('de-de', constants::M_COMPONENT),
                constants::M_LANG_DECH => get_string('de-ch', constants::M_COMPONENT),
                constants::M_LANG_ENUS => get_string('en-us', constants::M_COMPONENT),
                constants::M_LANG_ENGB => get_string('en-gb', constants::M_COMPONENT),
                constants::M_LANG_ENAU => get_string('en-au', constants::M_COMPONENT),
                constants::M_LANG_ENIN => get_string('en-in', constants::M_COMPONENT),
                constants::M_LANG_ENIE => get_string('en-ie', constants::M_COMPONENT),
                constants::M_LANG_ENWL => get_string('en-wl', constants::M_COMPONENT),
                constants::M_LANG_ENAB => get_string('en-ab', constants::M_COMPONENT),
                constants::M_LANG_ESUS => get_string('es-us', constants::M_COMPONENT),
                constants::M_LANG_ESES => get_string('es-es', constants::M_COMPONENT),
                constants::M_LANG_FAIR => get_string('fa-ir', constants::M_COMPONENT),
                constants::M_LANG_FRCA => get_string('fr-ca', constants::M_COMPONENT),
                constants::M_LANG_FRFR => get_string('fr-fr', constants::M_COMPONENT),
                constants::M_LANG_HIIN => get_string('hi-in', constants::M_COMPONENT),
                constants::M_LANG_HEIL => get_string('he-il', constants::M_COMPONENT),
                constants::M_LANG_IDID => get_string('id-id', constants::M_COMPONENT),
                constants::M_LANG_ITIT => get_string('it-it', constants::M_COMPONENT),
                constants::M_LANG_JAJP => get_string('ja-jp', constants::M_COMPONENT),
                constants::M_LANG_KOKR => get_string('ko-kr', constants::M_COMPONENT),
                constants::M_LANG_MSMY => get_string('ms-my', constants::M_COMPONENT),
                constants::M_LANG_NLNL => get_string('nl-nl', constants::M_COMPONENT),
                constants::M_LANG_PTBR => get_string('pt-br', constants::M_COMPONENT),
                constants::M_LANG_PTPT => get_string('pt-pt', constants::M_COMPONENT),
                constants::M_LANG_RURU => get_string('ru-ru', constants::M_COMPONENT),
                constants::M_LANG_TAIN => get_string('ta-in', constants::M_COMPONENT),
                constants::M_LANG_TEIN => get_string('te-in', constants::M_COMPONENT),
                constants::M_LANG_TRTR => get_string('tr-tr', constants::M_COMPONENT),
                constants::M_LANG_ZHCN => get_string('zh-cn', constants::M_COMPONENT)
        );
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


    /**
     * fetch a summary of rubric grade for thje student
     *
     * @param \context_module| $modulecontext
     * @param \stdClass| $moduleinstance
     * @param \stdClass| $attempt
     * @return string rubric results
     */
    public static function display_rubricgrade($modulecontext, $moduleinstance, $attempt, $gradinginfo){
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
        $gradingmanager = \get_grading_manager($modulecontext, 'mod_solo', 'solo');
        $gradingdisabled = false;
        $gradeid =$attempt->id;

        if ($controller = $gradingmanager->get_active_controller()) {
            $menu = make_grades_menu($moduleinstance->grade);
            $controller->set_grade_range($menu, $moduleinstance->grade > 0);
            $gradefordisplay = $controller->render_grade($PAGE,
                    $gradeid,
                    $gradingitem,
                    $gradebookgrade->str_long_grade,
                    $gradingdisabled);
        } else {
            $gradefordisplay = 'no grade available';
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

        $grademenu = make_grades_menu($moduleinstance->grade);
        $allowgradedecimals = $moduleinstance->grade > 0;

        $advancedgradingwarning = false;

        //necessary for M3.3
        require_once($CFG->dirroot .'/grade/grading/lib.php');

        $gradingmanager = \get_grading_manager($context, 'mod_solo', 'solo');
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

}
