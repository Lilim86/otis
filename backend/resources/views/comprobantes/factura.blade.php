<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Factura {{ $d['numero'] }}</title>@include('comprobantes._estilos')</head>
<body>
@include('comprobantes._cabecera', ['titulo' => 'FACTURA', 'tipo' => 'FACTURA'])

<table class="datos">
    <tr><td class="et">Nombre / Razón social:</td><td>{{ $d['cliente']['nombre'] }}</td><td class="et">NIT / CI:</td><td>{{ $d['cliente']['nit'] }}</td></tr>
    <tr><td class="et">Edificio / proyecto:</td><td>{{ $d['cliente']['edificio'] ?: '—' }}</td><td class="et">Contrato:</td><td>{{ $d['contrato'] ?: '—' }}</td></tr>
    <tr><td class="et">Dirección:</td><td colspan="3">{{ $d['cliente']['direccion'] ?: '—' }}</td></tr>
</table>

<table class="detalle">
    <thead><tr><th width="9%">Código</th><th width="8%" class="centro">Cant.</th><th width="9%">Unidad</th><th>Descripción</th><th width="14%" class="der">P. unitario</th><th width="14%" class="der">Subtotal</th></tr></thead>
    <tbody>
        @foreach($d['items'] as $i)
            <tr><td>{{ $i['codigo'] }}</td><td class="centro">{{ $i['cantidad'] }}</td><td>{{ $i['unidad'] }}</td><td>{{ $i['descripcion'] }}</td><td class="der">{{ number_format($i['precio'], 2) }}</td><td class="der">{{ number_format($i['subtotal'], 2) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top:6px"><tr>
    <td width="58%" style="vertical-align:top">
        <div class="letras"><strong>Son:</strong> {{ $d['total_letras'] }}</div>
        <table style="margin-top:10px"><tr><td width="80"><div class="qr">QR fiscal</div></td><td class="sub" style="padding-left:10px">Forma de pago: {{ $d['pago']['metodo'] }}@if($d['pago']['detalle'])<br>{{ $d['pago']['detalle'] }}@endif</td></tr></table>
    </td>
    <td width="42%">
        <table class="totales">
            <tr><td>Subtotal Bs</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
            <tr><td>Descuento Bs</td><td class="der">0.00</td></tr>
            <tr class="total-final"><td>TOTAL Bs</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
            <tr><td>Importe base crédito fiscal</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr>
        </table>
    </td>
</tr></table>

<div class="pie">
    Este es un <strong>diseño modelo</strong> de factura. La factura con validez tributaria debe emitirse desde el sistema de facturación autorizado por el Servicio de Impuestos Nacionales;
    esta plantilla ya trae los datos del cliente, el detalle y el monto en letras para conectarlos cuando se defina esa integración.
</div>
</body></html>
