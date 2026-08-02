<?php

namespace App\Services;

use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public function __construct(private readonly BrevoMailService $mail) {}

    public function send(User $user, ?string $ip = null, bool $force = false): ?string
    {
        if ($user->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'Adresse email deja verifiee.']);
        }

        $lastOtp = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        $cooldown = config('otp.email_resend_cooldown_seconds');
        if (! $force && $lastOtp && $lastOtp->created_at->greaterThan(now()->subSeconds($cooldown))) {
            throw ValidationException::withMessages([
                'email' => 'Veuillez patienter avant de demander un nouveau code.',
            ]);
        }

        $code = $this->generateCode();

        EmailVerificationOtp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.email_ttl_minutes')),
            'request_ip' => $ip,
        ]);

        $fallbackText = "Votre code de verification US est {$code}. Il expire dans ".config('otp.email_ttl_minutes').' minutes.';

        $this->mail->sendTemplate(
            $user->email,
            $user->name ?? $user->email,
            'Code de verification US',
            'emails.verification-otp',
            [
                'name' => $user->name,
                'code' => $code,
                'ttl' => config('otp.email_ttl_minutes'),
            ],
            $fallbackText
        );

        return config('otp.expose_in_response') ? $code : null;
    }

    public function verify(User $user, string $code): void
    {
        if ($user->email_verified_at) {
            return;
        }

        $otp = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('email', $user->email)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'Code expire ou introuvable.']);
        }

        if ($otp->attempts >= config('otp.email_max_attempts')) {
            throw ValidationException::withMessages(['code' => 'Trop de tentatives. Demandez un nouveau code.']);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages(['code' => 'Code de verification invalide.']);
        }

        $otp->update(['consumed_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function generateCode(): string
    {
        $digits = max(4, min(10, (int) config('otp.email_digits')));
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
