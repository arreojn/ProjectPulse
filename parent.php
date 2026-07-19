<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/parents.php';
require_once __DIR__ . '/app/grades.php';

function parent_portal_format_date(?string $value, string $format = 'D, M j, Y'): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date($format, $timestamp);
}

function parent_portal_format_time(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('h:i A', $timestamp);
}

parent_portal_bootstrap();
grade_book_bootstrap();

$user = require_roles(['parent']);
$linkedLearners = parent_linked_learners((int) $user['id']);
$filters = parent_portal_filters($linkedLearners);
$selectedChild = parent_portal_selected_child($linkedLearners, $filters['child_id']);
$selectedMonthLabel = parent_portal_month_label($filters['report_month']);
$attendanceRows = [];
$attendanceSummary = parent_child_month_summary([]);
$gradeHistoryGroups = [];

if ($selectedChild !== null) {
    $attendanceRows = parent_child_month_attendance(
        (int) $user['id'],
        (int) $selectedChild['id'],
        $filters['report_month']
    );
    $attendanceSummary = parent_child_month_summary($attendanceRows);
    $gradeHistoryGroups = grade_group_history_by_level(
        grade_parent_child_history((int) $user['id'], (int) $selectedChild['id'])
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Parent Portal</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body">
    <main class="dashboard-shell fullscreen-shell">
        <header class="topbar">
            <div class="header-title-block">
                <img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                <div class="header-copy">
                    <p class="eyebrow">Parent Portal</p>
                    <h1>Child Attendance</h1>
                </div>
            </div>

            <div class="topbar-actions">
                <p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p>
                <a href="<?php echo escape(route_url('change_password.php')); ?>" class="secondary-link">Change Password</a>
                <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a>
            </div>
        </header>

        <section class="parent-portal-grid">
            <aside class="parent-overview-stack">
                <article class="parent-panel-card">
                    <div class="panel-heading">
                        <h2>Family Overview</h2>
                        <p>Parents can review their linked child attendance records month by month.</p>
                    </div>

                    <div class="summary-grid parent-summary-grid">
                        <div class="summary-card">
                            <span class="summary-code">Linked Children</span>
                            <strong><?php echo escape((string) count($linkedLearners)); ?></strong>
                            <small>Available in this portal</small>
                        </div>

                        <div class="summary-card">
                            <span class="summary-code">Viewing Month</span>
                            <strong><?php echo escape(date('M Y', strtotime($filters['report_month'] . '-01'))); ?></strong>
                            <small>Active month filter</small>
                        </div>
                    </div>
                </article>

                <article class="parent-panel-card">
                    <div class="panel-heading">
                        <h2>Linked Children</h2>
                        <p>Select a child to switch the attendance log view.</p>
                    </div>

                    <?php if ($linkedLearners === []): ?>
                        <div class="alert neutral">No linked learners are available for this parent account yet.</div>
                    <?php else: ?>
                        <div class="parent-child-grid">
                            <?php foreach ($linkedLearners as $child): ?>
                                <a
                                    href="<?php echo escape(route_url('parent.php?child_id=' . urlencode((string) $child['id']) . '&report_month=' . urlencode($filters['report_month']))); ?>"
                                    class="parent-child-card<?php echo (string) $child['id'] === $filters['child_id'] ? ' is-active' : ''; ?>"
                                >
                                    <p class="parent-child-eyebrow">
                                        <?php echo escape($child['relationship']); ?>
                                        <?php echo (int) $child['is_primary_contact'] === 1 ? ' • Primary contact' : ''; ?>
                                    </p>
                                    <h3>
                                        <?php echo escape(trim($child['first_name'] . ' ' . $child['middle_name'] . ' ' . $child['last_name'])); ?>
                                    </h3>
                                    <p class="parent-child-detail"><?php echo escape($child['grade_level'] . ' - ' . $child['section_name']); ?></p>
                                    <p class="parent-child-detail">LRN: <?php echo escape($child['lrn']); ?></p>
                                    <div class="parent-child-footer">
                                        <strong><?php echo escape($child['latest_attendance_status']); ?></strong>
                                        <span><?php echo escape(parent_portal_format_date($child['latest_attendance_date'], 'M j, Y')); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </aside>

            <section class="parent-history-stack">
                <article class="parent-panel-card">
                    <div class="panel-heading">
                        <h2>Attendance Filters</h2>
                        <p>Choose a child and month to review attendance records.</p>
                    </div>

                    <form method="get" class="report-filter-grid parent-filter-grid">
                        <div class="report-filter-field report-filter-field-wide">
                            <label for="child_id">Child</label>
                            <select id="child_id" name="child_id"<?php echo $linkedLearners === [] ? ' disabled' : ''; ?>>
                                <?php if ($linkedLearners === []): ?>
                                    <option value="">No linked learners</option>
                                <?php else: ?>
                                    <?php foreach ($linkedLearners as $child): ?>
                                        <option value="<?php echo escape((string) $child['id']); ?>"<?php echo (string) $child['id'] === $filters['child_id'] ? ' selected' : ''; ?>>
                                            <?php echo escape(trim($child['last_name'] . ', ' . $child['first_name'] . ' ' . $child['middle_name']) . ' [' . $child['lrn'] . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="report-filter-field">
                            <label for="report_month">Month</label>
                            <input id="report_month" name="report_month" type="month" value="<?php echo escape($filters['report_month']); ?>">
                        </div>

                        <div class="report-actions">
                            <button type="submit" class="primary-button"<?php echo $linkedLearners === [] ? ' disabled' : ''; ?>>View Attendance</button>
                            <a href="<?php echo escape(route_url('parent.php')); ?>" class="secondary-link">Reset</a>
                        </div>
                    </form>
                </article>

                <?php if ($selectedChild !== null): ?>
                    <article class="parent-panel-card">
                        <div class="learner-header-row">
                            <div class="learner-summary">
                                <h3><?php echo escape(trim($selectedChild['first_name'] . ' ' . $selectedChild['middle_name'] . ' ' . $selectedChild['last_name'])); ?></h3>
                                <p>Attendance records for <?php echo escape($selectedMonthLabel); ?>.</p>
                            </div>
                        </div>

                        <dl class="detail-grid wide">
                            <div>
                                <dt>LRN</dt>
                                <dd><?php echo escape($selectedChild['lrn']); ?></dd>
                            </div>
                            <div>
                                <dt>Grade Level</dt>
                                <dd><?php echo escape($selectedChild['grade_level']); ?></dd>
                            </div>
                            <div>
                                <dt>Section</dt>
                                <dd><?php echo escape($selectedChild['section_name']); ?></dd>
                            </div>
                            <div>
                                <dt>School Year</dt>
                                <dd><?php echo escape($selectedChild['school_year_label']); ?></dd>
                            </div>
                            <div>
                                <dt>Relationship</dt>
                                <dd><?php echo escape($selectedChild['relationship']); ?></dd>
                            </div>
                            <div>
                                <dt>Primary Contact</dt>
                                <dd><?php echo (int) $selectedChild['is_primary_contact'] === 1 ? 'Yes' : 'No'; ?></dd>
                            </div>
                            <div>
                                <dt>Latest Status</dt>
                                <dd><?php echo escape($selectedChild['latest_attendance_status']); ?></dd>
                            </div>
                            <div>
                                <dt>Latest Attendance Date</dt>
                                <dd><?php echo escape(parent_portal_format_date($selectedChild['latest_attendance_date'])); ?></dd>
                            </div>
                        </dl>
                    </article>

                    <article class="parent-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Monthly Attendance Logs</h2>
                            <p>Showing <?php echo escape((string) count($attendanceRows)); ?> recorded day(s) for <?php echo escape($selectedMonthLabel); ?>.</p>
                        </div>

                        <div class="parent-month-summary-grid">
                            <div class="summary-card">
                                <span class="summary-code">Recorded Days</span>
                                <strong><?php echo escape((string) $attendanceSummary['days_with_records']); ?></strong>
                                <small>Days with attendance entries</small>
                            </div>

                            <div class="summary-card">
                                <span class="summary-code">Attended</span>
                                <strong><?php echo escape((string) $attendanceSummary['attended_days']); ?></strong>
                                <small>Present or late days</small>
                            </div>

                            <div class="summary-card">
                                <span class="summary-code">Present</span>
                                <strong><?php echo escape((string) $attendanceSummary['present_count']); ?></strong>
                                <small>Marked present</small>
                            </div>

                            <div class="summary-card">
                                <span class="summary-code">Late</span>
                                <strong><?php echo escape((string) $attendanceSummary['late_count']); ?></strong>
                                <small>Marked late</small>
                            </div>

                            <div class="summary-card">
                                <span class="summary-code">Absent</span>
                                <strong><?php echo escape((string) $attendanceSummary['absent_count']); ?></strong>
                                <small>Marked absent</small>
                            </div>

                            <div class="summary-card">
                                <span class="summary-code">Excused</span>
                                <strong><?php echo escape((string) $attendanceSummary['excused_count']); ?></strong>
                                <small>Marked excused</small>
                            </div>
                        </div>

                        <div class="table-shell">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>AM In</th>
                                        <th>AM Out</th>
                                        <th>PM In</th>
                                        <th>PM Out</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($attendanceRows === []): ?>
                                        <tr>
                                            <td colspan="7" class="empty-row">No attendance records were found for the selected child and month.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendanceRows as $row): ?>
                                            <tr>
                                                <td><?php echo escape(parent_portal_format_date($row['attendance_date'])); ?></td>
                                                <td><span class="table-status"><?php echo escape($row['attendance_status']); ?></span></td>
                                                <td><?php echo escape(parent_portal_format_time($row['am_time_in'])); ?></td>
                                                <td><?php echo escape(parent_portal_format_time($row['am_time_out'])); ?></td>
                                                <td><?php echo escape(parent_portal_format_time($row['pm_time_in'])); ?></td>
                                                <td><?php echo escape(parent_portal_format_time($row['pm_time_out'])); ?></td>
                                                <td><?php echo escape($row['remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="parent-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Grade History</h2>
                            <p>Subject grades are grouped by school year and grade level.</p>
                        </div>

                        <?php if ($gradeHistoryGroups === []): ?>
                            <div class="alert neutral">No grade records are available for this learner yet.</div>
                        <?php else: ?>
                            <div class="grade-history-stack">
                                <?php foreach ($gradeHistoryGroups as $gradeGroup): ?>
                                    <?php $usesSeniorGradeLayout = grade_is_senior_high((string) $gradeGroup['grade_level']); ?>
                                    <section class="grade-history-section">
                                        <div class="monthly-report-info">
                                            <p><strong>School Year:</strong> <?php echo escape($gradeGroup['school_year_label']); ?></p>
                                            <p><strong>Grade:</strong> <?php echo escape($gradeGroup['grade_level']); ?></p>
                                            <p><strong>Section:</strong> <?php echo escape($gradeGroup['section_name']); ?></p>
                                            <p><strong>Grand Average:</strong> <?php echo escape($gradeGroup['grand_average'] !== null ? (string) $gradeGroup['grand_average'] : '-'); ?></p>
                                        </div>

                                        <div class="table-shell">
                                            <?php if ($usesSeniorGradeLayout): ?>
                                                <table class="records-table report-table">
                                                    <thead>
                                                        <tr>
                                                            <th colspan="4">First Semester</th>
                                                        </tr>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q1</th>
                                                            <th>Q2</th>
                                                            <th>1st Sem Avg</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['first_semester_average'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <table class="records-table report-table grade-semester-table">
                                                    <thead>
                                                        <tr>
                                                            <th colspan="6">Second Semester</th>
                                                        </tr>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q3</th>
                                                            <th>Q4</th>
                                                            <th>2nd Sem Avg</th>
                                                            <th>Final Avg</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['second_semester_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <table class="records-table report-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q1</th>
                                                            <th>Q2</th>
                                                            <th>Q3</th>
                                                            <th>Q4</th>
                                                            <th>Average</th>
                                                            <th>Final Avg</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                                <?php $quarterAverage = grade_quarter_average($gradeRow); ?>
                                                                <td><?php echo escape($quarterAverage !== null ? (string) $quarterAverage : '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php else: ?>
                    <article class="parent-panel-card">
                        <div class="alert neutral">Choose a linked child to review monthly attendance logs.</div>
                    </article>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
