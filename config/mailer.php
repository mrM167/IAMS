<?php
// config/mailer.php — Email configuration (SMTP via PHPMailer or native mail)
// PHP 7.4 compatible

define('MAIL_ENABLED', false); // Set to true after configuring SMTP

// SMTP settings (use Brevo, Gmail, or your own)
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USER', 'your-email@gmail.com');
define('MAIL_SMTP_PASS', 'your-app-password');
define('MAIL_FROM', 'noreply@ub.ac.bw');
define('MAIL_FROM_NAME', 'IAMS - University of Botswana');

class Mailer {
    /**
     * Send an email using PHP's mail() (fallback) or SMTP if configured.
     */
    public static function send(string $to, string $toName, string $subject, string $body): bool {
        if (!MAIL_ENABLED) {
            // In demo mode, just log or skip
            error_log("Email disabled: To: $to, Subject: $subject");
            return false;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
        $message .= $body;
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px;">— IAMS, University of Botswana</p>';
        $message .= '</body></html>';

        return mail($to, $subject, $message, $headers);
    }

    public static function sendPasswordReset(string $to, string $toName, string $resetLink): bool {
        $subject = 'IAMS — Password Reset Request';
        $body = '<p>Dear ' . htmlspecialchars($toName) . ',</p>';
        $body .= '<p>You requested a password reset for your IAMS account.</p>';
        $body .= '<p><a href="' . $resetLink . '" style="background: #0a2f44; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Reset Your Password</a></p>';
        $body .= '<p>Or copy this link: <br><code>' . $resetLink . '</code></p>';
        $body .= '<p>This link expires in 1 hour.</p>';
        $body .= '<p>If you did not request this, please ignore this email.</p>';
        return self::send($to, $toName, $subject, $body);
    }

    public static function sendApplicationUpdate(string $to, string $toName, string $status, string $notes = ''): bool {
        $subject = 'IAMS — Application Status Update';
        $body = '<p>Dear ' . htmlspecialchars($toName) . ',</p>';
        $body .= '<p>Your attachment application status has been updated to: <strong>' . strtoupper(str_replace('_', ' ', $status)) . '</strong>.</p>';
        if ($notes) $body .= '<p><strong>Coordinator Notes:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>';
        $body .= '<p><a href="https://tomdon.infinityfreeapp.com/dashboard.php">View your dashboard →</a></p>';
        return self::send($to, $toName, $subject, $body);
    }

    public static function sendLogbookReminder(string $to, string $toName, int $week): bool {
        $subject = "IAMS — Week {$week} Logbook Reminder";
        $body = '<p>Dear ' . htmlspecialchars($toName) . ',</p>';
        $body .= "<p>This is a reminder that your Week {$week} logbook is due. Please submit it via the IAMS portal as soon as possible.</p>";
        $body .= '<p><a href="https://tomdon.infinityfreeapp.com/logbook.php">Submit Logbook →</a></p>';
        return self::send($to, $toName, $subject, $body);
    }

    public static function sendReportGraded(string $to, string $toName, string $grade, string $feedback = ''): bool {
        $subject = 'IAMS — Final Report Graded';
        $body = '<p>Dear ' . htmlspecialchars($toName) . ',</p>';
        $body .= '<p>Your final attachment report has been graded.</p>';
        $body .= '<p><strong>Grade:</strong> ' . htmlspecialchars($grade) . '</p>';
        if ($feedback) $body .= '<p><strong>Feedback:</strong><br>' . nl2br(htmlspecialchars($feedback)) . '</p>';
        $body .= '<p><a href="https://tomdon.infinityfreeapp.com/student_report.php">View your report →</a></p>';
        return self::send($to, $toName, $subject, $body);
    }
}