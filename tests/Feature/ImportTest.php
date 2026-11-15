<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('people.csv', "\xEF\xBB\xBF" . $content);
    }

    public function test_template_downloads(): void
    {
        $this->actingAs($this->user('Registrar'))
            ->get('/people/import/template')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="people-import-template.csv"');
    }

    public function test_rows_are_checked_then_imported_after_confirmation(): void
    {
        $this->locations();
        Person::factory()->create(['first_name' => 'Charles', 'last_name' => 'Mwinyi', 'date_of_birth' => '1991-03-21']);
        $user = $this->user('Registrar');

        $file = $this->csv(implode("\r\n", [
            'first_name,last_name,date_of_birth,gender,region,district,ward,village,phone',
            'Amina,Mrisho,1995-04-12,F,DAR-ES-SALAAM,ilala,Kariakoo,Congo,0754000001',   // sahihi (majina ya maeneo kwa herufi tofauti)
            'Baraka,Mollel,12/08/1988,Me,,,,,',                                           // sahihi (DD/MM/YYYY, Kiswahili)
            'Charles,Mwinyi,1991-03-21,Male,,,,,',                                        // nakala ya aliyepo
            ',NoFirst,2000-01-01,Male,,,,,',                                              // kosa: jina la kwanza
            'Dora,Kweka,31/02/1990,Female,,,,,',                                          // kosa: tarehe
            'Esta,Lyimo,1999-05-05,Female,Mbeyaa,,,,',                                    // kosa: mkoa haupo
        ]));

        $preview = $this->actingAs($user)->post('/people/import', ['file' => $file])->assertOk();
        $preview->assertSee('Check before importing')->assertSee('Region &quot;Mbeyaa&quot; was not found.', false);
        $this->assertSame(1, Person::anyStage()->count(), 'Nothing is saved at preview time');

        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $m);

        $this->actingAs($user)->post('/people/import/confirm', ['token' => $m[1]])->assertRedirect('/my-submissions');

        $this->assertSame(3, Person::anyStage()->count());
        $amina = Person::anyStage()->where('first_name', 'Amina')->sole();
        $this->assertSame(['Dar es Salaam', 'Ilala', 'Kariakoo', 'Congo'], [$amina->region, $amina->district, $amina->ward, $amina->village]);
        // Zinaingia kwenye foleni ya idhini; NIN inatolewa zikiidhinishwa.
        $this->assertSame('submitted', $amina->stage);
        $this->assertNull($amina->nin);
        $this->assertSame($user->id, $amina->created_by);
        $this->assertSame('1988-08-12', Person::anyStage()->where('first_name', 'Baraka')->sole()->date_of_birth->toDateString());

        // Token haiwezi kutumika mara mbili.
        $this->actingAs($user)->post('/people/import/confirm', ['token' => $m[1]])->assertSessionHasErrors('file');
        $this->assertSame(3, Person::anyStage()->count());
    }

    public function test_possible_duplicates_can_be_included_explicitly(): void
    {
        Person::factory()->create(['first_name' => 'Charles', 'last_name' => 'Mwinyi', 'date_of_birth' => '1991-03-21']);
        $user = $this->user();

        $preview = $this->actingAs($user)->post('/people/import', ['file' => $this->csv("first_name,last_name,date_of_birth,gender\r\nCharles,Mwinyi,1991-03-21,M")]);
        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $m);

        $this->actingAs($user)->post('/people/import/confirm', ['token' => $m[1], 'include_warnings' => 1]);
        $this->assertSame(2, Person::anyStage()->count());
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->post('/people/import', ['file' => $this->csv("name,dob\r\nAmina,1995-01-01")])
            ->assertSessionHasErrors('file');
    }
}
