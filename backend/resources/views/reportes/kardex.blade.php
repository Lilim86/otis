<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex de Inventario - OTIS</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header-table { w-full; border-bottom: 2px solid #0D1638; padding-bottom: 10px; margin-bottom: 20px; width: 100%; }
        .header-table td { vertical-align: middle; }
        .title-center { text-align: center; color: #555; }
        .title-center h2 { margin: 0; font-size: 20px; letter-spacing: 1px; color: #333;}
        .title-center h3 { margin: 5px 0 0 0; font-size: 16px; font-weight: normal; }
        .logo-cgi { color: #0D1638; font-size: 24px; font-weight: bold; margin:0; }
        .logo-otis { color: #C99A2E; font-size: 38px; font-weight: 900; text-align: right; margin:0; font-family: 'Arial Black', sans-serif;}
        .subtitle { font-size: 10px; color: #666; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background-color: #f4f4f4; border-bottom: 1px solid #ccc; padding: 8px; text-align: left; font-size: 11px; color: #0D1638;}
        .data-table td { border-bottom: 1px dashed #eee; padding: 8px; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="30%">
                <h1 class="logo-cgi">CGI</h1>
                <span class="subtitle">Compañía Comercial General Industrial Ltda.</span><br>
                <span class="subtitle">Almacen: 4 - ALMACEN DE HERRAMIENTAS LA PAZ</span>
            </td>
            <td width="40%" class="title-center">
                <h2>KARDEX DE INVENTARIO</h2>
                <h3>CANTIDAD TOTAL POR BIEN</h3>
            </td>
            <td width="30%" class="text-right">
                <h1 class="logo-otis">OTIS</h1>
                <span class="subtitle">Reporte: REP-0069</span><br>
                <span class="subtitle">Fecha: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Codigo de Bien</th>
                <th>Descripcion del Bien</th>
                <th>Tipo</th>
                <th class="text-center">Cantidad<br>Ingresada</th>
                <th class="text-center">Cantidad<br>Salida</th>
                <th class="text-right">Saldo<br>Fisico</th>
            </tr>
        </thead>
        <tbody>
            @foreach($insumos as $insumo)
            <tr>
                <td style="color: #666;">{{ $insumo->codigo }}</td>
                <td style="font-weight: bold;">{{ $insumo->descripcion }}</td>
                <td>{{ $insumo->tipo }}</td>
                <td class="text-center text-gray">-</td>
                <td class="text-center text-gray">-</td>
                <td class="text-right" style="font-weight: bold; color: #0D1638;">{{ number_format($insumo->stock_actual, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>