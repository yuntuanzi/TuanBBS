<?php
require_once 'common/functions.php';

user_logout();

header('Location: index.php');
exit;