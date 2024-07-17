// This file is part of Moodle - http://moodle.org/ //
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

import {get_strings} from 'core/str';
import Ajax from 'core/ajax';
import Log from 'core/log';
import Notify from 'core/notification';
import Templates from 'core/templates';

/**
 * Question AI Text Edit Form Helper
 *
 * @module     mod_solo/aigradepreview
 * @copyright  2024 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var Selectors = {
    fields: {
        sampleanswer: '#', //text input
        sampleanswereval: '#', //div to push results to
        feedbackscheme: '#', //text input
        markscheme: '#', //text input
        maxmarks: '#', //text input
    },
};

/**
 * Initialise the format chooser.
 */
export const init = (props) => {

    //get feedback button
    var previewbtn = document.querySelector(props.previewbtnid);

    //set up selectors
    Selectors.fields.sampleanswer = props.sampleanswerid;
    Selectors.fields.sampleanswereval = props.sampleanswerevalid;
    Selectors.fields.feedbackscheme = props.feedbackschemeid;
    Selectors.fields.markscheme = props.markschemeid;
   // Selectors.fields.maxmarks = props.maxmarksid;
    Selectors.fields.region = props.regionid;
    Selectors.fields.targetlanguage = props.targetlanguageid;
    Selectors.fields.feedbacklanguage = props.feedbacklanguageid;


    // Set up strings
    var strings={};
    get_strings([
        { "key": "prompttester", "component": 'mod_solo'},
        { "key": "sampleanswerempty", "component": 'mod_solo'},

    ]).done(function (s) {
        var i = 0;
        strings.prompttester = s[i++];
        strings.sampleanswerempty = s[i++];
    });
    Log.debug("addinge event listener to preview button");

    previewbtn.addEventListener('click', e => {

        const form = e.target.closest('form');
        const questiontext = form.querySelector(Selectors.fields.questiontext);
        const sampleanswer = form.querySelector(Selectors.fields.sampleanswer);
        const sampleanswereval = form.querySelector(Selectors.fields.sampleanswereval);
        const feedbackscheme = form.querySelector(Selectors.fields.feedbackscheme);
        const markscheme = form.querySelector(Selectors.fields.markscheme);
       // const maxmarks = form.querySelector(Selectors.fields.maxmarks);
        const region = form.querySelector(Selectors.fields.region);
        const targetlanguage = form.querySelector(Selectors.fields.targetlanguage);
        const feedbacklanguage = form.querySelector(Selectors.fields.feedbacklanguage);

        //if(sampleanswer.value==="" || feedbackscheme.value==="" || markscheme.value===""){
        if(sampleanswer.value===""){
            Notify.alert(strings.prompttester, strings.sampleanswerempty);
            return;
        }

        //put  spinner in place
        sampleanswereval.innerHTML='<i class="icon fa fa-spinner fa-spin fa-2x" style="margin: auto; padding: 10px;"></i>';
        Log.debug("calling ajax");
        Ajax.call([{
            methodname: 'mod_solo_fetch_ai_grade',
            args: {
                region: region.value,
                targetlanguage: targetlanguage.value,
                questiontext: '',//questiontext.value,
                studentresponse: sampleanswer.value,
                markscheme: markscheme.value,
                maxmarks: 100,
                feedbackscheme: feedbackscheme.value,
                feedbacklanguage: feedbacklanguage.value,
            },
            async: false
        }])[0].then(function(airesponse) {
            Log.debug(airesponse);
            if (airesponse.correctedtext) {
                Templates.render('mod_solo/aigradepreview',airesponse).then(
                    function(html,js){
                        sampleanswereval.innerHTML=html;
                    }
                );
            }
        });
    });//end of click
};
