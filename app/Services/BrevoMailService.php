<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BrevoMailService
{
    public function sendText(string $toEmail, string $toName, string $subject, string $text): void
    {
        $this->send($toEmail, $toName, $subject, $text);
    }

    public function sendTemplate(string $toEmail, string $toName, string $subject, string $view, array $data, string $fallbackText): void
    {
        $this->send($toEmail, $toName, $subject, $fallbackText, view($view, $data)->render());
    }

    private function send(string $toEmail, string $toName, string $subject, string $text, ?string $html = null): void
    {
        $apiKey = config('services.brevo.key');

        if (! $apiKey) {
            $messageBuilder = fn ($message) => $message
                ->to($toEmail, $toName ?: $toEmail)
                ->subject($subject);

            if ($html) {
                Mail::mailer(config('mail.default'))->html($html, $messageBuilder);
            } else {
                Mail::mailer(config('mail.default'))->raw($text, $messageBuilder);
            }

            return;
        }

        $payload = [
            'sender' => [
                'name' => config('services.brevo.sender_name'),
                'email' => config('services.brevo.sender_email'),
            ],
            'to' => [[
                'email' => $toEmail,
                'name' => $toName ?: $toEmail,
            ]],
            'subject' => $subject,
            'textContent' => $text,
        ];

        if (! filter_var($payload['sender']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse expediteur Brevo invalide: '.$payload['sender']['email']);
        }

        if ($html) {
            $payload['htmlContent'] = $html;
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post(config('services.brevo.url'), $payload);

        if ($response->failed()) {
            Log::error('Brevo email delivery failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Brevo a refuse l envoi: '.$response->body());
        }
    }
}
