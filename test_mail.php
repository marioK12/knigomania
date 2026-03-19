<?php
require_once "mailer.php";

$ok = km_send_mail(
  "YOUR_TEST_EMAIL@gmail.com",
  "Test",
  "Test mail",
  "<b>Hello</b> от КнигоМания ✅",
  true
);

echo $ok ? "OK" : "FAIL";
