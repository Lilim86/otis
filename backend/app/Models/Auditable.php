<?php

namespace App\Models;

use App\Models\Concerns\TieneAuditoria;
use Illuminate\Database\Eloquent\Model;

abstract class Auditable extends Model
{
    use TieneAuditoria;
}
