<?php

require_once('net/http/httputils.php');

/**
 * UClassifyConnection serves as an interface towards the
 * UClassify API.
 */
class UClassifyConnection
{
	var $_httpRequest;
	var $_readApiKey;
	var $_writeApiKey;
	var $_uclassifyUrl;


	function UClassifyConnection(&$httpRequest,
	                             $readApiKey,
	                             $writeApiKey,
								 $uclassifyUrl)
	{
		$this->_httpRequest  = $httpRequest;
		$this->_readApiKey   = $readApiKey;
		$this->_writeApiKey  = $writeApiKey;
		$this->_uclassifyUrl = $uclassifyUrl;
	}


	/**
	 * Checks if a classifier exists.
	 *
	 * @return array(false, errormessage) upon failure. 
	 *         Returns array(true, existence) on success.
	 */
	function exists($classifierName)
	{
		$innerXml =
			'<readCalls readApiKey="' . $this->_readApiKey . '" >' .
			'  <getInformation id="GetInformation" classifierName="' . $classifierName . '"/>' .
			'</readCalls>';

		$getInfoXml = $this->_createXml($innerXml);
		$getInfoRet = $this->_readWriteXml($getInfoXml);

		if (!$getInfoRet[0]) return $getInfoRet;

		//xml response successfully read from the server.
		//We treat a successful response (success="true") as an existance
		//of the classifier, and all other error codes as it
		//does not exist.
		return array(true, $this->_isSuccess($getInfoRet[1]));
	}


	/**
	 * Creates a classifier with the specified name.
	 *
	 * @return array(true) on success. Returns array(false, error message)
	 *         upon failure.
	 */
	function createClassifier($classifierName)
	{
		$existsRet = $this->exists($classifierName);
		if (!$existsRet[0]) return $existsRet;

		if ($existsRet[1])
		{
			//The classifier already exist. Return success.
			//TODO? Maybe we should check that it has the
			//required classes "spam" and "legitimate".
			return array(true);
		}

		$innerXml =
			'<writeCalls writeApiKey="' . $this->_writeApiKey . '" classifierName="' . $classifierName . '">' .
			'  <create id="CreateId"/>' .
			'  <addClass id="AddSpam" className="spam"/>' .
			'  <addClass id="AddLegitimate" className="legitimate"/>' .
			'</writeCalls>';

		$createXml = $this->_createXml($innerXml);
		$createRet = $this->_readWriteXml($createXml);

		if ($createRet[0] && $this->_isSuccess($createRet[1]))
		{
			return array(true);
		}

		return array(false, $createRet[1]);
	}


	/**
	 * Removes the specified classifier.
	 *
	 * @return array(true) on success. Returns array(false, error message)
	 *         upon failure.
	 */
	function removeClassifier($classifierName)
	{
		$existsRet = $this->exists($classifierName);
		if (!$existsRet[0]) return $existsRet;

		if (!$existsRet[1])
		{
			//Classifier did not exists. We treat this as a success
			//of the removeClassifier function. No nead to remove
			//something that is not there.
			return array(true);
		}

		$innerXml =
			'<writeCalls writeApiKey="' . $this->_writeApiKey . '" classifierName="' . $classifierName . "\">\n" .
			"  <remove id=\"removeid\"/>\n" .
			'</writeCalls>';

		$xml = $this->_createXml($innerXml);
		$removeRet = $this->_readWriteXml($xml);

		if ($removeRet[0] && $this->_isSuccess($removeRet[1]))
		{
			return array(true);
		}

		return array(false, $removeRet[1]);
	}


	/**
	 * Train a number of classifiers with a text. The text can be tagged as
	 * spam or legitimate. It is also possible to specify an untrain flag
	 * which will untrain the text from the "previous" class.
	 *
	 * @param input  Input data to train on.
	 *               Spec: array(array(classifierName, text,   isSpam, untrain), ..)
	 *               where isSpam and untrain are booleans.
	 *
	 *               Note: At the moment, you can only train on one classifier
	 *               in the same call to train(..). This may be relaxed in
	 *               future versios of the UClassify API.
	 *
	 * @return array(true) on success. Returns array(false, error message)
	 *         upon failure.
	 */
	function train($input)
	{
		if (!is_array($input) || count($input) == 0)
			return array(false, "Invalid input data");

		$texts      = '';
		$writeCalls = array();
		$id = 0;
		foreach ($input as $value)
		{
			if (!is_array($value) || count($value) != 4 ||
				!is_string($value[0]) || !is_string($value[1]) ||
				!is_bool($value[2]) || !is_bool($value[3]))
			{
				return array(false, "Invalid input data");
			}

			$classifierName = $value[0];
			$text           = $value[1];
			$isSpam         = $value[2];
			$untrain        = $value[3];

			if (!isset($writeCalls[$classifierName]))
			{
				$writeCalls[$classifierName] = '';
			}
			
			$className = $isSpam ? 'spam' : 'legitimate';
			$untrainXml = '';
			if ($untrain)
			{
				$untrainClassName = $isSpam ? 'legitimate' : 'spam';
				$writeCalls[$classifierName] = 
					$writeCalls[$classifierName] . 
				    '  <untrain id="untrain' .  $id . '" className="' . $untrainClassName . "\" textId=\"text$id\"/>\n";
			}

			$texts      .= '  <textBase64 id="text' . $id . '">' . base64_encode($text) . "</textBase64>\n";			

			$writeCalls[$classifierName] = 
				$writeCalls[$classifierName] . 
				'  <train id="train' . $id . '" className="' . $className . "\" textId=\"text$id\"/>\n";

			$id++;
		}

		$innerXml = "<texts>\n$texts</texts>\n";

		assert(is_array($writeCalls));
		foreach ($writeCalls as $cname => $xml)
		{
			$innerXml .= '<writeCalls writeApiKey="' . $this->_writeApiKey . '" classifierName="' . $cname . '">' .
			             "\n$xml" .
			             "</writeCalls>\n";
		}

		$trainXml = $this->_createXml($innerXml);

		$trainRet = $this->_readWriteXml($trainXml);
		if (!$trainRet[0])
		{
			return $trainRet;
		}

		if (!$this->_isSuccess($trainRet[1]))
		{
			return array(false, $trainRet[1]);
		}

		return array(true);
	}


	/**
	 * Classifies the specified input.
	 * @param input  Input data to the classifier. Spec: array(callId => array(classifierName, text))
	 *
	 * @return array(true, array(callId => spam_prob)) on success. Returns array(false, error message)
	 *         upon failure.
	 */
	function classify($input)
	{
		if (!is_array($input) || count($input) == 0)
			return array(false, "Invalid input data");

		$texts     = '';
		$readCalls = '';
		$c = 0;
		foreach ($input as $callId => $value)
		{
			if (!is_array($value) || count($value) != 2)
				return array(false, "Invalid input data");

			$classifierName = $value[0];
			$text           = $value[1];
			$texts     .= "  <textBase64 id=\"text$c\">" . base64_encode($text) . "</textBase64>\n";
			$readCalls .= "  <classify id=\"$callId\" classifierName=\"$classifierName\" textId=\"text$c\"/>\n";
			$c++;
		}
			
		$innerXml = 
			"<texts>\n$texts</texts>\n" .
			"<readCalls readApiKey=\"$this->_readApiKey\">\n$readCalls\n</readCalls>\n";

		$classifyXml = $this->_createXml($innerXml);
		$classifyRet = $this->_readWriteXml($classifyXml);
		if (!$classifyRet[0])
		{
			return $classifyRet;
		}

		if (!$this->_isSuccess($classifyRet[1]))
		{
			return array(false, $classifyRet[1]);
		}

		return array(true, $this->_getProbabilities($classifyRet[1]));
	}


	/**
	 * $return A complete UClassify Request XML document from the $innerXml.
	 */
	function _createXml($innerXml)
	{
		return
			'<?xml version="1.0" encoding="utf-8" ?>' . "\n" .
			'<uclassify xmlns="http://api.uclassify.com/1/RequestSchema" version="1.00">' . "\n" .
			$innerXml . "\n" .
			"</uclassify>\n";
	}

	/**
	 * Sends the xml on the request object and read the response.
	 *
	 * @return array(true, server response) on success.
	 *         array(false, error message) on failure.
	 */
	function _readWriteXml($xml)
	{
		$clsRet = $this->_httpRequest->request($this->_uclassifyUrl,
		                                       false,
		                                       "Content-type: text/xml; charset=utf-8\r\n",
		                                       $xml);

		if (!$clsRet->success)
		{
			//Classification failed. Return error back to the user.
			assert($clsRet->errorMessage != '');
			return array(false, $clsRet->errorMessage);
		}

		//Content was downloaded from the classification server. Check the headers.
		if (getHttpStatusCode($clsRet->headers) !== 200)
		{
			//Web server did not respond with 200 OK. Report this
			//along with the headers which should give the user an idea of
			//what is going wrong.
			$error = "Errors encountered while communicating with classification server. Server responded: " .
					 $clsRet->headers;
			return array(false, $error);
		}

		return array(true, $clsRet->content);
	}

	/**
	 * @return true if the xml string indicates a success-response from
	 *         the UClassify server.
	 */
	function _isSuccess($xml)
	{
		//A bit ugly, if the are additional whitespaces the check will
		//not work. Does not handle upper case letters either.
		return strpos($xml, 'status success="true"') !== false;
	}

	/**
	 * @return an array with "classify id" as index, and spam probability as value.
	 */
	function _getProbabilities($xml)
	{
		$r = preg_match_all('/<classify id="([^"]*)">\\s*<classification.*<class[ ]+className="spam"[ ]+p="(.*)"/iUs',
		                    $xml,
		                    $matches);

		assert($r !== false);

		$ids   = $matches[1];
		$probs = $matches[2];
		assert(count($ids) == count($probs));
		$res = array();
		for ($i=0; $i<count($ids); $i++)
		{
			$res[$ids[$i]] = (float)$probs[$i];
		}
		return $res;
	}
}

?>