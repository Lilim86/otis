<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; border-top: 5px solid #0D1638; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #0D1638; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .content { color: #333333; line-height: 1.6; }
        .codigo { background-color: #FDF9EC; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #F4DE9B; text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold; color: #0D1638; }
        .aviso { font-size: 13px; color: #666666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">SISTEMA LILIM - OTIS</div>
        <div class="content">
            <p>Hola <strong>{{ $nombre }}</strong>,</p>
            <p>Usa este código para terminar de iniciar sesión:</p>

            <div class="codigo">{{ $codigo }}</div>

            <p>El código vence en <strong>{{ $minutos }} minutos</strong> y solo puede usarse una vez.</p>
            <p class="aviso">Si no intentaste iniciar sesión, ignora este correo y considera cambiar tu contraseña. Nunca compartas este código.</p>
        </div>
    </div>
</body>
</html>
