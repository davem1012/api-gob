<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RucCache extends Model
{
    public $timestamps = false;
    protected $table = 'ruc_cache';
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
        'es_agente_retencion',
        'es_buen_contribuyente',
        'locales_anexos',
        'fecha_registro'
    ];
}
