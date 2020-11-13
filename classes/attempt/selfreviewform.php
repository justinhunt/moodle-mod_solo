<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:32
 */

namespace mod_solo\attempt;

use \mod_solo\constants;

class selfreviewform extends baseform
{

    public $type = constants::STEP_SELFREVIEW;
    public $typestring = constants::T_SELFREVIEW;
    public function custom_definition() {
        $this->moduleinstance = $this->_customdata['moduleinstance'];
        $this->selftranscript = $this->_customdata['selftranscript'];
        $this->autotranscript = $this->_customdata['autotranscript'];
        $this->attempt = $this->_customdata['attempt'];
        $this->aidata = $this->_customdata['aidata'];
        $this->stats = $this->_customdata['stats'];
        if($this->aidata){
            $this->add_selfreviewsummary('comparetranscripts','');
            //$this->add_markedpassage_field('comparetranscripts',get_string('transcriptscompare',constants::M_COMPONENT));
        }else{
            $this->add_comparison_field('comparetranscripts',get_string('transcriptscompare',constants::M_COMPONENT));
            $this->add_stats_field('stats',get_string('stats',constants::M_COMPONENT));
        }
        $this->add_selfreview_fields();

    }
    public function custom_definition_after_data() {


    }
    public function get_savebutton_text(){
        return get_string('finish', constants::M_COMPONENT);
    }

}