define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;

    log.debug('Corrections Markup: initialising');

    return {
        //controls

        controls: {},

        //class definitions
        cd: {
            correctionscontainer: 'mod_solo_corrections_cont',
            insertclass: 'mod_solo_finediffinsertion',
            passagewordclass: 'mod_solo_grading_passageword',
            //previously removed
            wordclass: 'mod_solo_grading_correctionsword',
            spaceclass: 'mod_solo_grading_correctionsspace',
            suggestionclass: 'mod_solo_corrections_suggestedword',
            wordomittedclass: 'mod_solo_corrections_omittedword',
            aiunmatched: 'mod_solo_aiunmatched',
            aicorrected: 'mod_solo_aicorrected'
        },

        options: {
            errorwords: {},
            grammarmatches: {},
            suggestedwords: {}
        },


        init: function (config) {

            //pick up opts from html
            var theid = '#' + config['id'];
            var configcontrol = $(theid).get(0);
            if (configcontrol) {
                var opts = JSON.parse(configcontrol.value);
                log.debug(opts);
                $(theid).remove();


                if (opts['sessionerrors'] !== '') {
                    this.options.suggestedwords = JSON.parse(opts['sessionerrors']);
                } else {
                    this.options.suggestedwords = {};
                }
                if (opts['sessionmatches'] !== '') {
                    this.options.grammarmatches = JSON.parse(opts['sessionmatches']);
                } else {
                    this.options.grammarmatches  = {};
                }


            } else if(config.hasOwnProperty('sessionerrors') &&
                config.hasOwnProperty('sessionmatches')){

                    this.options.suggestedwords = JSON.parse(config['sessionerrors']);
                    this.options.grammarmatches = JSON.parse(config['sessionmatches']);

            } else {
                //if there is no config we might as well give up
                log.debug('Corrections Markup js: No config found on page. Giving up.');
                return;
            }

            //register the controls
            this.register_controls();

            log.debug(this.options);

            //markup suggested words
            this.markup_suggestedwords();
            //mark up unmatched words
            this.markup_unmatchedwords();

            //register events
            this.register_events();

        },


        register_controls: function () {

            this.controls.correctionscontainer = $("." + this.cd.correctionscontainer);

        },

        register_events: function () {
            var that = this;
            //set up event handlers
            this.controls.correctionscontainer.on('click','.' + this.cd.wordclass + ',.' + this.cd.spaceclass, function () {
                var tpositions = $(this).attr('data-tpositions');
                if (typeof tpositions === 'undefined' || tpositions === '') {return;}
                that.highlightoriginal(tpositions);
                setTimeout(function () {
                    that.dehighlightoriginal(tpositions);
                }, 1000);
            });

            // Use mouseover event for highlighting
            this.controls.correctionscontainer.on('mouseover', '.' + this.cd.wordclass + ',.' + this.cd.spaceclass,  function () {
                var tpositions = $(this).attr('data-tpositions');
                if (typeof tpositions === 'undefined' || tpositions === '') {return;}
                that.highlightoriginal(tpositions);
            });

            // Use mouseout event for de-highlighting
            this.controls.correctionscontainer.on('mouseout','.' + this.cd.wordclass + ',.' + this.cd.spaceclass,  function () {
                var tpositions = $(this).attr('data-tpositions');
                if (typeof tpositions === 'undefined' || tpositions === '') {return;}
                that.dehighlightoriginal(tpositions);
            });
        },

        highlightoriginal: function (tpositionstring) {
            var that = this;
            var tpositions = tpositionstring.split(',');
            $.each(tpositions, function (index, tposition) {
                $('#' + that.cd.passagewordclass + '_' + tposition).addClass(that.cd.aicorrected);
            });

        },
        dehighlightoriginal: function (tpositionstring) {
            var that = this;
            var tpositions = tpositionstring.split(',');
            $.each(tpositions, function (index, tposition) {
                $('#' + that.cd.passagewordclass + '_' + tposition).removeClass(that.cd.aicorrected);
            });
        },

        markup_suggestedwords: function () {
            var m = this;
            $.each(m.options.suggestedwords, function (index) {
                    $('.' + m.cd.correctionscontainer + ' #' + m.cd.wordclass + '_' + (m.options.suggestedwords[index].wordnumber)).addClass(m.cd.suggestionclass);
                }
            );
        },

        //now we step through all the matched words, and look for "gaps"
        //we marked up new/replaced words in "markup_suggestedwords", but missing words can't be marked up(they are not there)
        //so we highlight the space where the missing word would have been
        //NB process is .. we step through each word in the corrected text. Each word has a tposition and pposition
        //NB tposition is the position in the original text.
        //NB pposition is the position in the corrected text.
        //NB if the tposition of the current word in the corrected text has jumped since the previous word, then we have a gap
        // .. "tposition" and "pposition" are a misleading terms here ..sorry
        //it would be possible to fetch the missing words and toggle or highlight them, but we did not do that yet
        markup_unmatchedwords: function () {
            var that = this;
            if (this.options.grammarmatches) {
                //we need a dummy prevmatch for the first loop
                var prevmatch = {tposition: 0, pposition: 0};
                $.each(this.options.grammarmatches, function (index, match) {
                    //if there is a gap since the previous word match in the tposition
                    //AND if we didn't just add a suggestion (which will cause a transcript mismatch too) then
                    // it's a missing word (ie in original but not in the corrected text)
                    //we want to get the prior space and highlight it to show its missing
                    if((match.tposition - prevmatch.tposition)>1) {
                        var missingwordspacenumber = match.pposition - 1;
                        if(missingwordspacenumber>0) {
                            //if we have a missing word space number greater than 0 (should add a 0 space actually)
                            //and it's not either side of a suggested word, then highlight
                            if (!$('#' + that.cd.wordclass + '_' + match.pposition).hasClass(that.cd.suggestionclass)&&
                                !$('#' + that.cd.wordclass + '_' + missingwordspacenumber).hasClass(that.cd.suggestionclass)) {
                                $('#' + that.cd.spaceclass + '_' + missingwordspacenumber).addClass(that.cd.wordomittedclass);
                            }
                            //compile a list of tpositions that we have missed. So we can highlight them on "tap"
                            var tpositions = [];
                            for(var i = prevmatch.tposition + 1; i < match.tposition; i++) {
                                tpositions.push(i);
                            }
                            //loop through the words and spaces that make up the gap and record the tpositions
                            //if it's just a missing word(s) with no corrections, we simply mark up the space with the tpositions
                            var p_gapcount = (match.pposition - prevmatch.pposition) -1;
                            if(p_gapcount ===0) {
                                $('#' + that.cd.spaceclass + '_' + missingwordspacenumber).attr('data-tpositions', tpositions.join(','));
                            }else{
                                for(var z = prevmatch.pposition + 1; z < match.pposition; z++) {
                                    $('#' + that.cd.spaceclass + '_' + z).attr('data-tpositions', tpositions.join(','));
                                    $('#' + that.cd.wordclass + '_' + z).attr('data-tpositions', tpositions.join(','));
                                }
                            }
                        }
                    }
                    //Always mark up the current words tposition as well
                    $('#' + that.cd.wordclass + '_' + match.pposition).attr('data-tpositions', match.tposition);
                    //store this match as the new prevmatch so on the next loop pass we can compare
                    prevmatch = match;
                });//end of $ each loop
            }

        },
    };
});