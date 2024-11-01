<?php

require_once("trollConstants.php");
require_once("TrollComments.php");
require_once("TrollOptions.php");
require_once('net/http/HttpRequestImpl.php');
require_once('net/socket/FClientSocketFactoryImpl.php');

class TrollClassifier
{
	var $uclassifyConnection;
	var $classifierName;

	function TrollClassifier($classifierName)
	{
		global $trollOptions;
		$socketFactory = new FClientSocketFactoryImpl;
		$httpRequest   = new HttpRequestImpl($socketFactory);

		$this->uclassifyConnection = new UClassifyConnection(
			$httpRequest,
			$trollOptions->getReadKey(),
			$trollOptions->getWriteKey(),
			"http://api.uclassify.com");

		$this->classifierName = $classifierName;
	}

	function classify($requests)
	{
		return $this->uclassifyConnection->classify($requests);
	}
}

function trollReturnSpam($a) { return 'spam'; }

class TrollClassifierAccumulator
{
	var $comments = array();
	var $classifier;

	function TrollClassifierAccumulator($classifier)
	{
		$this->classifier = $classifier;
	}

	function add($comment)
	{
		$this->comments[] = $comment;
	}

	function addArray($comments)
	{
		$this->comments = array_merge($this->comments, $comments);
	}

	function addPending()
	{
		global $wpdb;
		$comments = $wpdb->get_results("SELECT * FROM $wpdb->comments WHERE comment_approved ='0'", ARRAY_A);
		$this->addArray($comments);
	}

	// returns array(false, error), array(true, array(id, spam%))
	function execute($changeStatus)
	{
		global $wpdb, $trollLog, $trollOptions, $trollComments;

		if (count($this->comments) === 0)
			return array(false, "No comments to classify.");

		if ($trollOptions->getRequireTraining())
		{
			$numLeg = $trollOptions->getTrainedOnNumberOfLegitimate();
			$numSpam = $trollOptions->getTrainedOnNumberOfSpam();
			if ($numLeg < TrollRequiredTraining || $numSpam < TrollRequiredTraining)
			{
				$trollLog->log("Received a comment - but awaiting more training.");
				return array(false, "Received a comment - but awaiting more training.");
			}
		}

		// Build requests
		$requests = array();
		$trollCommentsAdapter = new TrollCommentsAdapter($trollComments);
		$num = 0;
		$size = 0;
		foreach($this->comments as $comment)
		{
			$data = $trollCommentsAdapter->getCommentData($comment);
			$callId = array_key_exists("comment_ID", $comment) ? $comment["comment_ID"] : "id_" . ($num ++);
			$requests[$callId] = array($this->classifier->classifierName, $data);
			$size += strlen($data) * 1.5;
			if ($size > 800000) break;
		}

		$result = $this->classifier->classify($requests);

		if (!$result[0])
		{
			$trollLog->error("Error when classifying a comment. " . $result[1]);
			return array(false, "Error when classifying a comment. " . $result[1]);
		}

		$results = $result[1];

		// Get spam cutoff
		$spamCutoff = $trollOptions->getSpamCutoff();
		if ($spamCutoff != strval(floatval($spamCutoff)) ||
			$spamCutoff < 0.5 || $spamCutoff > 1.0)
		{
			$trollLog->error("It seems like the spam cutoff value is wrong: '$spamCutoff'. Please see the config page. Falling back on the default value.");
			$spamCutoff = TrollDefaultSpamCutoff;
		}

		// Move messages and log
		$ret = array();
		foreach($results as $id => $probability)
		{
			$commentLink = "comment";

			$isSpam = $probability > $spamCutoff;
			if ($changeStatus)
			{
				assert(is_int($id));
				$link = admin_url('comment.php?action=editcomment&amp;c=') . $id;
				$commentLink = '<a href="'. $link . '" title="Open comment">comment</a>';

				if ($isSpam)
					$wpdb->query("UPDATE $wpdb->comments SET comment_approved='spam' WHERE comment_ID=$id");
			}

			$p = trollFormatProbability($probability);

			if ($isSpam)
				$trollLog->log("A $commentLink was classified as spam. Probability of spam: $p%.");
			else
				$trollLog->log("A $commentLink was classified as legitimate. Probability of spam: $p%.");

			$ret[] = array($id, $isSpam);
		}

		$this->comments = array();
		return array(true, $ret);
	}
}

function trollFormatProbability($probability)
{
	$p = number_format($probability*100, 5, '.', '');
	return rtrim(rtrim($p, '0'), '.');
}

function trollCommentPreprocess($comment, $classifier)
{
	// remove any previous filter
	remove_filter('pre_comment_approved', 'trollReturnSpam');

	// classify
	$accumulator = new TrollClassifierAccumulator($classifier);
	$accumulator->add($comment);
	$result = $accumulator->execute(false);

	// something went wrong
	if (!$result[0])
		return $comment;

	// get result (classifications->classification->(id, isSpam)
	$classifications = $result[1];
	assert(count($classifications) === 1);
	$classification = $classifications[0];

	// not spam?
	if ($classification[1])
		add_filter('pre_comment_approved', 'trollReturnSpam');

	return $comment;
}

function trollActionCommentPreprocess($comment)
{
	$classifier = new TrollClassifier(TrollClassifierContentName);
	return trollCommentPreprocess($comment, $classifier);
}

function trollClassifyPending()
{
	$classifier = new TrollClassifier(TrollClassifierContentName);
	$acc = new TrollClassifierAccumulator($classifier);
	$acc->addPending();
	$acc->execute(true);
}

function trollActionClassifyPending()
{
	trollClassifyPending();
	wp_redirect($_SERVER['HTTP_REFERER']);
	exit;
}

function trollGetClassifyButton($status)
{
	if ($status === 'spam' || $status === 'approved')
		return "";

	global $trollOptions;
	$numLeg = $trollOptions->getTrainedOnNumberOfLegitimate();
	$numSpam = $trollOptions->getTrainedOnNumberOfSpam();
	if (!($numLeg >= TrollRequiredTraining && $numSpam >= TrollRequiredTraining))
		return "";

	global $wpdb;
	$row = $wpdb->get_row("SELECT count(*) as n FROM $wpdb->comments WHERE comment_approved ='0'");
	if ($row->n == 0)
		return "";

	return '</div><div class="alignleft"><a class="button-secondary" href="admin.php?action=trollActionClassifyPending">' . __('Check pending for Spam') . "</a>";
}

function trollDisplayClassifyButton($status)
{
	echo trollGetClassifyButton($status);
}

?>