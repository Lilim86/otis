<?php

namespace App\Models;

/** Pedido que un cliente hace desde el catálogo; queda agendado para el gerente. */
class PedidoCatalogo extends Auditable
{
    protected $table = 'pedidos_catalogo';

    protected $fillable = [
        'producto_id', 'empresa_id', 'edificio_id', 'user_id', 'cita_id', 'cantidad', 'observaciones',
        'fecha_compromiso', 'situacion', 'notificado_correo', 'estado',
    ];

    protected $appends = ['codigo'];

    protected function casts(): array
    {
        return ['fecha_compromiso' => 'date:Y-m-d', 'notificado_correo' => 'boolean', 'estado' => 'boolean'];
    }

    public function getCodigoAttribute(): string
    {
        return 'PED-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function producto()
    {
        return $this->belongsTo(ProductoCatalogo::class, 'producto_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
