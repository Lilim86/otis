@if($d['modelo'])
    <div class="modelo">DOCUMENTO MODELO · SIN VALIDEZ TRIBUTARIA</div>
@endif
<table class="cabecera">
    <tr>
        <td width="55%">
            <p class="logo">{{ $d['emisor']['nombre_comercial'] }}</p>
            <div class="sub">
                <strong>{{ $d['emisor']['razon_social'] }}</strong><br>
                {{ $d['emisor']['actividad'] }}<br>
                {{ $d['emisor']['direccion'] }}<br>
                Tel.: {{ $d['emisor']['telefono'] }}@if($d['emisor']['correo']) · {{ $d['emisor']['correo'] }}@endif
            </div>
        </td>
        <td width="45%">
            <div class="caja">
                <h1>{{ $titulo }}</h1>
                @if($tipo === 'FACTURA')<div class="fila"><strong>NIT:</strong> {{ $d['emisor']['nit'] }}</div>@endif
                <div class="fila"><strong>N.º:</strong> {{ $d['numero'] }}</div>
                @if($tipo === 'FACTURA')<div class="fila"><strong>Cód. autorización:</strong> <span style="color:#999">lo asigna el sistema fiscal</span></div>@endif
                <div class="fila"><strong>Fecha:</strong> {{ $d['fecha'] }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="linea-oro"></div>
