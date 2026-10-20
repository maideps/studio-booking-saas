<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The waitlist (D-011), and the studio's cancellation window.
 *
 * Entries are served in id order (first in, first out). Like bookings,
 * `active` is true while the customer waits and NULL once they leave or are
 * promoted, so the unique index allows one waiting entry per customer and
 * session without blocking a later one (D-010).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancellation_window_hours')->default(12)->after('contact_email');
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('customer_id');
            $table->boolean('active')->nullable()->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'class_session_id', 'customer_id', 'active'], 'waitlist_one_active_per_customer');
            $table->index(['tenant_id', 'class_session_id', 'active', 'id']);
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
        Schema::dropIfExists('waitlist_entries');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('cancellation_window_hours');
        });
    }
};
