<?php

/**
 * Network utility functions.
 */

/**
 *    Dotted quad IPAddress within valid range? true or false
 *    Checks format, leading zeros, and values > 255
 *    Does not check for reserved or unroutable IPs.
 */
function isIpAddress($ip)
{
	//Possible improvements, but it will not give any error message.
	//$longip = ip2long($ip);
	//return array(long2ip($longip) == $ip, "");

    if (empty($ip))
    {
        return array(false, 'IP address empty.');
    }

    //    123456789012345
    //    xxx.xxx.xxx.xxx

    $len = strlen($ip);
    if( $len > 15 )
    {
		return array(false, "IP address is too long. [$ip]");
    }

    $Bad = eregi_replace("([0-9\.]+)", "", $ip);
    if (!empty($Bad))
    {
		return array(false, "Bad data in the IP address [$Bad]");
    }
    $chunks = explode(".", $ip);
    $count = count($chunks);

    if ($count != 4)
    {
		return array(false, "IP address is not a dotted quad [$ip]");
    }

	foreach($chunks as $val)
    {
		//Check for 0 in the beginning of each chunk
        if (ereg("^0[1-9]|^00[1-9]",$val))
        {
			return array(false, "Invalid IP address segment [$val] [$ip]");
        }
		else if (strlen($val) == 0)
		{
			return array(false, "Empty address segement in [$ip]");
		}
        $Num = $val;
        settype($Num, 'integer');
        if ($Num > 255)
        {
			return array(false, "Invalid IP address.  Segment out of range [$Num] [$ip]");
        }
    }

    return array(true, '');

}    // end is_ipaddress

?>