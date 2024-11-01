<?php

/**
 * Client socket based on fsockopen, fread, fwrite, fclose.
 * The shutdown method uses stream_socket_shutdown, which
 * requires php 5+.
 */
class FClientSocketImpl 
{
	var $_socket = null;

	function connect($address, $service_port)
	{
		$ret = @fsockopen($address, $service_port, $errno, $errMsg);
		if ($ret === false)
		{
			return array(false, "Failed to connect to $address:$service_port. $errMsg. errno: $errno.");
		}
		$this->_socket = $ret;
		return array(true);
	}

	function read($len)
	{
		assert(is_resource($this->_socket));

		$s = fread($this->_socket, $len);
		if ($s === false)
		{
			//TODO: Get a real error message.
			return array(false, "Error reading data from socket");
		}
		return array(true, $s);
	}

	function write($data)
	{
		assert(is_resource($this->_socket));

		$ret = fwrite($this->_socket, $data);
		if ($ret === false)
		{
			//TODO: Get a real error message.
			return array(false, "Error writing data to socket");
		}
		return array(true, $ret);
	}

	function shutdown($how)
	{
		assert(is_resource($this->_socket));

		$res = stream_socket_shutdown($this->_socket, $how);
		if ($res === false)
		{
			return array(false, "Error shutting down socket. how=$how");
		}
		return array(true);
	}

	function close()
	{
		$ret = fclose($this->_socket);
		if ($ret === false)
		{
			//TODO: Get a real error message.
			return array(false, "Error closing socket");
		}
		$this->_socket = null;
		return array(true);
	}
}

?>