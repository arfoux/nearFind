<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns the map, the list and the recap read. Until now a need was
     * only (title, category, location, status) and everything else — the marker
     * label, the description, the coordinates, the support count — lived in
     * `config/needs.php`. `/needs` now inserts rows, so those fields belong to
     * the row.
     */
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->string('marker_label')->nullable()->after('title');
            $table->text('description')->nullable()->after('location');
            $table->string('area')->nullable()->after('description');
            $table->decimal('latitude', 10, 7)->nullable()->after('area');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('distance_km', 6, 2)->nullable()->after('longitude');
            $table->unsignedInteger('supporters')->default(0)->after('distance_km');
            $table->boolean('opportunity')->default(false)->after('supporters');

            // The map plots a point per row, so a need without coordinates is a
            // listing, not a marker. Both are written together or not at all.
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);

            $table->dropColumn([
                'marker_label',
                'description',
                'area',
                'latitude',
                'longitude',
                'distance_km',
                'supporters',
                'opportunity',
            ]);
        });
    }
};
