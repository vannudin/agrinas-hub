<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Umpan balik antar-rekan kerja (Feedback). */
class AgriUmpan extends Model
{
    public const JENIS = ['UMUM' => 'Catatan', 'APRESIASI' => 'Apresiasi', 'SARAN' => 'Saran'];

    protected $connection = 'hub';
    protected $table = 'agri_umpan';
    public $timestamps = false;

    protected $fillable = ['kepada_id', 'oleh_id', 'jenis', 'isi', 'anonim', 'dibuat'];

    public function kepada()
    {
        return $this->belongsTo(User::class, 'kepada_id');
    }

    public function oleh()
    {
        return $this->belongsTo(User::class, 'oleh_id');
    }

    public function label_jenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
