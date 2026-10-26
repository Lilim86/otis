<x-mail-marco>
    <h2 style="margin:0 0 12px;color:#0D1638;font-family:Georgia,serif;">{{ $titulo }}</h2>
    @foreach($lineas as $linea)
        <p style="font-size:14px;line-height:1.6;margin:0 0 10px;">{{ $linea }}</p>
    @endforeach
    @if($enlace)
        <p style="margin:22px 0 0;text-align:center;"><a href="{{ $enlace }}" style="background:#C99A2E;color:#080E26;text-decoration:none;font-weight:bold;font-size:12px;letter-spacing:2px;padding:12px 22px;border-radius:6px;display:inline-block;">{{ strtoupper($textoEnlace ?? 'Abrir mi portal') }}</a></p>
    @endif
</x-mail-marco>
