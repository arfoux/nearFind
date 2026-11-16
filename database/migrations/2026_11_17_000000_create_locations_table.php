<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Places a warga explicitly saves from the map. Nominatim is lookup only:
     * the chosen row lives here, alongside the pin the visitor dropped.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('address')->nullable();
            $table->string('area')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source')->default('warga');
            $table->string('author_email');
            $table->timestamps();

            // Dedup first narrows by name, then measures the pin distance.
            $table->index('name');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
