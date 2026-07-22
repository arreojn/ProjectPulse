<?php

declare(strict_types=1);

require_once __DIR__ . '/attendance_settings.php';

function attendance_report_apply_summary(array $row, ?string $fallbackDate = null): array
{
    $attendanceDate = (string) ($row['attendance_date'] ?? '');
    if ($attendanceDate === '' && $fallbackDate !== null && $fallbackDate <= date('Y-m-d')) {
        $attendanceDate = $fallbackDate;
    }

    $summary = attendance_record_summary(
        $attendanceDate !== '' ? $attendanceDate : null,
        (string) ($row['attendance_code'] ?? ''),
        $row['am_time_in'] ?? null,
        $row['am_time_out'] ?? null,
        $row['pm_time_in'] ?? null,
        $row['pm_time_out'] ?? null
    );

    $row['attendance_code'] = $summary['code'];
    $row['attendance_status'] = $summary['label'];
    $row['present_units'] = $summary['present_units'];
    $row['absent_units'] = $summary['absent_units'];
    $row['is_late'] = $summary['is_late'];

    return $row;
}

function attendance_report_learner_order_sql(string $learnerAlias = 'l'): string
{
    return 'CASE ' . $learnerAlias . '.sex '
        . 'WHEN \'male\' THEN 1 '
        . 'WHEN \'female\' THEN 2 '
        . 'ELSE 3 END, '
        . $learnerAlias . '.last_name ASC, '
        . $learnerAlias . '.first_name ASC, '
        . $learnerAlias . '.middle_name ASC';
}

function attendance_report_sort_rows_by_sex_and_name(array $rows, string $sexKey = 'sex', string $nameKey = 'learner_name'): array
{
    usort($rows, static function (array $left, array $right) use ($sexKey, $nameKey): int {
        $sexOrder = ['male' => 0, 'female' => 1];
        $leftSex = $sexOrder[(string) ($left[$sexKey] ?? '')] ?? 2;
        $rightSex = $sexOrder[(string) ($right[$sexKey] ?? '')] ?? 2;

        if ($leftSex !== $rightSex) {
            return $leftSex <=> $rightSex;
        }

        return strcasecmp((string) ($left[$nameKey] ?? ''), (string) ($right[$nameKey] ?? ''));
    });

    return $rows;
}

function attendance_report_type_options(): array
{
    return [
        'daily_attendance' => 'Daily Attendance Report',
        'monthly_summary' => 'Monthly Attendance Summary',
        'section_attendance' => 'Section Attendance Report',
        'learner_history' => 'Learner Attendance History',
        'late_absence' => 'Late and Absence Report',
        'attendance_logs' => 'Attendance Log Report',
    ];
}

function attendance_report_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function attendance_report_valid_month(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        return false;
    }

    $year = (int) substr($value, 0, 4);
    $month = (int) substr($value, 5, 2);

    return checkdate($month, 1, $year);
}

function attendance_report_filters(): array
{
    $today = date('Y-m-d');
    $currentMonth = date('Y-m');
    $defaultFrom = date('Y-m-01');
    $reportTypes = attendance_report_type_options();

    $reportType = trim((string) ($_GET['report_type'] ?? ''));
    if ($reportType !== '' && !array_key_exists($reportType, $reportTypes)) {
        $reportType = '';
    }

    $reportDate = trim((string) ($_GET['report_date'] ?? $today));
    if (!attendance_report_valid_date($reportDate)) {
        $reportDate = $today;
    }

    $reportMonth = trim((string) ($_GET['report_month'] ?? $currentMonth));
    if (!attendance_report_valid_month($reportMonth)) {
        $reportMonth = $currentMonth;
    }

    $dateFrom = trim((string) ($_GET['date_from'] ?? $defaultFrom));
    if (!attendance_report_valid_date($dateFrom)) {
        $dateFrom = $defaultFrom;
    }

    $dateTo = trim((string) ($_GET['date_to'] ?? $today));
    if (!attendance_report_valid_date($dateTo)) {
        $dateTo = $today;
    }

    if ($dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    return [
        'report_type' => $reportType,
        'report_date' => $reportDate,
        'report_month' => $reportMonth,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'section_id' => trim((string) ($_GET['section_id'] ?? '')),
        'learner_id' => trim((string) ($_GET['learner_id'] ?? '')),
    ];
}

function attendance_report_learner_options(): array
{
    $schoolYear = require_current_school_year();

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name
         FROM learners l
         INNER JOIN learner_enrollments le
            ON le.learner_id = l.id
           AND le.school_year_id = :school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         ORDER BY l.last_name ASC, l.first_name ASC'
    );
    $statement->execute(['school_year_id' => $schoolYear['id']]);

    return $statement->fetchAll();
}

function attendance_report_filter_conditions(array $filters, array &$params, string $enrollmentAlias = 'le'): array
{
    $conditions = [];

    if ($filters['section_id'] !== '') {
        $conditions[] = "COALESCE({$enrollmentAlias}.section_id, 0) = :section_id";
        $params['section_id'] = (int) $filters['section_id'];
    }

    if ($filters['learner_id'] !== '') {
        $conditions[] = "{$enrollmentAlias}.learner_id = :learner_id";
        $params['learner_id'] = (int) $filters['learner_id'];
    }

    return $conditions;
}

function attendance_report_meta(string $reportType): array
{
    $defaults = [
        'title' => 'Attendance Reports',
        'description' => 'Select a report type to begin.',
        'filters' => [],
        'requires_learner' => false,
        'is_selected' => false,
    ];

    return match ($reportType) {
        'daily_attendance' => array_merge($defaults, [
            'title' => 'Daily Attendance Report', 'description' => 'Attendance snapshot for the selected day.',
            'filters' => ['report_date', 'section_id'], 'is_selected' => true,
        ]),
        'monthly_summary' => array_merge($defaults, [
            'title' => 'Monthly Attendance Summary', 'description' => 'Monthly attendance counts by learner.',
            'filters' => ['report_month', 'section_id'], 'is_selected' => true,
        ]),
        'section_attendance' => array_merge($defaults, [
            'title' => 'Section Attendance Report', 'description' => 'Section-filtered attendance for the selected day.',
            'filters' => ['report_date', 'section_id'], 'is_selected' => true,
        ]),
        'learner_history' => array_merge($defaults, [
            'title' => 'Learner Attendance History', 'description' => 'Date-by-date attendance history for the selected learner.',
            'filters' => ['report_month', 'learner_id'], 'requires_learner' => true, 'is_selected' => true,
        ]),
        'late_absence' => array_merge($defaults, [
            'title' => 'Late and Absence Report', 'description' => 'Learners with late, absent, or excused records in the selected range.',
            'filters' => ['date_range', 'section_id', 'learner_id'], 'is_selected' => true,
        ]),
        'attendance_logs' => array_merge($defaults, [
            'title' => 'Attendance Log Report', 'description' => 'Raw attendance scan entries for auditing and troubleshooting.',
            'filters' => ['date_range', 'section_id', 'learner_id'], 'is_selected' => true,
        ]),
        default => $defaults,
    };
}

function attendance_report_filter_map(): array
{
    $map = [];

    foreach (array_keys(attendance_report_type_options()) as $reportType) {
        $meta = attendance_report_meta($reportType);
        $map[$reportType] = $meta['filters'] ?? [];
    }

    return $map;
}

function attendance_report_data(array $filters): array
{
    $reportType = $filters['report_type'];
    $meta = attendance_report_meta($reportType);

    if ($reportType === '') {
        return array_merge($meta, ['rows' => []]);
    }

    $reportFunctionMap = [
        'daily_attendance' => 'attendance_report_daily',
        'monthly_summary' => 'attendance_report_monthly_summary',
        'section_attendance' => 'attendance_report_section',
        'learner_history' => 'attendance_report_learner_history',
        'late_absence' => 'attendance_report_late_absence',
        'attendance_logs' => 'attendance_report_logs',
    ];
    $reportFunction = $reportFunctionMap[$reportType] ?? '';
    $reportData = function_exists($reportFunction) ? $reportFunction($filters) : ['rows' => []];

    return array_merge($meta, $reportData);
}

function attendance_report_daily(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = [
        'school_year_id' => $schoolYear['id'],
        'report_date' => $filters['report_date'],
    ];
    $conditions = attendance_report_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            l.learner_number,
            l.lrn,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
            l.sex,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            ar.attendance_date,
            COALESCE(al.code, \'\') AS attendance_code,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
           AND ar.attendance_date = :report_date
         LEFT JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.grade_level ASC, section_name ASC, ' . attendance_report_learner_order_sql('l')
    );
    $statement->execute($params);

    return ['rows' => array_map(
        static fn (array $row): array => attendance_report_apply_summary($row, $filters['report_date']),
        $statement->fetchAll()
    )];
}

function attendance_report_monthly_summary(array $filters): array
{
    $schoolYear = require_current_school_year();
    $monthStart = $filters['report_month'] . '-01';
    $monthStamp = strtotime($monthStart);
    $monthEnd = $monthStamp === false ? date('Y-m-t') : date('Y-m-t', $monthStamp);
    $daysInMonth = (int) ($monthStamp === false ? date('t') : date('t', $monthStamp));
    $monthLabel = $monthStamp === false ? $filters['report_month'] : date('F', $monthStamp);
    $yearLabel = $monthStamp === false ? date('Y') : date('Y', $monthStamp);

    $params = [
        'school_year_id' => $schoolYear['id'],
        'date_from' => $monthStart,
        'date_to' => $monthEnd,
    ];
    $conditions = attendance_report_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            l.id AS learner_id,
            l.learner_number,
            l.sex,
            CONCAT(l.last_name, \', \', l.first_name) AS learner_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            ar.attendance_date,
            COALESCE(al.code, \'\') AS attendance_code,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
           AND ar.attendance_date BETWEEN :date_from AND :date_to
         LEFT JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY ' . attendance_report_learner_order_sql('l') . ', ar.attendance_date ASC'
    );
    $statement->execute($params);

    $rawRows = $statement->fetchAll();
    $rows = [];
    $dayTotals = array_fill(1, $daysInMonth, 0);
    $sectionLabel = 'All Sections';

    foreach ($rawRows as $rawRow) {
        $learnerId = (int) $rawRow['learner_id'];

        if ($filters['section_id'] !== '' && $rawRow['section_name'] !== '') {
            $sectionLabel = (string) $rawRow['grade_level'] . ' - ' . (string) $rawRow['section_name'];
        }

        if (!isset($rows[$learnerId])) {
            $rows[$learnerId] = [
                'no' => count($rows) + 1,
                'learner_name' => $rawRow['learner_name'],
                'sex' => $rawRow['sex'] ?? null,
                'grade_level' => $rawRow['grade_level'],
                'section_name' => $rawRow['section_name'],
                'days' => array_fill(1, $daysInMonth, ''),
                'total_absences' => 0,
                'total_present_days' => 0,
            ];
        }

        if (empty($rawRow['attendance_date'])) {
            continue;
        }

        $dayNumber = (int) date('j', strtotime((string) $rawRow['attendance_date']));
        $summary = attendance_report_apply_summary($rawRow);
        $attendanceCode = (string) $summary['attendance_code'];

        if ($dayNumber < 1 || $dayNumber > $daysInMonth) {
            continue;
        }

        $rows[$learnerId]['days'][$dayNumber] = $attendanceCode === 'A' && $summary['absent_units'] === 0.5
            ? 'A (0.5)'
            : $attendanceCode;
        $rows[$learnerId]['total_absences'] += $summary['absent_units'];
        $rows[$learnerId]['total_present_days'] += $summary['present_units'];
        $dayTotals[$dayNumber] += $summary['present_units'];
    }

    if ($filters['section_id'] === '' && $rows !== []) {
        $firstRow = reset($rows);
        if (is_array($firstRow) && !empty($firstRow['section_name'])) {
            $sectionLabel = 'Multiple Sections';
        }
    }

    $sortedRows = attendance_report_sort_rows_by_sex_and_name(array_values($rows));
    foreach ($sortedRows as $index => &$row) {
        $row['no'] = $index + 1;
    }
    unset($row);

    return [
        'rows' => $sortedRows,
        'days_in_month' => range(1, $daysInMonth),
        'day_totals' => $dayTotals,
        'section_label' => $sectionLabel,
        'month_label' => $monthLabel,
        'year_label' => $yearLabel,
        'overall_absences' => array_sum(array_column(array_values($rows), 'total_absences')),
        'overall_present_days' => array_sum(array_column(array_values($rows), 'total_present_days')),
    ];
}

function attendance_report_section(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = [
        'school_year_id' => $schoolYear['id'],
        'report_date' => $filters['report_date'],
    ];
    $conditions = attendance_report_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            l.learner_number,
            l.lrn,
            CONCAT(l.last_name, \', \', l.first_name) AS learner_name,
            ar.attendance_date,
            COALESCE(al.code, \'\') AS attendance_code,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
           AND ar.attendance_date = :report_date
         LEFT JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.grade_level ASC, section_name ASC, ' . attendance_report_learner_order_sql('l')
    );
    $statement->execute($params);

    return ['rows' => array_map(
        static fn (array $row): array => attendance_report_apply_summary($row, $filters['report_date']),
        $statement->fetchAll()
    )];
}

function attendance_report_learner_history(array $filters): array
{
    if ($filters['learner_id'] === '') {
        $meta = attendance_report_meta('learner_history');
        $meta['description'] = 'Select a learner to view attendance history.';
        return array_merge($meta, ['rows' => []]);
    }

    $monthStamp = strtotime($filters['report_month'] . '-01');
    $dateFrom = $monthStamp === false ? date('Y-m-01') : date('Y-m-01', $monthStamp);
    $dateTo = $monthStamp === false ? date('Y-m-t') : date('Y-m-t', $monthStamp);

    $schoolYear = require_current_school_year();
    $params = [
        'school_year_id' => $schoolYear['id'],
        'learner_id' => (int) $filters['learner_id'],
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];

    $statement = database()->prepare(
        'SELECT
            ar.attendance_date,
            COALESCE(al.code, \'\') AS attendance_code,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out,
            CONCAT(l.last_name, \', \', l.first_name) AS learner_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
         LEFT JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE le.school_year_id = :school_year_id
           AND le.learner_id = :learner_id
           AND ar.attendance_date BETWEEN :date_from AND :date_to
         ORDER BY ar.attendance_date DESC'
    );
    $statement->execute($params);

    return ['rows' => array_map(
        static fn (array $row): array => attendance_report_apply_summary($row),
        $statement->fetchAll()
    )];
}

function attendance_report_late_absence(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = [
        'school_year_id' => $schoolYear['id'],
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
    ];
    $conditions = attendance_report_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            l.id AS learner_id,
            l.learner_number,
            l.lrn,
            l.sex,
            CONCAT(l.last_name, \', \', l.first_name) AS learner_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            ar.attendance_date,
            al.code AS attendance_code,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
           AND ar.attendance_date BETWEEN :date_from AND :date_to
         INNER JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY ' . attendance_report_learner_order_sql('l') . ', ar.attendance_date ASC'
    );
    $statement->execute($params);

    $rows = [];
    foreach ($statement->fetchAll() as $rawRow) {
        $summary = attendance_report_apply_summary($rawRow);
        $learnerId = (int) $rawRow['learner_id'];

        if (!isset($rows[$learnerId])) {
            $rows[$learnerId] = [
                'learner_number' => $rawRow['learner_number'],
                'lrn' => $rawRow['lrn'],
                'sex' => $rawRow['sex'],
                'learner_name' => $rawRow['learner_name'],
                'grade_level' => $rawRow['grade_level'],
                'section_name' => $rawRow['section_name'],
                'late_count' => 0,
                'absent_count' => 0.0,
                'excused_count' => 0,
            ];
        }

        $rows[$learnerId]['late_count'] += $summary['is_late'] ? 1 : 0;
        $rows[$learnerId]['absent_count'] += $summary['absent_units'];
        $rows[$learnerId]['excused_count'] += $summary['code'] === 'E' ? 1 : 0;
    }

    $rows = array_values(array_filter($rows, static fn (array $row): bool =>
        $row['late_count'] > 0 || $row['absent_count'] > 0 || $row['excused_count'] > 0
    ));
    usort($rows, static fn (array $left, array $right): int =>
        [$right['absent_count'], $right['late_count'], $left['learner_name']]
        <=> [$left['absent_count'], $left['late_count'], $right['learner_name']]
    );

    return ['rows' => $rows];
}

function attendance_report_logs(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = [
        'school_year_id' => $schoolYear['id'],
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
    ];
    $conditions = attendance_report_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            asl.scanned_at,
            l.learner_number,
            l.lrn,
            CONCAT(l.last_name, \', \', l.first_name) AS learner_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            asl.slot_label,
            al.label AS attendance_status
         FROM attendance_scan_logs asl
         INNER JOIN learner_enrollments le ON le.id = asl.learner_enrollment_id
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN attendance_legends al ON al.id = asl.legend_id
         WHERE le.school_year_id = :school_year_id
           AND DATE(asl.scanned_at) BETWEEN :date_from AND :date_to' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY asl.scanned_at DESC, asl.id DESC'
    );
    $statement->execute($params);

    return ['rows' => $statement->fetchAll()];
}
