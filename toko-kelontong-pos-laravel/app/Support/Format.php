<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Format tampilan (setara lib/utils.ts). Dipakai di Blade: {{ \App\Support\Format::rupiah($x) }} atau alias `Format`. */
class Format
{
    public const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public static function rupiah(int|float|null $n): string
    {
        return 'Rp'.number_format((float) ($n ?? 0), 0, ',', '.');
    }

    public static function angka(int|float|null $n): string
    {
        return number_format((float) ($n ?? 0), 0, ',', '.');
    }

    /** "2026-11-08" → "8 Nov 2026" (opsional dengan nama hari). */
    public static function tanggal(CarbonInterface|string|null $t, bool $denganHari = false): string
    {
        if (! $t) {
            return '-';
        }
        $d = $t instanceof CarbonInterface ? $t : Carbon::parse($t);

        return ($denganHari ? self::HARI[$d->dayOfWeek].', ' : '').$d->day.' '.self::BULAN[$d->month - 1].' '.$d->year;
    }

    /** "2026-11-08 14:05:00" → "8 Nov 2026 14.05". */
    public static function waktu(CarbonInterface|string|null $t): string
    {
        if (! $t) {
            return '-';
        }
        $d = $t instanceof CarbonInterface ? $t : Carbon::parse($t);

        return self::tanggal($d).' '.$d->format('H.i');
    }

    /** Normalisasi nomor HP Indonesia ke format wa.me (62xxxx). */
    public static function nomorWa(?string $no): string
    {
        $d = preg_replace('/\D/', '', (string) $no);
        if (str_starts_with($d, '0')) {
            $d = '62'.substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62'.$d;
        }

        return $d;
    }
}
