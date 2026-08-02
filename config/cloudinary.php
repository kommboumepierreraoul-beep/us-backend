<?php

return [
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'folder' => env('CLOUDINARY_PROFILE_FOLDER', 'us/profile-photos'),
    'verification_folder' => env('CLOUDINARY_VERIFICATION_FOLDER', 'us/verifications'),
];
