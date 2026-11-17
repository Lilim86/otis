<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * QR de cobro que el cliente escanea desde su portal. Lo administra Cobranzas: sube la imagen que
 * le da el banco y sus datos. Mientras no suba ninguno se usa el QR de prueba de config('sofia.pagos.qr').
 */
class QrCobroService
{
    private const CARPETA = 'pagos/qr';

    private const DATOS = self::CARPETA.'/datos.json';

    public function datos(): array
    {
        $base = config('sofia.pagos.qr');
        unset($base['imagen']);
        $propios = Storage::exists(self::DATOS) ? (array) json_decode(Storage::get(self::DATOS), true) : [];

        return array_merge($base, $propios, ['personalizado' => $this->rutaPropia() !== null]);
    }

    /** @return array{0:string,1:string} contenido y tipo MIME */
    public function imagen(): array
    {
        if ($ruta = $this->rutaPropia()) {
            return [Storage::get($ruta), Storage::mimeType($ruta) ?: 'image/png'];
        }

        return [file_get_contents(config('sofia.pagos.qr.imagen')), 'image/png'];
    }

    /** La imagen como data URI, para incrustarla en los PDF. */
    public function dataUri(): string
    {
        [$contenido, $tipo] = $this->imagen();

        return "data:{$tipo};base64,".base64_encode($contenido);
    }

    public function guardar(?UploadedFile $imagen, array $datos, int $usuarioId): array
    {
        if ($imagen) {
            foreach (Storage::files(self::CARPETA) as $anterior) {
                if ($anterior !== self::DATOS) {
                    Storage::delete($anterior);
                }
            }
            $imagen->storeAs(self::CARPETA, 'qr.'.strtolower($imagen->extension() ?: 'png'));
        }

        $actuales = Storage::exists(self::DATOS) ? (array) json_decode(Storage::get(self::DATOS), true) : [];
        Storage::put(self::DATOS, json_encode(array_merge($actuales, $datos, [
            'prueba' => (bool) ($datos['prueba'] ?? $actuales['prueba'] ?? false),
            'actualizado_por' => $usuarioId, 'actualizado_en' => now()->toIso8601String(),
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $this->datos();
    }

    /** Vuelve al QR de prueba. */
    public function restablecer(): array
    {
        Storage::deleteDirectory(self::CARPETA);

        return $this->datos();
    }

    private function rutaPropia(): ?string
    {
        return collect(Storage::files(self::CARPETA))->first(fn ($f) => $f !== self::DATOS);
    }
}
