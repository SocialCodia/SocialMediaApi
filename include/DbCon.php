<?php

class DbCon{

	private $con;
	function Connect()
	{
		$this->con = new mysqli('localhost','root','','socialmedia');
		if(mysqli_connect_error())
		{
			echo "Failed to conenct with database server";
			return false;
		}
		return $this->con;
	}

}