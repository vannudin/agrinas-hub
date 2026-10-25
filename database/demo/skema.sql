-- Skema Agrinas Hub (SQLite) — dibuat dari basis data kerja.
-- Tanpa data pribadi. Dipakai oleh database/demo/buat-demo.php.

CREATE TABLE agri_absen_hub (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        koperasi_id INTEGER,
        tanggal VARCHAR(10) NOT NULL,
        jenis VARCHAR(20) NOT NULL,
        waktu VARCHAR(5) NOT NULL,
        pada VARCHAR(40) NOT NULL DEFAULT '',
        zona VARCHAR(64) NOT NULL DEFAULT '',
        foto VARCHAR(160) NOT NULL DEFAULT '',
        lat VARCHAR(32),
        lng VARCHAR(32),
        akurasi VARCHAR(32),
        lokasi VARCHAR(255) NOT NULL DEFAULT '',
        jarak_m INTEGER,
        ip VARCHAR(64) NOT NULL DEFAULT '',
        perangkat VARCHAR(255) NOT NULL DEFAULT '',
        dibuat VARCHAR(40) NOT NULL DEFAULT ''
    , catatan VARCHAR(200) NOT NULL DEFAULT '');

CREATE TABLE agri_ac_absen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        tanggal VARCHAR(10) NOT NULL,
        masuk VARCHAR(25) NOT NULL DEFAULT '',
        keluar VARCHAR(25) NOT NULL DEFAULT '',
        telat INTEGER NOT NULL DEFAULT 0,
        pulang_awal INTEGER NOT NULL DEFAULT 0,
        alpha INTEGER NOT NULL DEFAULT 0,
        libur INTEGER NOT NULL DEFAULT 0,
        jam_kerja VARCHAR(8) NOT NULL DEFAULT '',
        remark VARCHAR(255) NOT NULL DEFAULT '',
        disinkron VARCHAR(40) NOT NULL DEFAULT ''
    );

CREATE TABLE "agri_berkas" ("id" integer primary key autoincrement not null, "koperasi_id" integer, "kategori" varchar not null default 'REGULASI', "judul" varchar not null, "keterangan" varchar not null default '', "nama_asli" varchar not null default '', "nama_simpan" varchar not null default '', "mime" varchar not null default '', "ukuran" integer not null default '0', "oleh" integer, "dibuat" varchar);

CREATE TABLE "agri_fokus" ("id" integer primary key autoincrement not null, "user_id" integer not null, "materi_id" integer not null, "langkah" varchar not null, "selesai" integer not null default '0', "dibuat" varchar, "dicatat" varchar);

CREATE TABLE "agri_imbalan" ("id" integer primary key autoincrement not null, "judul" varchar not null, "keterangan" varchar not null default '', "biaya" integer not null default '0', "stok" integer not null default '0', "aktif" integer not null default '1', "dibuat" varchar);

CREATE TABLE "agri_materi" ("id" integer primary key autoincrement not null, "judul" varchar not null, "ringkas" varchar not null default '', "isi" text, "tautan" varchar not null default '', "menit" integer not null default '0', "posisi" integer not null default '0', "oleh" integer, "dibuat" varchar, "langkah" text);

CREATE TABLE "agri_pengajuan" ("id" integer primary key autoincrement not null, "user_id" integer not null, "koperasi_id" integer, "jenis" varchar not null default 'UMUM', "judul" varchar not null, "keterangan" text, "nilai" integer not null default '0', "tanggal" varchar not null default '', "tanggal_akhir" varchar not null default '', "status" varchar not null default 'DIAJUKAN', "catatan" varchar not null default '', "pemutus_id" integer, "dibuat" varchar, "diputus" varchar);

CREATE TABLE "agri_poin" ("id" integer primary key autoincrement not null, "user_id" integer not null, "jumlah" integer not null, "hal" varchar not null, "dibuat" varchar);

CREATE TABLE "agri_presensi" ("id" integer primary key autoincrement not null, "user_id" integer not null, "koperasi_id" integer, "tanggal" varchar not null, "masuk" varchar not null default '', "keluar" varchar not null default '', "catatan" varchar not null default '', "dibuat" varchar, "masuk_foto" varchar not null default '', "masuk_lat" varchar, "masuk_lng" varchar, "masuk_akurasi" varchar, "masuk_lokasi" varchar not null default '', "masuk_pada" varchar not null default '', "keluar_foto" varchar not null default '', "keluar_lat" varchar, "keluar_lng" varchar, "keluar_akurasi" varchar, "keluar_lokasi" varchar not null default '', "keluar_pada" varchar not null default '');

CREATE TABLE "agri_tugas" ("id" integer primary key autoincrement not null, "user_id" integer not null, "koperasi_id" integer, "judul" varchar not null, "keterangan" text, "jenis" varchar not null default 'TASK', "prioritas" varchar not null default 'NORMAL', "tanggal" varchar not null default '', "jam" varchar not null default '', "status" varchar not null default 'BARU', "dibuat" varchar);

CREATE TABLE "agri_umpan" ("id" integer primary key autoincrement not null, "kepada_id" integer not null, "oleh_id" integer not null, "jenis" varchar not null default 'UMUM', "isi" text not null, "anonim" integer not null default '0', "dibuat" varchar);

CREATE TABLE "aspirasi" ("id" integer primary key autoincrement not null, "user_id" integer not null, "jenis" varchar not null default 'MASUKAN', "perihal" varchar not null, "pesan" text not null, "koperasi" varchar not null default '', "status" varchar not null default 'BARU', "tanggapan" text, "penanggap" varchar not null default '', "ditanggapi" datetime, "dibuat" varchar not null default '', "created_at" datetime, "updated_at" datetime);

CREATE TABLE audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aktor TEXT, aksi TEXT NOT NULL, target TEXT,
  ip TEXT, detail TEXT, dibuat TEXT NOT NULL
);

CREATE TABLE "barang" ("id" integer primary key autoincrement not null, "kode" varchar not null, "nama" varchar not null, "satuan" varchar not null default 'SAK', "kategori" varchar not null default 'PANGAN', "stok" integer not null default '0', "stok_min" integer not null default '0', "catatan" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null, "vendor_id" integer, "harga" integer not null default '0', "gambar" varchar not null default '', "sumber" varchar not null default 'GUDANG', "aktif" integer not null default '1', subsidi INTEGER NOT NULL DEFAULT 0);

CREATE TABLE barang_harga_grosir (
                id integer primary key autoincrement,
                barang_id integer not null,
                min_jumlah integer not null,
                harga integer not null,
                dibuat varchar
            );

CREATE TABLE "broadcast" ("id" integer primary key autoincrement not null, "kanal" varchar not null, "perihal" varchar not null default '', "pesan" text not null, "sasaran" varchar not null default '', "jumlah" integer not null default '0', "pengirim" varchar not null default '', "dibuat" varchar not null);

CREATE TABLE "catatan_harian" ("id" integer primary key autoincrement not null, "user_id" integer not null, "koperasi_id" integer, "tanggal" varchar not null, "isi" text, "kendala" text, "dibuat" varchar, "diperbarui" varchar, judul TEXT NOT NULL DEFAULT '', jenis TEXT NOT NULL DEFAULT 'kegiatan');

CREATE TABLE "distribusi" ("id" integer primary key autoincrement not null, "kode" varchar not null, "koperasi_id" integer not null, "pemohon" integer, "status" varchar not null default 'DIAJUKAN', "catatan" varchar not null default '', "catatan_agrinas" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null);

CREATE TABLE "distribusi_item" ("id" integer primary key autoincrement not null, "distribusi_id" integer not null, "barang_id" integer not null, "jumlah" integer not null, "jumlah_diterima" integer not null default '0');

CREATE TABLE "dokumen" ("id" integer primary key autoincrement not null, "koperasi_id" integer, "judul" varchar not null, "jenis" varchar not null default 'docx', "berkas" varchar not null, "kunci" varchar not null, "dibuat_oleh" integer, "dibuat" varchar, "diperbarui" varchar, "pribadi" integer not null default '0', "pemilik_id" integer, akses TEXT NOT NULL DEFAULT 'koperasi');

CREATE TABLE dokumen_akses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  dokumen_id INTEGER NOT NULL,
  pengguna_id INTEGER NOT NULL,
  pemohon_id INTEGER,
  pemutus_id INTEGER,
  status TEXT NOT NULL DEFAULT 'diminta',
  pesan TEXT NOT NULL DEFAULT '',
  diminta TEXT NOT NULL DEFAULT '',
  diputus TEXT,
  UNIQUE (dokumen_id, pengguna_id)
);

CREATE TABLE "keuangan" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "tanggal" varchar not null, "pemasukan" integer not null default '0', "pengeluaran" integer not null default '0', "rincian_pemasukan" varchar not null default '', "rincian_pengeluaran" varchar not null default '', "catatan" varchar not null default '', "dicatat_oleh" integer, "dibuat" varchar not null, "diperbarui" varchar not null, "kanal" varchar not null default '', "ref" varchar not null default '', "kategori_pemasukan" varchar not null default '', "kategori_pengeluaran" varchar not null default '');

CREATE TABLE kiriman (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tugas_id INTEGER NOT NULL REFERENCES tugas(id),
  nama_asli TEXT NOT NULL,
  path TEXT NOT NULL,
  sha256 TEXT NOT NULL,
  ukuran INTEGER NOT NULL,
  catatan TEXT,
  oleh INTEGER REFERENCES users(id),
  dibuat TEXT NOT NULL
);

CREATE TABLE komentar (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tugas_id INTEGER NOT NULL REFERENCES tugas(id),
  oleh INTEGER REFERENCES users(id),
  nama TEXT NOT NULL,
  isi TEXT NOT NULL,
  dibuat TEXT NOT NULL
);

CREATE TABLE "konferensi" ("id" integer primary key autoincrement not null, "judul" varchar not null, "ruang" varchar not null, "mulai" varchar not null, "selesai" varchar not null default '', "catatan" varchar not null default '', "oleh" integer, "dibuat" varchar not null, "siaran_sesi" varchar not null default '', "siaran_oleh" varchar not null default '', "jenis" varchar not null default 'meeting', "siaran_penyiar" text not null default '', "kendali" text not null default '', "pembicara" text not null default '');

CREATE TABLE "kontrak" ("id" integer primary key autoincrement not null, "user_id" integer not null, "periode_id" integer not null, "periode_mulai" varchar not null default '', "periode_selesai" varchar not null default '', "periode_status" varchar not null default '', "periode_program" varchar not null default '', "penempatan_koperasi_id" integer not null default '0', "penempatan_nama" varchar not null default '', "penempatan_provinsi" varchar not null default '', "penempatan_wilayah" varchar not null default '', "penempatan_jenis" varchar not null default '', "penempatan_mulai" varchar not null default '', "penempatan_selesai" varchar not null default '', "kontrak_id" integer, "kontrak_nomor" varchar not null default '', "kontrak_komitmen" varchar not null default '', "kontrak_jenis" varchar not null default '', "kontrak_status" varchar not null default '', "kontrak_label" varchar not null default '', "kontrak_mulai" varchar not null default '', "kontrak_selesai" varchar not null default '', "kontrak_ttd" varchar not null default '', "kontrak_sumber" varchar not null default '', "kontrak_tautan" varchar not null default '', "sumber" varchar not null default 'SIMKOPDES', "ditarik_pada" varchar not null default '', "dibuat" varchar not null default '', "diperbarui" varchar not null default '');

CREATE TABLE koperasi (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kode TEXT UNIQUE NOT NULL,
  nama TEXT NOT NULL,
  wilayah TEXT,
  alamat TEXT,
  no_rekening TEXT,
  manajer_id INTEGER REFERENCES users(id),
  status TEXT NOT NULL DEFAULT 'AKTIF',
  versi INTEGER NOT NULL DEFAULT 1,
  diperbarui TEXT
, "koperasi_simkopdes_id" integer, "provinsi" varchar not null default '', "pemetaan_lahan_id" integer);

CREATE TABLE "koperasi_barang" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "kode" varchar not null default '', "barcode" varchar not null default '', "nama" varchar not null, "satuan" varchar not null default 'pcs', "kategori" varchar not null default 'LAINNYA', "stok" integer not null default '0', "stok_min" integer not null default '0', "harga" integer not null default '0', "catatan" varchar not null default '', "oleh_id" integer, "dibuat" varchar, "diperbarui" varchar, "promo_jenis" varchar not null default '', "promo_nilai" integer not null default '0', "promo_min" integer not null default '0', "promo_bonus_id" integer not null default '0');

CREATE TABLE "koperasi_barang_stok" ("id" integer primary key autoincrement not null, "koperasi_barang_id" integer not null, "koperasi_id" integer not null, "user_id" integer, "sebelum" integer not null default '0', "sesudah" integer not null default '0', "selisih" integer not null default '0', "sebab" varchar not null default 'HITUNG', "catatan" varchar not null default '', "tanggal" varchar not null, "dibuat" varchar);

CREATE TABLE "koperasi_gerai" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "outlet_simkopdes_id" integer, "nama" varchar not null default '', "jenis" varchar not null default '', "alamat" varchar not null default '', "telepon" varchar not null default '', "status" varchar not null default '', "mentah" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "koperasi_jual" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "kasir_id" integer, "nomor" varchar not null, "pembeli" varchar not null default '', "kontak" varchar not null default '', "bayar" varchar not null default 'TUNAI', "total" integer not null default '0', "dibayar" integer not null default '0', "kembali" integer not null default '0', "catatan" varchar not null default '', "status" varchar not null default 'LUNAS', "tanggal" varchar not null, "dibuat" varchar);

CREATE TABLE "koperasi_jual_item" ("id" integer primary key autoincrement not null, "koperasi_jual_id" integer not null, "koperasi_id" integer not null, "koperasi_barang_id" integer, "nama" varchar not null, "satuan" varchar not null default 'pcs', "harga" integer not null default '0', "jumlah" integer not null default '1', "subtotal" integer not null default '0', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "koperasi_karyawan" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "nama" varchar not null, "jabatan" varchar not null default '', "telepon" varchar not null default '', "tanggal_masuk" date, "aktif" integer not null default '1', "catatan" varchar not null default '', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "koperasi_lahan" ("id" integer primary key autoincrement not null, "koperasi_id" integer, "asset_simkopdes_id" integer, "koperasi_simkopdes_id" integer, "nama" varchar not null default '', "nama_koperasi" varchar not null default '', "kontak" varchar not null default '', "luas" numeric, "lebar" numeric, "panjang" numeric, "status_aset" varchar not null default '', "kepemilikan" varchar not null default '', "status" varchar not null default '', "akses_jalan" varchar not null default '', "surveyor" varchar not null default '', "pemilik_terdaftar" varchar not null default '', "alamat" varchar not null default '', "latitude" numeric, "longitude" numeric, "keterangan" text, "village_code" varchar not null default '', "berkas" varchar not null default '', "foto" varchar not null default '', "aset_diperbarui" datetime, "ditarik" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "koperasi_sarana" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "koperasi_simkopdes_id" integer, "legal_stage" varchar not null default '', "legal_stage_sequence" integer not null default '0', "operasional" varchar not null default 'BELUM', "skor_operasional" integer not null default '0', "alasan" varchar not null default '', "ditarik" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "koperasi_sarana_centang" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "sarana_indikator_id" integer not null, "tersedia" tinyint(1) not null default '1', "keterangan" varchar, "created_at" datetime, "updated_at" datetime, "kondisi" varchar not null default 'BAIK');

CREATE TABLE "koperasi_sarana_progres" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "pekan" integer not null, "nilai_pekan" numeric not null default '0', "nilai_kumulatif" numeric not null default '0', "rincian" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "lark_koneksi" ("id" integer primary key autoincrement not null, "user_id" integer not null, "koperasi_id" integer, "no_hp" varchar not null default '', "open_id" varchar not null default '', "union_id" varchar not null default '', "lark_user_id" varchar not null default '', "nama" varchar not null default '', "email" varchar not null default '', "hp_lark" varchar not null default '', "avatar" varchar not null default '', "departemen" varchar not null default '', "jabatan" varchar not null default '', "access_token" text, "refresh_token" text, "scope" varchar not null default '', "kedaluwarsa_pada" varchar, "data_profil" text, "data_kalender" text, "data_tugas" text, "data_chat" text, "data_berkas" text, "catatan_sinkron" text, "sinkron_pada" varchar, "dibuat" varchar, "diperbarui" varchar);

CREATE TABLE "lms_materi" ("id" integer primary key autoincrement not null, "lms_id" integer not null, "path_id" integer not null, "path_judul" varchar not null, "judul" varchar not null, "slug" varchar not null default '', "ringkas" varchar not null default '', "konten" text not null default '', "menit" integer not null default '0', "posisi" integer not null default '0', "disunting_oleh" integer, "dibuat" varchar not null, "diperbarui" varchar not null);

CREATE TABLE "lms_progres" ("id" integer primary key autoincrement not null, "user_id" integer not null, "lms_id" integer not null, "selesai_pada" varchar not null);

CREATE TABLE "materi" ("id" integer primary key autoincrement not null, "judul" varchar not null, "kategori" varchar not null default 'DOKUMEN', "nama_asli" varchar not null, "berkas" varchar not null, "mime" varchar not null default '', "ukuran" integer not null default '0', "catatan" varchar not null default '', "oleh" integer, "dibuat" varchar not null, "kelompok" varchar not null default '', "thumb" varchar not null default '');

CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE "obrolan_anggota" ("id" integer primary key autoincrement not null, "ruang_id" integer not null, "user_id" integer not null, "dibuat" varchar not null default '');

CREATE TABLE "obrolan_baca" ("id" integer primary key autoincrement not null, "ruang_id" integer not null, "user_id" integer not null, "pesan_id" integer not null default '0', "dibaca_pada" integer not null default '0', "disematkan" varchar not null default '');

CREATE TABLE "obrolan_pesan" ("id" integer primary key autoincrement not null, "ruang_id" integer not null, "oleh" varchar not null default '', "nama" varchar not null default '', "isi" text not null, "terjemahan" text, "bahasa" varchar not null default '', "dibuat" varchar, "balas_id" integer, "jenis" varchar not null default 'teks', "lampiran" varchar not null default '', "lampiran_nama" varchar not null default '', "lampiran_ukuran" integer not null default '0', "lampiran_jenis" varchar not null default '', "sebut" varchar not null default '', "disematkan" varchar not null default '', "disunting" varchar not null default '', "dihapus" varchar not null default '', "kirim_pada" varchar);

CREATE TABLE "obrolan_reaksi" ("id" integer primary key autoincrement not null, "pesan_id" integer not null, "user_id" integer not null, "emoji" varchar not null, "dibuat" varchar not null default '');

CREATE TABLE "obrolan_ruang" ("id" integer primary key autoincrement not null, "nama" varchar not null, "jenis" varchar not null default 'grup', "acuan" varchar not null default '', "acuan_id" integer, "koperasi_id" integer, "dibuat_oleh" integer, "dibuat" varchar, "pemilik_id" integer, "kunci" varchar not null default '', "topik" varchar not null default '', "lingkup" varchar not null default '');

CREATE TABLE "pasokan" ("id" integer primary key autoincrement not null, "kode" varchar not null, "koperasi_id" integer not null, "umkm_nama" varchar not null, "umkm_kontak" varchar not null default '', "umkm_alamat" varchar not null default '', "komoditas" varchar not null, "jumlah" integer not null default '0', "satuan" varchar not null default 'KG', "harga_satuan" integer not null default '0', "tanggal" varchar not null, "status" varchar not null default 'DIAJUKAN', "catatan" varchar not null default '', "catatan_agrinas" varchar not null default '', "pemohon" integer, "dibuat" varchar not null, "diperbarui" varchar not null, "jenis" varchar not null default 'UMKM', "tayang" integer not null default '0', "harga_jual" integer not null default '0');

CREATE TABLE "pasokan_bayar" ("id" integer primary key autoincrement not null, "pasokan_id" integer not null, "koperasi_id" integer not null, "bank" varchar not null default '', "no_rekening" varchar not null default '', "atas_nama" varchar not null default '', "invoice_nomor" varchar not null default '', "invoice_tanggal" varchar not null default '', "invoice_nama" varchar not null default '', "invoice_simpan" varchar not null default '', "invoice_ukuran" integer not null default '0', "invoice_mime" varchar not null default '', "bukti_nama" varchar not null default '', "bukti_simpan" varchar not null default '', "bukti_ukuran" integer not null default '0', "bukti_mime" varchar not null default '', "bukti_catatan" varchar not null default '', "status" varchar not null default 'DIAJUKAN', "catatan" varchar not null default '', "diajukan_oleh" integer, "diputus_oleh" integer, "dibayar_oleh" integer, "diajukan_pada" varchar, "diputus_pada" varchar, "dibayar_pada" varchar, "dibuat" varchar);

CREATE TABLE "pemetaan_lahan" ("id" integer primary key autoincrement not null, "pemetaan_id" integer not null, "nama" varchar not null default '', "telepon" varchar not null default '', "lokasi" varchar not null default '', "desa" varchar not null default '', "kecamatan" varchar not null default '', "kabupaten" varchar not null default '', "provinsi" varchar not null default '', "luas" numeric, "potensi" varchar not null default '', "latitude" numeric, "longitude" numeric, "pembangunan_100" tinyint(1) not null default '0', "pembangunan_mulai" tinyint(1) not null default '0', "ditarik" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "pengumuman_baca" ("id" integer primary key autoincrement not null, "user_id" integer not null, "siaran_id" integer not null, "dibaca" datetime);

CREATE TABLE "persetujuan" ("id" integer primary key autoincrement not null, "koperasi_id" integer, "judul" varchar not null, "jenis" varchar not null default 'Umum', "isi" text, "pemohon_id" integer, "pemutus_id" integer, "status" varchar not null default 'DIAJUKAN', "catatan" text, "dibuat" varchar, "diputus" varchar, "nominal" integer, "tanggal" varchar);

CREATE TABLE "pesanan" ("id" integer primary key autoincrement not null, "kode" varchar not null, "invoice" varchar not null default '', "koperasi_id" integer not null, "pemesan" integer, "total" integer not null default '0', "kanal" varchar not null default 'AGRN', "status" varchar not null default 'DIPESAN', "catatan" varchar not null default '', "tanggal" varchar not null default '', "dibayar" varchar not null default '', "dikirim" varchar not null default '', "diterima" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null, "antar" varchar not null default 'KIRIM', "surat_jalan" varchar not null default '', "diambil" varchar not null default '', "penjual_id" integer);

CREATE TABLE "pesanan_item" ("id" integer primary key autoincrement not null, "pesanan_id" integer not null, "barang_id" integer, "nama" varchar not null, "satuan" varchar not null default 'UNIT', "jumlah" integer not null default '0', "harga" integer not null default '0', "pasokan_id" integer);

CREATE TABLE "pesanan_tindakan" ("id" integer primary key autoincrement not null, "pesanan_id" integer not null, "oleh" integer not null, "tindakan" varchar not null, "pesan" varchar not null default '', "lampiran" varchar not null default '', "dibuat" varchar not null default '');

CREATE TABLE "profil" ("id" integer primary key autoincrement not null, "user_id" integer not null, "sumber" varchar not null default 'SIMKOPDES', "simkopdes_user_id" integer, "email" varchar not null default '', "nama" varchar not null default '', "niak" varchar not null default '', "user_code" varchar not null default '', "telepon" varchar not null default '', "peran_simkopdes" varchar not null default '', "gender" varchar not null default '', "tempat_lahir" varchar not null default '', "tanggal_lahir" varchar not null default '', "agama" varchar not null default '', "status_kawin" varchar not null default '', "golongan_darah" varchar not null default '', "alamat" varchar not null default '', "kode_pos" varchar not null default '', "provinsi" varchar not null default '', "kabupaten" varchar not null default '', "kecamatan" varchar not null default '', "desa" varchar not null default '', "koperasi_id" integer, "koperasi_simkopdes_id" integer, "terverifikasi" varchar not null default '', "profil" text, "akhir_tarik" varchar not null default '');

CREATE TABLE "rekrutmen_pelamar" ("id" integer primary key autoincrement not null, "nama" varchar not null, "email" varchar not null default '', "telepon" varchar not null default '', "jabatan" varchar not null default '', "unit" varchar not null default '', "sumber" varchar not null default '', "status" varchar not null default 'MENUNGGU', "catatan" varchar not null default '', "dilamar" varchar not null default '', "user_id" integer, "dicatat_oleh" integer, "diputus_pada" varchar not null default '', "diputus_oleh" varchar not null default '', "dibuat" varchar not null default '');

CREATE TABLE riwayat (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tugas_id INTEGER NOT NULL REFERENCES tugas(id),
  aktor TEXT NOT NULL,
  aksi TEXT NOT NULL,
  dari TEXT, ke TEXT, catatan TEXT,
  dibuat TEXT NOT NULL
);

CREATE TABLE "saldo" ("id" integer primary key autoincrement not null, "koperasi_id" integer not null, "saldo" integer not null default '400000000', "catatan" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null);

CREATE TABLE "sarana_indikator" ("id" integer primary key autoincrement not null, "indikator_simkopdes_id" integer, "jenis_pengembangan" varchar not null default '', "jenis_konstruksi" varchar not null default '', "indikator" varchar not null default '', "keterangan" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "sdm_pegawai" ("id" integer primary key autoincrement not null, "user_id" integer not null, "status" varchar not null default 'AKTIF', "tanggal_awal" varchar not null default '', "tanggal_akhir" varchar not null default '', "jabatan" varchar not null default '', "unit" varchar not null default '', "atasan_id" integer, "nilai" integer, "periode_nilai" varchar not null default '', "catatan_nilai" varchar not null default '', "nilai_oleh" integer, "nilai_pada" varchar not null default '', "slip_nomor" varchar not null default '', "slip_berkas" varchar not null default '', "slip_pada" varchar not null default '', "slip_oleh" varchar not null default '', "rekrut_status" varchar not null default '', "rekrut_pada" varchar not null default '', "rekrut_oleh" integer, "catatan" varchar not null default '', "dicatat_oleh" integer, "dibuat" varchar not null default '', "diubah" varchar, "bank_nama" varchar, "bank_nomor" varchar, "bank_pemilik" varchar, "bank_cabang" varchar, "bank_berkas" varchar, "bpjs_kesehatan" varchar, "bpjs_bpjs_ketenagakerjaan" varchar, "bpjs_status" varchar, "bpjs_program" varchar, "nik_bpjs" varchar, "data_bank_pada" varchar);

CREATE TABLE "simkopdes_absensi" ("id" integer primary key autoincrement not null, "user_id" integer not null, "simkopdes_absensi_id" integer, "periode_id" integer, "tanggal" varchar not null default '', "koperasi_simkopdes_id" integer, "aktivitas" varchar not null default '', "catatan" varchar not null default '', "masuk" varchar not null default '', "keluar" varchar not null default '', "masuk_pada" varchar not null default '', "keluar_pada" varchar not null default '', "lat" varchar, "lng" varchar, "foto_masuk_url" text, "foto_keluar_url" text, "masuk_foto" varchar not null default '', "keluar_foto" varchar not null default '', "sumber" varchar not null default 'SIMP', "kirim_pada" varchar not null default '', "ditarik_pada" varchar not null default '');

CREATE TABLE tugas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  koperasi_id INTEGER NOT NULL REFERENCES koperasi(id),
  judul TEXT NOT NULL,
  deskripsi TEXT,
  jenis TEXT NOT NULL DEFAULT 'DOKUMEN',
  prioritas TEXT NOT NULL DEFAULT 'NORMAL',
  deadline TEXT,
  status TEXT NOT NULL DEFAULT 'BARU',
  dibuat_oleh INTEGER REFERENCES users(id),
  catatan_agrinas TEXT,
  dibuat TEXT NOT NULL,
  diperbarui TEXT NOT NULL
, "induk_id" integer, "petugas_id" integer, "petugas_peran" varchar not null default 'MANAJER', "petugas_wilayah" varchar not null default '', sasaran TEXT NOT NULL DEFAULT 'KOPERASI', sasaran_wilayah TEXT NOT NULL DEFAULT '');

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE NOT NULL,
  nama TEXT NOT NULL,
  peran TEXT NOT NULL,
  sandi TEXT NOT NULL,
  koperasi_id INTEGER,
  wajib_ganti INTEGER NOT NULL DEFAULT 1,
  aktif INTEGER NOT NULL DEFAULT 1,
  dibuat TEXT NOT NULL,
  masuk_terakhir TEXT
, "simkopdes_id" integer, "koperasi_simkopdes_id" integer, "sandi_sumber" varchar not null default 'LOKAL', "wilayah" varchar not null default '', "simkopdes_facilitator_id" integer, "simkopdes_sandi" text, "agri_sesi" text, "agri_username" varchar, "agri_tersambung_pada" datetime, "vendor_id" integer, "simkopdes_token" text, "tanggal_lahir" varchar not null default '');

CREATE TABLE "vendor" ("id" integer primary key autoincrement not null, "kode" varchar not null, "nama" varchar not null, "jenis" varchar not null default 'BUMN', "komoditas" varchar not null default '', "kontak" varchar not null default '', "catatan" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null);

CREATE TABLE "vendor_tagihan" ("id" integer primary key autoincrement not null, "kode" varchar not null, "vendor_id" integer not null, "koperasi_id" integer, "pesanan_id" integer, "keterangan" varchar not null default '', "jumlah" integer not null default '0', "kanal" varchar not null default 'SALDO', "status" varchar not null default 'DIAJUKAN', "catatan" varchar not null default '', "dibuat" varchar not null, "diperbarui" varchar not null, "dibayar" varchar not null default '');

CREATE TABLE "vote_suara" ("id" integer primary key autoincrement not null, "topik" varchar not null, "user_id" integer not null, "pilihan" integer not null, "catatan" text, "dibuat" varchar not null default '', "diperbarui" varchar not null default '');

CREATE TABLE "wiki" ("id" integer primary key autoincrement not null, "induk_id" integer, "koperasi_id" integer, "judul" varchar not null, "isi" text, "dibuat_oleh" integer, "dibuat" varchar, "diperbarui" varchar);

CREATE INDEX "aspirasi_status_index" on "aspirasi" ("status");
CREATE INDEX "aspirasi_user_id_index" on "aspirasi" ("user_id");
CREATE INDEX barang_harga_grosir_barang on barang_harga_grosir (barang_id, min_jumlah);
CREATE UNIQUE INDEX "barang_kode_unique" on "barang" ("kode");
CREATE UNIQUE INDEX "distribusi_kode_unique" on "distribusi" ("kode");
CREATE INDEX "idx_agri_ajuan" on "agri_pengajuan" ("status", "jenis");
CREATE INDEX "idx_agri_fokus_user" on "agri_fokus" ("user_id", "materi_id");
CREATE INDEX "idx_agri_poin_user" on "agri_poin" ("user_id");
CREATE INDEX "idx_agri_tugas_user" on "agri_tugas" ("user_id", "status");
CREATE INDEX "idx_agri_umpan_kepada" on "agri_umpan" ("kepada_id");
CREATE INDEX "idx_baca_user" on "obrolan_baca" ("user_id");
CREATE INDEX "idx_catatan_tanggal" on "catatan_harian" ("tanggal");
CREATE INDEX idx_catatan_user_tgl ON catatan_harian (user_id, tanggal);
CREATE INDEX "idx_dok_koperasi" on "dokumen" ("koperasi_id");
CREATE INDEX idx_dokakses_pengguna ON dokumen_akses (pengguna_id, status);
CREATE INDEX "idx_kop_barang_nama" on "koperasi_barang" ("koperasi_id", "nama");
CREATE INDEX "idx_kop_jual_hari" on "koperasi_jual" ("koperasi_id", "tanggal");
CREATE INDEX "idx_kop_stok_hari" on "koperasi_barang_stok" ("koperasi_id", "tanggal");
CREATE INDEX "idx_pasokan_bayar" on "pasokan_bayar" ("pasokan_id", "id");
CREATE INDEX "idx_pasokan_bayar_status" on "pasokan_bayar" ("koperasi_id", "status");
CREATE INDEX "idx_pesan_kirim_pada" on "obrolan_pesan" ("kirim_pada");
CREATE INDEX "idx_pesan_ruang" on "obrolan_pesan" ("ruang_id");
CREATE INDEX "idx_reaksi_pesan" on "obrolan_reaksi" ("pesan_id");
CREATE INDEX "idx_ruang_acuan" on "obrolan_ruang" ("jenis", "acuan_id");
CREATE INDEX "idx_ruang_koperasi" on "obrolan_ruang" ("koperasi_id");
CREATE INDEX "idx_ruang_lingkup" on "obrolan_ruang" ("lingkup");
CREATE INDEX "idx_setuju_status" on "persetujuan" ("status");
CREATE INDEX "idx_tugas_induk" on "tugas" ("induk_id");
CREATE INDEX "idx_wiki_induk" on "wiki" ("induk_id");
CREATE INDEX ix_absen_hub_orang ON agri_absen_hub (user_id, tanggal);
CREATE INDEX ix_absen_hub_tanggal ON agri_absen_hub (tanggal);
CREATE INDEX ix_ac_absen_tanggal ON agri_ac_absen (tanggal);
CREATE INDEX ix_audit_waktu ON audit(dibuat DESC);
CREATE INDEX ix_komentar_tugas ON komentar(tugas_id);
CREATE INDEX ix_tugas_kop ON tugas(koperasi_id, status);
CREATE UNIQUE INDEX "keuangan_koperasi_id_tanggal_unique" on "keuangan" ("koperasi_id", "tanggal");
CREATE INDEX "keuangan_tanggal_index" on "keuangan" ("tanggal");
CREATE INDEX "kontrak_status_idx" on "kontrak" ("kontrak_status");
CREATE UNIQUE INDEX "kontrak_unik" on "kontrak" ("user_id", "periode_id", "penempatan_koperasi_id");
CREATE INDEX "koperasi_gerai_koperasi_id_index" on "koperasi_gerai" ("koperasi_id");
CREATE INDEX "koperasi_jual_item_koperasi_id_index" on "koperasi_jual_item" ("koperasi_id");
CREATE INDEX "koperasi_jual_item_koperasi_jual_id_index" on "koperasi_jual_item" ("koperasi_jual_id");
CREATE INDEX "koperasi_karyawan_koperasi_id_index" on "koperasi_karyawan" ("koperasi_id");
CREATE INDEX "koperasi_koperasi_simkopdes_id_index" on "koperasi" ("koperasi_simkopdes_id");
CREATE UNIQUE INDEX "koperasi_lahan_asset_simkopdes_id_unique" on "koperasi_lahan" ("asset_simkopdes_id");
CREATE INDEX "koperasi_lahan_koperasi_id_index" on "koperasi_lahan" ("koperasi_id");
CREATE INDEX "koperasi_lahan_koperasi_simkopdes_id_index" on "koperasi_lahan" ("koperasi_simkopdes_id");
CREATE INDEX "koperasi_pemetaan_lahan_id_index" on "koperasi" ("pemetaan_lahan_id");
CREATE INDEX "koperasi_provinsi_index" on "koperasi" ("provinsi");
CREATE UNIQUE INDEX "koperasi_sarana_centang_koperasi_id_sarana_indikator_id_unique" on "koperasi_sarana_centang" ("koperasi_id", "sarana_indikator_id");
CREATE UNIQUE INDEX "koperasi_sarana_koperasi_id_unique" on "koperasi_sarana" ("koperasi_id");
CREATE INDEX "koperasi_sarana_koperasi_simkopdes_id_index" on "koperasi_sarana" ("koperasi_simkopdes_id");
CREATE INDEX "koperasi_sarana_progres_koperasi_id_index" on "koperasi_sarana_progres" ("koperasi_id");
CREATE UNIQUE INDEX "lms_materi_lms_id_unique" on "lms_materi" ("lms_id");
CREATE INDEX "lms_materi_path_id_index" on "lms_materi" ("path_id");
CREATE INDEX "lms_materi_posisi_index" on "lms_materi" ("posisi");
CREATE INDEX "lms_progres_user_id_index" on "lms_progres" ("user_id");
CREATE UNIQUE INDEX "lms_progres_user_id_lms_id_unique" on "lms_progres" ("user_id", "lms_id");
CREATE UNIQUE INDEX "obrolan_anggota_unik" on "obrolan_anggota" ("ruang_id", "user_id");
CREATE INDEX "obrolan_anggota_user_id_index" on "obrolan_anggota" ("user_id");
CREATE UNIQUE INDEX "obrolan_baca_unik" on "obrolan_baca" ("ruang_id", "user_id");
CREATE UNIQUE INDEX "obrolan_reaksi_unik" on "obrolan_reaksi" ("pesan_id", "user_id", "emoji");
CREATE INDEX "obrolan_ruang_kunci_index" on "obrolan_ruang" ("kunci");
CREATE UNIQUE INDEX "pasokan_kode_unique" on "pasokan" ("kode");
CREATE INDEX "pasokan_koperasi_id_index" on "pasokan" ("koperasi_id");
CREATE INDEX "pasokan_status_index" on "pasokan" ("status");
CREATE INDEX "pemetaan_lahan_desa_index" on "pemetaan_lahan" ("desa");
CREATE INDEX "pemetaan_lahan_kabupaten_index" on "pemetaan_lahan" ("kabupaten");
CREATE INDEX "pemetaan_lahan_kecamatan_index" on "pemetaan_lahan" ("kecamatan");
CREATE INDEX "pemetaan_lahan_pembangunan_100_index" on "pemetaan_lahan" ("pembangunan_100");
CREATE UNIQUE INDEX "pemetaan_lahan_pemetaan_id_unique" on "pemetaan_lahan" ("pemetaan_id");
CREATE INDEX "pemetaan_lahan_provinsi_index" on "pemetaan_lahan" ("provinsi");
CREATE INDEX "pengumuman_baca_user_id_index" on "pengumuman_baca" ("user_id");
CREATE UNIQUE INDEX "pengumuman_baca_user_id_siaran_id_unique" on "pengumuman_baca" ("user_id", "siaran_id");
CREATE INDEX "pesanan_item_pesanan_id_index" on "pesanan_item" ("pesanan_id");
CREATE UNIQUE INDEX "pesanan_kode_unique" on "pesanan" ("kode");
CREATE INDEX "pesanan_koperasi_id_index" on "pesanan" ("koperasi_id");
CREATE INDEX "pesanan_status_index" on "pesanan" ("status");
CREATE INDEX "pesanan_tindakan_pesanan_id_index" on "pesanan_tindakan" ("pesanan_id");
CREATE INDEX "profil_simkopdes_user_id_index" on "profil" ("simkopdes_user_id");
CREATE UNIQUE INDEX "profil_user_id_unique" on "profil" ("user_id");
CREATE INDEX "rekrutmen_pelamar_email_index" on "rekrutmen_pelamar" ("email");
CREATE INDEX "rekrutmen_pelamar_status_index" on "rekrutmen_pelamar" ("status");
CREATE UNIQUE INDEX "saldo_koperasi_id_unique" on "saldo" ("koperasi_id");
CREATE UNIQUE INDEX "sarana_indikator_indikator_simkopdes_id_unique" on "sarana_indikator" ("indikator_simkopdes_id");
CREATE INDEX "sarana_indikator_jenis_pengembangan_index" on "sarana_indikator" ("jenis_pengembangan");
CREATE INDEX "sdm_pegawai_periode_nilai_index" on "sdm_pegawai" ("periode_nilai");
CREATE INDEX "sdm_pegawai_status_index" on "sdm_pegawai" ("status");
CREATE UNIQUE INDEX "sdm_pegawai_user_id_unique" on "sdm_pegawai" ("user_id");
CREATE UNIQUE INDEX "simkopdes_absensi_simkopdes_absensi_id_unique" on "simkopdes_absensi" ("simkopdes_absensi_id");
CREATE INDEX "simkopdes_absensi_user_id_tanggal_index" on "simkopdes_absensi" ("user_id", "tanggal");
CREATE INDEX "tugas_petugas_id_index" on "tugas" ("petugas_id");
CREATE UNIQUE INDEX "uq_agri_fokus_langkah" on "agri_fokus" ("user_id", "materi_id", "langkah");
CREATE UNIQUE INDEX uq_kop_barang_barcode on koperasi_barang (koperasi_id, barcode) where barcode <> '';
CREATE UNIQUE INDEX "uq_kop_gerai_sim" on "koperasi_gerai" ("koperasi_id", "outlet_simkopdes_id");
CREATE UNIQUE INDEX "uq_kop_jual_nomor" on "koperasi_jual" ("koperasi_id", "nomor");
CREATE UNIQUE INDEX "uq_kop_sarana_pekan" on "koperasi_sarana_progres" ("koperasi_id", "pekan");
CREATE UNIQUE INDEX "uq_lark_koneksi_user" on "lark_koneksi" ("user_id");
CREATE UNIQUE INDEX "uq_presensi_hari" on "agri_presensi" ("user_id", "tanggal");
CREATE INDEX "users_email_idx" on "users" ("email");
CREATE INDEX "users_simkopdes_id_idx" on "users" ("simkopdes_id");
CREATE INDEX "users_wilayah_index" on "users" ("wilayah");
CREATE UNIQUE INDEX ux_absen_keluar ON agri_absen_hub (user_id, tanggal) WHERE jenis = 'keluar';
CREATE UNIQUE INDEX ux_absen_masuk ON agri_absen_hub (user_id, tanggal) WHERE jenis = 'masuk';
CREATE UNIQUE INDEX ux_ac_absen_orang_tanggal ON agri_ac_absen (user_id, tanggal);
CREATE UNIQUE INDEX "vendor_kode_unique" on "vendor" ("kode");
CREATE UNIQUE INDEX "vendor_tagihan_kode_unique" on "vendor_tagihan" ("kode");
CREATE UNIQUE INDEX "vendor_tagihan_pesanan_id_unique" on "vendor_tagihan" ("pesanan_id");
CREATE INDEX "vendor_tagihan_status_index" on "vendor_tagihan" ("status");
CREATE INDEX "vendor_tagihan_vendor_id_index" on "vendor_tagihan" ("vendor_id");
CREATE INDEX "vote_suara_topik_pilihan_index" on "vote_suara" ("topik", "pilihan");
CREATE UNIQUE INDEX "vote_suara_topik_user_id_unique" on "vote_suara" ("topik", "user_id");
