@extends('emails.layouts.us', [
    'subject' => 'Code de reinitialisation US',
    'preview' => 'Utilisez ce code pour choisir un nouveau mot de passe.',
])

@section('content')
    <h1 style="margin:0;color:#ffffff;font-family:Poppins,Inter,Arial,sans-serif;font-size:28px;line-height:34px;font-weight:900;">Reinitialisation du mot de passe</h1>
    <p style="margin:12px 0 0;color:#B9B4C4;font-size:15px;line-height:24px;">
        Bonjour {{ $name ?: 'etudiant' }}, utilisez ce code pour choisir un nouveau mot de passe sur US.
    </p>

    <div style="margin:26px 0 22px;padding:22px;border-radius:22px;background:linear-gradient(135deg,#15101F 0%,#2B123F 100%);border:1px solid rgba(212,175,55,.35);text-align:center;">
        <div style="color:#D4AF37;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">Code confidentiel</div>
        <div style="margin-top:10px;color:#ffffff;font-family:Poppins,Inter,Arial,sans-serif;font-size:42px;line-height:48px;font-weight:900;letter-spacing:.18em;">{{ $code }}</div>
    </div>

    <p style="margin:0;color:#B9B4C4;font-size:14px;line-height:22px;">
        Il expire dans <strong style="color:#ffffff;">{{ $ttl }} minutes</strong>. Apres validation, toutes vos sessions actives seront revoquees pour proteger votre compte.
    </p>
@endsection
