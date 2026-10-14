<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ValidaUbicacion;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EmpresaController extends CrudController
{
    use ValidaUbicacion;

    protected string $modelo = Empresa::class;

    protected bool $conAprobacion = true;

    protected array $buscar = ['denominacion', 'nit', 'sigla', 'correo_contacto'];

    protected array $filtros = ['tipo_empresa', 'departamento_id'];

    protected array $fechas = ['fecha_creacion'];

    protected array $orden = [['denominacion', 'asc']];

    protected array $dependientes = ['contratos' => 'contratos'];

    public function __construct()
    {
        $this->con = $this->relacionesUbicacion();
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'tipo_empresa' => ['required', 'string', 'max:100'],
            'tipo_documento' => ['nullable', Rule::in(array_keys(config('sofia.tipos_documento')))],
            'nit' => ['required', 'string', 'max:50'],
            'expedido_en' => ['nullable', Rule::in(array_keys(config('sofia.expedido_en')))],
            'iniciales_sucursal' => ['nullable', 'string', 'max:10'],
            'denominacion' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'fecha_creacion' => ['nullable', 'date', 'before_or_equal:today'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'telefono_fax' => ['nullable', 'string', 'max:30'],
            'telefono_celular' => ['nullable', 'string', 'max:30'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'pagina_web' => ['nullable', 'url', 'max:255'],
        ] + $this->reglasUbicacion();
    }
}
