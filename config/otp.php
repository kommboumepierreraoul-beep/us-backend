<?php

return [
    'email_digits' => (int) env('OTP_EMAIL_DIGITS', 6),
    'email_ttl_minutes' => (int) env('OTP_EMAIL_TTL_MINUTES', 10),
    'email_resend_cooldown_seconds' => (int) env('OTP_EMAIL_RESEND_COOLDOWN_SECONDS', 60),
    'email_max_attempts' => (int) env('OTP_EMAIL_MAX_ATTEMPTS', 5),
    'password_reset_digits' => (int) env('OTP_PASSWORD_RESET_DIGITS', 6),
    'password_reset_ttl_minutes' => (int) env('OTP_PASSWORD_RESET_TTL_MINUTES', 10),
    'password_reset_cooldown_seconds' => (int) env('OTP_PASSWORD_RESET_COOLDOWN_SECONDS', 60),
    'password_reset_max_attempts' => (int) env('OTP_PASSWORD_RESET_MAX_ATTEMPTS', 5),
    'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
];
