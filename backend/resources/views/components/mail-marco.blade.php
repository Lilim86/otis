<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#FBF9F4;font-family:Arial,Helvetica,sans-serif;color:#2b2b2b;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#FBF9F4;padding:24px 0;"><tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #F4DE9B;">
        <tr><td style="background:#0D1638;padding:22px 28px;text-align:center;">
            <div style="font-family:Georgia,serif;font-size:28px;letter-spacing:8px;color:#ECC862;font-weight:bold;">OTIS</div>
            <div style="font-size:10px;letter-spacing:4px;color:#C8D0EA;margin-top:2px;">{{ strtoupper(config('sofia.emisor.nombre_comercial')) }}</div>
        </td></tr>
        <tr><td style="padding:28px;">
            {{ $slot }}
        </td></tr>
        <tr><td style="background:#F3F5FB;padding:14px 28px;font-size:11px;color:#6A7DB8;text-align:center;">
            {{ config('sofia.emisor.razon_social') }} · Tel. {{ config('sofia.emisor.telefono') }}<br>Este correo se generó automáticamente desde el sistema LILIM.
        </td></tr>
    </table>
</td></tr></table>
</body></html>
