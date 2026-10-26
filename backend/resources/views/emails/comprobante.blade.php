<x-mail-marco>
    <h2 style="margin:0 0 12px;color:#0D1638;font-family:Georgia,serif;">Hola, {{ $cliente }}</h2>
    <p style="font-size:14px;line-height:1.6;">Te enviamos tu <strong>{{ $tipo }} {{ $numero }}</strong> por <strong>Bs {{ $monto }}</strong>. Encontrarás el documento en PDF adjunto a este correo.</p>
    <p style="font-size:13px;line-height:1.6;color:#555;">Si algo no coincide con lo que pagaste, responde a este correo o llámanos al {{ config('sofia.emisor.telefono') }}.</p>
</x-mail-marco>
