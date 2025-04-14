define(['jquery','core/log','mod_solo/cloudpoodll'], function($,log,CloudPoodll){
    return {
        init: function(recorderid, thecallback){
            CloudPoodll.createRecorder(recorderid);
            CloudPoodll.theCallback = thecallback;
            CloudPoodll.initEvents();
            $( "iframe" ).on("load",function(){
                $(".mod_solo_recording_cont").css('background-image','none');
            });
        }
   }
});