<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AttendanceImportParser
{
    public function parse(string $path, Carbon $fromDate, Carbon $toDate): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = [];
        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $rows[] = ['__sheet_boundary__'];
                foreach ($sheet->toArray(null, true, true, false) as $row) {
                    $rows[] = $row;
                }
            }
            $parsed = $this->parseRows($rows, $fromDate, $toDate);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
        if (!$parsed['employees']) {
            throw ValidationException::withMessages([
                'excel_file' => 'No attendance rows were found inside the selected range. Use Date, Day, Name, In, Out, Status or a Bio Manager employee block.',
            ]);
        }
        return $parsed;
    }

    private function parseRows(array $rows, Carbon $fromDate, Carbon $toDate): array
    {
        $employees = [];
        $current = null;
        $columns = null;
        $outside = 0;
        $seen = [];
        foreach ($rows as $row) {
            if (($row[0] ?? null) === '__sheet_boundary__') {
                $current = $columns = null;
                continue;
            }
            if ($header = $this->extractEmployeeHeader($row)) {
                $current = $header;
                continue;
            }
            if ($detected = $this->detectColumns($row)) {
                $columns = $detected;
                continue;
            }
            if (!$columns) {
                continue;
            }
            $date = $this->parseDate($row[$columns['date']] ?? null);
            if (!$date) {
                continue;
            }
            if ($date->lt($fromDate->copy()->startOfDay()) || $date->gt($toDate->copy()->endOfDay())) {
                $outside++;
                continue;
            }
            $identity = $current;
            if (isset($columns['name'])) {
                $name = $this->cleanScalar($row[$columns['name']] ?? null);
                if (!$name) {
                    throw ValidationException::withMessages(['excel_file' => 'Every template attendance row needs an employee name.']);
                }
                $identity = ['paycode' => null, 'name' => $name];
            }
            if (!$identity) {
                continue;
            }
            // Namespaces keep template names distinct from device paycodes.
            $key = $identity['paycode'] !== null
                ? 'paycode-' . hash('sha256', $identity['paycode'])
                : 'name-' . hash('sha256', $this->normalizeName($identity['name']));
            if (!isset($employees[$key])) {
                $employees[$key] = [
                    'paycode' => $identity['paycode'], 'mapping_key' => $key,
                    'file_name' => $identity['name'], 'records' => [],
                ];
            }
            $rawIn = $row[$columns['in']] ?? null;
            $rawOut = $row[$columns['out']] ?? null;
            $record = [
                'attendance_date' => $date->toDateString(),
                'day_name' => $this->cleanScalar($row[$columns['day'] ?? -1] ?? null) ?? $date->format('l'),
                'in_time' => $this->normalizeTime($rawIn), 'out_time' => $this->normalizeTime($rawOut),
                'raw_in' => $this->cleanScalar($rawIn), 'raw_out' => $this->cleanScalar($rawOut),
                'raw_status' => $this->cleanScalar($row[$columns['status']] ?? null),
            ];
            $dayKey = $key . '|' . $record['attendance_date'];
            if (isset($seen[$dayKey])) {
                if ($seen[$dayKey] !== $record) {
                    throw ValidationException::withMessages([
                        'excel_file' => "Conflicting attendance rows for {$identity['name']} on {$record['attendance_date']}. Correct the file before importing.",
                    ]);
                }
                continue;
            }
            $seen[$dayKey] = $record;
            $employees[$key]['records'][] = $record;
        }
        $flat = [];
        foreach ($employees as &$employee) {
            usort($employee['records'], fn ($a, $b) => strcmp($a['attendance_date'], $b['attendance_date']));
            $dates = array_column($employee['records'], 'attendance_date');
            $employee['record_count'] = count($dates);
            $employee['period_from'] = min($dates);
            $employee['period_to'] = max($dates);
            foreach ($employee['records'] as $record) {
                $flat[] = array_merge([
                    'paycode' => $employee['paycode'], 'mapping_key' => $employee['mapping_key'],
                    'file_name' => $employee['file_name'],
                ], $record);
            }
        }
        unset($employee);
        usort($flat, fn ($a, $b) => [$a['attendance_date'], $a['mapping_key']] <=> [$b['attendance_date'], $b['mapping_key']]);
        $dates = array_column($flat, 'attendance_date');
        return [
            'employees' => array_values($employees), 'rows' => $flat,
            'period_from' => $dates ? min($dates) : null, 'period_to' => $dates ? max($dates) : null,
            'outside_range_rows' => $outside,
        ];
    }

    private function extractEmployeeHeader(array $row): ?array
    {
        if (!preg_match('/^paycode\s*:?\s*-?/i', trim((string) ($row[0] ?? '')))) {
            return null;
        }
        $paycode = $this->cleanScalar($row[3] ?? null);
        $name = null;
        foreach ($row as $index => $value) {
            if (preg_match('/^name\s*:\s*-?\s*(.*)$/i', trim((string) $value), $match)) {
                $name = $this->cleanScalar($match[1]);
                for ($next = $index + 1; !$name && $next < count($row); $next++) {
                    $candidate = $this->cleanScalar($row[$next]);
                    if ($candidate && preg_match('/(?:department|designation|paycode)\s*:/i', $candidate)) {
                        break;
                    }
                    $name = $candidate;
                }
                break;
            }
        }
        $name = $name ?? $this->cleanScalar($row[4] ?? null);
        return $paycode !== null ? ['paycode' => $paycode, 'name' => $name ?? 'Unknown Employee'] : null;
    }

    private function detectColumns(array $row): ?array
    {
        $columns = [];
        foreach ($row as $index => $value) {
            $label = strtolower(trim((string) $value));
            if (in_array($label, ['date', 'day', 'name', 'in', 'out', 'status'], true) && !isset($columns[$label])) {
                $columns[$label] = $index;
            }
        }
        foreach (['date', 'in', 'out', 'status'] as $required) {
            if (!isset($columns[$required])) {
                return null;
            }
        }
        return $columns;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }
        if (is_numeric($value) && (float) $value > 0) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }
        $value = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Throwable $e) {
                // Report headings and totals are not attendance dates.
            }
        }
        return null;
    }

    private function normalizeTime($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if (is_numeric($value)) {
            return (float) $value >= 0 && (float) $value < 1
                ? ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s') : null;
        }
        $value = trim((string) $value);
        foreach (['H:i:s', 'H:i', 'h:i A', 'h:i a', 'g:i A', 'g:i a'] as $format) {
            try {
                $time = Carbon::createFromFormat('!' . $format, $value);
                if ($time && $time->format($format) === $value) {
                    return $time->format('H:i:s');
                }
            } catch (\Throwable $e) {
                // Device placeholders (for example 3) are not clock times.
            }
        }
        return null;
    }

    private function cleanScalar($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($name))));
    }
}
