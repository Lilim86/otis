<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $reporte['titulo'] }}</title>
    @php $chico = count($reporte['columnas']) > 9; @endphp
    <style>
        body { font-family: Arial, sans-serif; color: #333; font-size: {{ $chico ? '8' : '10' }}px; }
        .cabecera { width: 100%; border-bottom: 2px solid #0D1638; margin-bottom: 12px; }
        .cabecera td { vertical-align: middle; padding-bottom: 8px; }
        .logo-cgi { color: #0D1638; font-size: 22px; font-weight: bold; margin: 0; }
        .logo-otis { color: #C99A2E; font-size: 32px; font-weight: 900; margin: 0; text-align: right; }
        .titulo { text-align: center; }
        .titulo h2 { margin: 0; font-size: 16px; color: #222; }
        .titulo h3 { margin: 4px 0 0; font-size: 11px; font-weight: normal; color: #555; }
        .chico { font-size: 9px; color: #666; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #f4f4f4; border-bottom: 1px solid #aaa; padding: 5px 4px; text-align: left; color: #0D1638; }
        table.datos td { border-bottom: 1px dashed #ddd; padding: 4px; vertical-align: top; }
        tr.total td { border-top: 2px solid #0D1638; font-weight: bold; color: #0D1638; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td width="28%"><p class="logo-cgi">CGI</p><span class="chico">Compañía Comercial General Industrial Ltda.</span></td>
            <td width="44%" class="titulo">
                <h2>{{ mb_strtoupper($reporte['titulo']) }}</h2>
                <h3>
                    Área: {{ $reporte['area'] }}
                    @if(!empty($filtros['desde']) || !empty($filtros['hasta'])) · Del {{ $filtros['desde'] ?? '—' }} al {{ $filtros['hasta'] ?? '—' }} @endif
                    @if(!empty($filtros['q'])) · Búsqueda: "{{ $filtros['q'] }}" @endif
                </h3>
            </td>
            <td width="28%"><p class="logo-otis">OTIS</p><div style="text-align:right" class="chico">Emitido por: {{ $emitidoPor }}<br>{{ now()->format('d/m/Y H:i') }}</div></td>
        </tr>
    </table>

    <table class="datos">
        <thead>
            <tr>@foreach($reporte['columnas'] as $etiqueta) <th>{{ $etiqueta }}</th> @endforeach</tr>
        </thead>
        <tbody>
            @forelse($reporte['filas'] as $fila)
                <tr>
                    @foreach(array_keys($reporte['columnas']) as $clave)
                        @php $v = $fila[$clave] ?? null; @endphp
                        <td class="{{ is_numeric($v) && !in_array($clave, ['codigo','nit','ci','telefono','celular','cuenta','nro_factura','nro_recibo','nro_cuota','contrato']) ? 'num' : '' }}">
                            {{ is_float($v) || (is_numeric($v) && str_contains((string) $v, '.')) ? number_format((float) $v, 2) : ($v ?? '') }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($reporte['columnas']) }}" style="text-align:center;padding:16px;">No hay datos para este reporte.</td></tr>
            @endforelse
            @if($reporte['sumas'])
                <tr class="total">
                    @foreach(array_keys($reporte['columnas']) as $i => $clave)
                        <td class="num">{{ $i === 0 ? 'TOTALES' : (isset($reporte['sumas'][$clave]) ? number_format($reporte['sumas'][$clave], 2) : '') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
    <p class="chico" style="margin-top:10px">{{ count($reporte['filas']) }} registro(s).</p>
</body>
</html>
