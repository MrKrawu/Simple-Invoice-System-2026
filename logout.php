<?php
	require_once "bootstrap.php";
	$_SESSION = [];
	session_destroy();
	header("location:login.html");
