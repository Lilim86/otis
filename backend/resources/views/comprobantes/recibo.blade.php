@php
    // Lo que no se sabe queda como en el talonario: la casilla vacía, para que la llene Cobranzas.
    $v = fn ($valor) => $valor === null || $valor === '' ? '' : $valor;
    $x = fn ($marcado) => $marcado ? 'X' : '';
    $r = $d['emisor']['recibo'];
    $f = $d['fecha'];
    $meses = [[1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio'], [7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre']];
@endphp
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Recibo {{ $d['numero'] ?? 'en blanco' }}</title>
<style>
    @page { margin: 16px 20px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #1d1d1d; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: middle; }
    .marco td, .celdas td { border: 1px solid #333; padding: 3px 5px; }
    .et { font-weight: bold; font-size: 7.5px; }
    .lleno { font-family: DejaVu Sans Mono, monospace; font-size: 10px; color: #0D1638; }
    .banda { background: #4B3A9E; color: #fff; padding: 6px 10px; border-radius: 6px; }
    .banda .logo { font-size: 20px; font-weight: bold; letter-spacing: .5px; margin: 0; }
    .banda .cia { font-size: 6.5px; letter-spacing: 1.6px; font-weight: bold; margin-bottom: 3px; }
    .banda .dir { font-size: 6.5px; line-height: 1.35; }
    .titulo { color: #4B3A9E; font-weight: bold; font-size: 12px; letter-spacing: .5px; margin: 5px 0 0; text-align: center; }
    .nro { color: #C62828; font-size: 13px; font-family: DejaVu Sans Mono, monospace; letter-spacing: 1px; }
    .centro { text-align: center; }
    .mes { font-size: 7px; font-weight: bold; height: 9px; width: 10.83%; }
    .mesv { height: 15px; text-align: center; }
    .firma { height: 46px; vertical-align: bottom !important; text-align: center; font-weight: bold; font-size: 7px; }
    .nota { font-size: 7px; border: 1px solid #333; border-top: 0; padding: 3px 5px; }
    .modelo { background: #C99A2E; color: #fff; text-align: center; font-weight: bold; letter-spacing: 2px; padding: 2px; font-size: 7px; margin-bottom: 4px; }
</style>
</head>
<body>
@if($d['modelo'])
    <div class="modelo">RECIBO EN BLANCO · LO LLENA COBRANZAS SEGÚN EL CLIENTE DEL EDIFICIO</div>
@endif

{{-- Cabecera: banda de la empresa a la izquierda; código, número y fecha a la derecha --}}
<table>
    <tr>
        <td width="56%" style="vertical-align:top">
            <div class="banda">
                <p class="logo">CGI Ltda. - OTIS</p>
                <div class="cia">CÍA. COMERCIAL GENERAL INDUSTRIAL LTDA.</div>
                <div class="dir"><strong>Dirección:</strong><br>{{ $r['direccion'] }}<br><strong>Teléfono:</strong> {{ $r['telefono'] }}<br>{{ $d['emisor']['ciudad'] }} - Bolivia</div>
            </div>
            <div class="titulo">RECIBO OFICIAL DE COBRANZA</div>
        </td>
        <td width="4%"></td>
        <td width="40%" style="vertical-align:top">
            <table class="marco">
                <tr><td class="et centro" width="40%" style="text-decoration:underline">Cod.</td><td class="centro">{{ $r['codigo'] }}</td></tr>
                <tr><td class="et centro" style="text-decoration:underline">Versión</td><td class="centro">{{ $r['version'] }}</td></tr>
            </table>
            <table class="marco" style="margin-top:3px">
                <tr><td class="et centro" width="40%" style="height:20px">{{ $d['emisor']['ciudad'] }}.</td><td class="centro"><span class="nro">Nº {{ $d['numero'] ?? '' }}</span></td></tr>
            </table>
            <table class="marco" style="margin-top:3px">
                <tr>
                    <td class="et centro" rowspan="2" width="40%">FECHA:</td>
                    <td class="et centro" style="font-size:6.5px">DIA</td><td class="et centro" style="font-size:6.5px">MES</td><td class="et centro" style="font-size:6.5px">AÑO</td>
                </tr>
                <tr>
                    <td class="centro lleno" style="height:14px">{{ $f ? $f->format('d') : '' }}</td>
                    <td class="centro lleno">{{ $f ? $f->format('m') : '' }}</td>
                    <td class="centro lleno">{{ $f ? $f->format('Y') : '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Recibí de / Cod. cliente --}}
<table class="celdas" style="margin-top:6px">
    <tr>
        <td class="et" width="15%" style="height:20px">RECIBÍ DE:</td>
        <td class="lleno">{{ $v($d['cliente']['nombre']) }}</td>
        <td class="et" width="11%">COD<br>CLIENTE:</td>
        <td class="lleno centro" width="13%">{{ $v($d['cliente']['codigo']) }}</td>
    </tr>
</table>

{{-- Edificio / Contrato --}}
<table class="celdas" style="margin-top:3px">
    <tr>
        <td class="et" width="15%" style="height:20px">EDIFICIO:</td>
        <td class="lleno">{{ $v($d['cliente']['edificio']) }}</td>
        <td class="et" width="11%">CONTRATO:</td>
        <td class="lleno centro" width="13%">{{ $v($d['contrato']) }}</td>
    </tr>
</table>

{{-- La suma de / Bs --}}
<table class="celdas" style="margin-top:3px">
    <tr>
        <td class="et" width="15%" style="height:20px">LA SUMA DE.</td>
        <td class="lleno">{{ $v($d['suma_letras']) }}</td>
        <td class="et centro" width="7%">BS.-</td>
        <td class="lleno centro" width="17%">{{ $d['total'] !== null ? number_format($d['total'], 2, ',', '.') : '' }}</td>
    </tr>
</table>

{{-- Forma de pago --}}
<table class="celdas" style="margin-top:3px">
    <tr>
        <td class="et" width="11%" style="height:20px">EFECTIVO.</td>
        <td class="lleno centro" width="6%">{{ $x($d['forma']['efectivo']) }}</td>
        <td class="et centro" width="10%">NRO<br>CHEQUE.</td>
        <td class="lleno" width="11%">{{ $v($d['forma']['cheque']) }}</td>
        <td class="et centro" width="7%">BANCO</td>
        <td class="lleno" width="23%">{{ $v($d['forma']['banco']) }}</td>
        <td class="et centro" width="8%">TRANSF.</td>
        <td class="lleno">{{ $v($d['forma']['transferencia']) }}</td>
    </tr>
</table>

{{-- Concepto, tipo de servicio y meses cancelados --}}
<table class="celdas" style="margin-top:3px">
    <tr>
        <td class="et" rowspan="2" width="9%">POR<br>CONCEPTO<br>DE:</td>
        <td class="et centro" width="8%">Pago<br>Total</td>
        <td class="et centro" width="8%">Pago<br>parcial</td>
        <td class="et" rowspan="2" width="10%">TIPO DE<br>SERVICIO.</td>
        <td class="et centro" width="11%">MANT.</td>
        <td class="et centro" width="11%">REPARACIÓN</td>
        <td class="et centro" colspan="2">MODERNIZACIÓN.</td>
        <td class="et centro" colspan="2">GESTIÓN</td>
    </tr>
    <tr>
        <td class="lleno centro" style="height:15px">{{ $d['pago_total'] === true ? 'X' : '' }}</td>
        <td class="lleno centro">{{ $d['pago_total'] === false ? 'X' : '' }}</td>
        <td class="lleno centro">{{ $x($d['servicio'] === 'MANT') }}</td>
        <td class="lleno centro">{{ $x($d['servicio'] === 'REPARACION') }}</td>
        <td class="lleno centro" colspan="2">{{ $x($d['servicio'] === 'MODERNIZACION') }}</td>
        <td class="lleno centro" colspan="2">{{ $v($d['gestion']) }}</td>
    </tr>
    @foreach($meses as $i => $fila)
        <tr>
            @if($i === 0)
                <td class="centro" colspan="4" rowspan="4" style="font-weight:bold; font-size:7.5px">Colocar el Nro. Factura de los meses<br>cancelados.</td>
            @endif
            @foreach($fila as $nombre)
                <td class="mes">{{ $nombre }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach(array_keys($fila) as $m)
                <td class="mesv lleno">{{ $d['meses'][$m] ?? '' }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

{{-- Firmas y nota --}}
<table class="celdas" style="margin-top:3px">
    <tr>
        <td class="firma" width="50%">FIRMA EJECUTIVO DE COBRANZAS</td>
        <td class="firma" width="50%">FIRMA O SELLO DE CLIENTE.</td>
    </tr>
</table>
<div class="nota"><strong>NOTAS:</strong> Cualquier borrón, alteración y/o rectificación ANULA este documento.<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Por cada pago exija su recibo oficial de cobranzas.</div>
</body></html>
