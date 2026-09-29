# Kontrak API WhatsApp (VPS)

> DITUNDA. Tidak dipakai sampai Fase 10. Selama Fase 0–9, notifikasi memakai `LogTicketNotifier`.

> Isi bagian [ISI] sesuai API di VPS kamu. Kalau bentuk API-mu berbeda dari usulan di bawah, ganti seluruh contoh dengan yang sebenarnya. Claude Code hanya boleh mengikuti yang tertulis di sini.

## Endpoint

| Item | Nilai |
|---|---|
| Base URL | [ISI] (simpan di `.env` sebagai `WA_API_URL`) |
| Autentikasi | [ISI, mis. header `Authorization: Bearer <WA_API_TOKEN>`] |
| Timeout yang wajar dari Laravel | 10 detik |

## Kirim pesan

`POST [ISI path, mis. /send]`

Request (usulan, sesuaikan):
```json
{
  "to": "6281234567890",
  "message": "teks pesan",
  "ref": "PJT26-7K3M9Q"
}
```

Respons sukses (usulan, sesuaikan):
```json
{ "ok": true, "id": "<id pesan di VPS>" }
```

Respons gagal (usulan, sesuaikan):
```json
{ "ok": false, "error": "alasan" }
```

## Perilaku yang diharapkan

- VPS yang mengatur antrean dan jeda antar pesan (anti-banned). Laravel cukup mengirim satu request per pesan dan menganggap "diterima VPS" = `sent`.
- `id` dari VPS disimpan di `notification_logs.provider_message_id`.
- HTTP non-2xx atau `ok: false` = gagal → retry oleh job Laravel (maks 3 percobaan).
- [ISI: apakah VPS punya webhook status terkirim/dibaca? Jika tidak, abaikan.]

## Template pesan

```
Halo {nama},

Pendaftaran Opening Ceremony Porprov Jateng XVII 2026 berhasil.

Kode registrasi: {kode}
Jumlah tiket: {qty} (tukar dengan {qty} gelang)
Acara: {tanggal & jam}, {lokasi}

E-ticket & QR: {link /tiket/{token}}

Tunjukkan QR saat registrasi ulang.
```
