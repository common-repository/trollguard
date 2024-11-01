<?php

class TrollTable
{
	var $tableName;
	function TrollTable($tableName)
	{
		$this->tableName = $tableName;
	}

	function emptyTable()
	{
		global $wpdb;
		$wpdb->query("DELETE FROM " . $this->tableName);
	}

	function removeTable()
	{
		global $wpdb;
		if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->tableName . "'") !== $this->tableName)
			return;

		$wpdb->query("DROP TABLE " . $this->tableName);
	}
}

?>