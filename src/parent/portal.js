import { showAppAlert } from '../shared/dialogs.js';

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatDate(value) {
  if (!value) return '-';
  const parsed = new Date(value + 'T00:00:00');
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(parsed);
}

function formatTime(value) {
  if (!value) return '-';
  const parsed = new Date(`2000-01-01T${value}`);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(parsed);
}

function renderAttendanceTable(rows) {
  const table = document.querySelector('[data-vue-parent-attendance-table]');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  if (!tbody) return;

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No attendance records were found for the selected child and month.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map((row) => {
    const status = row.attendance_status || 'No record';
    const remarks = row.remarks || '-';
    return `
      <tr>
        <td data-label="Date">${formatDate(row.attendance_date)}</td>
        <td data-label="Status"><span class="table-status">${escapeHtml(status)}</span></td>
        <td data-label="AM In">${formatTime(row.am_time_in)}</td>
        <td data-label="AM Out">${formatTime(row.am_time_out)}</td>
        <td data-label="PM In">${formatTime(row.pm_time_in)}</td>
        <td data-label="PM Out">${formatTime(row.pm_time_out)}</td>
        <td data-label="Remarks">${escapeHtml(remarks)}</td>
      </tr>
    `;
  }).join('');
}

function renderSummary(summary) {
  const summaryRoot = document.getElementById('parent-month-summary');
  if (!summaryRoot) return;

  const cards = [
    ['Recorded Days', summary.days_with_records ?? 0, 'Days with attendance entries'],
    ['Attended', summary.attended_days ?? 0, 'Present or late days'],
    ['Present', summary.present_count ?? 0, 'Marked present'],
    ['Late', summary.late_count ?? 0, 'Marked late'],
    ['Absent', summary.absent_count ?? 0, 'Marked absent'],
    ['Excused', summary.excused_count ?? 0, 'Marked excused'],
  ];

  summaryRoot.innerHTML = cards.map(([label, value, note]) => `
    <div class="summary-card">
      <span class="summary-code">${escapeHtml(label)}</span>
      <strong>${escapeHtml(String(value))}</strong>
      <small>${escapeHtml(note)}</small>
    </div>
  `).join('');
}

function renderGradeTable(columns, rows, semesterLabel = '') {
  const semesterHeading = semesterLabel
    ? `<tr><th colspan="${columns.length}">${escapeHtml(semesterLabel)}</th></tr>`
    : '';
  const headings = columns.map(([label]) => `<th>${escapeHtml(label)}</th>`).join('');
  const cells = rows.map((row) => `
    <tr>${columns.map(([label, valueForRow]) => `
      <td data-label="${escapeHtml(label)}">${escapeHtml(valueForRow(row) ?? '-')}</td>
    `).join('')}</tr>
  `).join('');

  return `
    <div class="table-shell">
      <table class="records-table report-table mobile-record-table">
        <thead>${semesterHeading}<tr>${headings}</tr></thead>
        <tbody>${cells}</tbody>
      </table>
    </div>
  `;
}

function renderGradeHistory(groups) {
  const gradeRoot = document.getElementById('parent-grade-history-root');
  if (!gradeRoot) return;

  if (!groups.length) {
    gradeRoot.innerHTML = '<div class="alert neutral">No grade records are available for this learner yet.</div>';
    return;
  }

  gradeRoot.innerHTML = groups.map((group) => {
    const rows = group.rows || [];
    const isSeniorHigh = /^(?:grade\s*)?(?:11|12)$/i.test(String(group.grade_level || '').trim());
    const quarterAverage = (row) => {
      const values = [row.quarter_1_grade, row.quarter_2_grade, row.quarter_3_grade, row.quarter_4_grade]
        .filter((value) => value !== null && value !== '');
      return values.length
        ? values.reduce((total, value) => total + Number(value), 0) / values.length
        : null;
    };

    const gradeTables = isSeniorHigh
      ? renderGradeTable([
        ['Subject', (row) => row.subject_name],
        ['Q1', (row) => row.quarter_1_grade],
        ['Q2', (row) => row.quarter_2_grade],
        ['1st Sem Avg', (row) => row.first_semester_average],
      ], rows, 'First Semester') + renderGradeTable([
        ['Subject', (row) => row.subject_name],
        ['Q3', (row) => row.quarter_3_grade],
        ['Q4', (row) => row.quarter_4_grade],
        ['2nd Sem Avg', (row) => row.second_semester_average],
        ['Final Avg', (row) => row.final_average],
        ['Remarks', (row) => row.remarks],
      ], rows, 'Second Semester')
      : renderGradeTable([
        ['Subject', (row) => row.subject_name],
        ['Q1', (row) => row.quarter_1_grade],
        ['Q2', (row) => row.quarter_2_grade],
        ['Q3', (row) => row.quarter_3_grade],
        ['Q4', (row) => row.quarter_4_grade],
        ['Average', (row) => {
          const average = quarterAverage(row);
          return average !== null ? average.toFixed(2) : '-';
        }],
        ['Final Avg', (row) => row.final_average],
        ['Remarks', (row) => row.remarks],
      ], rows);

    return `
      <section class="grade-history-section">
        <div class="monthly-report-info">
          <p><strong>School Year:</strong> ${escapeHtml(group.school_year_label || '-')}</p>
          <p><strong>Grade:</strong> ${escapeHtml(group.grade_level || '-')}</p>
          <p><strong>Section:</strong> ${escapeHtml(group.section_name || '-')}</p>
          <p><strong>Grand Average:</strong> ${escapeHtml(group.grand_average !== null ? String(group.grand_average) : '-')}</p>
        </div>
        ${gradeTables}
      </section>
    `;
  }).join('');
}

async function fetchParentPortalData(childId, reportMonth) {
  const root = document.getElementById('parent-portal-root');
  if (!root) return;

  const endpoint = root.dataset.parentApiEndpoint || 'api/parent_portal.php';
  const params = new URLSearchParams({ child_id: childId || '', report_month: reportMonth || '' });
  const response = await fetch(`${endpoint}?${params.toString()}`, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  });

  const payload = await response.json().catch(() => ({ success: false }));
  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'Unable to load the selected child record.');
  }

  renderAttendanceTable(payload.attendance_rows || []);
  renderSummary(payload.attendance_summary || {});
  renderGradeHistory(payload.grade_history || []);
}

export function attachParentPortalWorkflow() {
  const root = document.getElementById('parent-portal-root');
  if (!root) return;

  const childSelect = document.getElementById('child_id');
  const monthInput = document.getElementById('report_month');
  const closeButton = document.getElementById('close-announcement-modal');
  const modal = document.getElementById('announcement-modal');
  const endpoint = root.dataset.parentApiEndpoint || 'api/parent_portal.php';

  if (childSelect && monthInput) {
    const handler = async () => {
      try {
        await fetchParentPortalData(childSelect.value, monthInput.value);
      } catch (error) {
        await showAppAlert(error instanceof Error ? error.message : 'Unable to load the selected child record.');
      }
    };

    childSelect.addEventListener('change', handler);
    monthInput.addEventListener('change', handler);
  }

  if (closeButton && modal) {
    const acknowledge = async () => {
      const csrfToken = root.dataset.parentCsrf || '';
      try {
        await fetch(endpoint, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'acknowledge_announcements', csrf_token: csrfToken }),
          credentials: 'same-origin'
        });
      } catch (error) {
        console.warn(error);
      } finally {
        modal.classList.remove('is-open');
        modal.style.display = 'none';
      }
    };

    closeButton.addEventListener('click', acknowledge);
  }
}
