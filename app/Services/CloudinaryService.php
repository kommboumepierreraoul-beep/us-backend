<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudinaryService
{
    public function uploadProfilePhoto(UploadedFile $file): array
    {
        return $this->uploadImage($file, config('cloudinary.folder'));
    }

    public function uploadVerificationImage(UploadedFile $file, string $type): array
    {
        return $this->uploadImage($file, trim(config('cloudinary.verification_folder'), '/').'/'.$type);
    }

    public function uploadEventImage(UploadedFile $file): array
    {
        return $this->uploadImage($file, trim(config('cloudinary.folder'), '/').'/events');
    }

    public function uploadSupportAttachment(UploadedFile $file): array
    {
        return $this->uploadImage($file, trim(config('cloudinary.folder'), '/').'/support');
    }

    private function uploadImage(UploadedFile $file, string $folder): array
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            throw new RuntimeException('Configuration Cloudinary manquante.');
        }

        $timestamp = time();
        $params = [
            'asset_folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($params);

        $signaturePayload = collect($params)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode('&');
        $signature = sha1($signaturePayload.$apiSecret);

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'asset_folder' => $folder,
            'signature' => $signature,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Upload Cloudinary impossible.');
        }

        return [
            'url' => $response->json('secure_url'),
            'public_id' => $response->json('public_id'),
        ];
    }
}
