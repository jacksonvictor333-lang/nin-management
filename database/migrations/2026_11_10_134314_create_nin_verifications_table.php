
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nin_verifications', function (Blueprint $table) {
            $table->id();

            // NIN being verified
            $table->string('nin', 20)->index();

            // Secure verification session
            $table->string('session_token_hash', 64)->unique();

            // Five questions in randomized order
            $table->json('questions');

            // Number of questions answered
            $table->unsignedTinyInteger('questions_answered')->default(0);

            // Correct answers count
            $table->unsignedTinyInteger('correct_answers')->default(0);

            // Verification status
            $table->enum('status', [
                'pending',
                'passed',
                'failed'
            ])->default('pending');

            // Session expiration
            $table->timestamp('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nin_verifications');
    }
};
