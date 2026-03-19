<?php
// mailer.php (uses your /PHPMailer/*.php structure + localhost SSL fix)

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/Exception.php";
require_once __DIR__ . "/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/SMTP.php";

function km_send_mail(string $toEmail, string $toName, string $subject, string $body, bool $isHtml = true): bool {
  $mail = new PHPMailer(true);

  try {
    // Server
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;

    // ✅ ТУК: сложи app password (16 chars, без интервали)
    $mail->Username   = "mariokanturski187@gmail.com";
    $mail->Password   = "pcujsxxlzzmmqtrm";

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = "UTF-8";

    // ✅ Localhost SSL fix (като в send-mail.php)
    $mail->SMTPOptions = [
      'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
      ]
    ];

    // From / To
    $mail->setFrom($mail->Username, "КнигоМания");
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    // Content
    $mail->isHTML($isHtml);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = $isHtml ? strip_tags($body) : $body;

    $mail->send();
    return true;

  } catch (Exception $e) {
    // по желание лог:
    // file_put_contents(__DIR__ . '/mail-error.txt', "[".date('Y-m-d H:i:s')."] ".$mail->ErrorInfo.PHP_EOL, FILE_APPEND);
    return false;
  }
}
