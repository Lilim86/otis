<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; border-top: 5px solid #0D1638; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #0D1638; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .content { color: #333333; line-height: 1.6; }
        .credenciales { background-color: #FDF9EC; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #F4DE9B; }
        .btn { display: block; width: 200px; margin: 20px auto; padding: 10px; background-color: #0D1638; color: #ffffff; text-align: center; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">SISTEMA LILIM - OTIS</div>
        <div class="content">
            <p>Hola <strong>{{ $nombre }}</strong>,</p>
            <p>El administrador te ha dado de alta en el nuevo Sistema de Gestión Integral LILIM. A continuación, te enviamos tus credenciales de acceso seguras:</p>
            
            <div class="credenciales">
                <p><strong>Usuario (Correo):</strong> {{ $email }}</p>
                <p><strong>Contraseña Temporal:</strong> {{ $password }}</p>
            </div>

            <p>Te recomendamos cambiar tu contraseña una vez que ingreses al sistema por primera vez.</p>
            
            <a href="http://127.0.0.1:8000/login" class="btn">Ingresar al Sistema</a>
        </div>
    </div>
</body>
</html>