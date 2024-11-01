<?php

require_once("TrollComments.php");
require_once("TrollCommentToData.php");
require_once("trollConstants.php");
require_once("TrollOptions.php");
require_once("TrollLog.php");

class TrollTrainer
{
	var $uclassifyConnection;

	function TrollTrainer($classifierName)
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

	function train($requests)
	{
		return $this->uclassifyConnection->train($requests);
	}
}

class TrollTrainAccumulator
{
	var $commentIds = array();
	var $trainer;

	function TrollTrainAccumulator($trainer)
	{
		$this->trainer = $trainer;
	}

	function add($commentId)
	{
		$this->commentIds[] = $commentId;
	}

	function addArray($commentIds)
	{
		$this->commentIds = array_merge($this->commentIds, $commentIds);
	}

	function addUntrained()
	{
		global $trollComments;
		$legIds = $trollComments->getUntrainedLegitimate();
		$spamIds = $trollComments->getUntrainedSpam();
		$this->addArray($legIds);
		$this->addArray($spamIds);
	}

	function execute()
	{
		global $wpdb, $trollComments, $trollOptions, $trollLog;

		$legAdder = 0;
		$spamAdder = 0;
		$size = 0;
		$requests = array();
		$localReq = array();

		$trollCommentsAdapter = new TrollCommentsAdapter($trollComments);
		// Build a request
		foreach($this->commentIds as $commentId)
		{
			// Get the comment
			$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID = '$commentId'");

			// Comment was deleted
			if (!$comment)
			{
				$trollComments->delete($commentId);
				continue;
			}
			else if ($comment->comment_approved !== 'spam' && $comment->comment_approved !== '1')
				continue;

			$trainAsSpam = $comment->comment_approved === 'spam';

			// Check if untraining is necessary
			$untrain = false;
			$previousTrained = $trollComments->get($commentId);
			if ($previousTrained !== null)
				$untrain = $previousTrained->trainedAs !== $comment->comment_approved;

			// Add it for training
			$data = $trollCommentsAdapter->getCommentData($comment);
			$requests[] = array($this->trainer->classifierName, $data, $trainAsSpam, $untrain);
			$localReq[] = array($commentId, $data, $trainAsSpam, $untrain);

			// Count
			if ($trainAsSpam)
			{
				++ $spamAdder;
				if ($untrain)
					-- $legAdder;
			}
			else
			{
				++ $legAdder;
				if ($untrain)
					-- $spamAdder;
			}
			
			$size += strlen($data) * 1.5;
			if ($size > 800000) break;
		}

		$this->commentIds = array();

		if (count($requests) === 0)
			return;

		// execute
		$result = $this->trainer->train($requests);

		if (!$result[0])
		{
			$trollLog->error("Error when training. " . $result[1]);
			return;
		}

		// Keep count of training
		$trollOptions->setTrainedOnNumberOfSpam(
			max(0, $trollOptions->getTrainedOnNumberOfSpam() + $spamAdder));
		$trollOptions->setTrainedOnNumberOfLegitimate(
			max(0, $trollOptions->getTrainedOnNumberOfLegitimate() + $legAdder));

		// Insert or update troll comment database
		foreach($localReq as $request)
		{
			$commentId = $request[0];
			$data = $request[1];
			$trainAsSpam = $request[2];
			$untrain = $request[3];

			if ($untrain === true)
				$trollComments->update($commentId, $trainAsSpam ? 'spam' : '1', $data);
			else
				$trollComments->insert($commentId, $trainAsSpam ? 'spam' : '1', $data);

			// Log it
			$trainedAs = $trainAsSpam ? "spam" : "legitimate";

			$link = admin_url('comment.php?action=editcomment&amp;c=') . $commentId;
			$commentLink = '<a href="'. $link . '" title="Open comment">comment</a>';

			$trollLog->log("Trained a $commentLink as '$trainedAs'.");
		}
	}
}

function trollCommentIdStatusChanged($commentId, $trainer)
{
	$trollAccumulator = new TrollTrainAccumulator($trainer);
	$trollAccumulator->add($commentId);
	$result = $trollAccumulator->execute();
}

// Entry point from Wordpress action
function trollActionCommentIdStatusChanged($commentId)
{
	$trainer = new TrollTrainer(TrollClassifierContentName);
	trollCommentIdStatusChanged($commentId, $trainer);
}

?>