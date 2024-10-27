<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Error extends Model
{
    protected $table = 'errores';

    protected $fillable = [
        'bitacora_id',
        'error_log'
    ];
}
