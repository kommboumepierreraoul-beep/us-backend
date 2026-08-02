<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\AdminNotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function createIntent(Request $request, PaymentService $payments, AdminNotificationService $adminNotifications)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless($idempotencyKey, 409, 'Idempotency-Key requis.');

        $payment = $payments->createIntent($request->user(), Plan::findOrFail($data['plan_id']), $data['phone'], $idempotencyKey);

        $adminNotifications->notify(
            'Paiement en attente',
            'Une intention de paiement mobile money doit etre suivie.',
            '/admin/payments',
            ['payment_id' => $payment->id, 'user_id' => $request->user()->id]
        );

        return $this->ok($payment, 'Intention de paiement creee.', status: 201);
    }

    public function webhook(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'provider_reference' => ['required', 'string'],
            'status' => ['required', 'string'],
            'signature' => ['nullable', 'string'],
        ]);

        $payment = Payment::query()->where('provider_reference', $data['provider_reference'])->firstOrFail();
        if ($data['status'] === 'confirmed') {
            $payment = $payments->confirm($payment, $request->all());
        } else {
            $payment->update(['status' => $data['status'], 'payload' => $request->all()]);
        }

        return $this->ok($payment, 'Webhook traite.');
    }
}
