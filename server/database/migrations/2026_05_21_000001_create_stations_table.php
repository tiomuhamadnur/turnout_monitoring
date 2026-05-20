<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();      // e.g. LBB, BLM, BHI
            $table->string('name', 160);
            $table->string('line', 32)->nullable();    // line/route identifier
            $table->text('description')->nullable();
            $table->decimal('latitude',  10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
