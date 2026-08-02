<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\University;
use Illuminate\Http\Request;

class UniversityController extends ApiController
{
    public function index(Request $request)
    {
        $query = University::query()->where('is_active', true)->orderBy('city')->orderBy('name');
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"));
        }

        return $this->ok($query->paginate((int) $request->query('per_page', 50)));
    }
}
