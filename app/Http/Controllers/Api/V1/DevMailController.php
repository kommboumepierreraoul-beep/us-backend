<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\BrevoMailService;
use Illuminate\Http\Request;
use RuntimeException;

class DevMailController extends ApiController
{
    public function sendBrevoTest(Request $request, BrevoMailService $mail)
    {
        abort_unless(app()->environment('local'), 404);

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
