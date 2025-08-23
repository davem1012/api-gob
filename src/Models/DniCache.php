<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DniCache extends Model
{
    public $timestamps = false;
    protected $table = 'dni_cache';
    protected $fillable = [
    'first_name',
    'first_last_name',
    'second_last_name',
    'full_name',
    'document_number',
    'fecha_registro'
    ];
}
