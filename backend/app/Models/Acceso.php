<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acceso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'accesos';

    protected $fillable = ['user_id', 'username_intentado', 'evento', 'ip', 'user_agent'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
