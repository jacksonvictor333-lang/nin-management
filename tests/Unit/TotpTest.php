<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** Mifano rasmi ya RFC 6238 (SHA1), tarakimu 6 za mwisho. */
    public function test_rfc_6238_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame('287082', Totp::code($secret, intdiv(59, 30)));
        $this->assertSame('081804', Totp::code($secret, intdiv(1111111109, 30)));
        $this->assertSame('050471', Totp::code($secret, intdiv(1111111111, 30)));
        $this->assertSame('005924', Totp::code($secret, intdiv(1234567890, 30)));
    }

    public function test_verify_accepts_current_code_and_rejects_others(): void
    {
        $secret = Totp::generateSecret();

        $this->assertNotNull(Totp::verify($secret, Totp::code($secret)));
        $this->assertNotNull(Totp::verify($secret, substr(Totp::code($secret), 0, 3) . ' ' . substr(Totp::code($secret), 3)));
        $this->assertNull(Totp::verify($secret, '12345'));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, intdiv(time(), 30) - 5)));
    }

    public function test_base32_round_trip(): void
    {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
    }
}
