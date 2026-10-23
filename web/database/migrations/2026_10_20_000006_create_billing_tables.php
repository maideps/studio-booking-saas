<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio pricing, checkouts, memberships and the class-pack credit ledger
 * (D-012). All billing rows are tenant-owned, and every reference between
 * them is a composite (tenant_id, …) foreign key (D-009).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One monthly membership and one class pack per studio; NULL means not offered.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('currency', 3)->default('eur')->after('cancellation_window_hours');
            $table->unsignedInteger('membership_price_cents')->nullable()->after('currency');
            $table->unsignedSmallInteger('class_pack_size')->nullable()->after('membership_price_cents');
            $table->unsignedInteger('class_pack_price_cents')->nullable()->after('class_pack_size');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // membership, credit or free: how the booking was covered.
            $table->string('paid_with', 16)->nullable()->after('active');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('kind', 16);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->unsignedSmallInteger('credits')->nullable();
            $table->string('gateway_session_id')->nullable()->unique();
            $table->string('status', 16)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('users');
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('checkout_id')->nullable();
            $table->string('status', 16);
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('gateway_subscription_id')->nullable()->unique();
            $table->timestamps();

            // One membership row per customer; buying again updates it.
            $table->unique(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'checkout_id'])->references(['tenant_id', 'id'])->on('checkouts');
        });

        // Append-only: the balance is the sum of `change`.
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->integer('change');
            $table->string('reason', 16);
            // A checkout is credited once; only purchases carry one.
            $table->unsignedBigInteger('checkout_id')->nullable()->unique();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'checkout_id'])->references(['tenant_id', 'id'])->on('checkouts');
            $table->foreign(['tenant_id', 'booking_id'])->references(['tenant_id', 'id'])->on('bookings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('checkouts');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropColumn('paid_with');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['currency', 'membership_price_cents', 'class_pack_size', 'class_pack_price_cents']);
        });
    }
};
