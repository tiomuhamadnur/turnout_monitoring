<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();          // e.g. W1110
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('type', 64)->nullable();         // e.g. point machine type
            $table->string('line', 32)->nullable();
            $table->string('chainage', 32)->nullable();     // e.g. "KM 12+345"
            $table->decimal('latitude',  10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('photo_path')->nullable();       // storage/app/public/turnouts/...
            $table->timestamps();

            $table->index(['station_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnouts');
    }
};
