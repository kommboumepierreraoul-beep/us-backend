<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DevMailController;
use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MatchingController;
use App\Http\Controllers\Api\V1\ModerationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PremiumController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\UniversityController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'us_backend']));

$authRegisterThrottle = sprintf('throttle:%d,%d', config('rate_limits.auth_register_attempts'), config('rate_limits.auth_register_decay_minutes'));
$authLoginThrottle = sprintf('throttle:%d,%d', config('rate_limits.auth_login_attempts'), config('rate_limits.auth_login_decay_minutes'));
$authOtpThrottle = sprintf('throttle:%d,%d', config('rate_limits.auth_otp_attempts'), config('rate_limits.auth_otp_decay_minutes'));
$authResetThrottle = sprintf('throttle:%d,%d', config('rate_limits.auth_reset_attempts'), config('rate_limits.auth_reset_decay_minutes'));
$likesThrottle = sprintf('throttle:%d,%d', config('rate_limits.likes_attempts'), config('rate_limits.likes_decay_minutes'));
$messagesThrottle = sprintf('throttle:%d,%d', config('rate_limits.messages_attempts'), config('rate_limits.messages_decay_minutes'));
$reportsThrottle = sprintf('throttle:%d,%d', config('rate_limits.reports_attempts'), config('rate_limits.reports_decay_minutes'));

Route::prefix('v1')->group(function () use (
    $authRegisterThrottle,
    $authLoginThrottle,
    $authOtpThrottle,
    $authResetThrottle,
    $likesThrottle,
    $messagesThrottle,
    $reportsThrottle
) {
    Route::get('/health', HealthController::class);
    Route::get('/dev/brevo/test-email', [DevMailController::class, 'sendBrevoTest']);
    Route::post('/dev/brevo/test-email', [DevMailController::class, 'sendBrevoTest']);
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/premium/plans', [PremiumController::class, 'plans']);
    Route::post('/payments/mobile-money/webhook', [PaymentController::class, 'webhook']);

    Route::prefix('auth')->group(function () use (
        $authRegisterThrottle,
        $authLoginThrottle,
        $authOtpThrottle,
        $authResetThrottle
    ) {
        Route::post('/register', [AuthController::class, 'register'])->middleware($authRegisterThrottle);
        Route::post('/login', [AuthController::class, 'login'])->middleware($authLoginThrottle);
        Route::post('/google', [AuthController::class, 'google'])->middleware($authLoginThrottle);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware($authOtpThrottle);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware($authResetThrottle);
    });

    Route::middleware('auth:sanctum')->group(function () use (
        $authOtpThrottle,
        $authResetThrottle,
        $likesThrottle,
        $messagesThrottle,
        $reportsThrottle
    ) {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/email/otp', [AuthController::class, 'resendEmailOtp'])->middleware($authOtpThrottle);
        Route::post('/auth/email/verify', [AuthController::class, 'verifyEmail'])->middleware($authResetThrottle);
        Route::patch('/auth/status', [AuthController::class, 'updateStatus']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photos', [ProfileController::class, 'addPhoto']);
        Route::put('/profile/location', [ProfileController::class, 'updateLocation']);
        Route::put('/profile/preferences', [ProfileController::class, 'updatePreferences']);

        Route::get('/discovery', [DiscoveryController::class, 'index']);
        Route::get('/likes', [MatchingController::class, 'likes']);
        Route::post('/likes', [MatchingController::class, 'like'])->middleware($likesThrottle);
        Route::get('/matches', [MatchingController::class, 'matches']);
        Route::delete('/matches/{match}', [MatchingController::class, 'unmatch']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware($messagesThrottle);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);
        Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);
        Route::post('/push/test', [PushSubscriptionController::class, 'test']);

        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::get('/event-invitations', [EventController::class, 'invitations']);
        Route::patch('/event-invitations/{invitation}', [EventController::class, 'respond']);
        Route::get('/event-invitations/{invitation}/ticket', [EventController::class, 'ticket']);

        Route::post('/users/{user}/block', [ModerationController::class, 'block']);
        Route::delete('/users/{user}/block', [ModerationController::class, 'unblock']);
        Route::post('/reports', [ModerationController::class, 'report'])->middleware($reportsThrottle);

        Route::get('/verification/status', [VerificationController::class, 'status']);
        Route::post('/verification/selfie', [VerificationController::class, 'submitSelfie']);

        Route::get('/premium/subscription', [PremiumController::class, 'subscription']);
        Route::post('/payments/mobile-money/intents', [PaymentController::class, 'createIntent']);
    });
});
