<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending staff invitations, one per email and tenant (D-009). The token
 * is stored as a SHA-256 hash; an accepted invitation is deleted.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 16);
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Also serves as the tenant_id index.
            $table->unique(['tenant_id', 'email']);
            // The inviter is a user of the same tenant (D-009).
            $table->foreign(['tenant_id', 'invited_by_id'])->references(['tenant_id', 'id'])->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
