<?php
// Shared validation for saved templates and exported workbooks.
function columnTypes(array $payload, int $count): array
{
    $types = $payload['column_types'] ?? [];
    if (!is_array($types)) throw new InvalidArgumentException('Invalid column types.');
    $result = [];
    for ($c = 0; $c < $count; $c++) {
        $type = $types[$c] ?? 'text';
        if (!in_array($type, ['text', 'gender', 'dropdown', 'date', 'number'], true)) {
            throw new InvalidArgumentException('Invalid column type.');
        }
        $result[] = $type;
    }
    return $result;
}

function validateTypedRows(array $rows, array $types, array $options = []): void
{
    foreach ($rows as $r => $row) {
        foreach ($types as $c => $type) {
            $value = is_array($row) ? ($row[$c] ?? '') : '';
            if (!is_scalar($value)) $value = '';
            $value = (string)$value;
            if ($value === '' || $type === 'text') continue;
            $valid = false;
            if ($type === 'dropdown') $valid = in_array($value, $options[$c] ?? [], true);
            if ($type === 'gender') $valid = in_array($value, ['Male', 'Female'], true);
            if ($type === 'number') $valid = is_numeric($value) && is_finite((float)$value);
            if ($type === 'date') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $valid = $date && $date->format('Y-m-d') === $value && $value >= '1900-01-01' && $value <= '9999-12-31';
            }
            if (!$valid) throw new InvalidArgumentException('Row ' . ($r + 1) . ', column ' . ($c + 1) . ': enter a valid ' . $type . ' value.');
        }
    }
}

function columnOptions(array $payload, array $types): array
{
    $raw = $payload['column_options'] ?? [];
    if (!is_array($raw)) throw new InvalidArgumentException('Invalid selection choices.');
    $result = [];
    foreach ($types as $c => $type) {
        $choices = $raw[$c] ?? [];
        if (!is_array($choices)) throw new InvalidArgumentException('Invalid selection choices.');
        $clean = [];
        foreach ($choices as $choice) {
            if (!is_string($choice) || mb_strlen($choice, 'UTF-8') > 100) throw new InvalidArgumentException('Each selection choice must be text of up to 100 characters.');
            $choice = trim($choice);
            if ($choice !== '' && !in_array($choice, $clean, true)) $clean[] = $choice;
        }
        if (count($clean) > 100 || ($type === 'dropdown' && !$clean)) throw new InvalidArgumentException('Custom selection columns need 1 to 100 choices.');
        $result[] = $clean;
    }
    return $result;
}
