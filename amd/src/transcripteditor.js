define(['jquery','core/log', "mod_solo/conversationconstants",'mod_solo/definitions', 'core/notification', 'mod_solo/previewhelper', 'mod_solo/conversationeditor',  'mod_solo/vtthelper'],
    function($,log, constants, def,notification,previewhelper, conversationeditor, vtthelper) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */

    log.debug('solo Transcript Editor: initialising');

    return{

        init: function(opts) {
            var that=this;
            var controls = this.init_controls(opts);
            this.register_events(opts,controls);
            conversationeditor.init([],constants.mediatype_audio);
            previewhelper.init();
            var mediaurl = opts['mediaurl'];
            var transcriptjson = controls.updatecontrol.val();
            var vtturl = mediaurl + '.vtt';

            this.loadMedia(mediaurl);
            this.loadJSON(transcriptjson);
            //this.loadVTT(vtturl);

            //this will poke transcription data into our form field for saving
            conversationeditor.doSave=function(){
                var transcript = conversationeditor.fetchTranscriptionData();
                controls.updatecontrol.val(JSON.stringify(transcript));
            }
        },

        init_controls: function(opts){
            var controls ={};
            controls.updatecontrol = $('[name="' + opts['updatecontrol'] + '"]');
            controls.savebutton = $(constants.savebutton);
            controls.removeallbutton = $(constants.removeallbutton);

            return controls;

        },

        register_events: function(opts, controls){
            var that = this;
        },

        loadMedia: function(mediaurl){
            if(mediaurl && mediaurl !== ''){
                previewhelper.setMediaURL(mediaurl);
            }
        },
        loadVTT: function(vtturl){
            if(vtturl && vtturl !== ''){
                $.get(vtturl, function(thevtt) {
                    var transcript = vtthelper.convertVttToJson(thevtt);
                    conversationeditor.resetData(transcript);
                });
            }
        },
        loadJSON: function(json){
            if(json && json !== ''){
                var transcript = JSON.parse(json);
                conversationeditor.resetData(transcript);
            }
        }

};//end of return value

});

