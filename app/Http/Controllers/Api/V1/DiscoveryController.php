<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProfileResource;
use App\Services\DiscoveryService;
use Illuminate\Http\Request;

class DiscoveryController extends ApiController
{
    public function index(Request $request, DiscoveryService $discovery)
    {
        $profiles = $discovery->candidatesFor($request->user(), $request->only([
            'min_age', 'max_age', 'gender', 'same_university_only', 'per_page',
        ]));

        return $this->ok(ProfileResource::collection($profiles)->response()->getData(true));
    }
}
