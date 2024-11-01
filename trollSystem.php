<?php

require_once("trollConstants.php");

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

function trollPrintMessage($message)
{
	echo '<div id="message" class="updated fade">' . __($message) . '</div>';
}

function trollPrintError($message)
{
	echo '<div id="message" class="error">' . __($message) . '</div>';
}

function trollPluginLoaded()
{
	global $wp_version;
	if ($wp_version >= TrollWPMinVersion)
		return;

	$message = TrollPluginName . ' requires WordPress ' . TrollWPMinVersion . ' or later. Your version is ' . $wp_version .'.';

	if (function_exists('deactivate_plugins'))
	{
		deactivate_plugins(TrollMainFile, true);
		$message .= ' It has automatically been deactivated.';
	}

	trollPrintError($message);
}

?>