define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */

    log.debug('solo definitions: initialising');

    return{
        component: 'mod_solo',
        C_AUDIOPLAYER: 'vs_audioplayer',
        C_CURRENTFORMAT: 'vs_currentformat',
        C_KEYFORMAT: 'vs_keyformat',
        C_ATTRFORMAT: 'vs_attrformat',
        C_FILENAMETEXT: 'vs_filenametext',
        C_UPDATECONTROL: 'filename',
        C_STREAMINGCONTROL: 'streamingtranscript',
        topicscontainer: 'topicscontainer',
        topiccheckbox: 'topicscheckbox',
        C_BUTTONAPPLY: 'poodllconvedit_edapply',
        C_BUTTONDELETE: 'poodllconvedit_eddelete',
        C_BUTTONMOVEUP: 'poodllconvedit_edmoveup',
        C_BUTTONMOVEDOWN: 'poodllconvedit_edmovedown',
        C_BUTTONCANCEL: 'poodllconvedit_edcancel',
        C_EDITFIELD: 'poodllconvedit_edpart',
        C_TARGETWORDSDISPLAY: 'mod_solo_targetwordsdisplay',
        //hidden player
        hiddenplayer: 'mod_solo_hidden_player',
        hiddenplayerbutton: 'mod_solo_hidden_player_button',
        hiddenplayerbuttonactive: 'mod_solo_hidden_player_button_active',
        hiddenplayerbuttonpaused: 'mod_solo_hidden_player_button_paused',
        hiddenplayerbuttonplaying: 'mod_solo_hidden_player_button_playing',
        transcriber_amazonstreaming: 4,
        smallreportplaceholdertext: 'mod_solo_placeholdertext',
        smallreportplaceholderspinner: 'mod_solo_placeholderspinner',
        grammarsuggestionscont: 'mod_solo_corrections_cont',
        checkgrammarbutton: 'mod_solo_checkgrammarbutton',

        //VOICES
        voices: {'ar-AR': ['Zeina','Hala','Zayd'],
            'de-DE': ['Hans','Marlene','Vicki'],
            'en-US': ['Joey','Justin','Kevin','Matthew','Ivy','Joanna','Kendra','Kimberly','Salli'],
            'en-GB': ['Brian','Amy', 'Emma','Arthur'],
            'en-AU': ['Russell','Nicole','Olivia'],
            'en-NZ': ['Aria'],
            'en-ZA': ['Ayanda'],
            'en-IN': ['Aditi','Raveena'],
            'en-WL': ["Geraint"],
            'es-US': ['Miguel','Penelope','Lupe','Pedro'],
            'es-ES': [ 'Enrique','Conchita','Lucia'],
            'fr-CA': ['Chantal','Gabrielle'],
            'fr-FR': ['Mathieu','Celine','Lea','Remi'],
            'hi-IN': ["Aditi"],
            'it-IT': ['Carla','Bianca','Giorgio'],
            'ja-JP': ['Takumi','Mizuki','Kazuha','Tomoko'],
            'ko-KR': ['Seoyeon'],
            'nl-BE': ["Lisa"],
            'nl-NL': ["Ruben","Lotte"],
            'pt-BR': ['Ricardo','Vitoria'],
            'pt-PT': ["Ines",'Cristiano'],
            'ru-RU': ["Tatyana","Maxim"],
            'tr-TR': ['Filiz'],
            'zh-CN': ['Zhiyu']
        },

        neural_voices: ["Amy","Emma","Brian","Olivia","Aria","Ayanda","Ivy","Joanna","Kendra","Kimberly",
            "Salli","Joey","Justin","Kevin","Matthew","Camila","Lupe","Lucia","Gabrielle","Lea", "Vicki", "Seoyeon", "Takumi","Lucia",
            "Lea","Bianca","Laura","Kajal","Suvi","Liam","Daniel","Hannah","Camila","Ida","Kazuha","Tomoko","Elin","Hala","Zayd"]

    };//end of return value
});