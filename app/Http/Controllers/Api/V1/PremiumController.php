<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Plan;
use Illuminate\Http\Request;

class PremiumController extends ApiController
{
    public function plans()
    {
        return $this->ok(Plan::query()->where('is_active', true)->orderBy('price_cents')->get());
    }

    public function subscription(Request $request)
    {
        $subscription = $request->user()->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'grace'])
            ->latest('ends_at')
            ->first();

        return $this->ok($subscription);
    }
}
