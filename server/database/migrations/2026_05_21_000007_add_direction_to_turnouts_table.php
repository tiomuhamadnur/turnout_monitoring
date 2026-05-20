<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('turnouts', function (Blueprint $table) {
            $table->string('direction', 16)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('turnouts', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
