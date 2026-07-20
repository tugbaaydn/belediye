<?php
require_once 'includes/functions.php';
start_session();
$_SESSION = [];
session_destroy();
redirect('login.php');
