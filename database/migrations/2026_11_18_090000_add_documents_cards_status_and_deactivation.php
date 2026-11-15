<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Nyaraka za mwombaji (cheti cha kuzaliwa n.k.)
 * - Namba ya ombi (reference) ya kuangalia hali ya ombi
 * - Mzunguko wa kadi: imechapishwa → iko tayari → imekabidhiwa, na kadi mbadala
 * - Kusitisha NIN (kifo, udanganyifu, n.k.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('card_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('card_version')->default(1);
            $table->string('event', 20);          // printed, ready, collected, lost, damaged, stolen
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['person_id', 'id']);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('nin');

            $table->string('card_status', 20)->nullable()->after('review_note')->index();
            $table->unsignedSmallInteger('card_version')->default(1)->after('card_status');
            $table->timestamp('card_printed_at')->nullable()->after('card_version');
            $table->timestamp('card_ready_at')->nullable()->after('card_printed_at');
            $table->timestamp('card_collected_at')->nullable()->after('card_ready_at');
            $table->string('card_collected_by', 150)->nullable()->after('card_collected_at');

            $table->string('deactivation_reason', 30)->nullable()->after('card_collected_by');
            $table->string('deactivation_note', 500)->nullable()->after('deactivation_reason');
            $table->date('date_of_death')->nullable()->after('deactivation_note');
            $table->timestamp('deactivated_at')->nullable()->after('date_of_death');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
        });

        // Namba ya ombi kwa rekodi zilizopo: APP-<mwaka>-<id ya tarakimu 6>
        DB::table('people')->orderBy('id')->select('id', 'created_at')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('people')->where('id', $row->id)->update([
                    'reference' => sprintf('APP-%s-%06d', $row->created_at ? substr($row->created_at, 0, 4) : date('Y'), $row->id),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropUnique(['reference']);
            $table->dropIndex(['card_status']);
            $table->dropColumn([
                'reference', 'card_status', 'card_version', 'card_printed_at', 'card_ready_at', 'card_collected_at',
                'card_collected_by', 'deactivation_reason', 'deactivation_note', 'date_of_death', 'deactivated_at',
            ]);
        });

        Schema::dropIfExists('card_events');
        Schema::dropIfExists('person_documents');
    }
};
