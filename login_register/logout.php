<?php
require_once __DIR__ . '/../includes/functions.php';
session_destroy();
redirect('/login_register/login.php');
