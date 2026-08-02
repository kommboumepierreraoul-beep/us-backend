<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\DB;

class HealthController extends ApiController
{
    public function __invoke()
    {
        DB::select('select 1');

        return $this->ok([
            'service' => 'us_backend',
            'database' => 'ok',
            'time' => now()->toIso8601String(),
        ], 'Service disponible.');
    }
}
