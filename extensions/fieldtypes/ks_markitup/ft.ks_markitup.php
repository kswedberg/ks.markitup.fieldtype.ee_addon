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
    global $DSP, $FF, $IN;
    
    $field_id = isset($FF->row['field_id']) ? $FF->row['field_id'] : null ;
    
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

    if ($field_id) {
      $current_formatting = $this->get_current_formatting($field_id);

      $formatting_buttons = $this->text_formatting_select($field_id, $current_formatting);
    } else {
      $formatting_buttons = '';
    }
    
    return $field_output . $formatting_buttons;

  }

  function display_cell($cell_name, $cell_data, $cell_settings) {
    return $this->display_field($cell_name, $cell_data, $cell_settings);
  }
    

  function display_tag($params, $tagdata, $field_data, $field_settings) {
    global $TMPL, $FF;
    $this_row = $FF->weblog->query->row;
    $this_field_id = $FF->field_id;
    $parse_images = $FF->weblog->TYPE->parse_images;
    $parse_options = array(
      'text_format' => $this_row['field_ft_' . $this_field_id],
      'html_format'   => $this_row['weblog_html_formatting'],
      'auto_links'    => $this_row['weblog_auto_link_urls'],
      'allow_img_url' => $this_row['weblog_allow_img_urls'],
      'parse_images' => $parse_images
    );
    
    if ( ! class_exists('Typography')) {
      require PATH_CORE.'core.typography'.EXT;
    }
    $TYPE = new Typography;

    $parsed_contents = $TYPE->parse_type( $field_data, $parse_options );

    return $parsed_contents;
}


  function get_current_formatting($id) {
    global $DB, $FF, $IN;
    $selected_formatting = '';
    
    $entry_id = $IN->GBL('entry_id', 'GET');
    $posted_formatting = isset($_POST['field_ft_'.$FF->row['field_id']]) ? $_POST['field_ft_'.$FF->row['field_id']] : null;

    if ($entry_id) {
       $query = $DB->query("SELECT field_ft_{$id} FROM exp_weblog_data WHERE entry_id = {$entry_id};");
       if ($query->num_rows > 0) {
         $selected_formatting = $query->row['field_ft_'.$id];
       }
     } elseif ($posted_formatting) {
       $selected_formatting = $posted_formatting;
     } else {
       $selected_formatting = $FF->row['field_fmt'];
     }

    return $selected_formatting;
  }
  
  function text_formatting_select($id, $def_fmt) {
    global $DB, $DSP, $LANG;

    $query = $DB->query(
      "SELECT field_fmt 
      AS format 
      FROM exp_field_formatting 
      WHERE field_id = {$id} 
      AND field_fmt != 'none' 
      ORDER BY field_fmt"
    );

    $fmt_opt = array();
    foreach($query->result as $res) { 
      $fmt_opt[]=$res['format']; 
    }

    // Display Format Select
    $r = $DSP->div('xhtmlWrapper') . $DSP->qspan('xhtmlWrapperLight', $LANG->line('newline_format'));
    $r .= '<select name="field_ft_'.$id.'" class="select mrkitup">'. NL;
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
