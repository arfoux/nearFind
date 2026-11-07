<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-service registration: the account starts unusable (no
     * `email_verified_at`) and is activated by the token mailed to the address.
     * The token is a random string, not a signed payload: the controller that
     * reads it stays plain PHP and therefore compiles for the edge.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['email_verification_token']);
            $table->dropColumn(['email_verified_at', 'email_verification_token']);
        });
    }
};
