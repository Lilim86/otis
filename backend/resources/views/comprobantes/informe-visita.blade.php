<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Informe de visita</title>@include('comprobantes._estilos')</head>
<body>
<table class="cabecera"><tr>
    <td width="60%"><p class="logo">{{ $d['emisor']['nombre_comercial'] }}</p><div class="sub"><strong>{{ $d['emisor']['razon_social'] }}</strong><br>Tel.: {{ $d['emisor']['telefono'] }}</div></td>
    <td width="40%"><div class="caja"><h1 style="font-size:15px">INFORME DE VISITA</h1><div class="fila"><strong>Visita:</strong> {{ $d['tipo'] }}@if($d['nro']) #{{ $d['nro'] }}@endif</div><div class="fila"><strong>Contrato:</strong> {{ $d['contrato'] }}</div></div></td>
</tr></table>
<div class="linea-oro"></div>

<table class="datos">
    <tr><td class="et">Cliente:</td><td>{{ $d['cliente'] }}</td><td class="et">Edificio:</td><td>{{ $d['edificio'] }}</td></tr>
    <tr><td class="et">Ubicación:</td><td colspan="3">{{ $d['ubicacion'] ?: '—' }}</td></tr>
    <tr><td class="et">Programada:</td><td>{{ $d['programada'] }}</td><td class="et">Realizada:</td><td>{{ $d['realizada'] ?: '—' }}</td></tr>
    <tr><td class="et">Técnico:</td><td>{{ $d['tecnico'] ?: '—' }}</td><td class="et">Situación:</td><td>{{ $d['situacion'] }}</td></tr>
</table>

@if($d['equipos'])
    <p class="marca" style="margin:10px 0 3px"><strong>Equipos del edificio</strong></p>
    <table class="detalle"><thead><tr><th>Código</th><th>Equipo</th><th>Marca / modelo</th></tr></thead><tbody>
        @foreach($d['equipos'] as $e)<tr><td>{{ $e['codigo'] }}</td><td>{{ $e['descripcion'] }}</td><td>{{ $e['detalle'] }}</td></tr>@endforeach
    </tbody></table>
@endif

<p class="marca" style="margin:12px 0 3px"><strong>Trabajo solicitado / programado</strong></p>
<div class="letras">{{ $d['descripcion'] ?: '—' }}</div>
<p class="marca" style="margin:12px 0 3px"><strong>Informe técnico</strong></p>
<div class="letras" style="min-height:60px">{{ $d['informe'] ?: 'Sin informe registrado.' }}</div>

<table style="margin-top:18px"><tr>
    <td width="50%" class="centro">
        @if($d['firma'])<img class="firma-img" src="{{ $d['firma'] }}" alt="Firma"><br>@else<div style="height:60px"></div>@endif
        <div class="firma" style="margin-top:2px"></div>
        Conformidad del cliente<br><span class="sub">{{ $d['firmado_por'] ?: '—' }}@if($d['firmado_en']) · {{ $d['firmado_en'] }}@endif</span>
        @if($d['calificacion'])<br><span class="oro">{{ str_repeat('★', $d['calificacion']) }}</span><span style="color:#ccc">{{ str_repeat('★', 5 - $d['calificacion']) }}</span>@endif
    </td>
    <td width="50%" class="centro"><div style="height:60px"></div><div class="firma" style="margin-top:2px"></div>Técnico responsable<br><span class="sub">{{ $d['tecnico'] ?: '—' }}</span></td>
</tr></table>
</body></html>
