
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kwenye usakinishaji mpya columns hizi tayari zipo (create migration): ruka.
        if (Schema::hasColumn('nin_verifications', 'session_token_hash')) {
            return;
        }

        Schema::table('nin_verifications', function (Blueprint $table) {

            $table->string('session_token_hash', 64)->unique();

            $table->json('questions');

            $table->unsignedTinyInteger('questions_answered')
                ->default(0);

            $table->unsignedTinyInteger('correct_answers')
                ->default(0);

            $table->enum('status', [
                'pending',
                'passed',
                'failed'
            ])->default('pending');

            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nin_verifications', function (Blueprint $table) {

            $table->dropUnique(['session_token_hash']);

            $table->dropColumn([
                'session_token_hash',
                'questions',
                'questions_answered',
                'correct_answers',
                'status',
                'expires_at',
            ]);
        });
    }
};
