<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCodigo extends Model
{
    protected $table = 'otp_codigos';

    protected $fillable = ['user_id', 'codigo_hash', 'expira_en', 'intentos', 'usado_en'];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
        ];
    }
}
