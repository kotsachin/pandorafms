<?php

//Pandora FMS- http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

return;

/**
 * The code for to show in the operation menu entry.
 */
function main_view() {
	// Load global vars
	global $config;
	
	require_once("update_pandora.php");
	
	check_login ();
	
	if (! check_acl ($config["id_user"], 0, "PM")) {
		db_pandora_audit("ACL Violation",
			"Trying to access event viewer");
		require ("general/noaccess.php");
		return;
	}
	
	um_db_connect ('mysql', $config['dbhost'], $config['dbuser'],
		$config['dbpass'], $config['dbname'], $config['dbport']);
	
	$settings = um_db_load_settings ();
	$user_key = get_user_key ($settings);
	
	$buttons = array(
		'admin' => array(
		'active' => false,
		'text' => '<a href="index.php?sec=gextensions&sec2=extensions/update_manager">' . 
			html_print_image ("images/setup.png",
				true, array ("title" => __('Update manager'))) .'</a>'));
	
	ui_print_page_header (__('Update manager'), "images/extensions.png",
		false, "", false, $buttons);
	
	if (enterprise_installed()) {
		if (is_array($user_key)) {
			if (!$user_key['result']) {
				ui_print_error_message($user_key['message']);
			}
		}
		main_view_enterprise($settings, $user_key);
	}
	else {
		main_view_open($settings, $user_key);
	}
}

function main_view_enterprise($settings, $user_key) {
	global $config;
	
	require_once("debug.php");
	
	print_debug_message_trace("Init Call main_view_enterprise function.");
	
	$update_package = (bool) get_parameter_post ('update_package');
	
	if ($update_package) {
		print_debug_message_trace("Enter in update package.");
		if (enterprise_installed()) {
			$force = (bool) get_parameter_post ('force_update');
			
			$success = um_client_upgrade_to_latest ($user_key, $force);
			/* TODO: Add a new in tnews */
			
			ui_print_result_message($success,
				__('Success update to the last package.'),
				__('Error update to the last package.'));
			
			//Reload the update manager settings
			
			$settings = um_db_load_settings ();
			$user_key = get_user_key ($settings);
			if (is_array($user_key)) {
				if (!$user_key['result']) {
					ui_print_error_message($user_key['message']);
				}
			}
		}
		else {
			ui_print_error_message(__('This is an Enterprise feature. Visit %s for more information.', '<a href="http://pandorafms.com">http://pandorafms.com</a>'));
		}
	}
	
	$upload_package = (bool)get_parameter('upload_package');
	if ($upload_package) {
		if (!empty($_FILES)) {
			install_offline_enterprise_package($settings, $user_key);
		}
	}
	
	
	echo '<h4>';
		if ($settings->current_update == 0) 
			echo __('You do not have installed any updates of Pandora FMS Enterprise');
		else
			echo __('Your Pandora FMS Enterprise version number is') . ' ' .
				$settings->current_update;
	echo '</h4>';
	
	echo "<br><br>";
	
	
	/* Translators: Do not translade Update Manager, it's the name of the program */
	ui_print_info_message(
		'<p>' .
			__('The new <a href="http://updatemanager.sourceforge.net">Update Manager</a> client is shipped with Pandora FMS It helps system administrators to update their Pandora FMS automatically, since the Update Manager does the task of getting new modules, new plugins and new features (even full migrations tools for future versions) automatically.') .
		'</p>' .
		'<p>' .
			__('Update Manager is one of the most advanced features of Pandora FMS Enterprise version, for more information visit <a href="http://pandorafms.com">http://pandorafms.com</a>.') .
		'</p>' .
		'<p>' .
		__('Update Manager sends anonymous information about Pandora FMS usage (number of agents and modules running). To disable it, remove remote server address from Update Manager plugin setup.') .
		'</p>');
	
	
	echo '<h4>' . __('Online') . '</h4>';
	$table = null;
	$table->width = '98%';
	$table->size = array();
	$table->size[0] = '60%';
	$table->size[1] = '15%';
	$table->size[2] = '25%';
	$table->colspan = array();
	$table->colspan[0][0] = 3;
	$table->data = array();
	$table->data[1][0] = '<span id="box_ajax_checking_online">' .
		__('Checking for an update') . '&nbsp;' . html_print_image('images/spinner.gif', true) .
		'</span>';
	$table->data[1][1] = html_print_button(__('Details'),
		'details_online', true, 'show_details();', 'class="sub search"', true);
	$table->data[1][2] = __('Force') . ': ' .
		html_print_checkbox ('force_update', '1', false, true) .
		html_print_submit_button(__('Update'), 'update_online', true,
			'class="sub upd"', true);
	
	echo '<form method="post">';
	html_print_input_hidden ('update_package', 1);
	html_print_table($table);
	echo '</form>';
	
	
	
	?>
	<div id="dialog" title="<?php echo __('Details packge'); ?>"
		style="display: none;">
		<div style="position:absolute; top:20%; text-align: center; left: 0%; right: 0%; width: 600px;">
			<div id="dialog_version_package" style="margin-left: 40px; margin-bottom: 20px; text-align: left;">
			</div>
			<div id="dialog_description" style="margin-left: 40px; text-align: left; height: 250px; width: 550px; overflow: auto;">
			</div>
			<div id="button_close" style="position: absolute; top:280px; right:43%;">
				<?php
				html_print_submit_button(__("Close"),
					'hide_dialog', false, 'class="ui-button-dialog ui-widget ui-state-default ui-corner-all ui-button-text-only" style="width:100px;"');
				?>
			</div>
		</div>
	</div>
	<?php
	
	
	echo '<h4>' . __('Offline') . '</h4>';
	$table = null;
	$table->width = '98%';
	$table->data = array();
	$table->data[1][0] = '<h5>'.__('Offline packages loader'). ' ' . ui_print_help_tip (__('Apply, in order, the packets corresponding to your current version'), true) . '</h5>' . 
		'<input type="hidden" name="upload_package" value="1">' .
		'<input type="file" size="55" name="fileloaded">' . 
		'&nbsp;<input class="sub next" type="submit" name="upload_button" value="' . __('Upload') . '">';
	
	echo '<form method="post" enctype="multipart/form-data">';
	html_print_table($table);
	echo '</form>';
	
	?>
	<script type="text/javascript">
		$(document).ready(function() {
			$("#submit-hide_dialog").click (function () {
				$("#dialog" ).dialog('close');
			});
			
			ajax_checking_online_enterprise_package();
		});
		
		function show_details() {
			$("#dialog").dialog({
					resizable: false,
					draggable: false,
					modal: true,
					height: 430,
					width: 630,
					overlay: {
							opacity: 0.5,
							background: "black"
						}
				});
			$("#dialog").show();
		}
		
		function ajax_checking_online_enterprise_package() {
			var parameters = {};
			parameters['page'] = 'extensions/update_manager';
			parameters['checking_online_enterprise_package'] = 1;
			
			$.ajax({
				type: 'POST',
				url: 'ajax.php',
				data: parameters,
				dataType: "json",
				success: function(data) {
					$("#box_ajax_checking_online").html(data['text']);
					$("#dialog_version_package").html(data['version_package_text']);
					$("#dialog_description").html(data['details_text']);
					
					if (data['enable_buttons']) {
						$("input[name='details_online']").removeAttr("disabled");
						$("input[name='update_online']").removeAttr("disabled");
					}
				}
			});
		}
	</script>
	<?php
	
	print_debug_message_trace("End Call main_view_enterprise function.");
}

function main_view_open($settings, $user_key) {
	global $config;
	
	update_pandora_administration($settings, $user_key);
}
?>