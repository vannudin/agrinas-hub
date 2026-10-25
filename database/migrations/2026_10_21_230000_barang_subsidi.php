<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menandai barang gudang sebagai SUBSIDI atau bukan.
 *
 * Kenapa kolom baru, bukan menebak dari kategori: "Pangan" berisi beras medium
 * (bersubsidi) DAN beras premium (komersial) — kategori gudang tidak bisa
 * memisahkannya. Tanpa penanda eksplisit, filter "Produk Subsidi" mustahil
 * dibuat benar; menebak dari nama barang akan salah pada barang berikutnya.
 *
 * Nilai bawaan 0 (non-subsidi): menandai sesuatu sebagai subsidi adalah
 * keputusan yang harus diambil sadar oleh pengelola, bukan akibat sampingan
 * dari migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kolom = collect(DB::connection('hub')->select('PRAGMA table_info(barang)'))
            ->pluck('name')->all();

        if (in_array('subsidi', $kolom, true)) {
            return;
        }

        DB::connection('hub')->statement('ALTER TABLE barang ADD COLUMN subsidi INTEGER NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        // SQLite tidak bisa DROP COLUMN di versi lama; biarkan kolomnya.
    }
};
