<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('certification_score')->default(0)->after('completion_score');
            $table->string('certification_status')->default('not_eligible')->index()->after('certification_score');
            $table->timestamp('certified_at')->nullable()->after('certification_status');
            $table->timestamp('certification_notified_at')->nullable()->after('certified_at');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'certification_score',
                'certification_status',
                'certified_at',
                'certification_notified_at',
            ]);
        });
    }
};
