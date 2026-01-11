<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif; border: 1px solid #e5e7eb; border-radius: 20px; overflow: hidden;">
    <div style="background: #1e1b4b; padding: 30px; text-align: center;">
        <h1 style="color: #ddd6fe; margin: 0;">FACTOR FIT</h1>
    </div>
    <div style="padding: 40px; background: white; color: #374151;">
        <h2 style="color: #1e1b4b;">Hola, {{ $nombres }}</h2>
        <p>{{ $mensaje }}</p>
        <div style="background: #f3f4f6; padding: 20px; border-radius: 10px; text-align: center; margin: 25px 0;">
            <span style="display: block; font-size: 12px; color: #9ca3af; text-transform: uppercase; font-weight: bold; margin-bottom: 5px;">Contraseña Temporal</span>
            <span style="font-size: 24px; font-weight: bold; color: #7c3aed; letter-spacing: 2px;">{{ $password }}</span>
        </div>
        <p style="font-size: 14px; color: #6b7280;">Te recomendamos iniciar sesión y cambiar esta contraseña desde tu perfil inmediatamente por motivos de seguridad.</p>
        <a href="https://tudominio.com/auth/login" style="display: inline-block; background: #7c3aed; color: white; padding: 15px 30px; text-decoration: none; border-radius: 12px; font-weight: bold; margin-top: 20px;">Ir al Login</a>
    </div>
</div>