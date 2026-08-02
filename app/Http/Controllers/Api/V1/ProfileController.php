<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProfileResource;
use App\Models\Interest;
use App\Models\Photo;
use App\Services\CloudinaryService;
use App\Services\ProfileCertificationService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        return $this->ok(new ProfileResource($request->user()->profile->load(['user.photos', 'university', 'interests'])));
    }

    public function update(Request $request, ProfileCompletionService $completion, ProfileCertificationService $certification)
    {
        $profile = $request->user()->profile;
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:80'],
            'university_id' => ['sometimes', 'nullable', 'exists:universities,id'],
            'gender' => ['sometimes', Rule::in(['homme', 'femme', 'man', 'woman'])],
            'looking_for' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'study_level' => ['sometimes', 'nullable', 'string', 'max:80'],
            'languages' => ['sometimes', 'array'],
            'intentions' => ['sometimes', 'array'],
            'visibility' => ['sometimes', Rule::in(['visible', 'hidden'])],
            'interests' => ['sometimes', 'array', 'max:20'],
            'interests.*' => ['string', 'max:60'],
        ]);

        if (array_key_exists('university_id', $data) && $data['university_id'] !== $profile->university_id) {
            $data['university_changed_at'] = now();
        }
        if (isset($data['gender'])) {
            $data['gender'] = match ($data['gender']) {
                'man' => 'homme',
                'woman' => 'femme',
                default => $data['gender'],
            };
        }

        $interestNames = $data['interests'] ?? null;
        unset($data['interests']);
        $profile->update($data);

        if (is_array($interestNames)) {
            $ids = collect($interestNames)
                ->map(fn ($name) => Interest::query()->firstOrCreate(['name' => str($name)->lower()->trim()->toString()])->id)
                ->all();
            $profile->interests()->sync($ids);
        }

        $profile->update(['completion_score' => $completion->score($profile->refresh())]);
        $certification->refresh($request->user()->refresh());

        return $this->ok(new ProfileResource($profile->load(['user.photos', 'university', 'interests'])), 'Profil mis a jour.');
    }

    public function addPhoto(Request $request, ProfileCompletionService $completion, CloudinaryService $cloudinary, ProfileCertificationService $certification)
    {
        $data = $request->validate([
            'photo' => ['required_without_all:url,photos', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photos' => ['required_without_all:url,photo', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'url' => ['required_without_all:photo,photos', 'url', 'max:2048'],
            'cloudinary_public_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($data['is_primary'] ?? false) {
            $request->user()->photos()->update(['is_primary' => false]);
        }

        $uploads = [];
        if ($request->hasFile('photo')) {
            $uploads[] = $cloudinary->uploadProfilePhoto($request->file('photo'));
        }
        foreach ($request->file('photos', []) as $file) {
            $uploads[] = $cloudinary->uploadProfilePhoto($file);
        }
        if (! empty($data['url'])) {
            $uploads[] = ['url' => $data['url'], 'public_id' => $data['cloudinary_public_id'] ?? null];
        }

        $baseSortOrder = $data['sort_order'] ?? $request->user()->photos()->count();
        $hasExistingPhoto = $request->user()->photos()->exists();
        $photos = collect($uploads)->map(function (array $upload, int $index) use ($request, $data, $baseSortOrder, $hasExistingPhoto) {
            return Photo::query()->create([
                'user_id' => $request->user()->id,
                'cloudinary_public_id' => $upload['public_id'] ?? null,
                'url' => $upload['url'],
                'sort_order' => $baseSortOrder + $index,
                'is_primary' => ($data['is_primary'] ?? false) ? $index === 0 : (! $hasExistingPhoto && $index === 0),
                'moderation_status' => 'approved',
            ]);
        })->values();

        $profile = $request->user()->profile;
        $profile->update(['completion_score' => $completion->score($profile)]);
        $certification->refresh($request->user()->refresh());

        return $this->ok($photos->count() === 1 ? $photos->first() : $photos, $photos->count() > 1 ? 'Photos ajoutees.' : 'Photo ajoutee.', status: 201);
    }

    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $request->user()->location()->updateOrCreate(['user_id' => $request->user()->id], $data);

        return $this->ok(null, 'Position mise a jour.');
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'min_age' => ['nullable', 'integer', 'min:18', 'max:99'],
            'max_age' => ['nullable', 'integer', 'min:18', 'max:99', 'gte:min_age'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'same_university_only' => ['nullable', 'boolean'],
        ]);

        $profileGender = $request->user()->profile?->gender;
        $data['gender'] = match ($profileGender) {
            'homme', 'man' => 'femme',
            'femme', 'woman' => 'homme',
            default => null,
        };

        $preferences = $request->user()->discoveryPreference()->updateOrCreate(['user_id' => $request->user()->id], $data);

        return $this->ok($preferences, 'Preferences mises a jour.');
    }
}
