<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Factura {{ $d['numero'] }}</title>
<style>
    @page { margin: 30px 36px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .b { font-weight: bold; }
    .der { text-align: right; }
    .centro { text-align: center; }
    .emisor td { padding: 0; line-height: 1.35; font-size: 8.5px; }
    .titulo { text-align: center; margin: 22px 0 14px; }
    .titulo h1 { margin: 0; font-size: 13px; letter-spacing: .5px; }
    .titulo p { margin: 1px 0 0; font-size: 8.5px; }
    .cliente td { padding: 1px 0; font-size: 8.5px; }
    .detalle { margin-top: 6px; }
    .detalle th { font-size: 7px; font-weight: bold; padding: 5px 4px; border-top: 1px solid #555; border-bottom: 1px solid #555; line-height: 1.1; }
    .detalle td { padding: 8px 4px 18px; font-size: 7.5px; border-bottom: 1px solid #555; }
    .totales td { padding: 3.5px 4px; font-size: 7.5px; }
    .totales .et { font-weight: bold; }
    .leyenda { font-size: 7px; line-height: 2.1; }
    .modelo { background: #C99A2E; color: #fff; text-align: center; font-weight: bold; letter-spacing: 2px; padding: 3px; font-size: 8px; margin-bottom: 10px; }
    .gris { color: #888; }
</style>
</head>
<body>
@if($d['modelo'])
    <div class="modelo">DOCUMENTO MODELO · SIN VALIDEZ TRIBUTARIA</div>
@endif

<table class="emisor">
    <tr>
        <td width="62%">
            <div class="b">{{ mb_strtoupper($d['emisor']['razon_social']) }}</div>
            <div class="b">{{ $d['emisor']['sucursal'] }}</div>
            N° Punto de Venta {{ $d['emisor']['punto_venta'] }}<br>
            {!! nl2br(e(mb_strtoupper(str_replace([', Edificio', ', Piso'], ["\nEdificio", "\nPiso"], $d['emisor']['direccion'])))) !!}<br>
            Teléfono:&nbsp; {{ $d['emisor']['telefono'] }}<br>
            {{ $d['emisor']['ciudad'] }}
        </td>
        <td width="38%">
            <table>
                <tr><td class="b" width="38%">NIT</td><td>{{ $d['emisor']['nit'] }}</td></tr>
                <tr><td class="b">Factura N°</td><td>{{ $d['numero'] }}</td></tr>
                <tr><td class="b">Cod. Autorización</td><td class="gris">Lo asigna el SIN al emitir la factura en línea</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="titulo">
    <h1>FACTURA</h1>
    <p>(Con Derecho a Crédito Fiscal)</p>
</div>

<table class="cliente">
    <tr>
        <td class="b" width="16%">Fecha:</td><td width="50%">{{ $d['fecha'] }}</td>
        <td class="b der" width="14%">NIT/CI/CEX:</td><td width="20%" style="padding-left:8px">{{ $d['cliente']['nit'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="b">Nombre/Razón Social:</td><td>{{ mb_strtoupper($d['cliente']['nombre']) }}</td>
        <td class="b der">Cod. Cliente:</td><td style="padding-left:8px">{{ $d['cliente']['codigo'] ?: '—' }}</td>
    </tr>
</table>

<table class="detalle">
    <thead>
        <tr>
            <th width="12%" class="centro">CÓDIGO PRODUCTO<br>SERVICIO</th>
            <th width="8%" class="centro">CANTIDAD</th>
            <th width="9%" class="centro">UNIDAD DE<br>MEDIDA</th>
            <th style="text-align:left">DESCRIPCIÓN</th>
            <th width="10%" class="der">PRECIO<br>UNITARIO</th>
            <th width="10%" class="der">DESCUENTO</th>
            <th width="10%" class="der">SUBTOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach($d['items'] as $i)
            <tr>
                <td class="centro">{{ $i['codigo'] }}</td>
                <td class="centro">{{ number_format($i['cantidad'], 2) }}</td>
                <td class="centro">{{ $i['unidad'] }}</td>
                <td>{{ $i['descripcion'] }}</td>
                <td class="der">{{ number_format($i['precio'], 2) }}</td>
                <td class="der">{{ number_format($i['descuento'], 2) }}</td>
                <td class="der">{{ number_format($i['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top:6px">
    <tr>
        <td width="62%" style="vertical-align:bottom; padding-bottom:4px">Son: {{ mb_strtoupper($d['total_letras']) }}</td>
        <td width="38%">
            <table class="totales">
                <tr><td class="et">SUB TOTAL Bs.</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
                <tr><td class="et">DESCUENTO Bs.</td><td class="der">0.00</td></tr>
                <tr><td class="et">TOTAL Bs.</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
                <tr><td class="et">MONTO GIFT CARD Bs.</td><td class="der">0.00</td></tr>
                <tr><td class="et">MONTO A PAGAR Bs.</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
                <tr><td class="et">IMPORTE BASE CRÉDITO FISCAL Bs.</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top:14px">
    <tr>
        <td width="80%" class="leyenda" style="vertical-align:middle">
            ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY<br>
            <strong>Ley N° 453: La interrupción del servicio debe comunicarse con anterioridad a las Autoridades que correspondan y a los usuarios afectados.</strong><br>
            Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea
        </td>
        <td width="20%" class="der"><img src="{{ $d['qr'] }}" style="width:105px;height:105px"></td>
    </tr>
</table>

<p class="gris" style="margin-top:18px; font-size:7px">
    Modelo de la representación gráfica. La factura con validez tributaria la emite el sistema de facturación en línea autorizado por el SIN,
    que asigna el código de autorización (CUF) y el QR de verificación.
</p>
</body></html>
