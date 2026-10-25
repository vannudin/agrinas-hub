<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori kas: SATU kategori untuk total pemasukan hari itu dan satu untuk
 * total pengeluaran hari itu.
 *
 * Kenapa bukan tabel akun + jurnal: bentuk data halaman ini memang satu baris
 * per hari (UNIQUE koperasi_id + tanggal) yang memuat TOTAL masuk dan TOTAL
 * keluar — `rincian_pemasukan` pun sudah satu baris teks untuk seluruh hari.
 * Kategori mengikuti bentuk itu, bukan memaksanya jadi buku besar berjurnal
 * yang belum ada pemakainya.
 *
 * Nilainya teks bebas berkunci (lihat App\Models\Domain::KATEGORI_KAS); kolom
 * default '' = "tanpa kategori" supaya baris lama tetap sah dan tak perlu
 * diisi ulang.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);
        if (! $s->hasTable('keuangan')) {
            return;
        }

        $s->table('keuangan', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('keuangan', 'kategori_pemasukan')) {
                $t->string('kategori_pemasukan')->default('');
            }
            if (! $s->hasColumn('keuangan', 'kategori_pengeluaran')) {
                $t->string('kategori_pengeluaran')->default('');
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        if (! $s->hasTable('keuangan')) {
            return;
        }

        $s->table('keuangan', function (Blueprint $t) use ($s) {
            $boleh = [];
            foreach (['kategori_pemasukan', 'kategori_pengeluaran'] as $k) {
                if ($s->hasColumn('keuangan', $k)) {
                    $boleh[] = $k;
                }
            }
            if ($boleh) {
                $t->dropColumn($boleh);
            }
        });
    }
};
