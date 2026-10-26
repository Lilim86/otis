<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'empresa_id' => $this->empresa_id,
            'personal_id' => $this->personal_id,
            'roles' => $this->roles->pluck('name'),
            'estado' => (bool) $this->estado,
            'creado_por' => $this->creado_por,
            'actualizado_por' => $this->actualizado_por,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
