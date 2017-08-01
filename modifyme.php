<?php
include_once 'isWordPress.php';

$cls1 = @new isWordPress('https://make.wordpress.org/');
var_dump($cls1->result); // wordpress.org is made by WordPress, true

$cls2 = @new isWordPress('https://www.yahoo.com/');
var_dump($cls2->result); // yahoo.com is not made by WordPress, false

var_dump($cls2);  //another some infomation exist.
