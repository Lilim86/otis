<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Recibo {{ $d['numero'] }}</title>@include('comprobantes._estilos')</head>
<body>
@include('comprobantes._cabecera', ['titulo' => 'RECIBO', 'tipo' => 'RECIBO'])

<table class="datos">
    <tr><td class="et">Recibí de:</td><td colspan="3"><strong>{{ $d['cliente']['nombre'] }}</strong> @if($d['cliente']['nit'])· NIT {{ $d['cliente']['nit'] }}@endif</td></tr>
    <tr><td class="et">La suma de:</td><td colspan="3">{{ $d['total_letras'] }}</td></tr>
    <tr><td class="et">Por concepto de:</td><td colspan="3">{{ $d['items'][0]['descripcion'] }}</td></tr>
    <tr><td class="et">Contrato:</td><td>{{ $d['contrato'] ?: '—' }}</td><td class="et">Edificio:</td><td>{{ $d['cliente']['edificio'] ?: '—' }}</td></tr>
    <tr><td class="et">Forma de pago:</td><td>{{ $d['pago']['metodo'] }}</td><td class="et">Referencia:</td><td>{{ $d['pago']['detalle'] ?: '—' }}</td></tr>
</table>

<table style="margin-top:14px"><tr>
    <td width="60%"></td>
    <td width="40%"><table class="totales"><tr class="total-final"><td>TOTAL Bs</td><td class="der">{{ number_format($d['total'], 2) }}</td></tr></table></td>
</tr></table>

<table style="margin-top:10px"><tr>
    <td width="50%" class="centro"><div class="firma"></div>Entregué conforme<br><span class="sub">{{ $d['cliente']['nombre'] }}</span></td>
    <td width="50%" class="centro"><div class="firma"></div>Recibí conforme<br><span class="sub">{{ $d['emisor']['nombre_comercial'] }}</span></td>
</tr></table>

<div class="pie">Recibo de pago interno. No reemplaza a la factura, que se emite por separado desde el sistema de facturación autorizado.</div>
</body></html>
