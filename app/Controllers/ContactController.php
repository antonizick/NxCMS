<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Models\ContactSubmission;
use App\Models\PageView;
use App\Models\Settings;
use App\Support\Captcha;

final class ContactController
{
    /** Stopgap ceiling regardless of CAPTCHA state — see DAILY_CAPTCHA_THRESHOLD below. */
    private const DAILY_HARD_CAP = 40;

    /** Spec: "more than five submissions site-wide in a day" gates the form behind a CAPTCHA. */
    private const DAILY_CAPTCHA_THRESHOLD = 5;

    /** Per-IP: independent of the site-wide CAPTCHA gate, blocks a single sender hammering the form. */
    private const IP_LIMIT = 3;
    private const IP_WINDOW_MINUTES = 60;

    public function show(): void
    {
        PageView::recordCurrent();
        $this->render();
    }

    public function submit(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->render(['Your session expired. Please try again.'], $_POST);
            return;
        }

        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($message === '' || mb_strlen($message) > 5000) {
            $errors[] = 'Please enter a message (up to 5000 characters).';
        }
        // Honeypot: a real browser leaves this empty; bots fill every field.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $errors[] = 'Submission rejected.';
        }
        if (ContactSubmission::recentByIp($ip, self::IP_WINDOW_MINUTES) >= self::IP_LIMIT) {
            $errors[] = 'Too many messages from this connection — please try again later.';
        }
        if (ContactSubmission::countToday() >= self::DAILY_HARD_CAP) {
            $errors[] = 'The contact form is closed for today. Please try again tomorrow.';
        }

        if (self::captchaRequired() && !$this->captchaOk()) {
            $errors[] = 'Please complete the verification and try again.';
        }

        if ($errors) {
            $this->render($errors, $_POST);
            return;
        }

        ContactSubmission::create($name, $email, $message, $ip);

        header('Location: /contact?sent=1', true, 303);
    }

    public static function captchaRequired(): bool
    {
        return ContactSubmission::countToday() >= self::DAILY_CAPTCHA_THRESHOLD;
    }

    private function captchaOk(): bool
    {
        $encoded = (string) ($_POST['captcha_payload'] ?? '');
        $number = (string) ($_POST['captcha_number'] ?? '');

        $json = base64_decode($encoded, true);
        if ($json === false) {
            return false;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return false;
        }

        return Captcha::verify($payload, $number);
    }

    /**
     * @param list<string> $errors
     * @param array<string, mixed> $old
     */
    private function render(array $errors = [], array $old = []): void
    {
        $site = Settings::site();
        $captchaRequired = self::captchaRequired();

        View::render('contact', [
            'site' => $site,
            'errors' => $errors,
            'old' => $old,
            'sent' => isset($_GET['sent']),
            'captcha' => $captchaRequired ? Captcha::challenge() : null,
            'pageTitle' => 'Contact — ' . ($site['display_name'] ?? ''),
            'metaDescription' => 'Get in touch with ' . ($site['display_name'] ?? '') . '.',
        ]);
    }
}
