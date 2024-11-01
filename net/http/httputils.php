<?php

/**
 * HTTP utility functions.
 */



 class ContentTypeInfo
 {
	 function ContentTypeInfo($suc, $ct = '', $cs = '')
	 {
		 $this->success = $suc;
		 $this->contentType = $ct;
		 $this->charset = $cs;
	 }

	 var $success;
	 var $contentType;
	 var $charset;
 }

/**
 * Returns the content type charset stated in the HTTP headers.
 * Returns [true, content-type, charset] upon success.
 *
 * For example: [true, text/html, ISO-8859-1] is returned when
 * <meta http-equiv="Content-type" content="text/html; charset=ISO-8859-1" />
 * appear in the html
 */
function getContentTypeInfoFromHtml($html)
{
	//First, trim down the document to only use the data before the <body> tag.
	$bodypos = 0;
	if (($bodypos = strpos($html, '<body')) !== FALSE)
	{
		$html = substr($html, 0, $bodypos);
	}
	else if (($bodypos = strpos($html, '<BODY')) !== FALSE)
	{
		$html = substr($html, 0, $bodypos);
	}

	$r = preg_match('/<meta[ ]+http-equiv[ ]*=[ ]*"Content-type"[ ]+content[ ]*=[ ]*"([^"]+)"/i',
                    $html,
	                $matches);
	
	if ($r !== 1)
	{
		return new ContentTypeInfo(false, '', '');
	}

	return getContentTypeInfoFromHeaders('Content-Type: ' . $matches[1]);
}

/**
 * Returns the content type charset stated in the HTTP headers.
 * Returns [true, content-type, charset] upon success.
 *
 * For example: [true, text/html, utf-8], is returned when
 * passing "Content-Type: text/html; charset=utf-8"
 */
function getContentTypeInfoFromHeaders($headers)
{
	$CONTENT_TYPE = 'Content-Type:';
	$SEMICOLON    = ';';
	$CHARSET      = 'charset=';
	
	$cpos = strpos($headers, $CONTENT_TYPE);
	if ($cpos === FALSE)
	{
		return new ContentTypeInfo(false);
	}

	$crlfpos = strpos($headers, "\r\n", $cpos);
	$crlfpos = $crlfpos === false ? strlen($headers) : $crlfpos;

	$semipos = strpos($headers, $SEMICOLON, $cpos);
	
	$startpos = $cpos + strlen($CONTENT_TYPE);
	$endpos = $semipos ? $semipos : $crlfpos;
	$contentType = substr($headers,
	                      $startpos,
	                      $endpos - $startpos);

	if ($semipos === FALSE)
	{
		return new ContentTypeInfo(true, itrim($contentType), '');
	}

	//Charset is optional
	$charsetpos = strpos($headers, $CHARSET, $semipos);
	if ($charsetpos === FALSE)
	{
		return new ContentTypeInfo(true, itrim($contentType), '');
	}
	
	$startpos = $charsetpos + strlen($CHARSET);
	$charset = substr($headers,
	                  $startpos,
	                  $crlfpos - $startpos);
	return new ContentTypeInfo(true, itrim($contentType), itrim($charset));
}

/**
 * Returns the status code from HTTP response header.
 * Example input/output: 
 * HTTP/1.1 301 Moved Permanently -> 301
 * HTTP/1.0 302 -> 302
 * HTTP/1.1 200 OK -> 200
 * HTTP/12.451 200 OK -> 200
 *
 * Returns -1 the status code is not found.
 */
function getHttpStatusCode($headers)
{
	//The status line is always in the beginning of the headers.
	if (strpos($headers, 'HTTP/') !== 0)
		return -1;

	//Find the first whitespace after HTTP/
	$firstWs = strpos($headers, ' ', 5);
	if ($firstWs === false)
		return -1;
	
	//Find the second white space or carriage return.
	$endPos1 = strpos($headers, ' ', $firstWs+1);
	$endPos2 = strpos($headers, "\r", $firstWs+1);
	if ($endPos1 === false && $endPos2 === false)
		return -1;
	
	if ($endPos1 !== false && $endPos2 !== false)
		$endPos = min($endPos1, $endPos2);
	else
		$endPos = $endPos1 === false ? $endPos2 : $endPos1;

	$istr = substr($headers, $firstWs+1, $endPos-$firstWs-1);
	$i = intval($istr);
	if ($i === 0 && $istr !== "0")
		return -1;

	assert(is_int($i));
	return $i;
}

/**
 * Trims and convert to lower case.
 */
function itrim($s)
{
	return trim(strtolower($s));
}

?>