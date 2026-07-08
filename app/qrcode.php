<?php

declare(strict_types=1);

function qr_svg(string $text, int $scale = 8, int $quietZone = 4): string
{
    $matrix = qr_matrix($text);
    $size = count($matrix);
    $outer = ($size + ($quietZone * 2)) * $scale;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $outer . '" height="' . $outer . '" viewBox="0 0 ' . $outer . ' ' . $outer . '" role="img" aria-label="QR code">';
    $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
    foreach ($matrix as $row => $cols) {
        foreach ($cols as $col => $dark) {
            if ($dark) {
                $x = ($col + $quietZone) * $scale;
                $y = ($row + $quietZone) * $scale;
                $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $scale . '" height="' . $scale . '" fill="#000"/>';
            }
        }
    }
    return $svg . '</svg>';
}

function qr_matrix(string $text): array
{
    $version = 3;
    $size = 17 + ($version * 4);
    $dataCodewords = 55;
    $eccCodewords = 15;
    $bytes = array_values(unpack('C*', $text));
    if (count($bytes) > 53) {
        throw new RuntimeException('QR content is too long for the built-in encoder.');
    }

    $bits = [0, 1, 0, 0];
    foreach (qr_bits(count($bytes), 8) as $bit) {
        $bits[] = $bit;
    }
    foreach ($bytes as $byte) {
        foreach (qr_bits($byte, 8) as $bit) {
            $bits[] = $bit;
        }
    }
    $capacity = $dataCodewords * 8;
    for ($i = 0; $i < min(4, $capacity - count($bits)); $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }

    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $value = 0;
        for ($j = 0; $j < 8; $j++) {
            $value = ($value << 1) | $bits[$i + $j];
        }
        $data[] = $value;
    }
    for ($pad = 0; count($data) < $dataCodewords; $pad++) {
        $data[] = ($pad % 2 === 0) ? 0xEC : 0x11;
    }

    $codewords = array_merge($data, qr_reed_solomon($data, $eccCodewords));
    $modules = array_fill(0, $size, array_fill(0, $size, false));
    $function = array_fill(0, $size, array_fill(0, $size, false));

    qr_finder($modules, $function, 0, 0);
    qr_finder($modules, $function, $size - 7, 0);
    qr_finder($modules, $function, 0, $size - 7);
    qr_alignment($modules, $function, 22, 22);
    qr_timing($modules, $function);
    qr_set($modules, $function, 4 * $version + 9, 8, true, true);
    qr_format($modules, $function, 1, 0);
    qr_data($modules, $function, $codewords);
    qr_apply_mask($modules, $function, 0);
    qr_format($modules, $function, 1, 0);

    return $modules;
}

function qr_bits(int $value, int $length): array
{
    $bits = [];
    for ($i = $length - 1; $i >= 0; $i--) {
        $bits[] = ($value >> $i) & 1;
    }
    return $bits;
}

function qr_set(array &$modules, array &$function, int $row, int $col, bool $dark, bool $isFunction): void
{
    if ($row < 0 || $col < 0 || $row >= count($modules) || $col >= count($modules)) {
        return;
    }
    $modules[$row][$col] = $dark;
    if ($isFunction) {
        $function[$row][$col] = true;
    }
}

function qr_finder(array &$modules, array &$function, int $left, int $top): void
{
    for ($dy = -1; $dy <= 7; $dy++) {
        for ($dx = -1; $dx <= 7; $dx++) {
            $row = $top + $dy;
            $col = $left + $dx;
            $dark = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
            qr_set($modules, $function, $row, $col, $dark, true);
        }
    }
}

function qr_alignment(array &$modules, array &$function, int $centerRow, int $centerCol): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dark = max(abs($dx), abs($dy)) !== 1;
            qr_set($modules, $function, $centerRow + $dy, $centerCol + $dx, $dark, true);
        }
    }
}

function qr_timing(array &$modules, array &$function): void
{
    $size = count($modules);
    for ($i = 8; $i < $size - 8; $i++) {
        $dark = $i % 2 === 0;
        if (!$function[6][$i]) {
            qr_set($modules, $function, 6, $i, $dark, true);
        }
        if (!$function[$i][6]) {
            qr_set($modules, $function, $i, 6, $dark, true);
        }
    }
}

function qr_format(array &$modules, array &$function, int $eccLevel, int $mask): void
{
    $size = count($modules);
    $data = ($eccLevel << 3) | $mask;
    $rem = $data << 10;
    for ($i = 14; $i >= 10; $i--) {
        if ((($rem >> $i) & 1) !== 0) {
            $rem ^= 0x537 << ($i - 10);
        }
    }
    $bits = (($data << 10) | $rem) ^ 0x5412;

    for ($i = 0; $i <= 5; $i++) {
        qr_set($modules, $function, 8, $i, (($bits >> $i) & 1) !== 0, true);
    }
    qr_set($modules, $function, 8, 7, (($bits >> 6) & 1) !== 0, true);
    qr_set($modules, $function, 8, 8, (($bits >> 7) & 1) !== 0, true);
    qr_set($modules, $function, 7, 8, (($bits >> 8) & 1) !== 0, true);
    for ($i = 9; $i < 15; $i++) {
        qr_set($modules, $function, 14 - $i, 8, (($bits >> $i) & 1) !== 0, true);
    }
    for ($i = 0; $i < 8; $i++) {
        qr_set($modules, $function, $size - 1 - $i, 8, (($bits >> $i) & 1) !== 0, true);
    }
    for ($i = 8; $i < 15; $i++) {
        qr_set($modules, $function, 8, $size - 15 + $i, (($bits >> $i) & 1) !== 0, true);
    }
}

function qr_data(array &$modules, array &$function, array $codewords): void
{
    $bits = [];
    foreach ($codewords as $byte) {
        foreach (qr_bits($byte, 8) as $bit) {
            $bits[] = $bit;
        }
    }

    $size = count($modules);
    $index = 0;
    $upward = true;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right--;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            $row = $upward ? $size - 1 - $vert : $vert;
            for ($j = 0; $j < 2; $j++) {
                $col = $right - $j;
                if (!$function[$row][$col] && $index < count($bits)) {
                    $modules[$row][$col] = $bits[$index] === 1;
                    $index++;
                }
            }
        }
        $upward = !$upward;
    }
}

function qr_apply_mask(array &$modules, array $function, int $mask): void
{
    $size = count($modules);
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if (!$function[$row][$col] && (($row + $col) % 2 === 0)) {
                $modules[$row][$col] = !$modules[$row][$col];
            }
        }
    }
}

function qr_reed_solomon(array $data, int $degree): array
{
    $generator = [1];
    for ($i = 0; $i < $degree; $i++) {
        $generator = qr_poly_multiply($generator, [1, qr_gf_pow(2, $i)]);
    }

    $result = array_fill(0, $degree, 0);
    foreach ($data as $byte) {
        $factor = $byte ^ $result[0];
        array_shift($result);
        $result[] = 0;
        for ($i = 0; $i < $degree; $i++) {
            $result[$i] ^= qr_gf_multiply($generator[$i + 1], $factor);
        }
    }
    return $result;
}

function qr_poly_multiply(array $a, array $b): array
{
    $result = array_fill(0, count($a) + count($b) - 1, 0);
    foreach ($a as $i => $av) {
        foreach ($b as $j => $bv) {
            $result[$i + $j] ^= qr_gf_multiply($av, $bv);
        }
    }
    return $result;
}

function qr_gf_pow(int $value, int $power): int
{
    $result = 1;
    for ($i = 0; $i < $power; $i++) {
        $result = qr_gf_multiply($result, $value);
    }
    return $result;
}

function qr_gf_multiply(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = (($z << 1) ^ ((($z >> 7) & 1) * 0x11D)) & 0xFF;
        if ((($y >> $i) & 1) !== 0) {
            $z ^= $x;
        }
    }
    return $z;
}
