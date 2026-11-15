<?php

namespace App\Support;

/**
 * Hutengeneza sahihi ya mfano (PNG yenye uwazi) kutoka kwa jina,
 * bila kuhitaji GD: mistari ya Catmull-Rom inachorwa kwenye buffer
 * ya alpha kisha inaandikwa kama PNG kwa zlib.
 *
 * Kwa data ya majaribio TU — si sahihi halisi ya mtu.
 */
class SignatureGenerator
{
    private int $w = 360;
    private int $h = 110;
    private array $alpha = [];

    /** @return string PNG binary */
    public function make(string $first, string $last, ?int $seed = null): string
    {
        mt_srand($seed ?? crc32($first . '|' . $last));
        $this->alpha = array_fill(0, $this->w * $this->h, 0.0);

        $baseline = mt_rand(62, 72);
        $slant = mt_rand(10, 28) / 100;   // mwinamo wa mwandiko
        $pen = mt_rand(12, 16) / 10;      // unene wa kalamu
        $x = mt_rand(10, 22);

        // Herufi kubwa ya jina la kwanza + mawimbi ya herufi ndogo.
        $x = $this->word($x, $baseline, $first, $slant, $pen, mt_rand(0, 1) === 1);

        // Wengine huandika jina la pili lote, wengine herufi ya kwanza tu.
        $x += mt_rand(10, 22);
        $short = mt_rand(1, 100) <= 35;
        $endX = $this->word($x, $baseline + mt_rand(-4, 4), $short ? mb_substr($last, 0, 1) : $last, $slant, $pen, true);

        // Mstari wa mwisho (flourish) chini ya sahihi.
        if (mt_rand(1, 100) <= 70) {
            $y = $baseline + mt_rand(8, 16);
            $this->stroke([
                [$endX - 4, $baseline - 6],
                [$endX + 6, $y - 2],
                [($endX + 20) / 2, $y + mt_rand(-3, 3)],
                [max(14, 20 + mt_rand(0, 30)), $y + mt_rand(0, 6)],
            ], $pen * 0.8);
        }

        $ink = [[15, 23, 42], [30, 41, 59], [29, 78, 216], [30, 58, 138]][mt_rand(0, 3)];

        return $this->png($ink);
    }

    /** Neno moja: herufi kubwa yenye kitanzi, kisha "humps" za herufi ndogo. */
    private function word(float $x, float $base, string $text, float $slant, float $pen, bool $loopCap): float
    {
        $tall = mt_rand(36, 50);
        $pts = [];

        // Herufi kubwa: panda juu, rudi chini (na kitanzi wakati mwingine).
        $pts[] = [$x, $base];
        $pts[] = [$x + 6, $base - $tall * 0.55];
        $pts[] = [$x + 12, $base - $tall];
        if ($loopCap) {
            $pts[] = [$x + 2, $base - $tall * 0.75];
            $pts[] = [$x + 10, $base - $tall * 0.4];
        }
        $pts[] = [$x + 14, $base];
        $x += 16;

        // Herufi ndogo: kila herufi ni wimbi lenye urefu tofauti.
        $letters = min(mb_strlen($text) - 1, 7);
        for ($i = 0; $i < $letters; $i++) {
            $width = mt_rand(11, 17);
            $up = mt_rand(1, 100) <= 18 ? mt_rand(24, 34) : mt_rand(8, 14); // mara nyingine herufi ndefu (l, h, k)
            $pts[] = [$x + $width * 0.45, $base - $up];
            $pts[] = [$x + $width, $base - mt_rand(0, 3)];
            $x += $width;
        }

        $pts[] = [$x + 6, $base - mt_rand(6, 14)];

        // Mwinamo: sehemu za juu zisogee kulia.
        $pts = array_map(fn ($p) => [$p[0] + ($base - $p[1]) * $slant, $p[1]], $pts);

        $this->stroke($pts, $pen);

        return $x + 8;
    }

    /** Chora mstari laini kupitia pointi (Catmull-Rom). */
    private function stroke(array $pts, float $pen): void
    {
        $n = count($pts);
        if ($n < 2) {
            return;
        }

        array_unshift($pts, $pts[0]);
        $pts[] = $pts[$n];

        for ($i = 1; $i < $n; $i++) {
            [$p0, $p1, $p2, $p3] = [$pts[$i - 1], $pts[$i], $pts[$i + 1], $pts[$i + 2]];
            $len = hypot($p2[0] - $p1[0], $p2[1] - $p1[1]);
            $steps = max(4, (int) ($len * 2));

            for ($s = 0; $s <= $steps; $s++) {
                $t = $s / $steps;
                $t2 = $t * $t;
                $t3 = $t2 * $t;
                $px = 0.5 * ((2 * $p1[0]) + (-$p0[0] + $p2[0]) * $t + (2 * $p0[0] - 5 * $p1[0] + 4 * $p2[0] - $p3[0]) * $t2 + (-$p0[0] + 3 * $p1[0] - 3 * $p2[0] + $p3[0]) * $t3);
                $py = 0.5 * ((2 * $p1[1]) + (-$p0[1] + $p2[1]) * $t + (2 * $p0[1] - 5 * $p1[1] + 4 * $p2[1] - $p3[1]) * $t2 + (-$p0[1] + 3 * $p1[1] - 3 * $p2[1] + $p3[1]) * $t3);

                // Kalamu nyembamba mwanzo na mwisho wa mstari.
                $taper = min(1, ($i - 1 + $t) * 1.5, ($n - $i - $t + 1) * 1.5);
                $this->dot($px, $py, $pen * (0.55 + 0.45 * max(0, $taper)));
            }
        }
    }

    private function dot(float $cx, float $cy, float $r): void
    {
        $x0 = max(0, (int) floor($cx - $r - 1));
        $x1 = min($this->w - 1, (int) ceil($cx + $r + 1));
        $y0 = max(0, (int) floor($cy - $r - 1));
        $y1 = min($this->h - 1, (int) ceil($cy + $r + 1));

        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $a = $r - hypot($x + 0.5 - $cx, $y + 0.5 - $cy) + 0.5; // anti-aliasing
                if ($a > 0) {
                    $k = $y * $this->w + $x;
                    $this->alpha[$k] = max($this->alpha[$k], min(1.0, $a));
                }
            }
        }
    }

    private function png(array $rgb): string
    {
        // Kata nafasi tupu: baki na eneo lenye wino tu (+ ukingo mdogo).
        [$minX, $minY, $maxX, $maxY] = [$this->w, $this->h, 0, 0];
        foreach ($this->alpha as $k => $a) {
            if ($a > 0.02) {
                $x = $k % $this->w;
                $y = intdiv($k, $this->w);
                [$minX, $minY, $maxX, $maxY] = [min($minX, $x), min($minY, $y), max($maxX, $x), max($maxY, $y)];
            }
        }
        $minX = max(0, $minX - 4);
        $minY = max(0, $minY - 4);
        $maxX = min($this->w - 1, $maxX + 4);
        $maxY = min($this->h - 1, $maxY + 4);
        $cw = max(1, $maxX - $minX + 1);
        $ch = max(1, $maxY - $minY + 1);

        $raw = '';
        for ($y = $minY; $y < $minY + $ch; $y++) {
            $raw .= "\0";
            for ($x = $minX; $x < $minX + $cw; $x++) {
                $raw .= chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]) . chr((int) round($this->alpha[$y * $this->w + $x] * 255));
            }
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $cw, $ch, 8, 6, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw, 9))
            . $chunk('IEND', '');
    }
}
