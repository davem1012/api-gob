<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RucPadronStaging extends Model
{
    public $timestamps = false;
    protected $table = 'ruc_padron_staging';
    protected $primaryKey = 'numero_documento';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'numero_documento',
        'razon_social',
        'estado',
        'condicion',
        'direccion',
        'ubigeo',
        'via_tipo',
        'via_nombre',
        'zona_codigo',
        'zona_tipo',
        'numero',
        'interior',
        'lote',
        'dpto',
        'manzana',
        'kilometro',
        'distrito',
        'provincia',
        'departamento',
        'row_hash',
    ];
}
