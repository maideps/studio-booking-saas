<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class templates and the sessions scheduled from them (D-009).
 *
 * A reference from one tenant-owned row to another is a composite foreign
 * key on (tenant_id, <id>), so the database itself refuses a session that
 * points at another tenant's template or instructor. Each parent table has
 * a unique (tenant_id, id) index for these keys to reference.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('class_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->timestamps();

            // Also serves as the tenant_id index.
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('class_template_id');
            $table->unsignedBigInteger('instructor_id');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'class_template_id']);
            $table->index(['tenant_id', 'instructor_id']);
            $table->foreign(['tenant_id', 'class_template_id'])->references(['tenant_id', 'id'])->on('class_templates');
            $table->foreign(['tenant_id', 'instructor_id'])->references(['tenant_id', 'id'])->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('class_templates');
    }
};
