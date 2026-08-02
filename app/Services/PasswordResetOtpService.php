<?php

namespace App\Services;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpService
{
    public function __construct(private readonly BrevoMailService $mail) {}

    public function send(string $email, ?string $ip = null): ?string
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return null;
        }

        $lastOtp = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        $cooldown = config('otp.password_reset_cooldown_seconds');
        if ($lastOtp && $lastOtp->created_at->greaterThan(now()->subSeconds($cooldown))) {
            throw ValidationException::withMessages([
                'email' => 'Veuillez patienter avant de demander un nouveau code.',
            ]);
        }

        $code = $this->generateCode();

        PasswordResetOtp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.password_reset_ttl_minutes')),
            'request_ip' => $ip,
        ]);

        $fallbackText = "Votre code de reinitialisation US est {$code}. Il expire dans ".config('otp.password_reset_ttl_minutes').' minutes.';

        $this->mail->sendTemplate(
            $user->email,
            $user->name ?? $user->email,
            'Code de reinitialisation US',
            'emails.password-reset-otp',
            [
                'name' => $user->name,
                'code' => $code,
                'ttl' => config('otp.password_reset_ttl_minutes'),
            ],
            $fallbackText
        );

        return config('otp.expose_in_response') ? $code : null;
    }

    public function reset(string $email, string $code, string $password): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Code ou email invalide.']);
        }

        $otp = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('email', $user->email)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'Code expire ou introuvable.']);
        }

        if ($otp->attempts >= config('otp.password_reset_max_attempts')) {
            throw ValidationException::withMessages(['code' => 'Trop de tentatives. Demandez un nouveau code.']);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages(['code' => 'Code de reinitialisation invalide.']);
        }

        $otp->update(['consumed_at' => now()]);
        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();
    }

    private function generateCode(): string
    {
        $digits = max(4, min(10, (int) config('otp.password_reset_digits')));
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
