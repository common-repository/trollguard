<?php
 
require_once('HttpRequest.php');
require_once('httputils.php');
require_once('net/netutils.php');


function debugTrace($s1, $s2)
{
	//print("$s1 $s2\n");
	return true;
}

class HttpRequestImpl extends HttpRequest
{
	var $_follow30x;

	function HttpRequestImpl($follow30x)
	{
		$this->_follow30x = $follow30x;
	}

	function _request($url, $useGet, $headers, $content='')
	{
		//debugTrace('function downloadPage($url)', 'ENTER');

		$urlComponents = @parse_url($url);
		assert($urlComponents !== null);
		if ($urlComponents === false || $urlComponents === null)
		{
			return new HttpResponse(false, "Could not parse url '$url'.", '', '');
		}

		// Get the IP address for the target host.
		assert(is_array($urlComponents));
		$host = array_key_exists('host', $urlComponents) ? $urlComponents['host'] : '';
		$isIpAddressRes = isIpAddress($host);
		if ($isIpAddressRes[0])
		{
			$address = $host;
		}
		else
		{
			if ($host === '')
			{
				return new HttpResponse(false, "Could not parse url '$url'. Host name appears to be missing.", '', '');
			}
			$address = @gethostbyname($host);
			if ($address == $host)
			{
				//debugTrace('function downloadPage($url)', 'RETURN 1');
				return new HttpResponse(false, "Could not convert '$host' to an IP address.", '', '');
			}
		}

		$service_port = array_key_exists('port', $urlComponents) ? $urlComponents['port'] : '80';
		$path = array_key_exists('path', $urlComponents) ? $urlComponents['path'] : '';

		// Create a TCP/IP socket.
		$socket = @fsockopen($address, $service_port, $errno, $errMsg);
		if ($socket === false) 
		{
			//debugTrace('function downloadPage($url)', 'RETURN 2');
			return new HttpResponse(false, 'Error creating socket: ' . $errMsg, '', '');
		}

		$path = ltrim($path, '/');

		if ($useGet)
		{
			$q = array_key_exists('query', $urlComponents) ? ('?' . $urlComponents['query']) : '';
			$in  = "GET /$path$q HTTP/1.0\r\n";
			$in .= "Host: $host\r\n";
			$in .= $headers;
			$in .= "Connection: Close\r\n\r\n";
		}
		else
		{
			$in  = "POST /$path HTTP/1.0\r\n";
			$in .= "Host: $host\r\n";
			$in .= $headers;
			$in .= "Connection: Close\r\n";
			$in .= "Content-Length: " . strlen($content). "\r\n\r\n";
			$in .= $content;
		}
		$out = '';

		//debugTrace($in, 'PRINT');

		$writeResult = fwrite($socket, $in);
		
		$maxLen = 999999999;
		$contentLength = $maxLen;
		$headerLength = $maxLen;
		$totalLen = 0;
		$chunk = '';
		$readBufferSize = 8000;
		$breakCounter = 0;
		while (//debugTrace('beginning of while loop', 'PRINT') &&
			   ($headerLength + $contentLength > $totalLen) && 
			   (($chunk = fread($socket, $readBufferSize)) !== false)) 
		{
		   if ($chunk == '') 
		   {
			   break;
		   }

		   $totalLen += strlen($chunk);
		   $out .= $chunk;

		   if ($headerLength + $contentLength < $totalLen) {/*echo "Breaking loop";*/ break;}

		   if ($headerLength == $maxLen)
		   {
			   $headerEndPos = strpos($out, "\r\n\r\n");
			   if ($headerEndPos)
			   {
				   $headerLength = $headerEndPos;
			   }
		   }

		   if ($contentLength == $maxLen)
		   {
			   $clPos = strpos($out, 'Content-Length: ');
			   if ($clPos)
			   {
				   $endPos  = strpos($out, "\r", $clPos);
				   if ($endPos)
				   {
					   $begin = $clPos + strlen('Content-Length: ');
					   $contentLengthString = (int)substr($out, $begin, $endPos - $begin);
					   $contentLength = (int)$contentLengthString;
				   }
			   }
		   }
		   //debugTrace('bottom of while loop', 'PRINT');
		}

		//debugTrace("totalLen: $totalLen<br>", 'PRINT');

		fclose($socket);

		$endOfHeader = strpos($out, "\r\n\r\n");
		if ($endOfHeader === false)
		{
			return new HttpResponse(false,
			                        'Could not find end of headers in response. Response: ' . substr($out, 0, 512), 
			                        '',
			                        '');
		}

		$endOfHeader += 4;
		$header = substr($out, 0, $endOfHeader);
		$out = substr($out, $endOfHeader);
		if ($out === false)
		{
			//Out did only contain headers.
			$out = '';
		}

		//debugTrace('function downloadPage($url)', 'RETURN 5');
		return new HttpResponse(true, '', $header, $out);
	}

	function request($url, $useGet, $headers, $content='')
	{
		//Keep track of the number of HTTP 30x redirects. Stop at 3.
		$c = 0; 
		$res = $this->_request($url, $useGet, $headers, $content);
		while ($c++ < 3)
		{
			assert(is_object($res));
			$scode = getHttpStatusCode($res->headers);
			if ($res->success && 
				($scode === 301 ||
				 $scode === 302 ||
				 $scode === 303 ||
				 $scode === 307) &&
				$this->_follow30x)
			{
				$location = 'Location: ';
				$locpos = strposi($res->headers, $location);
				if ($locpos === false) return $res;

				$endpos = strpos($res->headers, "\r", $locpos);
				if ($endpos === false) return $res;

				$startpos = $locpos + strlen($location);
				$newurl = substr($res->headers, $startpos, $endpos - $startpos);
				$res = $this->_request($newurl, $useGet, $headers, $content);
			}
			else
			{
				return $res;
			}
		}

		//Too many redirects. Return result from the last HTTP request.
		return $res;
	}
}

/**
 * Downloads a web page.
 */
function downloadPage($url, $useGet=true, $postContent='')
{
	$encoded = '';
	$headers = '';

	if (!$useGet)
	{
		foreach ($postContent as $a) 
		{
			$encoded .= ($encoded !== '' ? '&' : '') . urlencode($a[0]) . '=' . urlencode($a[1]);
		}
		$headers = "Content-Type: application/x-www-form-urlencoded\r\n";
	}

	$h = new HttpRequestImpl(true);
	return $h->request($url, $useGet, $headers, $encoded);
}

?>