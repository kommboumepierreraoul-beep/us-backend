<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        config(['otp.expose_in_response' => true]);

        $university = University::query()->create([
            'name' => 'Universite de Douala',
            'city' => 'Douala',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'student@example.com',
            'password' => 'password123',
            'first_name' => 'Amina',
            'birth_date' => now()->subYears(20)->toDateString(),
            'gender' => 'woman',
            'university_id' => $university->id,
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user', 'otp_debug' => ['code']]]);

        $this->assertDatabaseHas('profiles', ['first_name' => 'Amina']);
        $this->assertDatabaseHas('discovery_preferences', ['user_id' => 1]);
        $this->assertDatabaseCount('email_verification_otps', 1);
    }

    public function test_user_can_register_with_completed_profile_data(): void
    {
        config(['otp.expose_in_response' => true]);

        $university = University::query()->create([
            'name' => 'Universite de Yaounde I',
            'city' => 'Yaounde',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'complete@example.com',
            'password' => 'password123',
            'first_name' => 'Ariane',
            'birth_date' => now()->subYears(21)->toDateString(),
            'gender' => 'woman',
            'university_id' => $university->id,
            'looking_for' => 'serious',
            'bio' => 'Etudiante passionnee par la technologie.',
            'study_level' => 'Licence 3',
            'languages' => ['fr', 'en'],
            'intentions' => ['relation-serieuse'],
            'interests' => ['tech', 'lecture'],
            'photo_url' => 'https://example.com/photo.jpg',
            'min_age' => 20,
            'max_age' => 29,
            'radius_km' => 15,
            'preferred_gender' => 'man',
            'same_university_only' => true,
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.profile.bio', 'Etudiante passionnee par la technologie.');

        $this->assertDatabaseHas('profiles', [
            'first_name' => 'Ariane',
            'study_level' => 'Licence 3',
        ]);
        $this->assertDatabaseHas('interests', ['name' => 'tech']);
        $this->assertDatabaseHas('photos', ['url' => 'https://example.com/photo.jpg', 'is_primary' => true]);
        $this->assertDatabaseHas('discovery_preferences', [
            'min_age' => 20,
            'max_age' => 29,
            'radius_km' => 15,
            'gender' => 'homme',
            'same_university_only' => true,
        ]);
        $this->assertGreaterThan(60, Profile::query()->where('first_name', 'Ariane')->first()->completion_score);
    }

    public function test_user_can_verify_email_with_otp(): void
    {
        config(['otp.expose_in_response' => true]);

        $register = $this->postJson('/api/v1/auth/register', [
            'email' => 'verify@example.com',
            'password' => 'password123',
            'first_name' => 'Nora',
            'birth_date' => now()->subYears(20)->toDateString(),
            'gender' => 'woman',
            'device_name' => 'phpunit',
        ]);

        $token = $register->json('data.token');
        $code = $register->json('data.otp_debug.code');

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('message', 'Adresse email verifiee.');

        $this->assertNotNull(User::query()->where('email', 'verify@example.com')->first()->email_verified_at);
    }

    public function test_user_can_reset_forgotten_password_with_otp(): void
    {
        config(['otp.expose_in_response' => true]);

        $user = User::factory()->create([
            'email' => 'forgot@example.com',
            'password' => 'old-password123',
        ]);

        $forgot = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ])->assertOk();

        $code = $forgot->json('data.otp_debug.code');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'forgot@example.com',
            'code' => $code,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk()
            ->assertJsonPath('message', 'Mot de passe reinitialise.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_transactional_email_templates_match_us_brand(): void
    {
        $verificationHtml = view('emails.verification-otp', [
            'name' => 'Amina',
            'code' => '123456',
            'ttl' => 10,
        ])->render();

        $resetHtml = view('emails.password-reset-otp', [
            'name' => 'Amina',
            'code' => '654321',
            'ttl' => 10,
        ])->render();

        $this->assertStringContainsString('#D7263D', $verificationHtml);
        $this->assertStringContainsString('#EC4899', $verificationHtml);
        $this->assertStringContainsString('#6C2BD9', $verificationHtml);
        $this->assertStringContainsString('Confirmez votre email', $verificationHtml);
        $this->assertStringContainsString('Reinitialisation du mot de passe', $resetHtml);
        $this->assertStringContainsString('US Nous', $resetHtml);
    }

    public function test_user_can_login_with_verified_google_id_token(): void
    {
        config([
            'services.google.allowed_client_ids' => ['google-client.apps.googleusercontent.com'],
            'services.google.tokeninfo_url' => 'https://oauth2.googleapis.com/tokeninfo',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123',
                'aud' => 'google-client.apps.googleusercontent.com',
                'email' => 'google@example.com',
                'email_verified' => 'true',
                'name' => 'Google User',
                'given_name' => 'Google',
            ]),
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-google-id-token',
            'device_name' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_profile', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'google_id' => 'google-user-123',
        ]);
        $this->assertNotNull(User::query()->where('email', 'google@example.com')->first()->email_verified_at);
    }

    public function test_profile_can_be_updated_with_interests(): void
    {
        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Raoul',
            'birth_date' => now()->subYears(22)->toDateString(),
            'gender' => 'man',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/profile', [
            'bio' => 'Etudiant curieux.',
            'study_level' => 'Master',
            'languages' => ['fr', 'en'],
            'intentions' => ['serious'],
            'interests' => ['tech', 'musique'],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('interests', ['name' => 'tech']);
        $this->assertGreaterThan(0, $user->profile()->first()->completion_score);
    }

    public function test_user_can_upload_profile_photo_file(): void
    {
        config([
            'cloudinary.cloud_name' => 'demo-cloud',
            'cloudinary.api_key' => 'demo-key',
            'cloudinary.api_secret' => 'demo-secret',
        ]);
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/v1/us/profile-photos/profile.jpg',
                'public_id' => 'us/profile-photos/profile',
            ]),
        ]);

        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Raoul',
            'birth_date' => now()->subYears(22)->toDateString(),
            'gender' => 'man',
        ]);
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/profile/photos', [
            'photo' => UploadedFile::fake()->image('profile.jpg', 800, 1000),
            'is_primary' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('photos', [
            'user_id' => $user->id,
            'is_primary' => true,
            'moderation_status' => 'approved',
            'cloudinary_public_id' => 'us/profile-photos/profile',
        ]);
        $this->assertStringStartsWith('https://res.cloudinary.com/', $response->json('data.url'));
    }

    public function test_user_can_upload_multiple_profile_photos_to_cloudinary(): void
    {
        config([
            'cloudinary.cloud_name' => 'demo-cloud',
            'cloudinary.api_key' => 'demo-key',
            'cloudinary.api_secret' => 'demo-secret',
        ]);
        Http::fake([
            'api.cloudinary.com/*' => Http::sequence()
                ->push(['secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/one.jpg', 'public_id' => 'one'])
                ->push(['secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/two.jpg', 'public_id' => 'two'])
                ->push(['secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/three.jpg', 'public_id' => 'three']),
        ]);

        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Amina',
            'birth_date' => now()->subYears(22)->toDateString(),
            'gender' => 'woman',
        ]);
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/profile/photos', [
            'photos' => [
                UploadedFile::fake()->image('one.jpg', 800, 1000),
                UploadedFile::fake()->image('two.jpg', 800, 1000),
                UploadedFile::fake()->image('three.jpg', 800, 1000),
            ],
            'is_primary' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $this->assertDatabaseHas('photos', ['user_id' => $user->id, 'cloudinary_public_id' => 'one', 'sort_order' => 0, 'is_primary' => true]);
        $this->assertDatabaseHas('photos', ['user_id' => $user->id, 'cloudinary_public_id' => 'two', 'sort_order' => 1, 'is_primary' => false]);
        $this->assertDatabaseHas('photos', ['user_id' => $user->id, 'cloudinary_public_id' => 'three', 'sort_order' => 2, 'is_primary' => false]);
    }

    public function test_reciprocal_likes_create_match_and_conversation(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        foreach ([$alice, $bob] as $user) {
            Profile::query()->create([
                'user_id' => $user->id,
                'first_name' => 'Student',
                'birth_date' => now()->subYears(21)->toDateString(),
                'gender' => 'student',
            ]);
        }

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/likes', ['receiver_id' => $bob->id])->assertCreated()
            ->assertJsonPath('data.match', null);

        Sanctum::actingAs($bob);
        $this->postJson('/api/v1/likes', ['receiver_id' => $alice->id])->assertCreated()
            ->assertJsonPath('message', 'Match cree.');

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_matches_endpoint_returns_matched_profile_and_conversation(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        Profile::query()->create([
            'user_id' => $alice->id,
            'first_name' => 'Alice',
            'birth_date' => now()->subYears(21)->toDateString(),
            'gender' => 'woman',
        ]);
        Profile::query()->create([
            'user_id' => $bob->id,
            'first_name' => 'Bob',
            'birth_date' => now()->subYears(23)->toDateString(),
            'gender' => 'man',
            'bio' => 'Etudiant en informatique.',
        ]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/likes', ['receiver_id' => $bob->id])->assertCreated();
        Sanctum::actingAs($bob);
        $this->postJson('/api/v1/likes', ['receiver_id' => $alice->id])->assertCreated();

        Sanctum::actingAs($alice);
        $this->getJson('/api/v1/matches')
            ->assertOk()
            ->assertJsonPath('data.data.0.matched_user.id', $bob->id)
            ->assertJsonPath('data.data.0.matched_user.profile.first_name', 'Bob')
            ->assertJsonStructure(['data' => ['data' => [['conversation' => ['id'], 'compatibility' => ['score', 'explanation']]]]]);
    }

    public function test_free_user_is_limited_to_fifteen_messages_per_conversation(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        foreach ([$alice, $bob] as $user) {
            Profile::query()->create([
                'user_id' => $user->id,
                'first_name' => 'Student',
                'birth_date' => now()->subYears(21)->toDateString(),
                'gender' => 'student',
            ]);
        }

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/likes', ['receiver_id' => $bob->id])->assertCreated();
        Sanctum::actingAs($bob);
        $match = $this->postJson('/api/v1/likes', ['receiver_id' => $alice->id])->assertCreated();
        $conversationId = $match->json('data.conversation.id');

        Sanctum::actingAs($alice);
        for ($i = 1; $i <= 15; $i++) {
            $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
                'type' => 'text',
                'body' => "Message {$i}",
            ])->assertCreated();
        }

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'type' => 'text',
            'body' => 'Message 16',
        ])->assertForbidden();
    }

    public function test_unread_message_count_is_returned(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        foreach ([$alice, $bob] as $user) {
            Profile::query()->create([
                'user_id' => $user->id,
                'first_name' => 'Student',
                'birth_date' => now()->subYears(21)->toDateString(),
                'gender' => 'student',
            ]);
        }

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/likes', ['receiver_id' => $bob->id])->assertCreated();
        Sanctum::actingAs($bob);
        $match = $this->postJson('/api/v1/likes', ['receiver_id' => $alice->id])->assertCreated();
        $conversationId = $match->json('data.conversation.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'type' => 'text',
            'body' => 'Salut Alice',
        ])->assertCreated();

        Sanctum::actingAs($alice);
        $this->getJson('/api/v1/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.data.0.unread_count', 1);
    }

    public function test_user_can_register_push_subscription(): void
    {
        config(['services.webpush.vapid_public_key' => 'public-vapid-key']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'public-vapid-key');

        $this->postJson('/api/v1/push/subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/123',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/subscription/123',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_user_can_submit_selfie_verification(): void
    {
        config([
            'cloudinary.cloud_name' => 'demo-cloud',
            'cloudinary.api_key' => 'demo-key',
            'cloudinary.api_secret' => 'demo-secret',
            'cloudinary.verification_folder' => 'us/verifications',
        ]);
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/selfie.jpg',
                'public_id' => 'us/verifications/selfie/selfie',
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/verification/selfie', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 1000),
            'consent' => '1',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'selfie')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $user->id,
            'type' => 'selfie',
            'status' => 'pending',
            'cloudinary_public_id' => 'us/verifications/selfie/selfie',
        ]);
    }
}
