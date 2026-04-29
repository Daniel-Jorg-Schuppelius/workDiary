<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('legacy_user_id')->nullable()->after('id');
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('legacy_user_id');
        });
    }
};
