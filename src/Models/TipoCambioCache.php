<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambioCache extends Model
{
    protected $table = 'tipo_cambio_cache';

    protected $fillable = [
        'buy_price',
        'sell_price',
        'base_currency',
        'quote_currency',
        'date',
        'fecha_registro'
    ];

    // Desactivar timestamps automáticos de Laravel ya que manejamos fecha_registro manualmente
    public $timestamps = false;

    // Definir los campos que deberían ser tratados como fechas
    protected $dates = ['fecha_registro'];

    /**
     * Obtener el tipo de cambio por fecha
     */
    public static function getByDate($date)
    {
        return self::where('date', $date)->first();
    }

    /**
     * Limpiar registros antiguos según TTL
     */
    public static function cleanOldRecords($ttlDays = 30)
    {
        $cutoffDate = date('Y-m-d H:i:s', time() - ($ttlDays * 86400));
        return self::where('fecha_registro', '<', $cutoffDate)->delete();
    }

    /**
     * Obtener registros recientes
     */
    public static function getRecentRecords($days = 7)
    {
        $cutoffDate = date('Y-m-d H:i:s', time() - ($days * 86400));
        return self::where('fecha_registro', '>=', $cutoffDate)
            ->orderBy('fecha_registro', 'desc')
            ->get();
    }
}
