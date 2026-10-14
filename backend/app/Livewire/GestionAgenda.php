<?php

namespace App\Livewire;

use App\Models\Cita;
use App\Models\Edificio;
use App\Models\Empresa;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionAgenda extends Component
{
    public ?int $cita_id = null;

    public $titulo;

    public $tipo = 'CITA';

    public $fecha_inicio;

    public $fecha_fin;

    public $lugar;

    public $con_quien;

    public $telefono;

    public $empresa_id = '';

    public $edificio_id = '';

    public $motivo;

    public $situacion = 'PROGRAMADA';

    /** Filtros del listado. */
    public $desde;

    public $hasta;

    public $buscar = '';

    public function mount(): void
    {
        $this->desde = now()->toDateString();
    }

    public function render()
    {
        $citas = Cita::with(['empresa:id,denominacion', 'edificio:id,denominacion'])
            ->where('responsable', config('sofia.gerente_general'))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_inicio', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_inicio', '<=', $this->hasta))
            ->when($this->buscar, fn ($q) => $q->where(fn ($s) => $s
                ->whereRaw('LOWER(titulo) LIKE ?', ['%'.mb_strtolower($this->buscar).'%'])
                ->orWhereRaw('LOWER(con_quien) LIKE ?', ['%'.mb_strtolower($this->buscar).'%'])))
            ->orderBy('fecha_inicio')
            ->get();

        return view('livewire.gestion-agenda', [
            'citas' => $citas,
            'gerente' => config('sofia.gerente_general'),
            'tipos' => config('sofia.tipos_cita'),
            'situaciones' => config('sofia.situaciones_cita'),
            'empresas' => Empresa::orderBy('denominacion')->get(['id', 'denominacion']),
            'edificios' => Edificio::orderBy('denominacion')->get(['id', 'denominacion']),
        ]);
    }

    public function guardar(): void
    {
        $this->autorizar($this->cita_id ? 'citas.editar' : 'citas.crear');

        $datos = $this->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => ['required', Rule::in(array_keys(config('sofia.tipos_cita')))],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'lugar' => 'nullable|string|max:255',
            'con_quien' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'empresa_id' => 'nullable|exists:empresas,id',
            'edificio_id' => 'nullable|exists:edificios,id',
            'motivo' => 'nullable|string|max:2000',
            'situacion' => ['required', Rule::in(array_keys(config('sofia.situaciones_cita')))],
        ]);

        $gerente = config('sofia.gerente_general');
        if ($datos['situacion'] !== 'CANCELADA' && Cita::cruzadas($gerente, $datos['fecha_inicio'], $datos['fecha_fin'], $this->cita_id)->exists()) {
            $this->addError('fecha_inicio', "Ese horario se cruza con otra cita de {$gerente}.");

            return;
        }

        $datos['empresa_id'] = $datos['empresa_id'] ?: null;
        $datos['edificio_id'] = $datos['edificio_id'] ?: null;

        Cita::updateOrCreate(['id' => $this->cita_id], $datos + ['responsable' => $gerente]);

        $this->limpiarCampos();
        session()->flash('mensaje', 'Agenda actualizada.');
    }

    public function editar(int $id): void
    {
        $cita = Cita::findOrFail($id);

        $this->fill([
            'cita_id' => $cita->id,
            'titulo' => $cita->titulo,
            'tipo' => $cita->tipo,
            'fecha_inicio' => $cita->fecha_inicio->format('Y-m-d\TH:i'),
            'fecha_fin' => $cita->fecha_fin->format('Y-m-d\TH:i'),
            'lugar' => $cita->lugar,
            'con_quien' => $cita->con_quien,
            'telefono' => $cita->telefono,
            'empresa_id' => $cita->empresa_id ?? '',
            'edificio_id' => $cita->edificio_id ?? '',
            'motivo' => $cita->motivo,
            'situacion' => $cita->situacion,
        ]);
        $this->resetValidation();
    }

    public function cambiarSituacion(int $id, string $situacion): void
    {
        $this->autorizar('citas.editar');
        abort_unless(array_key_exists($situacion, config('sofia.situaciones_cita')), 422);

        Cita::findOrFail($id)->update(['situacion' => $situacion]);
    }

    public function eliminar(int $id): void
    {
        $this->autorizar('citas.eliminar');
        Cita::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos(): void
    {
        $this->reset(['cita_id', 'titulo', 'fecha_inicio', 'fecha_fin', 'lugar', 'con_quien', 'telefono', 'empresa_id', 'edificio_id', 'motivo']);
        $this->tipo = 'CITA';
        $this->situacion = 'PROGRAMADA';
        $this->resetValidation();
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(! config('sofia.permisos_activos') || auth()->user()->can($permiso), 403, "No tienes permiso ({$permiso}).");
    }
}
