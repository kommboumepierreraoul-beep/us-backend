@php
    $previewText = $preview ?? 'US Nous - votre espace de rencontres universitaires.';
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="supported-color-schemes" content="dark light">
    <title>{{ $subject ?? 'US Nous' }}</title>
</head>
<body style="margin:0;padding:0;background:#0F0B16;color:#ffffff;font-family:Inter,Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $previewText }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0F0B16;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#15101F;border:1px solid rgba(255,255,255,.12);border-radius:24px;overflow:hidden;">
                    <tr>
                        <td style="padding:0;background:linear-gradient(135deg,#D7263D 0%,#EC4899 52%,#6C2BD9 100%);height:8px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="display:inline-block;width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,#D7263D 0%,#EC4899 52%,#6C2BD9 100%);text-align:center;line-height:48px;color:#ffffff;font-weight:900;font-size:20px;font-family:Poppins,Inter,Arial,sans-serif;">US</div>
                                    </td>
                                    <td style="vertical-align:middle;padding-left:14px;">
                                        <div style="font-family:Poppins,Inter,Arial,sans-serif;font-size:20px;font-weight:900;line-height:1;color:#ffffff;">US</div>
                                        <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#B9B4C4;margin-top:4px;">Nous</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 8px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#1B1424;border:1px solid rgba(255,255,255,.10);border-radius:18px;">
                                <tr>
                                    <td style="padding:16px 18px;color:#B9B4C4;font-size:13px;line-height:20px;">
                                        <strong style="color:#ffffff;">Conseil securite</strong><br>
                                        US ne vous demandera jamais votre mot de passe par email. Si vous n'etes pas a l'origine de cette demande, ignorez simplement ce message.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;">
                    <tr>
                        <td align="center" style="padding:18px 20px 0;color:#6B6778;font-size:12px;line-height:18px;">
                            US Nous - Plateforme premium de rencontres universitaires.<br>
                            Ce message est automatique, merci de ne pas y repondre.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
