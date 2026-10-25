<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data lahan & pembangunan KDKMP (verval aset) per koperasi — ditarik dari
 * SIMKOPDES `/cooperative-assets/by-regional-facilitator-id/{fid}`.
 *
 * Satu tabel tersendiri, BUKAN kolom tambahan di `koperasi`: satu koperasi bisa
 * punya lebih dari satu aset (bidang lahan), dan kolom `koperasi` sudah dipakai
 * layar lain. Tautannya `koperasi_simkopdes_id` (kolom yang sudah ada di
 * `koperasi`), bukan id internal — supaya baris tetap ketemu walau koperasi
 * ditambah/ditarik ulang.
 *
 * Nilainya DISIMPAN, bukan diambil saat halaman dibuka: API SIMKOPDES 403/500
 * sesekali dan halaman Koperasi tidak boleh ikut mati karenanya. Ini juga yang
 * membuat kolom di tabel bisa dicari/diurutkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->create('koperasi_lahan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('koperasi_id')->nullable();       // koperasi Hub (bila tertaut)
            $table->unsignedBigInteger('asset_simkopdes_id')->nullable(); // cooperative_asset_id
            $table->unsignedBigInteger('koperasi_simkopdes_id')->nullable();
            $table->string('nama')->default('');               // nama aset
            $table->string('nama_koperasi')->default('');      // dari relasi `cooperative.name`
            $table->string('kontak')->default('');             // `cooperative.phone` — PIC koperasi
            $table->decimal('luas', 14, 2)->nullable();        // land_area (m2)
            $table->decimal('lebar', 10, 2)->nullable();       // land_width
            $table->decimal('panjang', 10, 2)->nullable();     // land_length
            $table->string('status_aset')->default('');        // asset_status (Hibah, ...)
            $table->string('kepemilikan')->default('');        // ownership (Agrinas, Desa, KOPERASI, ...)
            $table->string('status')->default('');             // status verval (Terverifikasi, ...)
            $table->string('akses_jalan')->default('');        // road_access
            $table->string('surveyor')->default('');           // pendamping yang mensurvei
            $table->string('pemilik_terdaftar')->default('');  // registered_owner_name
            $table->string('alamat')->default('');
            $table->decimal('latitude', 16, 10)->nullable();
            $table->decimal('longitude', 16, 10)->nullable();
            $table->text('keterangan')->nullable();            // asset_information (JSON) — akses air/listrik/jarak
            $table->string('village_code')->default('');       // kode desa resmi (76.02.07.2005)
            $table->string('berkas')->default('');             // document_primary_file (URL GCS bertanda tangan)
            $table->string('foto')->default('');               // image_primary (URL GCS bertanda tangan)
            $table->timestamp('aset_diperbarui')->nullable();  // updated_at dari SIMKOPDES
            $table->timestamp('ditarik')->nullable();          // kapan Hub terakhir menarik
            $table->timestamps();

            $table->unique('asset_simkopdes_id');
            $table->index('koperasi_id');
            $table->index('koperasi_simkopdes_id');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('koperasi_lahan');
    }
};
