<?php

namespace Database\Seeders;

use App\Models\Interest;
use App\Models\Plan;
use App\Models\University;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        collect([
            ['name' => 'Universite de Yaounde I', 'acronym' => 'UYI', 'city' => 'Yaounde', 'type' => 'public'],
            ['name' => 'Universite de Douala', 'acronym' => 'UDla', 'city' => 'Douala', 'type' => 'public'],
            ['name' => 'Universite de Buea', 'acronym' => 'UB', 'city' => 'Buea', 'type' => 'public'],
            ['name' => 'Universite Catholique d Afrique Centrale', 'acronym' => 'UCAC', 'city' => 'Yaounde', 'type' => 'private'],
        ])->each(fn ($university) => University::query()->firstOrCreate(
            ['name' => $university['name'], 'city' => $university['city']],
            $university
        ));

        collect(['sport', 'musique', 'cinema', 'lecture', 'tech', 'entrepreneuriat', 'voyage', 'cuisine'])
            ->each(fn ($name) => Interest::query()->firstOrCreate(['name' => $name]));

        collect([
            ['code' => 'plus_monthly', 'name' => 'US Plus Mensuel', 'price_cents' => 250000, 'daily_likes' => 200, 'super_likes' => 10, 'features' => ['likes_boostes', 'super_likes', 'filtres_avances']],
            ['code' => 'premium_monthly', 'name' => 'US Premium Mensuel', 'price_cents' => 500000, 'daily_likes' => 1000, 'super_likes' => 25, 'features' => ['likes_illimites', 'boost', 'super_likes', 'filtres_avances']],
        ])->each(fn ($plan) => Plan::query()->firstOrCreate(['code' => $plan['code']], $plan));
    }
}
