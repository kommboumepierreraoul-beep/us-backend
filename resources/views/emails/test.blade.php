@extends('emails.layouts.us', [
    'subject' => 'Test email Brevo - US',
    'preview' => 'Votre integration Brevo est connectee au backend US.',
])

@section('content')
    <h1 style="margin:0;color:#ffffff;font-family:Poppins,Inter,Arial,sans-serif;font-size:28px;line-height:34px;font-weight:900;">Brevo est connecte</h1>
    <p style="margin:12px 0 0;color:#B9B4C4;font-size:15px;line-height:24px;">
        Ceci est un email de test envoye depuis le backend Laravel US avec le mailer Brevo API.
    </p>

    <div style="margin:24px 0 18px;padding:18px;border-radius:18px;background:#24182F;border:1px solid rgba(255,255,255,.12);">
        <p style="margin:0;color:#ffffff;font-size:15px;line-height:24px;font-weight:800;">Etat</p>
        <p style="margin:6px 0 0;color:#B9B4C4;font-size:14px;line-height:22px;">
            Les emails transactionnels US peuvent maintenant utiliser la meme identite visuelle que l'application.
        </p>
    </div>
@endsection
