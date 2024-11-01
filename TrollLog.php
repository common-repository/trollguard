<?php

require_once("trollConstants.php");
require_once("TrollOptions.php");
require_once("TrollTable.php");

class TrollLog extends TrollTable
{
	var $enabled;
	function TrollLog()
	{
		global $wpdb;
		parent::TrollTable($wpdb->prefix . TrollLogTableName);

		$this->enabled = true;

		if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->tableName . "'") === $this->tableName)
			return;

		$charset_collate = '';
		if ($wpdb->has_cap('collation'))
		{
			if (! empty($wpdb->charset))
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if (! empty($wpdb->collate))
				$charset_collate .= " COLLATE $wpdb->collate";
		}

		$sql = "CREATE TABLE " . $this->tableName . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			message text NOT NULL,
			date datetime NOT NULL,
			UNIQUE KEY id (id)
			) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function log($message)
	{
		$this->_insert("log", $message);
	}

	function error($message)
	{
		$this->_insert("error", $message);
	}

	function get($max)
	{
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM " . $this->tableName . " ORDER BY date DESC LIMIT 0, $max");
	}

	function enable($enable)
	{
		$this->enabled = $enable;
	}

	function _insert($type, $message)
	{
		global $wpdb;
		if (!$this->enabled)
			return;
		$data = mysql_escape_string($message);
		$insert =
			"INSERT INTO " . $this->tableName . " (type, message, date) " .
			"VALUES ('$type', '$data', NOW())";
		$wpdb->query($insert);
	}

}

if (!isset($trollLog))
{
	// This creates a global troll database
	$trollLog = new TrollLog();
	$trollLog->enable($trollOptions->getLogEnabled());
}

?>