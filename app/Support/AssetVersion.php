<?php

namespace App\Support;

/**
 * Cache busting untuk aset statis milik proyek, tanpa build tool.
 * URL-nya asset('css/app.css') ditambah '?v={filemtime}', jadi browser dan
 * cache shared hosting mengambil ulang file begitu isinya berubah.
 *
 * Hanya untuk aset yang kita tulis sendiri (public/css, public/js, public/img).
 * public/metronic/ tidak pernah diubah, jadi biarkan memakai asset() biasa.
 */
class AssetVersion
{
    /**
     * filemtime per path, supaya satu request tidak menyentuh disk berulang.
     *
     * @var array<string, int|null>
     */
    private static array $cache = [];

    public static function url(string $path): string
    {
        $version = self::$cache[$path] ??= self::modifiedAt($path);

        return $version === null ? asset($path) : asset($path).'?v='.$version;
    }

    /**
     * Null jika file tidak ada (mis. aset belum ter-upload): URL tetap
     * dihasilkan tanpa query string, tidak melempar exception.
     */
    private static function modifiedAt(string $path): ?int
    {
        $file = public_path($path);

        if (! is_file($file)) {
            return null;
        }

        return filemtime($file) ?: null;
    }

    /**
     * Dipakai di tes; cache statis tidak ikut ter-reset antar tes.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
