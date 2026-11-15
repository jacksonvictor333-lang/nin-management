<?php

namespace Tests\Unit;

use App\Support\DuplicateFinder;
use PHPUnit\Framework\TestCase;

class DuplicateNamesTest extends TestCase
{
    public function test_similar_names_match(): void
    {
        $f = new DuplicateFinder();

        $this->assertTrue($f->namesMatch('Juma', null, 'Mushi', 'Juma', 'Ally', 'Mushi'));          // sawa kabisa
        $this->assertTrue($f->namesMatch('Jumaa', null, 'Mushi', 'Juma', null, 'Mushi'));          // herufi moja zaidi
        $this->assertTrue($f->namesMatch('Mushi', null, 'Juma', 'Juma', null, 'Mushi'));           // yamegeuzwa
        $this->assertTrue($f->namesMatch('Ally', null, 'Mushi', 'Juma', 'Ally', 'Mushi'));         // jina la kati kama la kwanza
        $this->assertTrue($f->namesMatch('ÉSTHER', null, 'mollel', 'Esther', null, 'Mollel'));    // herufi kubwa/alama
    }

    public function test_different_names_do_not_match(): void
    {
        $f = new DuplicateFinder();

        $this->assertFalse($f->namesMatch('Juma', null, 'Mushi', 'Asha', null, 'Mushi'));
        $this->assertFalse($f->namesMatch('Juma', null, 'Mushi', 'Juma', null, 'Kimaro'));
        $this->assertFalse($f->namesMatch('', null, 'Mushi', 'Juma', null, 'Mushi'));
    }
}
