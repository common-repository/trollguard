<?php

require_once('FClientSocketImpl.php');

class FClientSocketFactoryImpl 
{
	function create()
	{
		return new FClientSocketImpl;
	}
}

?>