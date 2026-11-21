<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('app_name', 60)->nullable()->after('name');
            $table->string('tagline', 120)->nullable()->after('app_name');
            $table->string('logo')->nullable()->after('address');
            $table->json('settings')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['app_name', 'tagline', 'logo', 'settings']);
        });
    }
};
