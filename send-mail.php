<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contacts.php");
    exit;
}

// 1. Вземане и валидация на данните
$name    = trim($_POST["name"] ?? "");
$email   = filter_var(trim($_POST["email"] ?? ""), FILTER_VALIDATE_EMAIL);
$subject = trim($_POST["subject"] ?? "Съобщение от сайта");
$message = trim($_POST["message"] ?? "");

// Проверка за задължителни полета
if (!$email || empty($name) || empty($message)) {
    header("Location: contacts.php?status=invalid");
    exit;
}

$mail = new PHPMailer(true);

try {
    // 2. Настройки на сървъра
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mariokanturski187@gmail.com';
    // ВАЖНО: Тук поставете 16-цифрената парола от "App Passwords" на Google
    $mail->Password   = 'pcujsxxlzzmmqtrm';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // 3. Фикс за Localhost
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // 4. Получатели
    $mail->setFrom('mariokanturski187@gmail.com', 'Контакт форма');
    $mail->addAddress('mariokanturski187@gmail.com'); 
    $mail->addReplyTo($email, $name);

    // 5. Съдържание
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body    = "Име: $name\nИмейл: $email\n\nСъобщение:\n$message";

    $mail->send();
    header("Location: contacts.php?status=success");
    exit;

} catch (Exception $e) {
    file_put_contents(__DIR__ . '/mail-error.txt', "[" . date('Y-m-d H:i:s') . "] " . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
    header("Location: contacts.php?status=error");
    exit;
}
