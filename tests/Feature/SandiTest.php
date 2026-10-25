<?php

namespace Tests\Feature;

use App\Support\ScryptHasher;
use Tests\TestCase;

class SandiTest extends TestCase
{
    public function test_memverifikasi_sandi_yang_benar(): void
    {
        $h = ScryptHasher::buat('SandiUji#2026');

        $this->assertStringStartsWith('scrypt2$16384$8$1$', $h);
        $this->assertTrue(ScryptHasher::cek('SandiUji#2026', $h));
    }

    public function test_menolak_sandi_yang_salah(): void
    {
        $h = ScryptHasher::buat('SandiUji#2026');

        $this->assertFalse(ScryptHasher::cek('sandiSalah', $h));
    }

    /** Nilai tersimpan rusak tidak boleh memicu error — harus ditolak dengan aman. */
    public function test_menolak_nilai_rusak_tanpa_error(): void
    {
        foreach (['', 'scrypt$16384$8$1$pendek$x', 'bcrypt$abc',
                  'scrypt$16384$8$1$!!!$x', 'scrypt$16384$9$1$YWJj$eA'] as $rusak) {
            $this->assertFalse(ScryptHasher::cek('apa pun', $rusak), "gagal tolak: {$rusak}");
        }
    }

    public function test_salt_acak_bikin_hash_berbeda(): void
    {
        $this->assertNotSame(ScryptHasher::buat('sama'), ScryptHasher::buat('sama'));
    }
}
