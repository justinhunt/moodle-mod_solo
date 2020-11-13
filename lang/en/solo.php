<?php



/**
 * English strings for solo
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'P-CHAT';
$string['modulenameplural'] = 'P-CHATs';
$string['modulename_help'] = 'P-CHAT is an activity designed to give students conversation practice and feedback. Students record their conversation, transcribe their own speech and compare their transcript with an auto generated one.';
$string['solofieldset'] = 'Custom example fieldset';
$string['soloname'] = 'P-CHAT';
$string['soloname_help'] = 'This is the content of the help tooltip associated with the soloname field. Markdown syntax is supported.';
$string['solo'] = 'solo';
$string['activitylink'] = 'Link to next activity';
$string['activitylink_help'] = 'To provide a link after the attempt to another activity in the course, select the activity from the dropdown list.';
$string['activitylinkname'] = 'Continue to next activity: {$a}';
$string['pluginadministration'] = 'P-CHAT Administration';
$string['pluginname'] = 'P-CHAT Activity';
$string['someadminsetting'] = 'Some Admin Setting';
$string['someadminsetting_details'] = 'More info about Some Admin Setting';
$string['someinstancesetting'] = 'Some Instance Setting';
$string['someinstancesetting_details'] = 'More infor about Some Instance Setting';
$string['solosettings'] = 'solo settings';
$string['solo:addinstance'] = 'Add a new P-CHAT';
$string['solo:view'] = 'View P-CHAT';
$string['solo:viewreports'] = 'View P-CHAT reports';
$string['solo:selecttopics'] = 'Select topics for use in activity.';
$string['solo:managetopics'] = 'Manage topics (add/edit/delete)';
$string['solo:attemptview'] = 'View attempts';
$string['solo:attemptedit'] = 'Edit attempts';
$string['solo:manageattempts'] = 'Can manage P-CHAT attempts';
$string['solo:manage'] = 'Can manage P-CHAT instances';
$string['solo:submit'] = 'Can submit P-CHAT attempts';
$string['privacy:metadata'] = 'The Poodll P-CHAT plugin does store personal data.';
$string['privacy:metadata:solo'] = 'The Poodll P-CHAT plugin does store personal data.';
$string['privacy:metadata:attemptstable'] = 'The Poodll P-CHAT attempts table.';


$string['id']='ID';
$string['name']='Name';
$string['timecreated']='Time Created';
$string['basicheading']='Basic Report';
$string['totalattempts']='Attempts';
$string['overview']='Overview';
$string['overview_help']='Overview Help';
$string['view']='View';
$string['preview']='Preview';
$string['viewreports']='View Reports';
$string['reports']='My Reports';
$string['reports']='Reports';
$string['viewgrading']='View Grading';
$string['showingattempt']='Showing attempt for: {$a}';
$string['basicreport']='Basic Report';
$string['returntoreports']='Return to Reports';
$string['returntotop']='Return to Top';
$string['exportexcel']='Export to CSV';
$string['deletealluserdata'] = 'Delete all user data';
$string['maxattempts'] ='Max. Attempts';
$string['unlimited'] ='unlimited';
$string['defaultsettings'] ='Default Settings';
$string['exceededattempts'] ='You have completed the maximum {$a} attempts.';
$string['solotask'] ='P-CHAT Task';
$string['gotnosound'] = 'We could not hear you. Please check the permissions and settings for microphone and try again.';
$string['done'] = 'Done';
$string['processing'] = 'Processing';
$string['feedbackheader'] = 'Finished';
$string['beginreading'] = 'Begin Reading';
$string['errorheader'] = 'Error';
$string['uploadconverterror'] = 'An error occured while posting your file to the server. Your submission has NOT been received. Please refresh the page and try again.';
$string['attemptsreport'] = 'Attempts Report';
$string['submitted'] = 'submitted';
$string['id'] = 'ID';
$string['username'] = 'User';
$string['audiofile'] = 'Audio';
$string['timecreated'] = 'Time Created';
$string['nodataavailable'] = 'No Data Available Yet';
$string['saveandnext'] = 'Save .... and next';
$string['next'] = 'Next';
$string['finish'] = 'Finish';
$string['reattempt'] = 'Try Again';
$string['notgradedyet'] = 'Your submission has been received, but has not been graded yet';
$string['enabletts'] = 'Enable TTS(experimental)';
$string['enabletts_details'] = 'TTS is currently not implemented';
//we hijacked this setting for both TTS STT .... bad ... but they are always the same aren't they?
$string['ttslanguage'] = 'Passage Language';
$string['deleteattemptconfirm'] = "Are you sure that you want to delete this attempt?";
$string['deletenow']='';
$string['attemptsperpage']='Attempts per page';
$string['attemptsperpage_details']='This sets the number of rows to be shown on reports or lists of attempts.';

$string['apiuser']='Poodll API User ';
$string['apiuser_details']='The Poodll account username that authorises Poodll on this site.';
$string['apisecret']='Poodll API Secret ';
$string['apisecret_details']='The Poodll API secret. See <a href= "https://support.poodll.com/support/solutions/articles/19000083076-cloud-poodll-api-secret">here</a> for more details';
$string['enableai']='Enable AI';
$string['enableai_details']='P-CHAT can evaluate results from a student attempt using AI. Check to enable.';


$string['useast1'] = 'US East';
$string['tokyo'] = 'Tokyo, Japan';
$string['sydney'] = 'Sydney, Australia';
$string['dublin'] = 'Dublin, Ireland';
$string['ottawa'] = 'Ottawa, Canada';
$string['frankfurt'] = 'Frankfurt, Germany';
$string['london'] = 'London, U.K';
$string['saopaulo'] = 'Sao Paulo, Brazil';
$string['singapore'] = 'Singapore';
$string['mumbai'] = 'Mumbai, India';

$string['forever'] = 'Never expire';

$string['en-us'] = 'English (US)';
$string['es-us'] = 'Spanish (US)';
$string['en-au'] = 'English (Aus.)';
$string['en-gb'] = 'English (GB)';
$string['fr-ca'] = 'French (Can.)';
$string['fr-fr'] = 'French (FR)';
$string['it-it'] = 'Italian (IT)';
$string['pt-br'] = 'Portuguese (BR)';
$string['en-in'] = 'English (IN)';
$string['es-es'] = 'Spanish (ES)';
$string['fr-fr'] = 'French (FR)';
$string['de-de'] = 'German (DE)';
$string['da-dk'] = 'Danish (DK) (fast transcription only)';
$string['hi-in'] = 'Hindi';
$string['ko-kr'] = 'Korean';
$string['ar-ae'] = 'Arabic (Gulf)';
$string['ar-sa'] = 'Arabic (Modern Standard)';
$string['zh-cn'] = 'Chinese (Mandarin-Mainland)';
$string['nl-nl'] = 'Dutch';
$string['en-ie'] = 'English (Ireland)';
$string['en-wl'] = 'English (Wales)';
$string['en-ab'] = 'English (Scotland)';
$string['fa-ir'] = 'Farsi';
$string['de-ch'] = 'German (Swiss)';
$string['he-il'] = 'Hebrew';
$string['id-id'] = 'Indonesian';
$string['ja-jp'] = 'Japanese';
$string['ms-my'] = 'Malay';
$string['pt-pt'] = 'Portuguese (PT)';
$string['ru-ru'] = 'Russian';
$string['ta-in'] = 'Tamil';
$string['te-in'] = 'Telegu';
$string['tr-tr'] = 'Turkish';

$string['awsregion']='AWS Region';
$string['region']='AWS Region';
$string['expiredays']='Days to keep file';
$string['aigradenow']='AI Grade';

$string['attemptsperpage']="Attempts to show per page: ";
$string['backtotop']="Back to Start";
$string['transcript']="Transcript";
$string['quickgrade']="Quick Grade";
$string['ok']="OK";

$string['notimelimit']='No time limit';
$string['xsecs']='{$a} seconds';
$string['onemin']='1 minute';
$string['xmins']='{$a} minutes';
$string['oneminxsecs']='1 minutes {$a} seconds';
$string['xminsecs']='{$a->minutes} minutes {$a->seconds} seconds';

$string['postattemptheader']='Post attempt options';
$string['recordingaiheader']='Recording and AI options';

$string['grader']='Graded by';
$string['grader_ai']='AI';
$string['grader_human']='Human';
$string['grader_ungraded']='Ungraded';

$string['displaysubs'] = '{$a->subscriptionname} : expires {$a->expiredate}';
$string['noapiuser'] = "No API user entered. P-CHAT will not work correctly.";
$string['noapisecret'] = "No API secret entered. P-CHAT will not work correctly.";
$string['credentialsinvalid'] = "The API user and secret entered could not be used to get access. Please check them.";
$string['appauthorised']= "Poodll P-CHAT is authorised for this site.";
$string['appnotauthorised']= "Poodll P-CHAT is NOT authorised for this site.";
$string['refreshtoken']= "Refresh license information";
$string['notokenincache']= "Refresh to see license information. Contact Poodll support if there is a problem.";
//these errors are displayed on activity page
$string['nocredentials'] = 'API user and secret not entered. Please enter them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['novalidcredentials'] = 'API user and secret were rejected and could not gain access. Please check them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['nosubscriptions'] = "There is no current subscription for this site/plugin.";

$string['privacy:metadata:attemptid']='The unique identifier of a users P-CHAT attempt.';
$string['privacy:metadata:soloid']='The unique identifier of a P-CHAT activity instance.';
$string['privacy:metadata:userid']='The user id for the P-CHAT attempt';
$string['privacy:metadata:filename']='File urls of submitted recordings.';
$string['privacy:metadata:timemodified']='The last time attempt was modified for the attempt';
$string['privacy:metadata:attempttable']='Stores the scores and other user data associated with a P-CHAT attempt.';
$string['privacy:metadata:transcriptpurpose']='The recording short transcripts.';
$string['privacy:metadata:jsontranscriptpurpose']='The full transcripts of recordings.';
$string['privacy:metadata:cloudpoodllcom:userid']='The P-CHAT plugin includes the moodle userid in the urls of recordings and transcripts';
$string['privacy:metadata:cloudpoodllcom']='The P-CHAT plugin stores recordings in AWS S3 buckets via cloud.poodll.com.';

//attempts
$string['durationgradesettings'] = 'Grade Settings ';
$string['durationboundary']='{$a}: Completion time less than (seconds)';
$string['boundarygrade']='{$a}: points ';
$string['numeric']='Must be numeric ';
$string['attemptinuse']= 'This attempt is part of users attempt history. It cannot be deleted.';
$string['moveattemptup']='Up';
$string['moveattemptdown']='Down';

$string['attempts'] ='Attempts';
$string['manageattempts'] ='Manage Attempts';
$string['correctanswer'] ='Correct answer';
$string['letsgetstarted'] = 'Lets have a conversation';
$string['addnewattempt'] = 'Add a New attempt';
$string['addingattempt'] = 'Adding a New attempt';
$string['editingattempt'] = 'Editing a attempt';
$string['createaattempt'] = 'Create a attempt';
$string['attempt'] = 'Attempt';
$string['attempttitle'] = 'Attempt Title';
$string['attemptcontents'] = 'Attempt Description';
$string['answer'] = 'Answer';
$string['saveattempt'] = 'Save attempt';
$string['audioattemptfile'] = 'attempt Audio(MP3)';
$string['attemptname'] = 'Attempt Name';
$string['attemptorder'] = 'Attempt Order';
$string['correct'] = 'Correct';
$string['attempttype'] = 'Attempt Type';
$string['actions'] = 'Actions';
$string['editattempt'] = 'Edit attempt';
$string['previewattempt'] = 'Preview attempt';
$string['deleteattempt'] = 'Delete attempt';
$string['confirmattemptdelete'] = 'Are you sure you want to <i>DELETE</i> attempt?';
$string['confirmattemptdeletetitle'] = 'Really Delete attempt?';
$string['confirmattemptdelete'] = 'Are you sure you want to <i>DELETE</i> this attempt?';
$string['confirmattemptdeletealltitle'] = 'Really Delete ALL Attempts?';
$string['confirmattemptdeleteall'] = 'Are you sure you want to <i>DELETE ALL</i> attempts?';
$string['noattempts'] = 'This activity contains no attempts';
$string['attemptdetails'] = 'attempt Details: {$a}';
$string['attemptsummary'] = 'attempt Summary: {$a}';
$string['viewreport'] = 'view report';

$string['addrecordconversation'] = 'Record Conversation';
$string['adduserselections'] = 'User Selections';
$string['addselftranscribe'] = 'Self Transcribe';
$string['addselfreview'] = 'Self Review';


$string['readtext'] = 'Text to read';
$string['language_voice'] = 'Language and voice';
$string['listen'] = 'Listen';
$string['download'] = 'Download';
$string['tagarea_solo_attempts'] = 'P-CHAT Attempts';
$string['timemodified'] = 'Last Changed';

$string['picturechoice'] = 'Picture Choice';
$string['translate'] = 'Translate';
$string['pictureitemfile'] = 'Picture Item File';
$string['iscorrectlabel'] = 'Correct/Incorrect';
$string['textchoice'] = 'Text Area Choice';
$string['textboxchoice'] = 'Text Box Choice';
$string['audioresponse'] = 'Audio response';
$string['correcttranslationtitle'] = 'Correct Translation';
$string['audiochoice'] = 'Audio Choice';
$string['audioprompt'] = 'Audio Prompt';
$string['edit'] = 'Edit';
$string['gotoactivity'] = 'Start Activity';
$string['tryactivityagain'] = 'Try Again';
$string['shuffleanswers'] = 'Shuffle Answers';
$string['shufflequestions'] = 'Shuffle Questions';
$string['solo:attemptview'] = 'View attempts';
$string['solo:attemptedit'] = 'Edit attempts';
$string['attemptname'] = 'Attempt';
$string['nodataavailable'] = 'No data available';
$string['transcriber'] = 'Transcriber';
$string['transcriber_details'] = 'The transcription engine to use. Instant transcription only works if passage language is US/AU/GB English and FR/CA French, or when using US/Dublin/Sydney/Ireland AWS regions.';
$string['transcriber_amazontranscribe'] = 'Regular transcription(AWS)';
$string['transcriber_amazonstreaming'] = 'Instant Transcription(AWS)';
$string['transcriber_googlechrome'] = 'Instant transcription (Chrome only)';
$string['transcriber_googlecloud'] = 'Quick Transcription(Google)(audio length < 60s ony)';
$string['transcriber_none'] = 'No transcription';
$string['transcriptnotready'] = '<i>Transcript not ready yet</i>';
$string['transcripttitle'] = 'Transcript';

$string['createattempt'] = 'Create Attempt';
$string['addtopic'] = 'Add Topic';
$string['deletetopic'] = 'Delete Topic';
$string['edittopic'] = 'Edit Topic';
$string['editingtopic'] = 'Editing Topic';
$string['savetopic'] = 'Save Topic';
$string['createtopic'] = 'Create Topic';
$string['topicformtitle'] = 'Add/edit Topic';
$string['topiclevelcustom'] = 'Custom';
$string['topiclevelcourse'] = 'Course';
$string['grades'] = 'Grades';
$string['managegrades'] = 'Manage Grades';
$string['topics'] = 'Topics';
$string['managetopics'] = 'Manage Topics';
$string['topicselected'] = 'Selected';
$string['topicname'] = 'Topic';
$string['topiclevel'] = 'Level';
$string['topicicon'] = 'Icon';
$string['topictargetwords'] = 'Target Words';
$string['targetwords'] = 'Target Words';
$string['tips'] = 'Tips';
$string['confirmtopicdelete'] = 'Do you really want to delete topic: {$a}';
$string['choosetopic'] = 'Choose Topic';
$string['topicinstructions']='Add or edit topics. Custom topics will only be available here. Course level topics will be available course wide. Selected topics will be available for students to choose from in this activity. ';

$string['userselections'] = 'User Selection';
$string['selftranscribe'] = 'Transcribe Conversation';
$string['transcriptscompare'] = 'Compare Transcriptions';
$string['comparetranscripts'] = 'Compare Transcriptions';
$string['saveitem'] = 'Save';
$string['xminutes'] = '{$a}:00 minutes';
$string['convlength'] = 'Conversation Length';
$string['mywords'] = 'My Target Words';
$string['words'] = 'Words';
$string['speakingtips'] = 'Speaking Tips';
$string['speakingtips_details'] = '';
$string['speakingtips_default'] = 'Speak simply and clearly. So that your partner can understand and reply.';
$string['chooseusers'] = 'Choose Partner(s)';
$string['users'] = 'Partners';
$string['topic'] = 'Topic';

$string['attempt_partone'] = '1: Prepare';
$string['attempt_parttwo'] = '2: Record';
$string['attempt_partthree'] = '3: Transcribe';
$string['attempt_partfour'] = '4: Review';
$string['attempt_partone_instructions'] = 'Choose your partners, topic, target words and target speaking time from the options below. When you are ready, move to the next page and begin your conversation';
$string['attempt_parttwo_instructions'] = 'Use the recorder below to record your conversation. Try to use the target words. Good luck.';
$string['attempt_partthree_instructions'] = 'Listen to your conversation and enter what you said into the conversation editor below. Do not change what you said, when you type it.';
$string['attempt_partfour_instructions'] = 'Compare your conversation transcript, to the automatically generated transcript. Are there many differences? Could you improve your speaking? Enter your self review in the text fields below.';

$string['savesubtitles'] = 'Save Conversation';
$string['removesubtitles'] = 'Remove Conversation';
$string['addnew'] = 'Add new';
$string['stepback'] = 'Step back';
$string['stepahead'] = 'Step ahead';
$string['playpause'] = 'Play/pause';
$string['now'] = 'Now';
$string['cancel'] = 'Cancel';
$string['selftranscript'] = 'Self Transcript';
$string['autotranscript'] = 'Auto Transcript';
$string['stats'] = 'Stats';
$string['stats_words'] = 'Words';
$string['stats_turns'] = 'Turns';
$string['stats_avturn'] = 'Av. Turn Length';
$string['stats_longestturn'] = 'Longest Turn Length';
$string['stats_questions'] = 'Questions Asked';
$string['stats_targetwords'] = 'Target Words';
$string['stats_aiaccuracy'] = 'AI Accuracy';
$string['transcripteditor']= 'Transcript Editor';
$string['selfreview']= 'Self Review';
$string['multiattempts'] = 'Allow Multiple Attempts';
$string['multiattempts_details'] = 'If checked a student can choose to overwrite an existing attempt with a new one.';
$string['attemptsheading']= 'Attempts';
$string['partners']= 'Partners';
$string['turns']= 'Turns';
$string['ATL']= 'ATL';
$string['LTL']= 'LTL';
$string['TW']= 'TW';
$string['idnumber']= 'ID-number';



$string['audiorecording']= 'Audio Recording';
$string['summaryuserattemptheadertitle']= '(Attempt: {$a}) Conversation Details';
$string['summaryuserattemptheaderintro']= '';
$string['summaryheadertitle']= 'Your Conversation Details';
$string['summaryheaderintro']= 'Check the details and the results of your conversation below. How do you think? You should be getting better each time.';
$string['leaveedittopic']= 'Edit (diff. activity)';
$string['fonticonexplanation']= 'Add an icon graphic to represent the topic. Use FontAwesome for this. The pattern is fa-xxx where xxx is the icon name. Search for icons at: <a href="https://fontawesome.com/v4.7.0/icons">https://fontawesome.com/v4.7.0/icons</a>';

$string['targetwordsexplanation']= 'Add target words each on a new line.';

$string['confirmtopicdeletetitle']= 'Confirm Delete Topic:';

$string['userconvlength']= 'Conv. length override';
$string['userconvlength_details']= 'Allow users to override suggested conversation length';
$string['revq']= 'Reflection question {$a}';
$string['heard']= 'Heard';
$string['QS']= 'Qs';
$string['ACC']= 'Acc';

$string['notopicsavailable'] = "No topics have been added by your teacher.";

$string['postattemptedit'] = 'Can edit after finished';
$string['postattemptedit_details'] = 'Allow student to edit their submission after they have submitted it.';
$string['dopostattemptedit'] = 'Edit the last attempt';
$string['audiofilename'] = 'Audio';

#ID, Name, ai score, turns, av turn length
$string['gradesid'] = 'ID';
$string['gradesfirst'] = 'First';
$string['gradeslast'] = 'Last';
$string['gradesaiscore'] = 'AI Score';
$string['gradesturns'] = 'Turns';
$string['gradesavturnlength'] = 'AV Turn Length';
$string['gradesactions'] = 'Actions';
$string['gradesgraded'] = 'Graded';
$string['gradesgradedno'] = 'Ungraded';
$string['gradesgradedyes'] = 'Graded';
$string['gradesgrade'] = 'Grade';
$string['gradeschoose'] = 'Choose one:';
$string['gradesstudent'] = 'Student';
$string['gradesdatapoint'] = 'Data point';
$string['gradesrubric'] = 'Rubric';
$string['gradestranscript'] = 'Transcript';
$string['gradesfeedback'] = 'Feedback:';
$string['gradessubmit'] = 'Submit';
$string['gradesubmissions'] = 'Grade submissions';

$string['gradeitem:solo'] = 'P-CHAT';
$string['developer'] = 'Developer';
$string['dorubricgrade'] = 'Do Rubric Grade';

$string['detailedattemptsreport'] = 'Researcher Report';
$string['detailedattemptsheading']= 'Researcher Report';
$string['detailedattempts']= 'Researcher Report';

$string['classprogressreport'] = 'Class Progress';
$string['classprogressheading']= 'Class Progress';
$string['classprogress']= 'Class Progress';

$string['myprogressreport'] = 'My Progress';
$string['myprogressheading']= 'My Progress: {$a}';
$string['myprogress']= 'My Progress';

$string['userattempts'] = 'User Attempts';
$string['userattemptsheading']= '{$a} Attempts';
$string['userattempts']= 'User Attempts';

$string['myattempts'] = 'My Attempts';
$string['myattemptsheading']= 'My Attempts: {$a} ';
$string['myattempts']= 'My Attempts';

$string['downloadaudio'] = 'Download Audio';
$string['downloadaudioheading'] = 'Download Audio';
$string['downloadaudioreport'] = 'Download Audio';
$string['file'] = 'File';
$string['teachereval'] = 'Teacher Evaluation';

$string['completionallsteps']='Complete when all steps completed';
$string['completionallsteps_help']='Complete when all steps completed';
$string['yes']='Yes';
$string['no']='No';

$string['avturns']='Avg Turns';
$string['avatl']='Avg ATL';
$string['avltl']='Avg LTL';
$string['avw']='Avg Words';
$string['avtw']='Avg Target Words';
$string['avq']='Avg Questions';
$string['avacc']='Avg Acc';
$string['tabular']='Table View';

$string['grade']='Grade';

$string['reportmenuinstructions']="View reports by selecting the report from the buttons below.";
$string['totalgradeables']='Grading {$a} students';

$string['myreports']='My Reports';

