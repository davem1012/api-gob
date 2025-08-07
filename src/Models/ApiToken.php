<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    public $timestamps = false;
    protected $table = 'api_tokens';
    protected $fillable = [
        'token',
        'contador',
        'limite_mensual',
        'activo',
        'mes_actual',
        'anio_actual',
        'fecha_creacion',
        'fecha_actualizacion'
    ];

    /**
     * Obtener el próximo token disponible
     */
    public static function getAvailableToken()
    {
        $mesActual = (int)date('n');
        $anioActual = (int)date('Y');

        // Resetear contadores si cambió el mes
        self::resetCountersIfNewMonth($mesActual, $anioActual);

        // Buscar token disponible (que no haya alcanzado el límite)
        return self::where('activo', 1)
                  ->where('contador', '<', 999)
                  ->orderBy('contador', 'asc')
                  ->first();
    }

    /**
     * Incrementar contador del token
     */
    public function incrementCounter()
    {
        $this->increment('contador');
        $this->fecha_actualizacion = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Resetear contadores si es un nuevo mes
     */
    private static function resetCountersIfNewMonth($mesActual, $anioActual)
    {
        $needReset = self::where(function($query) use ($mesActual, $anioActual) {
            $query->where('mes_actual', '!=', $mesActual)
                  ->orWhere('anio_actual', '!=', $anioActual);
        })->exists();

        if ($needReset) {
            self::query()->update([
                'contador' => 0,
                'mes_actual' => $mesActual,
                'anio_actual' => $anioActual,
                'fecha_actualizacion' => date('Y-m-d H:i:s')
            ]);
        }
    }
}