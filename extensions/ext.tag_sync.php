<?php

if(!defined('EXT'))
{
	exit('Invalid file request');
}

class Tag_sync
{
	var $settings        = array();
	var $name            = 'Tag Synchronizer';
	var $version         = '1.0.1';
	var $description     = 'Synchronize Solspace Tag tags to a custom field when entries are publish or updated, or all at once.';
	var $settings_exist  = 'y';
	var $docs_url        = 'http://github.com/amphibian/ext.tag_sync.ee_addon';
	var $batch_size		 = 1000;

	
	// -------------------------------
	//   Constructor - Extensions use this for settings
	// -------------------------------
	
	function Tag_sync($settings='')
	{
	    $this->settings = $settings;
	}
	// END
	
	
	// --------------------------------
	//  Settings
	// --------------------------------  
	
	function settings_form($current)
	{
		global $DB, $DSP, $IN, $LANG, $PREFS;

		// Synchronize all tags to a specific weblog's custom field
		if(isset($_GET['sync_weblog']) && !empty($current['weblog_id_'.$_GET['sync_weblog']]))
		{
			$sync_weblog = $_GET['sync_weblog'];
			$custom_field = $current['weblog_id_'.$sync_weblog];
			$current_batch = (isset($_GET['batch'])) ? $_GET['batch'] : 1;
			
			// Get batch of entry IDs
			$sql = "SELECT entry_id FROM exp_weblog_titles WHERE weblog_id = ".$DB->escape_str($sync_weblog)." ORDER BY entry_id ASC";
			$entries = $DB->query($sql);
			
			if($entries->num_rows > 0)
			{
				$batches = ceil($entries->num_rows / $this->batch_size);
				$offset = ($current_batch - 1) * $this->batch_size;
				$entry_ids = array();
				foreach($entries->result as $entry)
				{
					$entry_ids[] = $entry['entry_id'];
				}
				$entry_ids = implode(',', array_slice($entry_ids, $offset, $this->batch_size));
			
				// Get an array of tags using this batch's entry ids
				$sql = "SELECT DISTINCT (t.tag_name), e.entry_id FROM exp_tag_entries AS e LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id WHERE e.entry_id IN($entry_ids) ORDER BY e.entry_id ASC";
				$get_tags = $DB->query($sql);
					
				if($get_tags->num_rows > 0)
				{
					$tags = array();
					foreach($get_tags->result as $tag)
					{
						$tags[$tag['entry_id']][] = $tag['tag_name'];
					}

					// Build and run our update statement
					$sql = "UPDATE exp_weblog_data SET ".$DB->escape_str($custom_field)." = CASE entry_id ";
					foreach($tags as $entry_id => $tag)
					{
						$sql .= "WHEN ".$entry_id." THEN '".str_replace(array("'","\""), "", implode(',', $tag))."' ";
					}
					$sql .= " END WHERE entry_id IN($entry_ids)";
					$DB->query($sql);
					
					if($DB->affected_rows == 0)
					{
						$message = $DSP->qspan('text', $LANG->line('nothing_to_sync'));
					}
					else
					{
						$message = $DSP->span('text');
						$message .= $DB->affected_rows.' ';
						$message .= ($DB->affected_rows == 1) ? $LANG->line('entry') : $LANG->line('entries');
						$message .= ' '.$LANG->line('synced_in_batch');
						$message .= $DSP->span_c();
					}		
				}
				else
				{
					$message = $DSP->qspan('text', $LANG->line('nothing_to_sync'));					
				}

				if($current_batch < $batches)
				{
					$message .= ' '.$DSP->anchor(
						BASE.AMP.'C=admin'.
						AMP.'M=utilities'.
						AMP.'P=extension_settings'.
						AMP.'name=tag_sync'.
						AMP.'sync_weblog='.$sync_weblog.
						AMP.'batch='.($current_batch + 1), 
						$LANG->line('run_batch').' '.($current_batch + 1).' '.$LANG->line('of').' '.$batches.'.'
					);
				}
				else
				{
					$message .= $DSP->qspan('success', ' '.$LANG->line('sync_complete'));
				}
			}
			else
			{
				$message = $DSP->qspan('text', $LANG-line('no_entries'));
			}
		}
		
		// Start building the page
		$DSP->crumbline = TRUE;
		
		$DSP->title  = $LANG->line('extension_settings');
		$DSP->crumb  = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities')).
		$DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')));
		$DSP->crumb .= $DSP->crumb_item($this->name);
		
		$DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));
		
		$DSP->body = '';
		
		// Did we just do a sync?
		if(isset($_GET['sync_weblog']))
		{
			$DSP->body .= $DSP->qdiv('box', $message);
		}
		
		$DSP->body .= $DSP->form_open(
			array(
				'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
				'name'   => 'tag_sync',
				'id'     => 'tag_sync'
			),
			array('name' => get_class($this))
		);
		
		// Open the table
		$DSP->body .=   $DSP->table('tableBorder', '0', '', '100%');
		$DSP->body .=   $DSP->tr();
		$DSP->body .=   $DSP->td('tableHeadingAlt', '', '3');
		$DSP->body .=   $this->name;
		$DSP->body .=   $DSP->td_c();
		$DSP->body .=   $DSP->tr_c();	

		// Get a list of weblogs
		$weblogs = array();
		$query = $DB->query("SELECT blog_title, weblog_id FROM exp_weblogs ORDER BY blog_title ASC");
		if($query->num_rows > 0) {
			foreach($query->result as $value)
			{
				$weblogs[$value['weblog_id']] = $value['blog_title'];
			}
		}	
		
		$i = 1;		
		foreach($weblogs as $weblog_id => $name)
		{
			$row_class = ($i % 2) ? 'tableCellTwo' : 'tableCellOne';
			
			// Get a list of text fields for this weblog
			$fields = array('' => '--');
			$sql = "SELECT f.field_id, f.field_label FROM exp_weblogs as w, exp_weblog_fields as f WHERE w.field_group = f.group_id AND w.weblog_id = $weblog_id AND f.field_type = 'text' ORDER BY f.field_order ASC";			
			$query = $DB->query($sql);
			
			if($query->num_rows > 0)
			{
				foreach($query->result as $value)
				{
					$fields['field_id_' . $value['field_id']] = $value['field_label'];
				}
			}			
			
			// Create a settings row for the weblog			
			$DSP->body .=   $DSP->tr();
			$DSP->body .=   $DSP->td($row_class, '45%');
			$DSP->body .=   $DSP->qdiv('defaultBold', $LANG->line('sync_tags').' '.strtoupper($name).' '.$PREFS->core_ini['weblog_nomenclature'].' '.$LANG->line('to_this_field').':');
			$DSP->body .=   $DSP->td_c();
			
			$DSP->body .=   $DSP->td($row_class);
			$DSP->body .=   $DSP->input_select_header('weblog_id_'.$weblog_id, null, null, '200px');
			foreach($fields as $id => $title)
			{
				$DSP->body .= $DSP->input_select_option($id, $title, ( isset($current['weblog_id_'.$weblog_id]) && $current['weblog_id_'.$weblog_id] == $id ) ? 1 : '');
			}
			$DSP->body .=   $DSP->input_select_footer();
			$DSP->body .=   $DSP->td_c();
			
			$DSP->body .=   $DSP->td($row_class.' defaultBold');
			// Should we display the sync link?
			if( isset($current['weblog_id_'.$weblog_id]) && !empty($current['weblog_id_'.$weblog_id]) )
			{
				$DSP->body .= $DSP->anchor(
					BASE.AMP.
					'C=admin'.
					AMP.'M=utilities'.
					AMP.'P=extension_settings'.
					AMP.'name=tag_sync'.
					AMP.'sync_weblog='.$weblog_id, 
					$LANG->line('sync_weblog').' '.$PREFS->core_ini['weblog_nomenclature']
				);
			}
			$DSP->body .=   $DSP->td_c();
			$DSP->body .=   $DSP->tr_c();			
			
			$i++;
		}
		
		// Wrap it up
		$DSP->body .=   $DSP->table_c();
		$DSP->body .=   $DSP->qdiv('itemWrapperTop', $DSP->input_submit());
		$DSP->body .=   $DSP->form_c();	   			
	}
	
	
	function save_settings()
	{
		global $DB;
		
		unset($_POST['name']);
		$data = array('settings' => addslashes(serialize($_POST)));
		$update = $DB->update_string('exp_extensions', $data, "class = 'Tag_sync'");
		$DB->query($update);
	}
		

	function submit_new_entry_absolute_end($entry_id, $data)
	{
        global $DB;
        
        if($this->settings)
        {
	        $get_weblog = $DB->query('SELECT weblog_id FROM exp_weblog_titles WHERE entry_id = '.$entry_id);
	        
	        if( isset($this->settings['weblog_id_'.$get_weblog->row['weblog_id']]) && !empty($this->settings['weblog_id_'.$get_weblog->row['weblog_id']]) )
        	{
				$custom_field = $this->settings['weblog_id_'.$get_weblog->row['weblog_id']];
				
				$sql = "SELECT DISTINCT (t.tag_name) FROM exp_tag_entries AS e LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id WHERE e.entry_id = ".$entry_id;
				$get_tags = $DB->query($sql);
				
				if($get_tags->num_rows > 0)
				{
					$tags = array();
					foreach($get_tags->result as $tag)
					{
						$tags[] = $tag['tag_name'];
					}

					// Build and run our update statement
					$sql = "UPDATE exp_weblog_data SET $custom_field = '".str_replace(array("'","\""), "", implode(',', $tags))."' WHERE entry_id = $entry_id";
					$DB->query($sql);		
				}
			}
		}	
	}  
	
   
	// --------------------------------
	//  Activate Extension
	// --------------------------------
	
	function activate_extension()
	{
	    global $DB;

	    $hooks = array(
	    	'submit_new_entry_absolute_end' => 'submit_new_entry_absolute_end',
	    );
	    
	    foreach($hooks as $hook => $method)
	    {
		    $DB->query($DB->insert_string('exp_extensions',
		    	array(
					'extension_id' => '',
			        'class'        => 'Tag_sync',
			        'method'       => $method,
			        'hook'         => $hook,
			        'settings'     => '',
			        'priority'     => 10,
			        'version'      => $this->version,
			        'enabled'      => "y"
					)
				)
			);
	    }
	    
	}
	// END


	// --------------------------------
	//  Update Extension
	// --------------------------------  
	
	function update_extension($current='')
	{
	    global $DB;
	    
	    if ($current == '' OR $current == $this->version)
	    {
	        return FALSE;
	    }
	    
	    $DB->query("UPDATE exp_extensions 
	                SET version = '".$DB->escape_str($this->version)."' 
	                WHERE class = 'Tag_sync'");
	}
	// END
	
	
	// --------------------------------
	//  Disable Extension
	// --------------------------------
	
	function disable_extension()
	{
	    global $DB;
	    
	    $DB->query("DELETE FROM exp_extensions WHERE class = 'Tag_sync'");
	}
	// END


}
// END CLASS