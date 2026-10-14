<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Flujo de aprobación del SOFIA: REG (registrado) -> APR (aprobado), o ANL (anulado).
 * `estado_registro` no es asignable en masa: solo cambia con aprobar() y anularRegistro().
 */
trait TieneAprobacion
{
    public function aprobar(): void
    {
        if ($this->estado_registro !== 'REG') {
            throw ValidationException::withMessages(['estado_registro' => 'Solo se puede aprobar un registro en estado REG (registrado).']);
        }

        $this->forceFill(['estado_registro' => 'APR'])->save();
    }

    public function anularRegistro(): void
    {
        if ($this->estado_registro === 'ANL') {
            throw ValidationException::withMessages(['estado_registro' => 'El registro ya está anulado.']);
        }

        $this->forceFill(['estado_registro' => 'ANL'])->save();
    }
}
