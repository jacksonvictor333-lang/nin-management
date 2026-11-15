<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Support\SignatureGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Weka sahihi za mfano (bandia) kwa kila mtu ambaye bado hana sahihi.
 * Sahihi zilizopo (zilizochorwa au kupakiwa) haziguswi.
 *
 *   php artisan db:seed --class=SampleSignaturesSeeder
 */
class SampleSignaturesSeeder extends Seeder
{
    public function run(): void
    {
        $generator = new SignatureGenerator();
        $count = 0;

        Person::whereNull('signature')
            ->each(function (Person $person) use ($generator, &$count) {
                $path = "signatures/sample-{$person->nin}.png";
                Storage::disk('public')->put($path, $generator->make($person->first_name, $person->last_name, crc32($person->nin)));

                // Bila kubadilisha updated_at ya rekodi.
                $person->timestamps = false;
                $person->update(['signature' => $path]);
                $count++;
            });

        $this->command?->info("Added signatures for {$count} sample people.");
    }
}
