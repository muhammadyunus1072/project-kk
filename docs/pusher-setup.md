# Panduan Konfigurasi Pusher / WebSocket Server (Self-Hosted Soketi / Laravel WebSockets)

Dokumen ini menjelaskan langkah demi langkah cara mengonfigurasi koneksi WebSocket berbasis protokol Pusher (seperti Soketi atau Laravel WebSockets yang berjalan di domain Anda sendiri, misal `app.exata.site:8080`).

---

## 1. Pemahaman Arsitektur

Pada proyek Anda (`gensen`), Anda menggunakan server WebSocket kompatibel Pusher mandiri (self-hosted) di host `app.exata.site` pada port `8080` dengan protokol `http`.

Format kredensial tersebut kompatibel 100% dengan paket PHP `pusher/pusher-php-server` dan client JavaScript `pusher-js` / `laravel-echo`.

---

## 2. Pengaturan `.env` di Backend Laravel (`kartu-keluarga-api`)

Salin konfigurasi berikut ke berkas `c:\dev\projects\kartu-keluarga-api\.env`:

```env
BROADCAST_CONNECTION=pusher

# Kredensial Pusher Server Anda
PUSHER_APP_ID=105232
PUSHER_APP_KEY=osvni4afqglvfe
PUSHER_APP_SECRET=lbwmwgat7

# Host dan Port Self-Hosted (Soketi / WebSockets)
PUSHER_HOST=app.exata.site
PUSHER_PORT=8080
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1

# Client Frontend (Vite)
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
```

> **Catatan Keamanan**: 
> - `PUSHER_APP_SECRET` hanya ada di `.env` server Laravel (tidak boleh diawali dengan `VITE_`).
> - Jika di masa mendatang domain WebSocket Anda menggunakan SSL/HTTPS (misalnya `https://ws.exata.site` pada port 443), ubah `PUSHER_SCHEME=https` dan `PUSHER_PORT=443`.

---

## 3. Konfigurasi `config/broadcasting.php` di Laravel

Pastikan koneksi `pusher` di `config/broadcasting.php` mendukung opsi kustom `host`, `port`, dan `scheme` secara dinamis:

```php
'pusher' => [
    'driver' => 'pusher',
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        'host' => env('PUSHER_HOST', 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com'),
        'port' => (int) env('PUSHER_PORT', 443),
        'scheme' => env('PUSHER_SCHEME', 'https'),
        'encrypted' => env('PUSHER_SCHEME', 'https') === 'https',
        'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
    ],
],
```

---

## 4. Konfigurasi Client Frontend (`laravel-echo` + `pusher-js`)

Di sisi React SPA (`kartu-keluarga/src/services/echo.ts`), `Echo` dikonfigurasi agar otomatis mendeteksi apakah menggunakan cloud pusher resmi atau host mandiri (`app.exata.site`):

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

export function getEcho(config?: EchoConfig): Echo<any> | null {
  const key = config?.appKey || import.meta.env.VITE_PUSHER_APP_KEY || '';
  const host = import.meta.env.VITE_PUSHER_HOST || '';
  const port = import.meta.env.VITE_PUSHER_PORT || '8080';
  const scheme = import.meta.env.VITE_PUSHER_SCHEME || 'http';
  const cluster = config?.cluster || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
  const apiBase = config?.apiBaseUrl || import.meta.env.VITE_LARAVEL_API_URL || '';
  const token = config?.token || localStorage.getItem('kk_sanctum_token') || '';

  if (!key) return null;

  return new Echo({
    broadcaster: 'pusher',
    key,
    cluster,
    wsHost: host || undefined,
    wsPort: host ? Number(port) : undefined,
    wssPort: host && scheme === 'https' ? Number(port) : undefined,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiBase}/api/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: token ? `Bearer ${token}` : '',
        Accept: 'application/json',
      },
    },
  });
}
```

---

## 5. Cara Pengujian (Troubleshooting)

1. **Uji Koneksi Backend**:
   Jalankan tinker untuk menguji broadcast:
   ```bash
   php artisan tinker
   >>> broadcast(new App\Events\KkExtractionProgressEvent(1, 'batch_test', 'sub_test', 'kk.jpg', 'processing'))
   ```
   Jika tidak ada exception cURL, berarti Laravel berhasil mengirim event ke `http://app.exata.site:8080`.

2. **Uji di Browser**:
   Buka DevTools Browser (`F12`) -> Tab **Network** -> Filter **WS** (WebSockets).
   Periksa apakah koneksi ke `ws://app.exata.site:8080/app/osvni4afqglvfe?...` berstatus `101 Switching Protocols` (Connected).
