<?php
require_once "db.php";
require_once "auth.php";

logout_user($pdo);

header("Location: index.php");
exit;
