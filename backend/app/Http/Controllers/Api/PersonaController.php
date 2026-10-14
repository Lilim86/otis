<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ValidaUbicacion;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PersonaController extends CrudController
{
    use ValidaUbicacion;

    protected string $modelo = Persona::class;

    protected bool $conAprobacion = true;

    protected array $buscar = ['nombres', 'primer_apellido', 'segundo_apellido', 'numero_documento', 'nit', 'correo_personal'];

    protected array $filtros = ['tipo_persona', 'tipo_documento', 'departamento_id', 'edificio_id'];

    protected array $fechas = ['fecha_nacimiento'];

    protected array $orden = [['primer_apellido', 'asc'], ['nombres', 'asc']];

    protected array $dependientes = [];

    protected array $padres = ['edificio' => 'edificio'];

    protected function relaciones(): array
    {
        return ['nacionalidad', 'edificio', ...$this->relacionesUbicacion()];
    }

    public function __construct()
    {
        $this->con = $this->relaciones();
    }

    protected function reglas(?Model $registro): array
    {
        $catalogo = fn (string $clave) => Rule::in(array_keys(config("sofia.{$clave}")));

        return [
            'tipo_documento' => ['required', $catalogo('tipos_documento')],
            'numero_documento' => ['required', 'string', 'max:30', $this->unico('personas', 'numero_documento', $registro)],
            'expedido_en' => ['required_if:tipo_documento,CI', 'nullable', $catalogo('expedido_en')],
            'primer_apellido' => ['required', 'string', 'max:255'],
            'segundo_apellido' => ['nullable', 'string', 'max:255'],
            'nombres' => ['required', 'string', 'max:255'],
            'iniciales' => ['nullable', 'string', 'max:10'],
            'tipo_persona' => ['required', $catalogo('tipos_persona')],
            'nit' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'cargo_puesto' => ['nullable', 'string', 'max:255'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'telefono_oficina' => ['nullable', 'string', 'max:30'],
            'telefono_celular' => ['required', 'string', 'max:30'],
            'correo_personal' => ['required', 'email', 'max:255'],
            'correo_institucional' => ['nullable', 'email', 'max:255'],
            'nacionalidad_id' => ['nullable', $this->existe('paises')],
            'edificio_id' => ['nullable', $this->existe('edificios')],
        ] + $this->reglasUbicacion();
    }

    protected function datosParaGuardar(array $datos): array
    {
        // Iniciales automáticas (primera letra del apellido y del nombre) si no se envían.
        if (empty($datos['iniciales']) && isset($datos['primer_apellido'], $datos['nombres'])) {
            $datos['iniciales'] = mb_strtoupper(mb_substr($datos['primer_apellido'], 0, 1).mb_substr($datos['nombres'], 0, 1));
        }

        return $datos;
    }
}
