<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estadistica extends Model
{
    protected $fillable = [
        'email',
        'jyv',
        'badmail',
        'baja',
        'fecha_envio',
        'fecha_open',
        'opens',
        'opens_virales',
        'fecha_click',
        'clicks',
        'clicks_virales',
        'links',
        'ips',
        'navegadores',
        'plataformas',
    ];
}
