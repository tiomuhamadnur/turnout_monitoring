<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('line');
        });

        Schema::table('turnouts', function (Blueprint $table) {
            $table->foreignId('line_id')->nullable()->after('type')->constrained('lines')->nullOnDelete();
            $table->float('chainage')->nullable()->change();
            $table->dropColumn(['line', 'manufacturer']);
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('line', 32)->nullable()->after('name');
        });

        Schema::table('turnouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_id');
            $table->string('line', 32)->nullable()->after('type');
            $table->string('manufacturer', 120)->nullable()->after('longitude');
            $table->string('chainage', 32)->nullable()->change();
        });
    }
};
