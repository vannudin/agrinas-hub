<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tugas pribadi AgriConnect.
 *
 * Di aplikasi aslinya Task, Overtime, dan Reminder adalah tiga menu; isinya satu
 * hal yang sama (pekerjaan berwaktu milik sendiri), jadi di Hub ketiganya satu
 * daftar dengan kolom `jenis`. Kolom `jam` hanya berarti untuk Reminder dan
 * Overtime — Task cukup tanggalnya.
 */
class AgriTugas extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_tugas';
    public $timestamps = false;

    public const JENIS = ['TASK' => 'Tugas', 'OVERTIME' => 'Lembur', 'REMINDER' => 'Pengingat'];
    public const PRIORITAS = ['RENDAH' => 'Rendah', 'NORMAL' => 'Normal', 'TINGGI' => 'Tinggi'];
    public const STATUS = ['BARU' => 'Baru', 'PROSES' => 'Dikerjakan', 'SELESAI' => 'Selesai'];

    protected $fillable = ['user_id', 'koperasi_id', 'judul', 'keterangan', 'jenis', 'prioritas',
                           'tanggal', 'jam', 'status', 'dibuat'];

    public function label_jenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
