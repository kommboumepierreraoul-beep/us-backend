<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DiscoveryPreference;
use App\Models\Interest;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\GoogleAuthService;
use App\Services\PasswordResetOtpService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AuthController extends ApiController
{
    public function register(Request $request, EmailOtpService $emailOtp, ProfileCompletionService $completion)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'first_name' => ['required', 'string', 'max:80'],
            'birth_date' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'gender' => ['required', 'string', Rule::in(['homme', 'femme', 'man', 'woman'])],
            'university_id' => ['nullable', 'exists:universities,id'],
            'looking_for' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:500'],
            'study_level' => ['nullable', 'string', 'max:80'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:30'],
            'intentions' => ['nullable', 'array'],
            'intentions.*' => ['string', 'max:60'],
            'interests' => ['nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:60'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'min_age' => ['nullable', 'integer', 'min:18', 'max:99'],
            'max_age' => ['nullable', 'integer', 'min:18', 'max:99', 'gte:min_age'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'same_university_only' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $gender = match ($data['gender']) {
            'man' => 'homme',
            'woman' => 'femme',
            default => $data['gender'],
        };

        $user = User::query()->create([
            'name' => $data['first_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => 'active',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'birth_date' => $data['birth_date'],
            'gender' => $gender,
            'university_id' => $data['university_id'] ?? null,
            'looking_for' => $data['looking_for'] ?? null,
            'bio' => $data['bio'] ?? null,
            'study_level' => $data['study_level'] ?? null,
            'languages' => $data['languages'] ?? [],
            'intentions' => $data['intentions'] ?? [],
        ]);

        if (! empty($data['interests'])) {
            $ids = collect($data['interests'])
                ->map(fn ($name) => Interest::query()->firstOrCreate(['name' => str($name)->lower()->trim()->toString()])->id)
                ->all();
            $profile->interests()->sync($ids);
        }

        if (! empty($data['photo_url'])) {
            Photo::query()->create([
                'user_id' => $user->id,
                'url' => $data['photo_url'],
                'sort_order' => 0,
                'is_primary' => true,
                'moderation_status' => 'approved',
            ]);
        }

        DiscoveryPreference::query()->create([
            'user_id' => $user->id,
            'min_age' => $data['min_age'] ?? 18,
            'max_age' => $data['max_age'] ?? 35,
            'radius_km' => $data['radius_km'] ?? 25,
            'gender' => $gender === 'homme' ? 'femme' : 'homme',
            'same_university_only' => $data['same_university_only'] ?? false,
        ]);

        $profile->update(['completion_score' => $completion->score($profile->refresh())]);

        try {
            $debugCode = $emailOtp->send($user, $request->ip(), force: true);
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), [], 502);
        }

        return $this->ok([
            'user' => $user->load(['profile.university', 'profile.interests', 'photos', 'discoveryPreference']),
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'email_verification_required' => true,
            'otp_debug' => $debugCode ? ['code' => $debugCode] : null,
        ], 'Compte cree.', status: 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! in_array($user->status, ['active', 'paused'], true)) {
            return $this->fail('Identifiants invalides.', [], 401);
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        return $this->ok([
            'user' => $user->load('profile.university'),
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'email_verification_required' => ! $user->email_verified_at,
        ], 'Connexion reussie.');
    }

    public function google(Request $request, GoogleAuthService $googleAuth)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $googleUser = $googleAuth->verifyIdToken($data['id_token']);

        $user = User::query()->firstOrCreate(
            ['email' => $googleUser['email']],
            [
                'name' => $googleUser['name'] ?? $googleUser['given_name'] ?? null,
                'google_id' => $googleUser['sub'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $user->forceFill([
            'name' => $user->name ?: ($googleUser['name'] ?? $googleUser['given_name'] ?? null),
            'google_id' => $googleUser['sub'],
            'email_verified_at' => $user->email_verified_at ?: now(),
            'last_seen_at' => now(),
        ])->save();

        return $this->ok([
            'user' => $user->load('profile.university'),
            'token' => $user->createToken($data['device_name'] ?? 'google')->plainTextToken,
            'requires_profile' => ! $user->profile()->exists(),
            'email_verification_required' => ! $user->email_verified_at,
        ], 'Connexion Google reussie.');
    }

    public function resendEmailOtp(Request $request, EmailOtpService $emailOtp)
    {
        try {
            $debugCode = $emailOtp->send($request->user(), $request->ip());
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), [], 502);
        }

        return $this->ok([
            'email' => $request->user()->email,
            'expires_in_minutes' => config('otp.email_ttl_minutes'),
            'otp_debug' => $debugCode ? ['code' => $debugCode] : null,
        ], 'Code de verification envoye.');
    }

    public function verifyEmail(Request $request, EmailOtpService $emailOtp)
    {
        $data = $request->validate([
            'code' => ['required', 'digits:'.config('otp.email_digits')],
        ]);

        $emailOtp->verify($request->user(), $data['code']);

        return $this->ok($request->user()->fresh(), 'Adresse email verifiee.');
    }

    public function forgotPassword(Request $request, PasswordResetOtpService $passwordResetOtp)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $debugCode = $passwordResetOtp->send($data['email'], $request->ip());
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), [], 502);
        }

        return $this->ok([
            'email' => $data['email'],
            'expires_in_minutes' => config('otp.password_reset_ttl_minutes'),
            'otp_debug' => $debugCode ? ['code' => $debugCode] : null,
        ], 'Si ce compte existe, un code de reinitialisation a ete envoye.');
    }

    public function resetPassword(Request $request, PasswordResetOtpService $passwordResetOtp)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:'.config('otp.password_reset_digits')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $passwordResetOtp->reset($data['email'], $data['code'], $data['password']);

        return $this->ok(null, 'Mot de passe reinitialise.');
    }

    public function me(Request $request)
    {
        return $this->ok($request->user()->load(['profile.university', 'photos', 'discoveryPreference']));
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused', 'deleted'])],
        ]);

        $request->user()->update($data);

        return $this->ok($request->user()->fresh(), 'Statut mis a jour.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Session revoquee.');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->ok(null, 'Toutes les sessions sont revoquees.');
    }
}
