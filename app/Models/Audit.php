<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $connection = 'hub';
    protected $table = 'audit';
    public $timestamps = false;

    protected $fillable = ['aktor', 'aksi', 'target', 'ip', 'detail', 'dibuat'];
}
