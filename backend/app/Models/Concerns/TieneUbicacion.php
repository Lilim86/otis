<?php

namespace App\Models\Concerns;

use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\Provincia;
use App\Models\ViaAcceso;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TieneUbicacion
{
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    public function viaAcceso(): BelongsTo
    {
        return $this->belongsTo(ViaAcceso::class, 'via_acceso_id');
    }
}
