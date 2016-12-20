<?php
/*
   Plugin Name: Mqtt Cogs
   Plugin URI: http://wordpress.org/extend/plugins/mqtt-cogs/
   Version: 0.1
   Author: Chris Knowles
   Description: Integrate IOT projects into your blog
   Text Domain: mqtt-cogs
   License: GPLv3
  */

/*
    "WordPress Plugin Template" Copyright (C) 2016 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This following part of this file is part of WordPress Plugin Template for WordPress.

    WordPress Plugin Template is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WordPress Plugin Template is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/

$MqttCogs_minimalRequiredPhpVersion = '5.0';

/**
 * Check the PHP version and give a useful error message if the user's version is less than the required version
 * @return boolean true if version check passed. If false, triggers an error which WP will handle, by displaying
 * an error message on the Admin page
 */
function MqttCogs_noticePhpVersionWrong() {
    global $MqttCogs_minimalRequiredPhpVersion;
    echo '<div class="updated fade">' .
      __('Error: plugin "Mqtt Cogs" requires a newer version of PHP to be running.',  'mqtt-cogs').
            '<br/>' . __('Minimal version of PHP required: ', 'mqtt-cogs') . '<strong>' . $MqttCogs_minimalRequiredPhpVersion . '</strong>' .
            '<br/>' . __('Your server\'s PHP version: ', 'mqtt-cogs') . '<strong>' . phpversion() . '</strong>' .
         '</div>';
}


function MqttCogs_PhpVersionCheck() {
    global $MqttCogs_minimalRequiredPhpVersion;
    if (version_compare(phpversion(), $MqttCogs_minimalRequiredPhpVersion) < 0) {
        add_action('admin_notices', 'MqttCogs_noticePhpVersionWrong');
        return false;
    }
    return true;
}


/**
 * Initialize internationalization (i18n) for this plugin.
 * References:
 *      http://codex.wordpress.org/I18n_for_WordPress_Developers
 *      http://www.wdmac.com/how-to-create-a-po-language-translation#more-631
 * @return void
 */
function MqttCogs_i18n_init() {
    $pluginDir = dirname(plugin_basename(__FILE__));
    load_plugin_textdomain('mqtt-cogs', false, $pluginDir . '/languages/');
}

//////////////////////////////////
// CK Add new cron schedule
/////////////////////////////////
function my_cron_schedules($schedules){
	
    if(!isset($schedules["1min"])){
        $schedules["1min"] = array(
            'interval' => 60,
            'display' => __('Once every 1 minutes'));
	    }
	    return $schedules;
    }

add_filter('cron_schedules','my_cron_schedules');


//////////////////////////////////
// Run initialization
/////////////////////////////////


// Initialize i18n
add_action('plugins_loadedi','MqttCogs_i18n_init');

// Run the version check.
// If it is successful, continue with initialization for this plugin
if (MqttCogs_PhpVersionCheck()) {
    // Only load and run the init function if we know PHP version can parse it
    include_once('mqtt-cogs_init.php');
    MqttCogs_init(__FILE__);
}
