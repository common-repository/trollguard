<?php

require_once("trollConstants.php");

class TrollOptions
{
	function getLogEnabled()
	{
		return get_option(TrollOptionEnableLog, "1") === "1" ? true : false;
	}

	function setLogEnabled($enable)
	{
		if ($enable === null)
			delete_option(TrollOptionEnableLog);
		else if ($enable === true)
			update_option(TrollOptionEnableLog, "1");
		else
			update_option(TrollOptionEnableLog, "0");
	}

	function getReadKey()
	{
		return get_option(TrollOptionReadKey);
	}

	function setReadKey($readKey)
	{
		if (empty($readKey))
			delete_option(TrollOptionReadKey);
		else
			update_option(TrollOptionReadKey, $readKey);
	}

	function getWriteKey()
	{
		return get_option(TrollOptionWriteKey);
	}

	function setWriteKey($writeKey)
	{
		if (empty($writeKey))
			delete_option(TrollOptionWriteKey);
		else
			update_option(TrollOptionWriteKey, $writeKey);
	}

	function getSpamCutoff()
	{
		return get_option(TrollOptionSpamCutoff, TrollDefaultSpamCutoff);
	}

	function setSpamCutoff($spamCutoff)
	{
		if (empty($spamCutoff))
			delete_option(TrollOptionSpamCutoff);
		else
			update_option(TrollOptionSpamCutoff, $spamCutoff);
	}

	function getRequireTraining()
	{
		return get_option(TrollOptionRequireTraining, "1") === "1" ? true : false;
	}

	function setRequireTraining($enable)
	{
		if ($enable === null)
			delete_option(TrollOptionRequireTraining);
		else if ($enable === true)
			update_option(TrollOptionRequireTraining, "1");
		else
			update_option(TrollOptionRequireTraining, "0");
	}

	function getTrainedOnNumberOfLegitimate()
	{
		return get_option(TrollOptionTrainedOnNumLeg, 0);
	}

	function setTrainedOnNumberOfLegitimate($numberOfLegitimate)
	{
		if (empty($numberOfLegitimate))
			delete_option(TrollOptionTrainedOnNumLeg);
		else
			update_option(TrollOptionTrainedOnNumLeg, $numberOfLegitimate);
	}

	function getTrainedOnNumberOfSpam()
	{
		return get_option(TrollOptionTrainedOnNumSpam, 0);
	}

	function setTrainedOnNumberOfSpam($numberOfSpam)
	{
		if (empty($numberOfSpam))
			delete_option(TrollOptionTrainedOnNumSpam);
		else
			update_option(TrollOptionTrainedOnNumSpam, $numberOfSpam);
	}

	function clearAll()
	{
		$this->setLogEnabled(null);
		$this->setReadKey(null);
		$this->setWriteKey(null);
		$this->setSpamCutoff(null);
		$this->setRequireTraining(null);
		$this->setTrainedOnNumberOfSpam(null);
		$this->setTrainedOnNumberOfLegitimate(null);
	}
}

if (!isset($trollOptions))
	$trollOptions = new TrollOptions();

?>