<?php

namespace Tests;

use App\Models\District;
use App\Models\Region;
use App\Models\User;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function user(string $role = 'Administrator', array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $attributes));
    }

    /** Eneo dogo la majaribio: Dar es Salaam > Ilala > Kariakoo > Mtaa wa Congo. */
    protected function locations(): array
    {
        $region = Region::create(['id' => 'TZ07', 'name' => 'Dar es Salaam']);
        $district = District::create(['id' => 'TZ0702', 'region_id' => 'TZ07', 'name' => 'Ilala']);
        $ward = Ward::create(['id' => 'TZ070201', 'district_id' => 'TZ0702', 'name' => 'Kariakoo']);
        $village = Village::create(['ward_id' => 'TZ070201', 'name' => 'Congo', 'type' => 'Street']);

        return compact('region', 'district', 'ward', 'village');
    }

    /** Taarifa za chini kabisa za usajili kupitia fomu. */
    protected function personForm(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amina',
            'middle_name' => 'Juma',
            'last_name' => 'Mrisho',
            'date_of_birth' => '1995-04-12',
            'gender' => 'Female',
            'nationality' => 'Tanzanian',
            'phone' => '0754123456',
        ], $overrides);
    }
}
