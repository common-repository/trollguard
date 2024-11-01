<?php
require_once("trollSystem.php");
require_once("TrollOptions.php");
require_once("TrollComments.php");
require_once("UClassifyConnection.php");

require_once('net/http/HttpRequestImpl.php');
require_once('net/socket/FClientSocketFactoryImpl.php');

function trollActionAddConfigPage()
{
	if (!function_exists('add_submenu_page'))
		return;

	$page = add_submenu_page(
		'options-general.php',
		__(TrollPluginName),
		__(TrollPluginName),
		'manage_options',
		'troll-config-page',
		'trollConfigPage');

	// Add hook to header so we can link .css
	add_action('admin_head-' . $page, 'trollActionHeaderConfigPage');
}

function trollActionHeaderConfigPage()
{
	print('<link rel="stylesheet" type="text/css" href="' . TrollPluginUrl . 'trollStyle.css" />');
}

function trollSetupClassifier($readKey, $writeKey)
{
	global $trollOptions;
	$socketFactory = new FClientSocketFactoryImpl;
	$httpRequest   = new HttpRequestImpl($socketFactory);

	$uc = new UClassifyConnection(
		$httpRequest,
		$trollOptions->getReadKey(),
		$trollOptions->getWriteKey(),
		"http://api.uclassify.com");

	// Creates the classifier if it doesn't exist
	return $uc->createClassifier(TrollClassifierContentName);
}

function trollSaveOptions($readKey, $writeKey, $enableLog, $spamCutoff)
{
	global $trollOptions;
	$trollOptions->setReadKey($readKey);
	$trollOptions->setWriteKey($writeKey);
	$trollOptions->setSpamCutoff($spamCutoff);
	$trollOptions->setLogEnabled($enableLog);

	global $trollLog;
	$trollLog->enable($enableLog);
}

function trollTrainOnUntrained()
{
	$trainer = new TrollTrainer(TrollClassifierContentName);
	$accumulator = new TrollTrainAccumulator($trainer);
	$accumulator->addUntrained();
	$accumulator->execute();
}


function trollPrintLocalMessage($msg) { print('<p class="trollMessage">' . __($msg) . '</p>'); }
function trollPrintLocalWarning($msg) { print('<p class="trollWarning">' . __($msg) . '</p>'); }
function trollPrintLocalError($msg) { print('<p class="trollError">' . __($msg) . '</p>'); }

function trollConfigPage()
{
	global $trollLog, $trollOptions, $trollComments;
	$spamCutoffErrorMsg = "";
	if (isset($_POST['TrollSaveOptions']))
	{
		if (function_exists('current_user_can') && !current_user_can('manage_options'))
			die(__('You must be logged in as the administrator to do this.'));
		//check_admin_referer($troll_nonce);

		$spamCutoff = $_POST[TrollOptionSpamCutoff];
		if ($spamCutoff != strval(floatval($spamCutoff)))
		{
			$spamCutoffErrorMsg = "It seems like the spamcutoff '$spamCutoff' is not numeric. You must set a value between 50% and 100%. You can use decimals. Falling back on the default value.";
			$spamCutoff = TrollDefaultSpamCutoff;
		}
		else
			$spamCutoff /= 100;

		if ($spamCutoff < 0.50 || $spamCutoff > 1.0)
		{
			$spamCutoffErrorMsg = "You must set a value between 50% and 100%. You can use decimals. Falling back on the default value.";
			$spamCutoff = TrollDefaultSpamCutoff;
		}

		trollSaveOptions(
			$_POST[TrollOptionReadKey],
			$_POST[TrollOptionWriteKey],
			isset($_POST[TrollOptionEnableLog]),
			$spamCutoff);
	}

	if (isset($_POST['TrollEmptyLog']))
		$trollLog->emptyTable();

	if (isset($_POST['TrollTrainOnUntrained']))
		trollTrainOnUntrained();

	$readKey = $trollOptions->getReadKey();
	$readKeyErrorMsg = $readKey === false ? 'You need to enter a read API key from uclassify.com.' : '';
	$writeKey = $trollOptions->getWriteKey();
	$writeKeyErrorMsg = $writeKey === false ? 'You need to enter a write API key from uclassify.com.' : '';

	$errorMessage = '';
	if (!empty($readKey) && !empty($writeKey))
	{
		$res = trollSetupClassifier($readKey, $writeKey);
		if ($res[0] === false)
			$errorMessage = $res[1];
	}

	// Header
	print('<div class="wrap"><h2>' . __(TrollPluginName . ' Configuration') . '</h2>');

	// Description
	print('<div class="trollConfigWrap">');
	print('This is a beta - we really need your <a href="mailto:' . TrollEmail . '?subject=Feedback" title="Send a feedback e-mail.">feedback</a>!');

	$numLeg = $trollOptions->getTrainedOnNumberOfLegitimate();
	$numSpam = $trollOptions->getTrainedOnNumberOfSpam();
	$enoughTraining = $numLeg >= TrollRequiredTraining && $numSpam >= TrollRequiredTraining;

	if ($errorMessage)
		trollPrintLocalError("Not working (see below)");
	if ($readKeyErrorMsg || $writeKeyErrorMsg)
		trollPrintLocalError("Needs configuration (see below)");
	else if (!$enoughTraining)
		trollPrintLocalWarning("Awaiting more training (see below)");
	else
		trollPrintLocalMessage("Working");

	// Start form
	print('<form action="" method="post" id="trollSaveOptions" >');

	// Read api key
	print('<div class="trollConfigPart">');
	print('<h3>' . __('uClassify.com Read API Key') . '</h3>');

	// Read key input box
	print('<div><input id="readKey" name="' . TrollOptionReadKey . '" type="text" size="34" maxlength="32" value="' .
		$readKey . '" /></div>');
	print("<div>" . __('Obtain this key for free at <a href="' . TrollUClassifySignUpUrl . '">uClassify.com</a></div>'));

	if (!empty($readKeyErrorMsg))
		trollPrintLocalError($readKeyErrorMsg);

	print('</div>');

	// Write api key
	print('<div class="trollConfigPart">');
	print('<h3>' . __('uClassify.com Write API Key') . '</h3>');

	// Write key input box
	print('<div><input id="writeKey" name="' . TrollOptionWriteKey . '" type="text" size="34" maxlength="32" value="' .
		$writeKey . '"/></div>');
	print("<div>" . __('Obtain this key for free at <a href="' . TrollUClassifySignUpUrl . '">uClassify.com</a></div>'));

	if (!empty($writeKeyErrorMsg))
		trollPrintLocalError($writeKeyErrorMsg);

	print('</div>');

	$spamCutoff = $trollOptions->getSpamCutoff();

	// Spam cutoff
	print('<h3>' . __('Spam Cutoff') . '</h3>');
	print('<div> &gt;' .
		'<input id="spamCutoff" name="' . TrollOptionSpamCutoff . '" type="text" size="8" maxlength="8" value="' .
		($spamCutoff * 100) . '" ' .
		'title="A high value means lower risk for false positives. You can use decimals (eg. 99.9)." ' .
		'/>%</div>');

	if (!empty($spamCutoffErrorMsg))
		trollPrintLocalError($spamCutoffErrorMsg);

	// Enable log checkbox
	print('<h3>' . __('Logging') . '</h3>');
	$checked = $trollOptions->getLogEnabled() ? ' checked="checked" ' : '';
	print('<input id="enableLog" name="' . TrollOptionEnableLog . '" type="checkbox" value="enableLog"' .
		$checked . '/>' . __('Enable logging'));

	// Submit button
	print('<div class="submit"><input type="submit" name="TrollSaveOptions" value="' . __('Save options') . '"/></div>');

	if (!empty($errorMessage))
		trollPrintLocalError($errorMessage);

	print_r('<h2>Training</h2>');
	// Remaining training
	if (!$enoughTraining)
		print('Before ' . __(TrollPluginName) . ' starts to protect you from spam it <span class="trollWarning">requires that you train it ' .
			'on ' . TrollRequiredTraining . ' spam and ' . TrollRequiredTraining . ' legitimate</span>. When this is done it will activate itself! ');
 	print(
		"It's currently trained on <strong>$numLeg legitimate</strong> and <strong>$numSpam spam</strong> comments. " .
		"Training occurs when you click on 'Approve' or 'Spam' in the WordPress 'Comments' menu. " .
		"More training means better results!");

	$untrainedLegitimate = count($trollComments->getUntrainedLegitimate());
	$untrainedSpam = count($trollComments->getUntrainedSpam());
	if (empty($errorMessage) && empty($readKeyErrorMsg) && empty($writeKeyErrorMsg) &&
		($untrainedLegtimate > 0 || $untrainedSpam > 0))
	{
		print(
			'<form action="" method="post" id="trollTrainOnUntrained"> ' .
			"<p>There are $untrainedLegitimate approved and $untrainedSpam" .
			' spam comments that ' . TrollPluginName . ' has not been trained on. Would you like to train it on those? ' .
			'<span class="submit"><input type="submit" name="TrollTrainOnUntrained" value="' . __('Train') . '"/></span></p></form>');
	}

	print('</form></div>'); // width div

	$logDisabled = $trollOptions->getLogEnabled() ? "" : " (Disabled)";

	// Show log
	print('<h2>' . TrollPluginName . ' Log ' . $logDisabled . '</h2>');
	print('<div>');

	$logs = $trollLog->get(15);
	foreach($logs as $log)
	{
		$msgClass = $log->type === 'error' ? 'trollError' : 'trollMessage';
		print('<div class="' . $msgClass . '">' . __($log->date) . ' - ' . __($log->message) . '</div>');
	}
	print('<form action="" method="post" id="trollEmptyLog"><p class="submit"><input type="submit" name="TrollEmptyLog" value="' . __('Empty log') . '"/></p></form>');

	print('</div></div>'); //	wrap
}

?>