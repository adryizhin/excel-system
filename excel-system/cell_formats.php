<?php
function columnWidths(array $payload, int $columns): array
{
    $raw = $payload['column_widths'] ?? [];
    if (!is_array($raw)) throw new InvalidArgumentException('Invalid column widths.');
    $widths = [];
    for ($c = 0; $c < $columns; $c++) {
        $width = $raw[$c] ?? 180;
        if (!is_numeric($width) || $width < 80 || $width > 1200) throw new InvalidArgumentException('Column widths must be between 80 and 1200 pixels.');
        $widths[] = (int)round((float)$width);
    }
    return $widths;
}

function cellFormats(array $payload, int $rows, int $columns): array
{
    $raw = $payload['formats'] ?? [];
    if (!is_array($raw)) throw new InvalidArgumentException('Invalid cell formatting.');
    $result = [];
    for ($r = 0; $r < $rows; $r++) {
        $result[$r] = [];
        for ($c = 0; $c < $columns; $c++) {
            $format = $raw[$r][$c] ?? [];
            if (!is_array($format)) throw new InvalidArgumentException('Invalid cell formatting.');
            $clean = [];
            foreach (['bold', 'italic', 'underline', 'strike'] as $key) if (!empty($format[$key])) $clean[$key] = true;
            if (isset($format['align'])) {
                if (!in_array($format['align'], ['left', 'center', 'right'], true)) throw new InvalidArgumentException('Invalid alignment.');
                $clean['align'] = $format['align'];
            }
            if (isset($format['valign'])) {
                if (!in_array($format['valign'], ['top', 'middle', 'bottom'], true)) throw new InvalidArgumentException('Invalid vertical alignment.');
                $clean['valign'] = $format['valign'];
            }
            if (isset($format['fontSize'])) {
                if (!in_array($format['fontSize'], [9, 10, 11, 12, 14, 16, 18, 20, 24], true)) throw new InvalidArgumentException('Invalid font size.');
                $clean['fontSize'] = $format['fontSize'];
            }
            $result[$r][] = $clean;
        }
    }
    return $result;
}
