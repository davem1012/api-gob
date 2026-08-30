<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ubigeo extends Model
{
    public $timestamps = false;
    protected $table = 'ubigeo';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'codigo',
        'distrito',
        'provincia',
        'departamento',
    ];
}
