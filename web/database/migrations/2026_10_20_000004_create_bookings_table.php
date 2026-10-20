<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's place in a class session (D-010).
 *
 * `active` is true while the booking holds a place and NULL once it is
 * released (cancellation, feat-006). The unique index over (tenant_id,
 * class_session_id, customer_id, active) therefore allows one active booking
 * per customer and session, because unique indexes treat NULLs as distinct in
 * PostgreSQL and SQLite, while released bookings never block rebooking.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('customer_id');
            $table->boolean('active')->nullable()->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'class_session_id', 'customer_id', 'active'], 'bookings_one_active_per_customer');
            $table->index(['tenant_id', 'class_session_id', 'active']);
            $table->index(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'class_session_id'])->references(['tenant_id', 'id'])->on('class_sessions');
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
