<?php

require_once("trollConstants.php");
require_once("TrollTable.php");

class TrollComments extends TrollTable
{
	var $tableName;
	function TrollComments()
	{
		global $wpdb;
		parent::TrollTable($wpdb->prefix . TrollCommentsTableName);

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
			commentId bigint(20) NOT NULL,
			trainedAs varchar(20) NOT NULL,
			data text NOT NULL,
			UNIQUE KEY id (id)
			) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function exists($commentId)
	{
		global $wpdb;
		$result = $wpdb->query("SELECT commentId FROM " . $this->tableName . " WHERE commentId=$commentId");
		return $result !== 0;
	}

	function insert($commentId, $trainedAs, $data)
	{
		global $wpdb;
		$data = mysql_escape_string($data);
		$insert =
			"INSERT INTO " . $this->tableName . " (commentId, trainedAs, data) " .
			"VALUES ('$commentId', '$trainedAs', '$data')";

		$results = $wpdb->query($insert);
	}

	function update($commentId, $trainedAs)
	{
		global $wpdb;
		$update =
			"UPDATE " . $this->tableName . " SET trainedAs='$trainedAs' WHERE commentId=$commentId";
		$results = $wpdb->query($update);
	}

	function get($commentId)
	{
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM " . $this->tableName . " WHERE commentId=$commentId");
	}

	function delete($commentId)
	{
		global $wpdb;
		$wpdb->query("DELETE FROM " . $this->tableName . " WHERE commentId=$commentId");
	}

	function getUntrainedSpam()
	{
		return $this->_getUntrained(true);
	}

	function getUntrainedLegitimate()
	{
		return $this->_getUntrained(false);
	}

	function _getUntrained($spam)
	{
		global $wpdb;
		$match = $spam === true ? 'spam' : '1';
		$opposite = $spam === true ? '1' : 'spam';
		$wpTable = $wpdb->comments;
		$trollTable = $this->tableName;
		$query = "SELECT comment_ID FROM $wpTable LEFT JOIN $trollTable " .
			"ON ($wpTable.comment_ID = $trollTable.commentId) WHERE " .
			"$wpTable.comment_approved='$match' AND " .
			"($trollTable.commentId IS NULL OR $trollTable.trainedAs='$opposite')";

		$results = $wpdb->get_results($query);
		$ret = array();
		foreach($results as $res)
			$ret[] = $res->comment_ID;
		return $ret;
	}
}

class TrollCommentsAdapter
{
	var $_trollComments;
	function TrollCommentsAdapter($trollComments)
	{
		$this->_trollComments = $trollComments;
	}

	function add($comment, $trainedAs)
	{
		assert(array_key_exists("comment_ID", $comment));
		$data = $this->_commentToData($comment);
		$this->_trollComments->insert($comment["comment_ID"], $trainedAs, $data);
	}

	function getCommentData($comment)
	{
		if (!array_key_exists("comment_ID", $comment))
			return $this->_commentToData($comment);

		if (is_array($comment))
			$commentId = $comment["comment_ID"];
		else
			$commentId = $comment->comment_ID;

		$trollComment = $this->_trollComments->get($commentId);
		if ($trollComment !== null)
			return $trollComment->data;
		else
			return $this->_commentToData($comment);
	}

	function _commentToData($comment)
	{
		$commentToData = new TrollCommentToData();

		if (is_array($comment))
			return $commentToData->format(
				$comment['comment_author'],
				$comment['comment_author_email'],
				$comment['comment_author_url'],
				$comment['comment_content'],
				isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "",
				isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
		else
			return $commentToData->format(
				$comment->comment_author,
				$comment->comment_author_email,
				$comment->comment_author_url,
				$comment->comment_content,
				isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "",
				isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
	}
}


if (!isset($trollComments))
	$trollComments = new TrollComments();

?>