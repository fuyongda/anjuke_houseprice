<?php
include('CURL.php');
include('phpQuery.php');
$db=new PDO('mysql:dbname=new;host=127.0.0.1','root','');
$db->exec("set names utf8");
$curl=new CUrl();
