<?php

return [
    'auth_register_attempts' => (int) env('RATE_LIMIT_AUTH_REGISTER_ATTEMPTS', env('APP_ENV') === 'local' ? 30 : 3),
    'auth_register_decay_minutes' => (int) env('RATE_LIMIT_AUTH_REGISTER_DECAY_MINUTES', 60),

    'auth_login_attempts' => (int) env('RATE_LIMIT_AUTH_LOGIN_ATTEMPTS', env('APP_ENV') === 'local' ? 60 : 5),
    'auth_login_decay_minutes' => (int) env('RATE_LIMIT_AUTH_LOGIN_DECAY_MINUTES', 15),

    'auth_otp_attempts' => (int) env('RATE_LIMIT_AUTH_OTP_ATTEMPTS', env('APP_ENV') === 'local' ? 30 : 3),
    'auth_otp_decay_minutes' => (int) env('RATE_LIMIT_AUTH_OTP_DECAY_MINUTES', 10),

    'auth_reset_attempts' => (int) env('RATE_LIMIT_AUTH_RESET_ATTEMPTS', env('APP_ENV') === 'local' ? 40 : 5),
    'auth_reset_decay_minutes' => (int) env('RATE_LIMIT_AUTH_RESET_DECAY_MINUTES', 10),

    'likes_attempts' => (int) env('RATE_LIMIT_LIKES_ATTEMPTS', 80),
    'likes_decay_minutes' => (int) env('RATE_LIMIT_LIKES_DECAY_MINUTES', 1440),

    'messages_attempts' => (int) env('RATE_LIMIT_MESSAGES_ATTEMPTS', env('APP_ENV') === 'local' ? 120 : 30),
    'messages_decay_minutes' => (int) env('RATE_LIMIT_MESSAGES_DECAY_MINUTES', 1),

    'reports_attempts' => (int) env('RATE_LIMIT_REPORTS_ATTEMPTS', 10),
    'reports_decay_minutes' => (int) env('RATE_LIMIT_REPORTS_DECAY_MINUTES', 1440),
];
