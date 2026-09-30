<?php

/**
 * Uji war kuota (docs/prompts.md Fase 9, poin 2).
 *
 * Membuktikan aturan 1 CLAUDE.md: kuota dicek DI DALAM lock, sehingga
 * `events.tickets_taken` tidak pernah melebihi `events.quota` walaupun banyak
 * pendaftaran masuk pada saat yang sama.
 *
 * Cara pakai (tiga langkah, dari root proyek):
 *
 *     php scripts/uji-war-kuota.php siapkan
 *     php scripts/uji-war-kuota.php tembak
 *     php scripts/uji-war-kuota.php bersihkan
 *
 * Keamanan data: seluruh uji berjalan di database TERPISAH
 * (`porprov_website_race`) dan skrip menolak jalan kalau `.env.race` menunjuk
 * ke database lain. Database lokal `porprov_website` tidak pernah disentuh.
 *
 * Kenapa proses terpisah, bukan curl ke `php artisan serve`: server bawaan PHP
 * melayani satu request pada satu waktu di Windows (PHP_CLI_SERVER_WORKERS
 * hanya jalan di Unix), jadi "50 request paralel" lewat HTTP justru berubah
 * jadi antrean dan tidak menguji apa pun. Di sini tiap pendaftar adalah proses
 * PHP sendiri yang membangunkan Laravel, menunggu sampai satu titik waktu yang
 * sama, lalu melewatkan request POST /daftar melalui HTTP kernel — middleware,
 * FormRequest, controller, dan RegisterAttendee berjalan apa adanya.
 */

const RACE_DB = 'porprov_website_race';
const RACE_ENV_FILE = '.env.race';

/** Sisa kuota yang dipasang sebelum tembakan. */
const SISA_KUOTA = 10;

/** Jumlah pendaftar yang menembak bersamaan. */
const JUMLAH_PENDAFTAR = 50;

/** Jeda sebelum titik tembak, memberi semua proses waktu untuk boot. */
const JEDA_BOOT_DETIK = 4.0;

$root = dirname(__DIR__);
$perintah = $argv[1] ?? 'bantuan';

switch ($perintah) {
    case 'siapkan':
        exit(siapkan($root));
    case 'tembak':
        exit(tembak($root));
    case 'bersihkan':
        exit(bersihkan($root));
    case '_pendaftar':
        exit(pendaftar($root, (int) ($argv[2] ?? 0), (float) ($argv[3] ?? 0)));
    default:
        bantuan();
        exit(1);
}

function bantuan(): void
{
    echo <<<TEKS

    Uji war kuota — Porprov Jateng XVII 2026

      php scripts/uji-war-kuota.php siapkan     Buat .env.race + database race, migrate:fresh --seed,
                                                pasang sisa kuota SISA_KUOTA tiket.
      php scripts/uji-war-kuota.php tembak      Kirim JUMLAH_PENDAFTAR pendaftaran serentak, lalu periksa.
      php scripts/uji-war-kuota.php bersihkan   Hapus database race dan .env.race.

    Database lokal porprov_website tidak pernah disentuh.


    TEKS;
}

// ---------------------------------------------------------------- siapkan --

function siapkan(string $root): int
{
    judul('Siapkan lingkungan uji');

    $envPath = $root.'/.env';

    if (! is_file($envPath)) {
        return gagal('.env tidak ditemukan. Jalankan dari root proyek dan pastikan .env sudah ada.');
    }

    $racePath = $root.'/'.RACE_ENV_FILE;

    if (is_file($racePath)) {
        baris(RACE_ENV_FILE.' sudah ada, dipakai apa adanya.');
    } else {
        tulisEnvRace($envPath, $racePath);
        baris(RACE_ENV_FILE.' dibuat dari .env (DB_DATABASE='.RACE_DB.').');
    }

    $env = bacaEnv($racePath);

    if (($env['DB_DATABASE'] ?? null) !== RACE_DB) {
        return gagal(RACE_ENV_FILE.' harus memakai DB_DATABASE='.RACE_DB.'. Dibatalkan demi keamanan data lokal.');
    }

    buatDatabase($env);
    baris('Database `'.RACE_DB.'` siap.');

    putenv('APP_ENV=race');
    $_ENV['APP_ENV'] = 'race';
    $_SERVER['APP_ENV'] = 'race';

    baris('Menjalankan migrate:fresh --seed pada database race...');

    $exitCode = 0;
    passthru(escapeshellarg(PHP_BINARY).' artisan migrate:fresh --seed --force', $exitCode);

    if ($exitCode !== 0) {
        return gagal('migrate:fresh --seed gagal (exit code '.$exitCode.').');
    }

    $pdo = koneksi($env);
    resetKuota($pdo);

    baris('Event dibuka, kuota dipasang '.SISA_KUOTA.' tiket (tickets_taken = 0).');
    echo "\nLanjut: php scripts/uji-war-kuota.php tembak\n\n";

    return 0;
}

/**
 * Salin .env menjadi .env.race, dengan nilai yang wajib berbeda ditimpa.
 */
function tulisEnvRace(string $envPath, string $racePath): void
{
    $timpa = [
        'APP_ENV' => 'race',
        'APP_DEBUG' => 'false',
        'DB_DATABASE' => RACE_DB,
        // Verifikasi Turnstile mustahil dilewati proses CLI; dimatikan supaya
        // yang diuji tinggal logika kuotanya.
        'TURNSTILE_ENABLED' => 'false',
        // Job notifikasi cukup masuk tabel jobs, tidak perlu dijalankan.
        'QUEUE_CONNECTION' => 'database',
        'SESSION_DRIVER' => 'database',
        'CACHE_STORE' => 'database',
        'TICKET_NOTIFIER' => 'log',
        'ADMIN_USERNAME' => 'race-admin',
        'ADMIN_PASSWORD' => 'race-password',
    ];

    $baris = [];
    $sudahDitulis = [];

    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $isi) {
        if (preg_match('/^([A-Z0-9_]+)=/', $isi, $cocok) && array_key_exists($cocok[1], $timpa)) {
            $baris[] = $cocok[1].'='.$timpa[$cocok[1]];
            $sudahDitulis[$cocok[1]] = true;

            continue;
        }

        $baris[] = $isi;
    }

    foreach ($timpa as $kunci => $nilai) {
        if (! isset($sudahDitulis[$kunci])) {
            $baris[] = $kunci.'='.$nilai;
        }
    }

    file_put_contents($racePath, implode(PHP_EOL, $baris).PHP_EOL);
}

// ----------------------------------------------------------------- tembak --

function tembak(string $root): int
{
    judul('Tembak '.JUMLAH_PENDAFTAR.' pendaftaran serentak');

    $env = bacaEnvRace($root);

    if ($env === null) {
        return 1;
    }

    $pdo = koneksi($env);
    resetKuota($pdo);

    $event = $pdo->query('SELECT id, quota, tickets_taken FROM events ORDER BY id LIMIT 1')->fetch();
    baris('Sebelum: quota = '.$event['quota'].', tickets_taken = '.$event['tickets_taken'].'.');

    $mulai = microtime(true) + JEDA_BOOT_DETIK;
    $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'porprov-race-'.getmypid();

    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }

    $proses = [];

    for ($i = 1; $i <= JUMLAH_PENDAFTAR; $i++) {
        $keluaran = $tmpDir.DIRECTORY_SEPARATOR.'pendaftar-'.$i.'.json';

        $perintah = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/scripts/uji-war-kuota.php'),
            '_pendaftar',
            (string) $i,
            (string) $mulai,
        ]);

        $handle = proc_open(
            $perintah,
            [1 => ['file', $keluaran, 'w'], 2 => ['file', $keluaran.'.err', 'w']],
            $pipes,
            $root,
            ['APP_ENV' => 'race', 'PATH' => getenv('PATH') ?: '', 'SystemRoot' => getenv('SystemRoot') ?: ''],
        );

        if (! is_resource($handle)) {
            return gagal('Gagal menjalankan proses pendaftar ke-'.$i.'.');
        }

        $proses[$i] = ['handle' => $handle, 'keluaran' => $keluaran];
    }

    baris(JUMLAH_PENDAFTAR.' proses dijalankan, menunggu titik tembak...');

    foreach ($proses as $item) {
        while (proc_get_status($item['handle'])['running']) {
            usleep(50_000);
        }

        proc_close($item['handle']);
    }

    $hasil = ['sukses' => 0, 'kuota' => 0, 'lain' => 0];
    $tiketDiminta = 0;
    $tiketDisetujui = 0;
    $contohPesan = [];

    foreach ($proses as $i => $item) {
        $isi = is_file($item['keluaran']) ? trim(file_get_contents($item['keluaran'])) : '';
        $data = json_decode($isi, true);

        if (! is_array($data)) {
            $hasil['lain']++;
            $contohPesan['tanpa keluaran'] = 'proses ke-'.$i.': '.mb_substr($isi, 0, 200);

            continue;
        }

        $tiketDiminta += $data['qty'] ?? 0;

        if (($data['hasil'] ?? '') === 'sukses') {
            $hasil['sukses']++;
            $tiketDisetujui += $data['qty'];
        } elseif (($data['hasil'] ?? '') === 'kuota') {
            $hasil['kuota']++;
            $contohPesan['kuota'] = $data['pesan'] ?? '';
        } else {
            $hasil['lain']++;
            $contohPesan[$data['hasil'] ?? 'tidak dikenal'] = $data['pesan'] ?? '';
        }
    }

    hapusDirektori($tmpDir);

    $sesudah = $pdo->query('SELECT quota, tickets_taken FROM events ORDER BY id LIMIT 1')->fetch();
    $agregat = $pdo->query('SELECT COUNT(*) AS jumlah, COALESCE(SUM(ticket_qty), 0) AS tiket FROM registrations WHERE cancelled_at IS NULL')->fetch();

    echo "\n";
    baris('Tiket diminta   : '.$tiketDiminta.' (oleh '.JUMLAH_PENDAFTAR.' pendaftar)');
    baris('Pendaftaran OK  : '.$hasil['sukses'].' (= '.$tiketDisetujui.' tiket)');
    baris('Ditolak kuota   : '.$hasil['kuota']);
    baris('Hasil lain      : '.$hasil['lain']);

    foreach ($contohPesan as $jenis => $pesan) {
        if ($pesan !== '') {
            baris('  contoh ['.$jenis.']: '.$pesan);
        }
    }

    echo "\n";
    baris('Sesudah: quota = '.$sesudah['quota'].', tickets_taken = '.$sesudah['tickets_taken'].'.');
    baris('Baris registrations = '.$agregat['jumlah'].', jumlah ticket_qty = '.$agregat['tiket'].'.');

    return periksa($sesudah, $agregat, $tiketDisetujui, $hasil);
}

/**
 * @param  array{quota: int|string, tickets_taken: int|string}  $sesudah
 * @param  array{jumlah: int|string, tiket: int|string}  $agregat
 * @param  array{sukses: int, kuota: int, lain: int}  $hasil
 */
function periksa(array $sesudah, array $agregat, int $tiketDisetujui, array $hasil): int
{
    $quota = (int) $sesudah['quota'];
    $terpakai = (int) $sesudah['tickets_taken'];
    $tiketTersimpan = (int) $agregat['tiket'];

    $pemeriksaan = [
        'tickets_taken ('.$terpakai.') tidak melebihi quota ('.$quota.')' => $terpakai <= $quota,
        'tickets_taken sama dengan jumlah ticket_qty yang tersimpan ('.$tiketTersimpan.')' => $terpakai === $tiketTersimpan,
        'tiket yang tersimpan sama dengan yang dilaporkan sukses ('.$tiketDisetujui.')' => $tiketTersimpan === $tiketDisetujui,
        'ada pendaftaran yang ditolak karena kuota (bukti kuota benar-benar mentok)' => $hasil['kuota'] > 0,
        'tidak ada hasil di luar sukses/kuota' => $hasil['lain'] === 0,
    ];

    echo "\n";
    $lulus = true;

    foreach ($pemeriksaan as $keterangan => $benar) {
        echo ($benar ? '  [OK]    ' : '  [GAGAL] ').$keterangan."\n";
        $lulus = $lulus && $benar;
    }

    echo "\n";

    if (! $lulus) {
        echo "  HASIL: GAGAL — aturan 1 CLAUDE.md tidak terpenuhi.\n\n";

        return 1;
    }

    echo "  HASIL: LULUS — kuota tidak pernah terlampaui.\n";
    echo "  Lanjut: php scripts/uji-war-kuota.php bersihkan\n\n";

    return 0;
}

// -------------------------------------------------------------- pendaftar --

/**
 * Satu proses pendaftar. Boot Laravel, tunggu titik tembak bersama, lalu
 * lewatkan POST /daftar melalui HTTP kernel.
 */
function pendaftar(string $root, int $index, float $mulai): int
{
    require $root.'/vendor/autoload.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require $root.'/bootstrap/app.php';

    // CSRF tidak relevan untuk uji ini: tidak ada browser dan tidak ada sesi
    // sebelumnya, jadi tidak ada token yang bisa dikirim. Hanya rute ini yang
    // dikecualikan; sisa pipeline web (sesi, throttle, FormRequest) tetap utuh.
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::except(['daftar']);

    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

    // Waktu isi minimum (3 detik sejak GET /daftar, dicatat di sesi): proses
    // ini tidak pernah membuka form, jadi cap waktunya diisi langsung. Store
    // sesi memakai atribut yang sudah ada saat middleware memulai sesi.
    $kernel->bootstrap();
    $app['session.store']->put(
        \App\Http\Requests\Public\StoreRegistrationRequest::RENDERED_AT_KEY,
        time() - 60,
    );

    $qty = 1 + ($index % 4);

    $request = \Illuminate\Http\Request::create('/daftar', 'POST', [
        'name' => 'Penguji War '.$index,
        'regency_id' => 1,
        'email_local' => 'perang'.$index,
        'email_domain' => 'gmail.com',
        'phone' => '08' . str_pad((string) (1200000000 + $index), 10, '0', STR_PAD_LEFT),
        'ticket_qty' => $qty,
    ], [], [], [
        // IP berbeda per pendaftar: throttle:20,1 pada POST /daftar dihitung
        // per IP, jadi 50 pendaftar dari satu IP akan tertolak 429 sebelum
        // sempat menyentuh kuota.
        'REMOTE_ADDR' => '10.0.'.intdiv($index, 250).'.'.(($index % 250) + 1),
    ]);

    // Boot-nya tidak serempak; semua proses menunggu titik waktu yang sama.
    while (microtime(true) < $mulai) {
        usleep(1_000);
    }

    try {
        $response = $kernel->handle($request);
    } catch (\Throwable $e) {
        echo json_encode(['hasil' => 'exception', 'qty' => $qty, 'pesan' => get_class($e).': '.$e->getMessage()]);

        return 1;
    }

    echo json_encode(bacaHasil($app, $response, $qty));

    $kernel->terminate($request, $response);

    return 0;
}

/**
 * Terjemahkan respons menjadi hasil ringkas: sukses (redirect ke halaman
 * sukses), kuota (redirect balik dengan error ticket_qty), atau lainnya.
 *
 * @return array<string, mixed>
 */
function bacaHasil(\Illuminate\Foundation\Application $app, \Symfony\Component\HttpFoundation\Response $response, int $qty): array
{
    $status = $response->getStatusCode();

    if ($status === 302 && str_contains((string) $response->headers->get('Location'), '/daftar/sukses/')) {
        return ['hasil' => 'sukses', 'qty' => $qty];
    }

    // Pesan error di-flash ke sesi request ini; dibaca langsung dari store,
    // bukan dari response, karena tidak ada request berikutnya yang membacanya.
    // Sesi proyek ini di-serialize sebagai JSON, jadi ViewErrorBag yang
    // di-flash kembali sebagai array biasa; kedua bentuk ditangani.
    $errors = $app['session.store']->get('errors');

    if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
        $errors = $errors->toArray();
    }

    $pesan = implode(' | ', kumpulkanPesan($errors));

    $adalahKuota = str_contains($pesan, 'Sisa kuota') || str_contains($pesan, 'kuota pendaftaran sudah penuh');

    return [
        'hasil' => $adalahKuota ? 'kuota' : 'status-'.$status,
        'qty' => $qty,
        'pesan' => $pesan,
    ];
}

/**
 * Ambil semua pesan error dari struktur bersarang ViewErrorBag/array, tanpa
 * peduli bentuk persisnya (kunci "format" dan "messages" ikut diabaikan).
 *
 * @return list<string>
 */
function kumpulkanPesan(mixed $simpul): array
{
    if (is_string($simpul)) {
        return [$simpul];
    }

    if (! is_array($simpul)) {
        return [];
    }

    $pesan = [];

    foreach ($simpul as $kunci => $anak) {
        if ($kunci === 'format') {
            continue;
        }

        $pesan = array_merge($pesan, kumpulkanPesan($anak));
    }

    return $pesan;
}

// ------------------------------------------------------------- bersihkan --

function bersihkan(string $root): int
{
    judul('Bersihkan lingkungan uji');

    $env = bacaEnvRace($root);

    if ($env === null) {
        return 1;
    }

    $pdo = koneksiServer($env);
    $pdo->exec('DROP DATABASE IF EXISTS `'.RACE_DB.'`');
    baris('Database `'.RACE_DB.'` dihapus.');

    $racePath = $root.'/'.RACE_ENV_FILE;

    if (is_file($racePath)) {
        unlink($racePath);
        baris(RACE_ENV_FILE.' dihapus.');
    }

    echo "\n";

    return 0;
}

// ---------------------------------------------------------------- bantuan --

/**
 * @return array<string, string>|null
 */
function bacaEnvRace(string $root): ?array
{
    $racePath = $root.'/'.RACE_ENV_FILE;

    if (! is_file($racePath)) {
        gagal(RACE_ENV_FILE.' tidak ditemukan. Jalankan dulu: php scripts/uji-war-kuota.php siapkan');

        return null;
    }

    $env = bacaEnv($racePath);

    if (($env['DB_DATABASE'] ?? null) !== RACE_DB) {
        gagal(RACE_ENV_FILE.' harus memakai DB_DATABASE='.RACE_DB.'. Dibatalkan demi keamanan data lokal.');

        return null;
    }

    return $env;
}

/**
 * @return array<string, string>
 */
function bacaEnv(string $path): array
{
    $env = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
        if (str_starts_with(trim($baris), '#') || ! str_contains($baris, '=')) {
            continue;
        }

        [$kunci, $nilai] = explode('=', $baris, 2);
        $env[trim($kunci)] = trim(trim($nilai), "\"'");
    }

    return $env;
}

/**
 * @param  array<string, string>  $env
 */
function koneksiServer(array $env): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%s', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306'),
        $env['DB_USERNAME'] ?? 'root',
        $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
}

/**
 * @param  array<string, string>  $env
 */
function koneksi(array $env): PDO
{
    // Pagar terakhir: kalaupun fungsi ini dipanggil dari tempat lain, ia hanya
    // pernah menyentuh database race.
    if (($env['DB_DATABASE'] ?? null) !== RACE_DB) {
        throw new RuntimeException('Koneksi uji hanya boleh ke database '.RACE_DB.'.');
    }

    $pdo = koneksiServer($env);
    $pdo->exec('USE `'.RACE_DB.'`');

    return $pdo;
}

/**
 * @param  array<string, string>  $env
 */
function buatDatabase(array $env): void
{
    koneksiServer($env)->exec(
        'CREATE DATABASE IF NOT EXISTS `'.RACE_DB.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}

/**
 * Kosongkan hasil tembakan sebelumnya dan pasang ulang sisa kuota, supaya
 * `tembak` bisa diulang tanpa `siapkan`.
 */
function resetKuota(PDO $pdo): void
{
    $pdo->exec('DELETE FROM scan_logs');
    $pdo->exec('DELETE FROM notification_logs');
    $pdo->exec('DELETE FROM registrations');
    $pdo->exec('DELETE FROM jobs');
    $pdo->exec('UPDATE events SET quota = '.SISA_KUOTA.', tickets_taken = 0, is_open = 1, '
        .'registration_open_at = DATE_SUB(NOW(), INTERVAL 1 HOUR), '
        .'registration_close_at = DATE_ADD(NOW(), INTERVAL 1 DAY)');
}

function hapusDirektori(string $dir): void
{
    foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $berkas) {
        unlink($berkas);
    }

    if (is_dir($dir)) {
        rmdir($dir);
    }
}

function judul(string $teks): void
{
    echo "\n== ".$teks." ==\n\n";
}

function baris(string $teks): void
{
    echo '  '.$teks."\n";
}

function gagal(string $teks): int
{
    echo "\n  GAGAL: ".$teks."\n\n";

    return 1;
}
