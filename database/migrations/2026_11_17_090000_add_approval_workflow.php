<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hatua za idhini: Rasimu → Imewasilishwa → Inakaguliwa → Imeidhinishwa
 * (au Imerudishwa / Imekataliwa). NIN inatolewa wakati wa kuidhinisha,
 * kwa hiyo rekodi ambazo bado hazijaidhinishwa hazina NIN.
 *
 * Rekodi zilizopo zinawekwa "approved" moja kwa moja (default ya safu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('nin', 20)->nullable()->change();

            $table->string('stage', 20)->default('approved')->after('status')->index();
            $table->foreignId('created_by')->nullable()->after('stage')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('reviewer_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_id');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });

        Schema::create('person_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);          // draft, submitted, claimed, released, approved, returned, rejected
            $table->text('note')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['person_id', 'id']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('person_reviews');

        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropIndex(['stage']);
            $table->dropColumn(['stage', 'submitted_at', 'reviewed_at', 'review_note']);
        });
    }
};
