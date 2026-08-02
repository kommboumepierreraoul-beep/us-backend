<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function createIntent(User $user, Plan $plan, string $phone, string $idempotencyKey): Payment
    {
        return Payment::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'phone' => $phone,
                'provider_reference' => 'US-'.strtoupper(str()->random(16)),
                'payload' => ['instructions' => 'Validez le paiement Mobile Money sur votre telephone.'],
            ]
        );
    }

    public function confirm(Payment $payment, array $payload = []): Payment
    {
        return DB::transaction(function () use ($payment, $payload) {
            if ($payment->status === 'confirmed') {
                return $payment;
            }

            $subscription = Subscription::query()->create([
                'user_id' => $payment->user_id,
                'plan_id' => $payment->plan_id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($payment->plan->duration_days),
            ]);

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'confirmed',
                'payload' => array_merge($payment->payload ?? [], $payload),
                'confirmed_at' => now(),
            ]);

            return $payment->refresh();
        });
    }
}
