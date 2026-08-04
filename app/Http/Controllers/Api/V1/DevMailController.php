<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\BrevoMailService;
use Illuminate\Http\Request;
use RuntimeException;

class DevMailController extends ApiController
{
    public function brevoStatus()
    {
        return $this->ok([
            'environment' => app()->environment(),
            'mailer' => config('mail.default'),
            'brevo_api_key_configured' => filled(config('services.brevo.key')),
            'brevo_url_configured' => filled(config('services.brevo.url')),
            'brevo_sender_email' => config('services.brevo.sender_email'),
            'brevo_sender_name' => config('services.brevo.sender_name'),
            'test_token_configured' => filled(config('services.brevo.test_token')),
        ], 'Diagnostic Brevo.');
    }

    public function sendBrevoTest(Request $request, BrevoMailService $mail)
    {
        if (! app()->environment('local')) {
            $token = config('services.brevo.test_token');

            abort_if(blank($token), 404, 'Route de test email desactivee.');
            abort_unless(hash_equals($token, (string) $request->query('token')), 403, 'Token de test email invalide.');
        }

        $data = $request->isMethod('post')
            ? $request->validate(['to' => ['nullable', 'email']])
            : [];

        $to = $data['to'] ?? 'raoulkm2006@gmail.com';

        try {
            $mail->sendTemplate(
                $to,
                'Raoul',
                'Test email Brevo - US',
                'emails.test',
                [],
                'Ceci est un email de test envoye depuis le backend Laravel US avec le mailer Brevo API.'
            );
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), [], 502);
        }

        return $this->ok([
            'to' => $to,
            'mailer' => config('mail.default'),
            'provider' => 'brevo',
        ], 'Email de test envoye.');
    }
}
