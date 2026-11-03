<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('username')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('password_hash')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->timestamps();
        });

        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('location')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::create('need_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['need_id', 'profile_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('need_id')->nullable()->constrained('needs')->nullOnDelete();
            $table->string('need_title')->nullable();
            $table->string('category')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('diproses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('need_supports');
        Schema::dropIfExists('needs');
        Schema::dropIfExists('profiles');
    }
};
