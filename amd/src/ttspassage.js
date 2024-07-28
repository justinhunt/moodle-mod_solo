define(['jquery','core/log','core/str','core/templates','mod_solo/definitions','mod_solo/pollyhelper'], function($,log,str,templates,def,polly) {
    "use strict"; // jshint ;_;


    log.debug('Solo TTS Passage: initialising');

    var app = {
        //controls
        controls: {},
        checking: '... checking ...',

        //init the module
        init: function(uniqid){
            this.uniqid=uniqid;
            this.ready=false;
            this.thesentence_number =0;
            this.stoporpause='pause';
            this.voice="Amy";//default voice
            
            //common selectors
            this.sentenceselector = '#' + this.uniqid + '_ttssentencecont span.tbr_sentence';
            
            //init other stuff
            this.init_strings();
            this.register_controls();
            this.register_events();
            this.init_polly();
        },

        init_strings: function(){
            var that =this;
            //not used here .. just for later use
            str.get_string('checking','mod_solo').done(function(s){that.checking=s;});
        },

        init_polly: function(){
            var token = this.controls.ttspassagecont.attr('data-token');
            var region = this.controls.ttspassagecont.attr('data-region');
            var owner = 'poodll';
            var lang = this.controls.ttspassagecont.attr('data-ttslanguage');
            var voices = def.voices[lang];
            var randomIndex = Math.floor(Math.random() * voices.length);
            this.voice = voices[randomIndex];
            polly.init(token, region,owner);
        },

        //load all the controls so we do not have to do it later
        register_controls: function(){
            this.controls.ttspassagecont = $('#' + this.uniqid + '_ttspassageplayer');
            this.controls.showttspassagebtn =  $('#' + this.uniqid + '_showttspassagebtn');
            this.controls.selftranscript = $("textarea[name='selftranscript']");
            this.controls.ttssentencecont= $('#' + this.uniqid + '_ttssentencecont .tbr_innerdiv');
            this.controls.ttssentencespinner= $('#' + this.uniqid + '_ttssentence_spinner');

            
            //audio player declarations
            this.controls.aplayer = $('#' +  this.uniqid + '_ttspassageaudio');
            this.controls.theaplayerbtn = $('#' +  this.uniqid + '_ttspassagebutton');
            this.controls.textblock = $('#' +  this.uniqid + '_textblock');
            this.controls.fa = $('#' +  this.uniqid + '_ttspassagebutton .fa');

            //passage lines
            this.controls.passagelines = $(this.sentenceselector);
        },

        //attach the various event handlers we need
        register_events: function() {
            var that = this;
            that.controls.showttspassagebtn.click(function(e){
                e.preventDefault();

                if(!that.controls.ttspassagecont.is(':visible')) {
                    //Quit if its empty
                    var text = that.controls.selftranscript.val();
                    if(!text || text==='' || text.trim()===''){
                        return;
                    }
                    that.markup_and_show();
                }
                //show the widget
                that.controls.ttspassagecont.toggle();
            });

            //AUDIO PLAYER events
            that.controls.aplayer[0].addEventListener('ended', function(){
                if(that.thesentence_number< that.controls.passagelines.length -1){
                    that.thesentence_number++;
                    that.doplayaudio(that.thesentence_number);
                }else{
                    that.dehighlight_all();
                    that.controls.fa.removeClass('fa-stop');
                    that.controls.fa.addClass('fa-volume-up');
                    that.thesentence_number=0;
                    that.controls.aplayer.removeAttr('src');
                }
            });

            //handle audio player button clicks
            that.controls.theaplayerbtn.click(function(){
                that.controls.passagelines = $(that.sentenceselector);
                if(!that.controls.aplayer[0].paused && !that.controls.aplayer[0].ended){
                    log.debug('not paused and not ended');
                    that.controls.aplayer[0].pause();
                    if(that.stoporpause=='stop'){
                        that.controls.aplayer[0].load();
                        that.thesentence_number=0;
                    }
                    that.controls.fa.removeClass('fa-stop');
                    that.controls.fa.addClass('fa-volume-up');

                    //if paused and in limbo no src state
                }else if(that.controls.aplayer[0].paused && that.controls.aplayer.attr('src')){
                    log.debug('inlimbo');
                    that.doplayaudio(that.thesentence_number);
                    that.controls.fa.removeClass('fa-volume-up');
                    that.controls.fa.addClass('fa-stop');
                    //play
                }else{
                    log.debug('play');
                    if(that.stoporpause=='stop'){
                        that.thesentence_number=0;
                    }
                    that.doplayaudio(that.thesentence_number);
                    that.controls.fa.removeClass('fa-volume-up');
                    that.controls.fa.addClass('fa-stop');
                }//end of if paused ended
            });

            //handle sentence clicks
            $('#' + that.uniqid + '_ttssentencecont  .tbr_innerdiv').on('click', '.tbr_sentence',function(){
                that.controls.aplayer[0].pause();
                var sentenceindex = $(this).attr('data-sentenceindex');
                that.controls.fa.removeClass('fa-volume-up');
                that.controls.fa.addClass('fa-stop');
                that.thesentence_number = sentenceindex;
                that.doplayaudio(sentenceindex);
            });

            
        },//end of register events

        //FUNCTION:  unhighlight a sentence as active
        dehighlight_all: function(){
            this.controls.passagelines.removeClass('passageplayer_activesentence');
        },

        //FUNCTION:  highlight a sentence as active
        highlight_sentence: function(thesentence){
            this.controls.passagelines.removeClass('passageplayer_activesentence');
            $(this.controls.passagelines[thesentence]).addClass('passageplayer_activesentence');
            // $(sentenceselector + '[data-sentenceindex=' + thesentence + ']').addClass('passageplayer_activesentence');
        },

        //FUNCTION: play a single sentence and mark it active for display purposes
        doplayaudio: function(thesentence){
            log.debug(thesentence);
            var audiourl = $(this.controls.passagelines[thesentence]).data('audiourl');
            log.debug(audiourl);
            this.highlight_sentence(thesentence);
            this.controls.aplayer.attr('src',audiourl);
            this.controls.aplayer[0].play();
        },

        markup_and_show: async function(){
            var that = this;
            //do the check
            var text = that.controls.selftranscript.val();
            //but quit if its empty
            if(!text || text==='' || text.trim()===''){
                return;
            }
            //clear the existing TTS markup
            that.controls.ttssentencecont.empty();

            //split the text into sentences
            //this is like split but it returns the delimiters too
            var sentences = text.match(/[^\.\!\?¿¡\.\.\.;]*[\.\!\?¿¡\.\.\.;]/g);
            var slowspeed=1;
            that.controls.ttssentencecont.empty();
            //show spinner and hide sentences that are processing
            that.controls.ttssentencespinner.show();
            that.controls.ttssentencecont.hide();
            for (var i=0; i<sentences.length; i++){
                if(sentences[i].trim()===''){continue;}
                var audiourl = await polly.fetch_polly_url(sentences[i],slowspeed,that.voice);
                templates.render('mod_solo/ttssentence',
                    {sentence: sentences[i], audiourl: audiourl, sentenceindex: i}).then(
                    function(html,js){
                        templates.appendNodeContents(that.controls.ttssentencecont, html, js);
                    }
                );
            }
            //hide spinner and show sentences
            that.controls.ttssentencespinner.hide();
            that.controls.ttssentencecont.show();
        }

    };//end of return value
    return app;
});