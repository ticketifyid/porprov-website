<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-ticket {{ $code }}</title>
</head>
<body style="margin:0; padding:0; background-color:#EEF3FB; font-family:'Plus Jakarta Sans', Arial, Helvetica, sans-serif; color:#0B1B3F;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF3FB;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0E2A6B; padding:24px; color:#FFFFFF;">
                            <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#D5E0F8;">E-ticket</div>
                            <div style="font-size:20px; font-weight:bold; line-height:1.3; margin-top:4px;">Opening Ceremony Porprov Jateng XVII 2026</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 16px;">Halo {{ $name }},</p>
                            <p style="margin:0 0 20px;">Pendaftaran Anda berhasil. Berikut e-ticket Anda.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #D6DEEC; border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 16px; border-bottom:1px solid #D6DEEC;">
                                        <div style="font-size:12px; color:#4B5A7A;">Kode registrasi</div>
                                        <div style="font-size:18px; font-weight:bold; color:#0E2A6B; letter-spacing:1px;">{{ $code }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;{{ ($eventStartsAt || $venue) ? ' border-bottom:1px solid #D6DEEC;' : '' }}">
                                        <div style="font-size:12px; color:#4B5A7A;">Jumlah tiket</div>
                                        <div style="font-weight:bold;">{{ $ticketQty }} tiket, tukar dengan {{ $ticketQty }} gelang</div>
                                    </td>
                                </tr>
                                @if ($eventStartsAt)
                                    <tr>
                                        <td style="padding:12px 16px;{{ $venue ? ' border-bottom:1px solid #D6DEEC;' : '' }}">
                                            <div style="font-size:12px; color:#4B5A7A;">Tanggal</div>
                                            <div>{{ $eventStartsAt }} WIB</div>
                                        </td>
                                    </tr>
                                @endif
                                @if ($venue)
                                    <tr>
                                        <td style="padding:12px 16px;">
                                            <div style="font-size:12px; color:#4B5A7A;">Lokasi</div>
                                            <div>{{ $venue }}</div>
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background-color:#0E2A6B; border-radius:8px;">
                                        <a href="{{ $ticketUrl }}" style="display:inline-block; padding:14px 24px; color:#FFFFFF; font-weight:bold; text-decoration:none;">Lihat e-ticket</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;">Tunjukkan QR pada e-ticket kepada petugas saat registrasi ulang untuk ditukar dengan gelang. Satu QR berlaku untuk semua gelang Anda sekaligus.</p>
                            <p style="margin:0; font-size:13px; color:#4B5A7A;">Jika tombol tidak bisa dibuka, salin link berikut ke browser Anda:<br>
                                <a href="{{ $ticketUrl }}" style="color:#0E2A6B; word-break:break-all;">{{ $ticketUrl }}</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; background-color:#EEF3FB; font-size:12px; color:#4B5A7A;">
                            Jangan bagikan link e-ticket ini kepada orang lain.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
