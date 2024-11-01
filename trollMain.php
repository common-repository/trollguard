<?php
/*
Plugin Name: TrollGuard
Plugin URI: http://www.trollguard.com/
Description: TrollGuard is a WordPress plugin that learns to filter out spam comments by using <a href="http://www.uclassify.com/" title="uClassify">uClassify.com</a> for classification. It requires Wordpress 2.7 or later.
Version: 0.9
Author: uClassify
Author URI: http://www.uclassify.com/
*/

/*
	Copyright 2008  Jon Kågström (uClassify)  (email : misc AT uclassify DOT com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once("trollSystem.php");
require_once("trollConfigPage.php");
require_once("trollCommentPreprocess.php");
require_once("trollCommentTrain.php");

// Add loaded action
add_action('plugins_loaded', 'trollPluginLoaded');

// Action to add sub menu
add_action('admin_menu', 'trollActionAddConfigPage');

// Action to hook comments before they are processed
add_action('preprocess_comment', 'trollActionCommentPreprocess', 1);

// If a comments status is changed
add_action('wp_set_comment_status', 'trollActionCommentIdStatusChanged');

// If the commment was edited or updated
add_action('edit_comment', 'trollActionCommentIdStatusChanged');

// Add the action to display the classify pending comments button
add_action('manage_comments_nav', 'trollDisplayClassifyButton');

// Add admin action to classify pending comments
add_action('admin_action_trollActionClassifyPending', 'trollActionClassifyPending');

?>
