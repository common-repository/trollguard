<?php

class TrollCommentToData
{
	function format(
		$author,
		$authorEmail,
		$authorUrl,
		$content,
		$userIp,
		$userAgent)
	{
		$authorUrlFragments = $this->_getUrlFragments($authorUrl);

		$data =
			$author . " " .
			$authorEmail . " " .
			$authorUrl . " " .
			$authorUrlFragments . " " .
			$content . " " .
			$userIp . " " .
			$userAgent;

		return trim($data);
	}

	function _getUrlFragments($url)
	{
		$delimiters =  "-/_";
		$tok = strtok($url, $delimiters);

		$frags = "";
		while ($tok !== false)
		{
			$frags .= $tok . " ";
			$tok = strtok($delimiters);
		}
		return trim($frags);
	}
}

?>