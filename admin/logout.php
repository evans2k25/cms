<?php

require_once __DIR__ . '/../helpers/auth.php';

logout();

header('Location: login.php');
exit;