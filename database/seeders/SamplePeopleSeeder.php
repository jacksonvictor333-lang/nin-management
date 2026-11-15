<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Person;
use App\Models\Region;
use App\Models\Village;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Watu wa mfano (data ya majaribio) wenye taarifa zote:
 * makazi halisi (mkoa → wilaya → kata → kijiji), mahali pa kuzaliwa,
 * mawasiliano, wazazi, mlezi na elimu inayolingana na umri.
 *
 *   php artisan db:seed --class=SamplePeopleSeeder
 *   SAMPLE_PEOPLE=250 php artisan db:seed --class=SamplePeopleSeeder
 */
class SamplePeopleSeeder extends Seeder
{
    private array $maleNames = [
        'Juma', 'Baraka', 'Emmanuel', 'Hamisi', 'Joseph', 'Daudi', 'Rashidi', 'Godfrey', 'Elia', 'Musa',
        'Salum', 'Peter', 'John', 'Abdallah', 'Hassan', 'Frank', 'Michael', 'Shabani', 'Yusuph', 'Erasto',
        'Gift', 'Amani', 'Ibrahim', 'Athumani', 'Kelvin', 'Samwel', 'Omari', 'Paulo', 'Faraji', 'Nassoro',
        'Iddi', 'Mussa', 'Lucas', 'Charles', 'Robert', 'Khamis', 'Bakari', 'Selemani', 'Ramadhani', 'Wilson',
    ];

    private array $femaleNames = [
        'Neema', 'Rehema', 'Asha', 'Zawadi', 'Mwanaidi', 'Grace', 'Esther', 'Halima', 'Upendo', 'Fatuma',
        'Mariam', 'Agnes', 'Saida', 'Happiness', 'Rose', 'Joyce', 'Mwajuma', 'Salma', 'Lucy', 'Witness',
        'Pendo', 'Elizabeth', 'Zuhura', 'Imani', 'Anna', 'Tumaini', 'Hadija', 'Faraja', 'Subira', 'Catherine',
        'Mwanahawa', 'Rukia', 'Sophia', 'Janeth', 'Hawa', 'Glory', 'Amina', 'Restituta', 'Tatu', 'Nasra',
    ];

    private array $surnames = [
        'Mwakalinga', 'Mushi', 'Kimaro', 'Massawe', 'Mwakyusa', 'Lyimo', 'Mollel', 'Njau', 'Shirima', 'Mfinanga',
        'Mrema', 'Kweka', 'Swai', 'Temba', 'Mbwambo', 'Kisanga', 'Chacha', 'Mwita', 'Magesa', 'Nyerere',
        'Mbise', 'Laizer', 'Sanga', 'Mgaya', 'Kilonzo', 'Mapunda', 'Komba', 'Haule', 'Ngonyani', 'Mbunda',
        'Said', 'Mohamed', 'Ally', 'Mrisho', 'Kombo', 'Mwinyi', 'Hamad', 'Juma', 'Makame', 'Kassim',
        'Kapinga', 'Nyoni', 'Kibona', 'Mwasomola', 'Mwalongo', 'Tarimo', 'Urio', 'Minja', 'Mosha', 'Lema',
    ];

    private array $primarySuffixes = ['Primary School', 'Primary School', 'English Medium Primary School'];

    private array $secondaryNames = [
        'Azania', 'Jangwani', 'Tambaza', 'Kibaha', 'Mzumbe', 'Ilboru', 'Tabora Boys', 'Msalato', 'Pugu', 'Kilakala',
        'Weruweru', 'Old Moshi', 'Mkwawa', 'Iyunga', 'Shaaban Robert', 'Lugalo', 'Benjamin Mkapa', 'Kisutu', 'Makongo', 'Mazengo',
    ];

    /** [taasisi, [ [kozi, sifa], ... ]] kwa ngazi ya elimu */
    private array $higher = [
        'Certificate' => [
            ['Vocational Education and Training Authority (VETA)', ['Certificate in Electrical Installation', 'Certificate in Tailoring', 'Certificate in Motor Vehicle Mechanics', 'Certificate in Food Production']],
            ['College of Business Education (CBE)', ['Certificate in Accountancy', 'Certificate in Procurement and Supplies']],
        ],
        'Diploma' => [
            ['Dar es Salaam Institute of Technology (DIT)', ['Ordinary Diploma in Civil Engineering', 'Ordinary Diploma in Electrical Engineering', 'Ordinary Diploma in Computer Engineering']],
            ['Institute of Finance Management (IFM)', ['Diploma in Accountancy', 'Diploma in Banking']],
            ['Kilimanjaro Christian Medical University College', ['Diploma in Clinical Medicine', 'Diploma in Nursing']],
            ['Tanzania Institute of Accountancy (TIA)', ['Diploma in Human Resource Management', 'Diploma in Marketing']],
        ],
        'Bachelor Degree' => [
            ['University of Dar es Salaam', ['Bachelor of Science in Computer Science', 'Bachelor of Laws (LLB)', 'Bachelor of Commerce in Finance', 'Bachelor of Arts in Education']],
            ['University of Dodoma', ['Bachelor of Business Administration', 'Bachelor of Science in Information Technology', 'Bachelor of Science in Nursing']],
            ['Sokoine University of Agriculture', ['Bachelor of Science in Agriculture General', 'Bachelor of Veterinary Medicine']],
            ['Muhimbili University of Health and Allied Sciences', ['Doctor of Medicine (MD)', 'Bachelor of Pharmacy']],
            ['Mzumbe University', ['Bachelor of Public Administration', 'Bachelor of Accounting and Finance']],
            ['Ardhi University', ['Bachelor of Science in Land Management', 'Bachelor of Architecture']],
        ],
        'Masters Degree' => [
            ['University of Dar es Salaam', ['Master of Business Administration', 'Master of Science in Computer Science']],
            ['Mzumbe University', ['Master of Public Administration', 'Master of Science in Development Policy']],
            ['Sokoine University of Agriculture', ['Master of Science in Agricultural Economics']],
        ],
        'PhD' => [
            ['University of Dar es Salaam', ['Doctor of Philosophy in Economics', 'Doctor of Philosophy in Chemistry']],
            ['Muhimbili University of Health and Allied Sciences', ['Doctor of Philosophy in Public Health']],
        ],
    ];

    private array $higherSuffix = [
        'Certificate' => 'Certificate',
        'Diploma' => 'Diploma',
        'Bachelor Degree' => 'Bachelor Degree',
        'Masters Degree' => 'Masters Degree',
        'PhD' => 'PhD',
    ];

    private array $usedPhones = [];

    public function run(): void
    {
        $count = (int) env('SAMPLE_PEOPLE', 100);

        $regions = Region::pluck('name', 'id');
        $districts = District::get(['id', 'region_id', 'name'])->groupBy('region_id');

        if ($regions->isEmpty() || Village::count() === 0) {
            $this->command?->error('Run TanzaniaAdministrativeDivisionsSeeder and TanzaniaVillagesSeeder first.');

            return;
        }

        $usedNins = Person::pluck('nin')->flip()->all();
        $this->usedPhones = Person::whereNotNull('phone')->pluck('phone')->flip()->all();
        $regionIds = $regions->keys()->all();

        DB::transaction(function () use ($count, $regions, $districts, $regionIds, &$usedNins) {
            for ($i = 0; $i < $count; $i++) {
                // Makazi: chagua mkoa kwanza (watu wagawanyike mikoa yote), kisha kijiji ndani yake.
                $regionId = $regionIds[$i % count($regionIds)];
                $village = Village::query()
                    ->join('wards', 'wards.id', '=', 'villages.ward_id')
                    ->join('districts', 'districts.id', '=', 'wards.district_id')
                    ->where('districts.region_id', $regionId)
                    ->inRandomOrder()
                    ->first([
                        'villages.id as village_id', 'villages.name as village',
                        'wards.id as ward_id', 'wards.name as ward',
                        'districts.id as district_id', 'districts.name as district',
                    ]);

                if (! $village) {
                    $village = Village::query()
                        ->join('wards', 'wards.id', '=', 'villages.ward_id')
                        ->join('districts', 'districts.id', '=', 'wards.district_id')
                        ->inRandomOrder()
                        ->first([
                            'villages.id as village_id', 'villages.name as village',
                            'wards.id as ward_id', 'wards.name as ward',
                            'districts.id as district_id', 'districts.name as district',
                            'districts.region_id as region_id',
                        ]);
                    $regionId = $village->region_id;
                }

                // Mahali pa kuzaliwa: 60% mkoa uleule, wengine wamehamia.
                $birthRegionId = mt_rand(1, 100) <= 60 ? $regionId : $regionIds[array_rand($regionIds)];
                $birthDistrict = $birthRegionId === $regionId && mt_rand(0, 1)
                    ? (object) ['id' => $village->district_id, 'name' => $village->district]
                    : $districts[$birthRegionId]->random();

                $gender = mt_rand(0, 1) ? 'Male' : 'Female';
                $first = $this->pick($gender === 'Male' ? $this->maleNames : $this->femaleNames);
                $fatherFirst = $this->pick($this->maleNames);
                $surname = $this->pick($this->surnames);
                $middle = $fatherFirst;

                $dob = $this->dateOfBirth();
                $age = $dob->age;

                $nin = $this->nin($dob, $usedNins);
                $usedNins[$nin] = true;

                $motherFirst = $this->pick($this->femaleNames);
                $motherSurname = $this->pick($this->surnames);

                // Mlezi: ndugu wa familia (mjomba, shangazi au kaka/dada mkubwa).
                $guardianIsMale = (bool) mt_rand(0, 1);
                $guardianName = $this->pick($guardianIsMale ? $this->maleNames : $this->femaleNames) . ' '
                    . (mt_rand(0, 1) ? $surname : $motherSurname);

                $createdAt = $this->registeredAt();

                Person::create(array_merge([
                    'nin' => $nin,
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $surname,
                    'date_of_birth' => $dob->toDateString(),
                    'gender' => $gender,
                    'nationality' => mt_rand(1, 100) <= 97 ? 'Tanzanian' : $this->pick(['Kenyan', 'Ugandan', 'Burundian', 'Rwandan']),

                    'birth_region_id' => $birthRegionId,
                    'birth_region' => $regions[$birthRegionId],
                    'birth_district_id' => $birthDistrict->id,
                    'birth_district' => $birthDistrict->name,

                    'region_id' => $regionId,
                    'region' => $regions[$regionId],
                    'district_id' => $village->district_id,
                    'district' => $village->district,
                    'ward_id' => $village->ward_id,
                    'ward' => $village->ward,
                    'village_id' => $village->village_id,
                    'village' => $village->village,

                    'phone' => $this->phone(),
                    'email' => $this->email($first, $surname),
                    'status' => mt_rand(1, 100) <= 93 ? 'Active' : 'Inactive',

                    'father_name' => "{$fatherFirst} {$this->pick($this->maleNames)} {$surname}",
                    'father_phone' => $this->phone(),
                    'mother_name' => "{$motherFirst} {$this->pick($this->maleNames)} {$motherSurname}",
                    'mother_phone' => $this->phone(),
                    'guardian_name' => $guardianName,
                    'guardian_phone' => $this->phone(),
                ], $this->education($dob, $age, $village->ward)))->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->save();
            }
        });

        $this->call(SampleSignaturesSeeder::class);

        $this->command?->info("Added {$count} sample people.");
    }

    /**
     * Elimu inayolingana na umri: darasa la saba ~ miaka 13, kidato cha nne ~ 17,
     * chuo baadaye. Wazee wengi wana elimu ya msingi tu.
     */
    private function education(Carbon $dob, int $age, string $ward): array
    {
        $born = $dob->year;
        $roll = mt_rand(1, 100);

        $level = match (true) {
            $age < 20 => $roll <= 35 ? 'Primary' : 'Secondary',
            $age < 23 => $roll <= 25 ? 'Primary' : ($roll <= 70 ? 'Secondary' : ($roll <= 88 ? 'Certificate' : 'Diploma')),
            $age >= 60 => $roll <= 55 ? 'Primary' : ($roll <= 80 ? 'Secondary' : ($roll <= 92 ? 'Diploma' : 'Bachelor Degree')),
            default => match (true) {
                $roll <= 22 => 'Primary',
                $roll <= 45 => 'Secondary',
                $roll <= 55 => 'Certificate',
                $roll <= 70 => 'Diploma',
                $roll <= 89 => 'Bachelor Degree',
                $roll <= 97 || $age < 30 => 'Masters Degree',
                default => 'PhD',
            },
        };

        $primaryYear = $born + 13;
        $data = [
            'education_level' => $level,
            'primary_school' => Str::title($ward) . ' ' . $this->pick($this->primarySuffixes),
            'primary_completion_year' => $primaryYear,
            'secondary_school' => null,
            'secondary_completion_year' => null,
            'higher_institution' => null,
            'course' => null,
            'qualification' => 'Certificate of Primary Education (CPEE)',
        ];

        if ($level === 'Primary') {
            return $data;
        }

        $data['secondary_school'] = $this->pick($this->secondaryNames) . ' Secondary School';
        $data['secondary_completion_year'] = min($primaryYear + (mt_rand(1, 100) <= 40 ? 6 : 4), (int) now()->year);
        $data['qualification'] = $data['secondary_completion_year'] - $primaryYear === 6
            ? 'Advanced Certificate of Secondary Education (ACSEE)'
            : 'Certificate of Secondary Education (CSEE)';

        if ($level === 'Secondary') {
            return $data;
        }

        [$institution, $courses] = $this->pick($this->higher[$level]);
        $data['higher_institution'] = $institution;
        $data['course'] = $this->pick($courses);
        $data['qualification'] = $this->higherSuffix[$level];

        return $data;
    }

    /** Umri wa miaka 18–80, wengi kati ya 20 na 45. */
    private function dateOfBirth(): Carbon
    {
        $age = mt_rand(1, 100) <= 70 ? mt_rand(20, 45) : mt_rand(18, 80);

        return now()->subYears($age)->subDays(mt_rand(1, 364))->startOfDay();
    }

    /** NIN: YYYYMMDD + tarakimu 12 (kama PersonController). */
    private function nin(Carbon $dob, array $used): string
    {
        do {
            $nin = $dob->format('Ymd');
            for ($d = 0; $d < 12; $d++) {
                $nin .= mt_rand(0, 9);
            }
        } while (isset($used[$nin]));

        return $nin;
    }

    private function phone(): string
    {
        $prefixes = ['0754', '0755', '0756', '0713', '0715', '0718', '0765', '0767', '0784', '0786', '0621', '0688', '0744', '0746'];

        do {
            $phone = $this->pick($prefixes) . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (isset($this->usedPhones[$phone]));

        $this->usedPhones[$phone] = true;

        return $phone;
    }

    private function email(string $first, string $last): string
    {
        $user = Str::lower(Str::ascii($first)) . $this->pick(['.', '', '_']) . Str::lower(Str::ascii($last)) . mt_rand(1, 999);

        return $user . '@' . $this->pick(['example.com', 'example.org', 'example.net']);
    }

    /** Tarehe ya usajili ndani ya miezi 12 iliyopita (kwa grafu ya dashboard). */
    private function registeredAt(): Carbon
    {
        return now()->subDays(mt_rand(0, 364))->setTime(mt_rand(8, 16), mt_rand(0, 59), mt_rand(0, 59));
    }

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
