<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\VerificationRequest;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class VerificationController extends ApiController
{
    public function status(Request $request)
    {
        $items = VerificationRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->groupBy('type')
            ->map(fn ($requests) => $requests->first())
            ->values();

        return $this->ok($items);
    }

    public function submitSelfie(Request $request, CloudinaryService $cloudinary)
    {
        $data = $request->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'consent' => ['accepted'],
        ]);

        $upload = $cloudinary->uploadVerificationImage($data['selfie'], 'selfie');

        $verification = VerificationRequest::query()->create([
            'user_id' => $request->user()->id,
            'type' => 'selfie',
            'status' => 'pending',
            'cloudinary_public_id' => $upload['public_id'],
            'image_url' => $upload['url'],
        ]);

        return $this->ok($verification, 'Selfie envoye pour verification.', status: 201);
    }
}
