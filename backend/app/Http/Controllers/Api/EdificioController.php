<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ValidaUbicacion;
use App\Models\Edificio;
use Illuminate\Database\Eloquent\Model;

class EdificioController extends CrudController
{
    use ValidaUbicacion;

    protected string $modelo = Edificio::class;

    protected bool $conAprobacion = true;

    protected array $buscar = ['codigo_edificio', 'codigo_administrativo', 'denominacion', 'referencia_cercana'];

    protected array $filtros = ['tipo_edificacion', 'departamento_id', 'zona_id', 'empresa_id'];

    protected array $orden = [['codigo_edificio', 'asc']];

    protected array $dependientes = ['contratos' => 'contratos', 'equipos' => 'equipos'];

    protected array $padres = ['empresa' => 'empresa'];

    public function __construct()
    {
        $this->con = [...$this->relacionesUbicacion(), 'propietario', 'empresa', 'contactoCobranzas', 'contactoTecnico'];
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_edificio' => ['required', 'string', 'max:255', $this->unico('edificios', 'codigo_edificio', $registro)],
            'codigo_administrativo' => ['nullable', 'string', 'max:20'],
            'denominacion' => ['required', 'string', 'max:255'],
            'tipo_edificacion' => ['required', 'string', 'max:100'],
            'nro_edificio' => ['nullable', 'string', 'max:20'],
            'referencia_cercana' => ['nullable', 'string', 'max:255'],
            'propietario_id' => ['nullable', $this->existe('personas')],
            'empresa_id' => ['nullable', $this->existe('empresas')],
            'contacto_cobranzas_id' => ['nullable', $this->existe('personas')],
            'contacto_tecnico_id' => ['nullable', $this->existe('personas')],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'altura_msnm' => ['nullable', 'numeric', 'between:-500,9000'],
        ] + $this->reglasUbicacion();
    }
}
