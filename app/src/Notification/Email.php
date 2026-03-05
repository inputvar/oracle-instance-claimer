<?php

namespace Hitrov\Notification;

use Hitrov\Interfaces\NotifierInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email implements NotifierInterface
{
    public function notify(string $message): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USERNAME');
            $mail->Password = getenv('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(getenv('SMTP_USERNAME'), 'OCI ARM Claimer');
            $mail->addAddress(getenv('NOTIFY_EMAIL'));

            $subject = 'OCI ARM Instance Notification';
            if (strpos($message, 'lifecycleState') !== false) {
                $subject = 'OCI ARM Instance Created Successfully!';
            } elseif (strpos($message, 'stopped') !== false || strpos($message, 'crashed') !== false) {
                $subject = 'OCI ARM Claimer - Loop Stopped!';
            }

            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return ['success' => true, 'message' => 'Email sent'];
        } catch (Exception $e) {
            error_log("Email send failed: " . $mail->ErrorInfo);
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    public function isSupported(): bool
    {
        return !empty(getenv('SMTP_USERNAME'))
            && !empty(getenv('SMTP_PASSWORD'))
            && !empty(getenv('NOTIFY_EMAIL'));
    }
}
