<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_applications', function (Blueprint $table) {
            $table->boolean('use_signed_token')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('sso_applications', function (Blueprint $table) {
            $table->dropColumn('use_signed_token');
        });
    }
};
