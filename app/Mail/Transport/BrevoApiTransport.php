<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoApiTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Brevo API transport supports Symfony Email messages only.');
        }

        $apiKey = config('services.brevo.key');

        if (! $apiKey) {
            throw new TransportException('BREVO_API_KEY is missing.');
        }

        $payload = [
            'sender' => $this->sender($email),
            'to' => $this->addresses($email->getTo()),
            'subject' => $email->getSubject() ?: '(sans sujet)',
        ];

        if ($email->getTextBody()) {
            $payload['textContent'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post(config('services.brevo.url'), $payload);

        if ($response->failed()) {
            throw new TransportException('Brevo API error '.$response->status().': '.$response->body());
        }

        $messageId = $response->json('messageId');

        if ($messageId) {
            $message->setMessageId($messageId);
        }
    }

    public function __toString(): string
    {
        return 'brevo_api';
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ]), $addresses);
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function sender(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        return array_filter([
            'email' => $from?->getAddress() ?: config('services.brevo.sender_email'),
            'name' => $from?->getName() ?: config('services.brevo.sender_name'),
        ]);
    }
}
