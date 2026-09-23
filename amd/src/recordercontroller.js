
define(['jquery', 'core/log','mod_solo/definitions', 'mod_solo/recorderhelper'],
    function($, log, def, recorderhelper) {

    "use strict"; // jshint ;_;

    log.debug('Recorder controller: initialising');

    return {

        //for making multiple instances
        clone: function(){
            return $.extend(true,{},this);
        },

        //pass in config, the jquery video/audio object, and a function to be called when conversion has finshed
        init: function(props){
            var dd = this.clone();

            if(props.hasOwnProperty('token')){
                //opts are passed in directly (probably from mustache template)
                dd.activitydata = props;

            }else {
                //pick up opts from html (stashed in hidden form field)
                var theid = '#amdopts_' + props.widgetid;
                var configcontrol = $(theid).get(0);
                if (configcontrol) {
                    dd.activitydata = JSON.parse(configcontrol.value);
                    dd.activitydata.widgetid  = props.widgetid;
                    $(theid).remove();
                } else {
                    //if there is no config we might as well give up
                    log.debug('solo Recorder Controller: No config found on page. Giving up.');
                    return;
                }
            }

            dd.cmid = props.cmid;
            dd.recorderid = dd.activitydata.recorderid;
            dd.updatecontrolid = dd.activitydata.widgetid + '_' + def.C_UPDATECONTROL;
log.debug( dd.activitydata);
log.debug('updateid', dd.updatecontrolid);

            //if the browser doesn't support html5 recording.
            //then do not go any further
            if(!dd.is_browser_ok()){
                return;
            }
            
            dd.setup_recorder();

        },

        is_browser_ok: function(){
            return (navigator && navigator.mediaDevices
                && navigator.mediaDevices.getUserMedia);
        },


        setup_recorder: function(){
            var dd = this;
            var theform = $('.mod_solo_step2').find('form');
            var uploadwarning = $('.mod_solo_uploadwarning');
            var recordingcontainer = $('.mod_solo_recordingcontainer');

            //Set up the callback functions for the audio recorder

            //originates from the recording:started event
            //contains no meaningful data
            //See https://api.poodll.com
            var on_recording_start= function(eventdata){
            };

            //the iframe recorder only sends speech events for transcribers that recognise while recording,
            //which this recorder does not use. The in page recorder (mod_solo/streamrecord) is the one that does.
            var on_speech = function (eventdata) {
            };

            var on_upload_details = function (eventdata) {
                log.debug(eventdata.id);
                log.debug(eventdata.uploaddetails);
                log.debug(eventdata.uploaddetails.postURL);
                log.debug(dd.activitydata.localposturl);
                var xhr = new XMLHttpRequest();
                var params = "datatype=register";
                params += "&sesskey=" + M.cfg.sesskey;
                params += "&id=" + eventdata.id;
                params += "&posturl=" + encodeURIComponent(eventdata.uploaddetails.postURL);
                xhr.open("POST", dd.activitydata.localposturl, true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.setRequestHeader("Cache-Control", "no-cache");
                xhr.send(params);
            };



            //originates from the recording:ended event
            //contains no meaningful data
            //See https://api.poodll.com
           var on_recording_end= function(eventdata){
               $("button.mod_solo_step2_btn").attr("disabled",true);
           };


            //data sent here originates from the awaiting_processing event
            //See https://api.poodll.com
            var on_media_processing= function(eventdata){
                uploadwarning.show();
                var updatecontrol = $('#' + dd.updatecontrolid);
                updatecontrol.val(eventdata.mediaurl);
               // recordingcontainer.hide();
            };

            //data sent here originates from the file_submitted event
            //See https://api.poodll.com
            var on_file_submitted= function(){
                uploadwarning.hide();
                //disable cancel button because users can try to leave too soon
                $("button.mod_solo_step2_btn").attr("disabled",false);
               // $("button.mod_solo_step2_btn").trigger('click');
            };


            //init the recorder
            recorderhelper.init(dd.activitydata,
                on_recording_start,
                on_recording_end,
                on_media_processing,
                on_speech,
                on_file_submitted,
                on_upload_details);
        },


        process_html: function(){
            var opts = this.activitydata;
            //these css classes/ids are all passed in from php in
            //renderer.php::fetch_activity_amd
            var controls ={};
            this.controls = controls;

            //init drop downs

        },

        register_events: function() {
            var dd = this;


        }
    };//end of returned object
});//total end
