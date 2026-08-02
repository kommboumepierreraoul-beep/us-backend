@extends('emails.layouts.us', [
    'subject' => 'Code de verification US',
    'preview' => 'Votre code de verification US expire bientot.',
])

@section('content')
    <h1 style="margin:0;color:#ffffff;font-family:Poppins,Inter,Arial,sans-serif;font-size:28px;line-height:34px;font-weight:900;">Confirmez votre email</h1>
    <p style="margin:12px 0 0;color:#B9B4C4;font-size:15px;line-height:24px;">
        Bonjour {{ $name ?: 'etudiant' }}, entrez ce code dans US pour securiser votre compte et terminer la verification.
    </p>

    <div style="margin:26px 0 22px;padding:22px;border-radius:22px;background:#24182F;border:1px solid rgba(255,255,255,.12);text-align:center;">
        <div style="color:#B9B4C4;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">Code de verification</div>
        <div style="margin-top:10px;color:#ffffff;font-family:Poppins,Inter,Arial,sans-serif;font-size:42px;line-height:48px;font-weight:900;letter-spacing:.18em;">{{ $code }}</div>
    </div>

    <p style="margin:0;color:#B9B4C4;font-size:14px;line-height:22px;">
        Ce code expire dans <strong style="color:#ffffff;">{{ $ttl }} minutes</strong>. Gardez-le pour vous, meme si quelqu'un pretend faire partie de l'equipe US.
    </p>
@endsection
