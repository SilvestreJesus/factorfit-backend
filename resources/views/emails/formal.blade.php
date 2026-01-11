<div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; border: 1px solid #f0f0f0; font-family: sans-serif;">
    <div class="header" style="background: #111827; padding: 20px; text-align: center;">
        <span style="color: white; font-size: 20px; font-weight: bold; letter-spacing: 2px;">FACTOR FIT</span>
    </div>
    
    <div class="content" style="padding: 30px; background: white;">
        <p style="color: #374151; font-size: 16px; line-height: 1.6;">
            {!! nl2br(e($mensaje)) !!}
        </p>

        {{-- MÉTODO PROFESIONAL: Usar embedData desde el controlador --}}
        @if(!empty($imagen))
            <div style="margin-top: 25px; text-align: center;">
                {{-- Laravel reconoce automáticamente los datos adjuntos --}}
                <img src="{{ $message->embedData(base64_decode(explode(',', $imagen)[1]), 'foto.png') }}" 
                     alt="Factor Fit News" 
                     style="max-width: 100%; height: auto; border-radius: 15px; display: block; margin: 0 auto; border: 1px solid #eee;">
            </div>
        @endif
    </div>

    <div class="footer" style="padding: 20px; background: #f9fafb; text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #eee;">
        © 2026 Factor Fit System | Sede: {{ $sede ?? 'General' }}<br>
        Este es un correo informativo, por favor no respondas a este mensaje.
    </div>
</div>