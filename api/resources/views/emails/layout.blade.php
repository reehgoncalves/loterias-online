<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Loterias Online' }}</title>
    <style>
        body { margin:0; padding:0; background:#f4f1fb; color:#24124d; font-family:Arial,sans-serif; }
        table { border-spacing:0; }
        .wrap { width:100%; padding:28px 12px; }
        .card { width:100%; max-width:560px; margin:0 auto; background:#fff; border-radius:22px; overflow:hidden; box-shadow:0 14px 38px rgba(55,28,118,.12); }
        .logo { font-weight:800; letter-spacing:-.04em; font-size:20px; color:#fff; }
        .hero { padding:30px 28px; background:linear-gradient(135deg,#2d1760,#6833c4 55%,#ef4d9c); color:#fff; }
        .eyebrow { color:#ffd15a; font-size:11px; font-weight:bold; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:14px 0 0; font-size:30px; line-height:1.05; letter-spacing:-.04em; }
        .body { padding:28px; }
        .muted { color:#716a83; line-height:1.55; }
        .highlight { margin:20px 0; padding:19px; border-radius:16px; background:#eefaf1; border:1px solid #d4efd9; text-align:center; }
        .highlight strong { display:block; color:#197443; font-size:31px; }
        .button { display:inline-block; padding:14px 22px; border-radius:12px; background:#f64c9d; color:#fff!important; text-decoration:none; font-weight:bold; }
        .numbers { padding:15px; border-radius:14px; background:#faf8ff; text-align:center; letter-spacing:5px; font-size:19px; font-weight:bold; color:#5c2db8; }
        .footer { padding:0 28px 26px; color:#91899f; font-size:11px; line-height:1.55; text-align:center; }
        .footer a { color:#5c2db8; }
        @media only screen and (max-width:600px) { .wrap { padding:12px 7px; } .hero,.body { padding:23px 20px; } h1 { font-size:26px; } }
    </style>
</head>
<body>
<table role="presentation" width="100%"><tr><td class="wrap">
    <table role="presentation" class="card" width="100%">
        <tr><td class="hero"><div class="logo">✦ Loterias Online</div>@yield('hero')</td></tr>
        <tr><td class="body">@yield('content')</td></tr>
        @if(!empty($unsubscribe_url))
        <tr><td class="footer">Você recebeu este e-mail porque autorizou comunicações da Loterias Online.<br><a href="{{ $unsubscribe_url }}">Cancelar comunicações de marketing</a></td></tr>
        @endif
    </table>
</td></tr></table>
</body>
</html>

