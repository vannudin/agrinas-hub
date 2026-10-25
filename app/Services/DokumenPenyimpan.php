<?php

namespace App\Services;

use App\Models\Dokumen;
use App\Models\RuangObrolan;
use App\Models\User;
use App\Support\TemplateDokumen;
use App\Support\Zip;
use Illuminate\Support\Facades\Storage;

/**
 * Membuat & merawat dokumen yang disunting ONLYOFFICE.
 *
 * Berkas kosong dibuat lebih dulu (template minimal yang sah untuk Word/Excel)
 * supaya editor punya sesuatu untuk dibuka; sesudah itu ONLYOFFICE yang menulis
 * isinya lewat callback. Isi dokumen juga dicerminkan ke sebuah ruang obrolan
 * (jenis=dokumen) — di situlah "kirim konteks langsung dari dokumen" berjalan.
 */
class DokumenPenyimpan
{
    public const DISK = 'r2';

    // Template OOXML kosong (zip + XML minimal) yang sah untuk Word dan Excel.
    // Ditanam sebagai base64, bukan dibuat dengan ZipArchive: kontainer PHP ini
    // tidak punya ekstensi zip (hanya zlib), dan menambah ekstensi untuk membuat
    // dua berkas kosong jelas tidak sepadan. ONLYOFFICE menimpanya saat simpan.
    private const KOSONG_DOCX = 'UEsDBBQAAAAIAEmDRF15bjPX6AAAAK0BAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbH1QyU7DMBD9FWuuKHHggBCK0wPLETiUDxjZk8SqN3nc0v49Tlt6QIXjzFv1+tXeO7GjzDYGBbdtB4KCjsaGScHn+rV5AMEFg0EXAyk4EMNq6NeHRCyqNrCCuZT0KCXrmTxyGxOFiowxeyz1zJNMqDc4kbzrunupYygUSlMWDxj6Zxpx64p42df3qUcmxyCeTsQlSwGm5KzGUnG5C+ZXSnNOaKvyyOHZJr6pBJBXExbk74Cz7r0Ok60h8YG5vKGvLPkVs5Em6q2vyvZ/mys94zhaTRf94pZy1MRcF/euvSAebfjpL49zD99QSwMEFAAAAAgASYNEXZv9N+qtAAAAKQEAAAsAAABfcmVscy8ucmVsc43POw7CMAwG4KtE3mlaBoRQ0y4IqSsqB7ASN61oHkrCo7cnAwNFDIy2f3+W6/ZpZnanECdnBVRFCYysdGqyWsClP232wGJCq3B2lgQsFKFt6jPNmPJKHCcfWTZsFDCm5A+cRzmSwVg4TzZPBhcMplwGzT3KK2ri27Lc8fBpwNpknRIQOlUB6xdP/9huGCZJRydvhmz6ceIrkWUMmpKAhwuKq3e7yCzwpuarF5sXUEsDBBQAAAAIAEmDRF2qKHxQqwAAAOQAAAARAAAAd29yZC9kb2N1bWVudC54bWxFjsGOwjAMRH8l8h3cLlLVrSjc9owEfEBITFupsas4UODrSZFWXOwZefTG2/0jjOZOUQfhFsp1AYbYiR+4a+F8+lvVYDRZ9nYUphaepLDfbefGi7sF4mQygLWZW+hTmhpEdT0Fq2uZiPPtKjHYlG3scJbopyiOVDM/jPhTFBUGOzAsyIv457InXKaSS4f48d3xZealoix/iwqy7rOu6k0NOYrfLP5D8Pvg7g1QSwECFAMUAAAACABJg0RdeW4z1+gAAACtAQAAEwAAAAAAAAAAAAAAgAEAAAAAW0NvbnRlbnRfVHlwZXNdLnhtbFBLAQIUAxQAAAAIAEmDRF2b/TfqrQAAACkBAAALAAAAAAAAAAAAAACAARkBAABfcmVscy8ucmVsc1BLAQIUAxQAAAAIAEmDRF2qKHxQqwAAAOQAAAARAAAAAAAAAAAAAACAAe8BAAB3b3JkL2RvY3VtZW50LnhtbFBLBQYAAAAAAwADALkAAADJAgAAAAA=';

    private const KOSONG_XLSX = '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';

    public function buat(User $u, string $judul, string $jenis, ?int $koperasiId,
                        string $akses = 'koperasi'): Dokumen
    {
        $jenis = array_key_exists($jenis, Dokumen::JENIS) ? $jenis : 'docx';
        $akses = array_key_exists($akses, Dokumen::AKSES) ? $akses : 'koperasi';
        // Hanya dokumen 'pribadi' yang tidak menempel pada koperasi: itu yang
        // membuat lingkup koperasi tidak ikut menariknya.
        $pribadi = $akses === 'pribadi' ? 1 : 0;
        $dok = Dokumen::create([
            'koperasi_id' => $pribadi ? null : $koperasiId, 'judul' => mb_substr(trim($judul), 0, 160) ?: 'Dokumen baru',
            'jenis' => $jenis, 'berkas' => '', 'kunci' => '', 'dibuat_oleh' => $u->id,
            'pribadi' => $pribadi, 'pemilik_id' => $pribadi ? $u->id : null,
            'akses' => $akses, 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);
        $dok->berkas = 'dokumen/'.$dok->id.'.'.$jenis;
        $dok->kunci = $dok->kunci_baru();
        $dok->save();

        // Berkas awal: zip kosong yang sah. ONLYOFFICE akan menimpanya saat simpan
        // pertama; mengirim 0 byte membuat editor menolak membuka.
        Storage::disk(self::DISK)->put($dok->berkas, $this->kosong($jenis));

        // Ruang pembahasan dokumen. Untuk dokumen pribadi, ruangnya diberi
        // pemilik_id supaya pembahasannya TIDAK terbaca sebagai ruang umum.
        RuangObrolan::create([
            'nama' => 'Pembahasan: '.mb_substr((string) $dok->judul, 0, 100), 'jenis' => 'dokumen',
            'acuan' => 'dokumen', 'acuan_id' => $dok->id,
            'koperasi_id' => $pribadi ? null : $koperasiId,
            'pemilik_id' => $pribadi ? $u->id : null,
            'dibuat_oleh' => $u->id, 'dibuat' => kini(),
        ]);

        return $dok;
    }

    /** Simpan isi dari ONLYOFFICE. Setiap simpan memutar document key. */
    public function simpan(Dokumen $dok, string $biner): bool
    {
        if (Storage::disk(self::DISK)->put($dok->berkas, $biner) === false) {
            return false;
        }
        $dok->kunci = $dok->kunci_baru();
        $dok->diperbarui = kini();
        $dok->save();

        return true;
    }

    public function isi(Dokumen $dok): ?string
    {
        return Storage::disk(self::DISK)->exists($dok->berkas)
            ? (string) Storage::disk(self::DISK)->get($dok->berkas) : null;
    }

    /**
     * Isi awal dokumen baru.
     *
     * docx & xlsx memakai template base64 yang sudah terbukti (dan sudah ada di
     * produksi sejak awal). pptx tidak punya template seperti itu: berkasnya
     * dibangun dari nol oleh Zip + TemplateDokumen — kontainer ini tidak punya
     * ekstensi zip, jadi tiap bagiannya (slide, layout, master, theme, txStyles)
     * ditulis sendiri. Lihat TemplateDokumen untuk bentuk minimum yang sah.
     */
    private function kosong(string $jenis): string
    {
        if ($jenis === 'pdf') {
            return TemplateDokumen::pdf();
        }

        if ($jenis === 'pptx') {
            return Zip::buat(TemplateDokumen::bagian('pptx'));
        }

        $b = base64_decode($jenis === 'xlsx' ? self::KOSONG_XLSX : self::KOSONG_DOCX, true);
        if ($b === false || $b === '') {
            throw new \RuntimeException('Template dokumen rusak.');
        }

        return $b;
    }

    /** Naikkan versi dokumen PDF dengan berkas yang diunggah pengguna. */
    public function ganti(Dokumen $dok, string $isi): bool
    {
        if (Storage::disk(self::DISK)->put($dok->berkas, $isi) === false) {
            return false;
        }
        $dok->kunci = $dok->kunci_baru();
        $dok->diperbarui = kini();
        $dok->save();

        return true;
    }
}
