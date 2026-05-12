<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class LocalizedDate
{
    public static function dateTime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return __('ui.common.not_available');
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        if (app()->getLocale() !== 'fa') {
            return $date->format('Y-m-d H:i');
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        return sprintf('%04d/%02d/%02d %s', $year, $month, $day, $date->format('H:i'));
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        $gy -= 1600;
        $gm -= 1;
        $gd -= 1;

        $gDayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);

        for ($i = 0; $i < $gm; $i++) {
            $gDayNo += $gDaysInMonth[$i];
        }

        if ($gm > 1 && (($gy + 1600) % 4 === 0 && (($gy + 1600) % 100 !== 0 || ($gy + 1600) % 400 === 0))) {
            $gDayNo++;
        }

        $gDayNo += $gd;
        $jDayNo = $gDayNo - 79;
        $jNp = intdiv($jDayNo, 12053);
        $jDayNo %= 12053;
        $jy = 979 + 33 * $jNp + 4 * intdiv($jDayNo, 1461);
        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jy += intdiv($jDayNo - 1, 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }

        for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; $i++) {
            $jDayNo -= $jDaysInMonth[$i];
        }

        return [$jy, $i + 1, $jDayNo + 1];
    }
}
