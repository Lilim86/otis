<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RegistroResource;
use App\Models\OrdenMantenimiento;
use App\Services\OrdenMantenimientoService;
use Illuminate\Database\Eloquent\Model;

class OrdenMantenimientoController extends CrudController
{
    protected string $modelo = OrdenMantenimiento::class;

    protected array $con = ['repuesto'];

    protected array $buscar = ['codigo_orden', 'equipo_ascensor', 'descripcion_trabajo'];

    protected array $filtros = ['situacion', 'repuesto_id'];

    protected array $orden = [['fecha_programada', 'desc']];

    protected array $fechas = ['fecha_programada'];

    protected array $padres = ['repuesto' => 'repuesto'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_orden' => ['required', 'string', 'max:255', $this->unico('orden_mantenimientos', 'codigo_orden', $registro)],
            'equipo_ascensor' => ['required', 'string', 'max:255'],
            'fecha_programada' => ['required', 'date'],
            // "Finalizado" solo se alcanza con finalizar(), que descuenta el stock.
            'situacion' => ['sometimes', 'in:Pendiente,En Proceso'],
            'descripcion_trabajo' => ['required', 'string'],
            'repuesto_id' => ['nullable', $this->existe('repuestos')],
            'cantidad_utilizada' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function valoresPorDefecto(): array
    {
        return ['situacion' => 'Pendiente'];
    }

    public function finalizar(string $id, OrdenMantenimientoService $servicio): RegistroResource
    {
        $orden = $servicio->finalizar($this->buscarRegistro($id));

        return new RegistroResource($orden->load($this->con));
    }
}
