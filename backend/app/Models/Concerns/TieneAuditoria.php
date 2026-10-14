<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Columnas de auditoría: creado_por, actualizado_por, estado,
 * created_at, updated_at y deleted_at (SoftDeletes).
 */
trait TieneAuditoria
{
    use SoftDeletes;

    public function initializeTieneAuditoria(): void
    {
        $this->mergeFillable(['estado']);
    }

    protected static function bootTieneAuditoria(): void
    {
        static::creating(function ($model) {
            $model->creado_por ??= Auth::id();
            $model->estado ??= true;
        });

        static::updating(function ($model) {
            $model->actualizado_por = Auth::id();
        });
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}
