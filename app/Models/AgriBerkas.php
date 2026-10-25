<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Berkas regulasi/panduan/template. Berkas nyata ada di disk `local`. */
class AgriBerkas extends Model
{
    public const DISK = 'r2';

    public const KATEGORI = ['REGULASI' => 'Regulasi', 'PANDUAN' => 'Panduan',
                             'TEMPLATE' => 'Template'];

    protected $connection = 'hub';
    protected $table = 'agri_berkas';
    public $timestamps = false;

    protected $fillable = ['koperasi_id', 'kategori', 'judul', 'keterangan', 'nama_asli',
                           'nama_simpan', 'mime', 'ukuran', 'oleh', 'dibuat'];

    public function label_kategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }
}
