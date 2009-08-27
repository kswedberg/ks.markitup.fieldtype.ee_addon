<?php

/** =ft.ks_markitup.php
************************************************************
@project      KS markItUp
@build        August 27, 2009
@author       Karl Swedberg (my first name at learningjquery dot com)
@author      Tim Kelty
@credit      Ryan Masuga
@credit      Brandon Kelly
************************************************************/

if ( !defined('EXT')) { exit('Invalid file request'); }

class Ks_markitup extends Fieldframe_Fieldtype {

  var $info = array(
    'name'              => 'KS Markitup',
    'version'           => '1.0.0',
    'desc'              => 'Use Markitup in FieldFrame',
    'docs_url'          => 'http://github.com/kswedberg/ks.markitup.fieldtype.ee_addon/',
    'no_lang'           => true
  );

  var $default_site_settings = array(
    'markitup_set' => 'default',
    'markitup_skin' => 'simple'
  );


  /**
    * Display Site Settings
    */
  function display_site_settings() {
    global $DB, $PREFS, $DSP, $FFSD;

    if ( ! isset($FFSD)) {
      $FFSD = new Fieldframe_SettingsDisplay();
    }
    $r = $FFSD->block();
    $r .= $FFSD->row(array(
      $FFSD->label('Markitup Set'),
      $FFSD->text('markitup_set', $this->site_settings['markitup_set'])
      )
    );
    $r .= $FFSD->row(array(
      $FFSD->label('Markitup Skin'),
      $FFSD->text('markitup_skin', $this->site_settings['markitup_skin'])
      )
    );

    $r  .= $FFSD->block_c();

    return $r;
  }


  function display_field_settings($field_settings) {
    	global $FFSD;

      // initialize Fieldframe_SettingsDisplay
      if ( ! isset($FFSD)) {
       $FFSD = new Fieldframe_SettingsDisplay();
      }
      $markitup_set = isset($field_settings['markitup_set']) ? $field_settings['markitup_set'] : '';
      $cell2_output = $FFSD->label('Markitup Set', 'leave blank to use the site\'s default markitup set');
      $cell2_output .= $FFSD->text('markitup_set', $markitup_set);
      
  		return array(
  		  'formatting_available' => true,
  		  'cell2' => $cell2_output,
  		);
    
  }
  
  function display_field($field_name, $field_data, $field_settings) {
    global $DSP;

    $field_id = str_replace('field_id_' , '', $field_name);
  	// Get the markitup set
	  $markitup_set = 'default';

  	if (isset($field_settings['markitup_set']) && !empty($field_settings['markitup_set'])) {
  	  $markitup_set = $field_settings['markitup_set'];
  	} elseif (isset($this->site_settings['markitup_set'])) {
  	  $markitup_set = $this->site_settings['markitup_set'];
  	} 
  	
  	// Get the markitup skin
    $markitup_skin = isset($this->site_settings['markitup_skin']) ? $this->site_settings['markitup_skin'] : 'markitup';

    // include stylesheets
    $this->include_css('markitup/skins/' . $markitup_skin . '/style.css');
    $this->include_css('markitup/sets/' . $markitup_set . '/style.css');
  
    // include scripts
    $this->include_js('markitup/jquery.markitup.js');
    $this->include_js('markitup/sets/' . $markitup_set . '/set.js');
    $this->include_js('markitup/sets/' . $markitup_set . '/init.js');
    
    $field_class = 'ksmarkitup-' . $markitup_set;
    $field_output = $DSP->input_textarea($field_name, $field_data, '10', $field_class, '100%');

    /** TODO: CHANGE THIS LATER
    ************************************************************
    * Temporary hack because I can't figure out how to get the field_id in a consistent manner.
    * field settings page is returning field_id as ftype[ftype_id_3][preview], causing DB error.
    * Query: SELECT field_fmt FROM exp_weblog_fields WHERE field_id = ftype[ftype_id_3][preview]
    * Not sure why display_field is being called in the field settings page anyway?
    ************************************************************/
    if (strpos($field_id, 'ftype') === false) {
      $current_formatting = $this->get_current_formatting($field_id);
      $formatting_buttons = $this->_text_formatting_buttons($field_id, $current_formatting);
    } else {
      $formatting_buttons = '';
    }
    /** end hack **********************************************/
    
    return $field_output . $formatting_buttons;

  }



  function display_cell($cell_name, $cell_data, $cell_settings) {
    return $this->display_field($cell_name, $cell_data, $cell_settings);
  }

  function get_current_formatting($id) {
    global $DB, $DSP, $LANG, $IN;
    $ks_def_formatting = 'none';
    $current_format = NULL;
    $entry_id = $IN->GBL('entry_id', 'GET');

    if ($entry_id) {
       $q = $DB->query("SELECT field_ft_{$id} FROM exp_weblog_data WHERE entry_id = {$entry_id};");
       if ($q->num_rows > 0) {
         $current_format = $q->row['field_ft_'.$id];
         $ks_def_formatting = $current_format;
       }
     }
     
     // Decide between Default and Current Formats (i.e. discard NULLs)
    
    // if there is a current format selected....
     if ($curr_format) {
       $ks_def_formatting = $current_format;
     } else {
       $query = $DB->query("SELECT field_fmt FROM exp_weblog_fields WHERE field_id = {$id};");
       if ($query->num_rows > 0) {
         $ks_def_formatting = $query->row['field_fmt'];
       } 
     }
    return $ks_def_formatting;
  }
  
  function _text_formatting_buttons($id, $def_fmt) {
    global $DB, $DSP, $LANG;
    $LANG->fetch_language_file('publish_ad');
    $spacer = NBS.NBS.NBS.NBS.'|'.NBS.NBS.NBS.NBS;

    if ( ! class_exists('Publish')) {
      require PATH_CP.'cp.publish'.EXT;
    }
    $PUB = new Publish();
    $PUB->SPELL = new Spellcheck();

    $query = $DB->query(
      "SELECT field_fmt 
      AS format 
      FROM exp_field_formatting 
      WHERE field_id = {$id} 
      AND field_fmt != 'none' 
      ORDER BY field_fmt"
    );

    if ($PUB->SPELL->enabled === TRUE) {
      if ($this->settings['misc_spellcheck'] == 'y') {
        $spell_check = ' <a href="javascript:void(0);" onclick="toggle_spellcheck(\''.$id.'\');return false;"><b>' .
          $LANG->line('check_spelling').'</b></a>'.$spacer;
      } else {
        $spell_check = '';
      }
    } else {
      $spell_check = '';
    }
  

    $r = 	$DSP->div('xhtmlWrapper').$DSP->qspan('lightLinks', $spell_check).
      $DSP->qspan('xhtmlWrapperLight', $LANG->line('newline_format'));

    $fmt_opt = array();
    foreach($query->result as $res) { 
      $fmt_opt[]=$res['format']; 
    }

    // Display Format Select
  
    $r .= '<select name="field_ft_'.$id.'" class="select mrkitup">'.NL;
    foreach($fmt_opt as $fmt) {
      $name = ucwords(str_replace('_',' ',$fmt));

      if ($name == 'Br') {
        $name = $LANG->line('auto_br');
      }
      elseif ($name == 'Xhtml') {
        $name = $LANG->line('xhtml');
      }

      $sel = ($def_fmt == $fmt) ? 1: '';
      $r .= $DSP->input_select_option($fmt,$name,$sel);
    }
    $sel = ($def_fmt == 'none') ? 1 : 0;
    $r .= $DSP->input_select_option('none', $LANG->line('none'), $sel);
    $r .= $DSP->input_select_footer().NBS;
    $r .= $DSP->div_c();

    return $r;
  }


    /* END class */
}
  
/* End of file ft.ks_markitup.php */
/* Location: ./system/extensions/fieldtypes/ks_markitup/ft.ks_markitup.php */