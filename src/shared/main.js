import { createApp } from 'vue';
import '../guidance/workflows.js';
import { mountVueStatus } from './status.js';
import { attachPasswordWorkflow } from '../auth/password.js';
import { attachParentPortalWorkflow } from '../parent/portal.js';
import '../teacher/workflows.js';
import { mountAdminWorkflows } from '../admin/workflows.js';

const SharedShell = {
  template: '<span aria-hidden="true"></span>',
  data() {
    return { mobileOpen: false, collapsed: false };
  },
  mounted() {
    this.toggleButton = document.getElementById('sidebar-toggle');
    this.sidebar = document.getElementById('admin-sidebar');
    this.backdrop = document.getElementById('sidebar-backdrop');
    this.mobileQuery = window.matchMedia('(max-width: 1200px)');
    this.storageKey = 'adminSidebarCollapsed';

    this.boundSyncViewport = this.syncViewport.bind(this);
    this.boundSyncReportFilters = this.syncReportFilters.bind(this);
    if (this.toggleButton && this.sidebar && this.backdrop) {
      this.sidebarLabel = this.toggleButton.getAttribute('data-sidebar-label') || 'menu';
      this.boundToggleSidebar = this.toggleSidebar.bind(this);
      this.boundCloseMobileSidebar = this.closeMobileSidebar.bind(this);
      this.boundKeydown = this.onKeydown.bind(this);
      this.boundSidebarClick = this.onSidebarClick.bind(this);
      this.toggleButton.addEventListener('click', this.boundToggleSidebar);
      this.backdrop.addEventListener('click', this.boundCloseMobileSidebar);
      document.addEventListener('keydown', this.boundKeydown);
      this.sidebar.addEventListener('click', this.boundSidebarClick);
      if (this.mobileQuery.addEventListener) this.mobileQuery.addEventListener('change', this.boundSyncViewport);
      else this.mobileQuery.addListener?.(this.boundSyncViewport);
      this.syncViewport();
    }
    this.initReportFilters();
    this.initAgeDisplays();
    this.initAnnouncementModal();
    this.initFormFeedback();
    this.initFileFeedback();
    this.initLearnerQuickFilter();
    this.initBmiPreview();
    this.initTextareaCounters();
    this.initParentAttendanceFilter();
    this.initDisabilityFields();
    this.initAttendanceCharts();
    attachParentPortalWorkflow();
    attachPasswordWorkflow();
    mountVueStatus();
    mountAdminWorkflows();
    document.body.dataset.vueShared = 'true';
  },
  beforeUnmount() {
    this.toggleButton?.removeEventListener('click', this.boundToggleSidebar);
    this.backdrop?.removeEventListener('click', this.boundCloseMobileSidebar);
    document.removeEventListener('keydown', this.boundKeydown);
    this.sidebar?.removeEventListener('click', this.boundSidebarClick);
    if (this.mobileQuery?.removeEventListener) this.mobileQuery.removeEventListener('change', this.boundSyncViewport);
    else this.mobileQuery?.removeListener?.(this.boundSyncViewport);
    this.reportTypeSelect?.removeEventListener('change', this.boundSyncReportFilters);
    this.announcementCloseButton?.removeEventListener('click', this.closeAnnouncementModal);
    this.formBindings?.forEach(({ form, handler }) => form.removeEventListener('submit', handler));
    this.fileBindings?.forEach(({ input, handler }) => input.removeEventListener('change', handler));
    this.learnerQuickFilter?.removeEventListener('input', this.boundLearnerQuickFilter);
    this.bmiBindings?.forEach(({ input, handler }) => input.removeEventListener('input', handler));
    this.textareaBindings?.forEach(({ textarea, handler }) => textarea.removeEventListener('input', handler));
    this.parentAttendanceFilter?.removeEventListener('input', this.boundParentAttendanceFilter);
    this.disabilityToggles?.forEach(({ toggle, handler }) => toggle.removeEventListener('change', handler));
  },
  methods: {
    setMobileState(open) {
      this.mobileOpen = open;
      document.body.classList.toggle('sidebar-open', open);
      if (this.backdrop) this.backdrop.hidden = !open;
      this.sidebar?.setAttribute('aria-hidden', open ? 'false' : 'true');
      this.updateButton(open ? true : false);
    },
    setDesktopState(collapsed) {
      this.collapsed = collapsed;
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      this.sidebar?.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
      window.localStorage.setItem(this.storageKey, collapsed ? 'true' : 'false');
      this.updateButton(!collapsed);
    },
    updateButton(expanded) {
      this.toggleButton?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      this.toggleButton?.setAttribute('aria-label', `${expanded ? 'Close' : 'Open'} ${this.sidebarLabel}`);
    },
    syncViewport() {
      if (this.mobileQuery.matches) {
        document.body.classList.remove('sidebar-collapsed');
        if (!this.mobileOpen) this.setMobileState(false);
        return;
      }
      this.setMobileState(false);
      this.setDesktopState(window.localStorage.getItem(this.storageKey) === 'true');
    },
    toggleSidebar() {
      if (this.mobileQuery.matches) this.setMobileState(!this.mobileOpen);
      else this.setDesktopState(!this.collapsed);
    },
    closeMobileSidebar() { this.setMobileState(false); },
    onKeydown(event) { if (event.key === 'Escape' && this.mobileOpen) this.closeMobileSidebar(); },
    onSidebarClick(event) {
      if (this.mobileQuery.matches && event.target instanceof Element && event.target.closest('a')) this.closeMobileSidebar();
    },
    initReportFilters() {
      this.reportTypeSelect = document.getElementById('report_type');
      this.reportFilterForm = document.getElementById('report-filter-form');
      if (!this.reportTypeSelect || !this.reportFilterForm) return;
      this.reportTypeSelect.addEventListener('change', this.boundSyncReportFilters);
      this.syncReportFilters();
    },
    syncReportFilters() {
      if (!this.reportFilterForm || !this.reportTypeSelect) return;
      let filterMap = {};
      try { filterMap = JSON.parse(this.reportFilterForm.getAttribute('data-report-filter-map') || '{}'); } catch (_) { return; }
      const visibleFilters = filterMap[this.reportTypeSelect.value] || [];
      this.reportFilterForm.querySelectorAll('[data-report-filter-key]').forEach((field) => {
        const visible = field.getAttribute('data-report-filter-key') === 'report_type' || visibleFilters.includes(field.getAttribute('data-report-filter-key'));
        field.hidden = !visible;
        field.querySelectorAll('input, select').forEach((control) => { if (control.name !== 'module' && control.name !== 'report_type') control.disabled = !visible; });
      });
    },
    initAgeDisplays() {
      document.querySelectorAll('input[type="date"][data-age-target][data-age-reference-date]').forEach((input) => {
        const target = document.getElementById(input.getAttribute('data-age-target'));
        if (!target) return;
        const render = () => {
          const birth = new Date(`${input.value}T00:00:00`);
          const reference = new Date(`${input.getAttribute('data-age-reference-date')}T00:00:00`);
          if (!input.value || Number.isNaN(birth.getTime()) || Number.isNaN(reference.getTime()) || birth > reference) { target.textContent = '-'; return; }
          let age = reference.getFullYear() - birth.getFullYear();
          if (reference.getMonth() < birth.getMonth() || (reference.getMonth() === birth.getMonth() && reference.getDate() < birth.getDate())) age -= 1;
          target.textContent = age >= 0 ? String(age) : '-';
        };
        input.addEventListener('input', render); input.addEventListener('change', render); render();
      });
    },
    initAnnouncementModal() {
      this.announcementModal = document.getElementById('announcement-modal');
      this.announcementCloseButton = document.getElementById('close-announcement-modal');
      if (!this.announcementModal || !this.announcementCloseButton) return;
      this.closeAnnouncementModal = () => { this.announcementModal.style.display = 'none'; this.announcementModal.classList.remove('is-open'); };
      this.announcementCloseButton.addEventListener('click', this.closeAnnouncementModal);
      this.announcementModal.addEventListener('click', (event) => { if (event.target === this.announcementModal) this.closeAnnouncementModal(); });
    },
    initFormFeedback() {
      this.formBindings = [];
      document.querySelectorAll('form').forEach((form) => {
        if (form.dataset.vueSubmitBound === 'true') return;
        const handler = () => {
          if (!form.checkValidity()) return;
          const isGet = (form.getAttribute('method') || 'get').toLowerCase() === 'get';
          form.dataset.vueSubmitting = 'true';
          form.setAttribute('aria-busy', 'true');
          form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            button.dataset.vueOriginalLabel = button.textContent || button.value || '';
            if (button.tagName === 'INPUT') button.value = isGet ? 'Loading…' : 'Saving…';
            else button.textContent = isGet ? 'Loading…' : 'Saving…';
            button.disabled = true;
          });
        };
        form.addEventListener('submit', handler);
        form.dataset.vueSubmitBound = 'true';
        this.formBindings.push({ form, handler });
      });
    },
    initFileFeedback() {
      this.fileBindings = [];
      document.querySelectorAll('input[type="file"]').forEach((input) => {
        const feedback = document.createElement('small');
        feedback.className = 'vue-file-feedback';
        feedback.setAttribute('aria-live', 'polite');
        feedback.textContent = 'No file selected.';
        input.insertAdjacentElement('afterend', feedback);
        const handler = () => {
          const files = Array.from(input.files || []);
          feedback.textContent = files.length === 0 ? 'No file selected.' : files.length === 1 ? `Selected: ${files[0].name}` : `${files.length} files selected.`;
        };
        input.addEventListener('change', handler);
        this.fileBindings.push({ input, handler });
      });
    },
    initLearnerQuickFilter() {
      this.learnerQuickFilter = document.getElementById('learner-quick-filter');
      const table = document.querySelector('.learner-list-table');
      if (!this.learnerQuickFilter || !table) return;
      this.boundLearnerQuickFilter = () => {
        const query = this.learnerQuickFilter.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach((row) => {
          const isEmpty = row.querySelector('.empty-row');
          row.hidden = !isEmpty && query !== '' && !row.textContent.toLowerCase().includes(query);
        });
      };
      this.learnerQuickFilter.addEventListener('input', this.boundLearnerQuickFilter);
    },
    initBmiPreview() {
      this.bmiBindings = [];
      document.querySelectorAll('[data-vue-bmi-form]').forEach((form) => {
        const height = form.closest('tr')?.querySelector('[data-vue-bmi-height]');
        const weight = form.closest('tr')?.querySelector('[data-vue-bmi-weight]');
        const output = form.closest('tr')?.querySelector('[data-vue-bmi-output]');
        if (!height || !weight || !output) return;
        const update = () => {
          const heightCm = Number(height.value); const weightKg = Number(weight.value);
          output.textContent = heightCm > 0 && weightKg > 0 ? (weightKg / ((heightCm / 100) ** 2)).toFixed(2) : '-';
        };
        height.addEventListener('input', update); weight.addEventListener('input', update);
        this.bmiBindings.push({ input: height, handler: update }, { input: weight, handler: update });
      });
    },
    initTextareaCounters() {
      this.textareaBindings = [];
      document.querySelectorAll('textarea').forEach((textarea) => {
        const counter = document.createElement('small');
        counter.className = 'vue-textarea-counter';
        counter.setAttribute('aria-live', 'polite');
        textarea.insertAdjacentElement('afterend', counter);
        const update = () => { counter.textContent = `${textarea.value.length} characters`; };
        textarea.addEventListener('input', update); update();
        this.textareaBindings.push({ textarea, handler: update });
      });
    },
    initParentAttendanceFilter() {
      this.parentAttendanceFilter = document.getElementById('parent-attendance-quick-filter');
      const table = document.querySelector('[data-vue-parent-attendance-table]');
      if (!this.parentAttendanceFilter || !table) return;
      this.boundParentAttendanceFilter = () => {
        const query = this.parentAttendanceFilter.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach((row) => {
          const empty = row.querySelector('.empty-row');
          row.hidden = !empty && query !== '' && !row.textContent.toLowerCase().includes(query);
        });
      };
      this.parentAttendanceFilter.addEventListener('input', this.boundParentAttendanceFilter);
    },
    initDisabilityFields() {
      this.disabilityToggles = [];
      ['has_disability', 'profile_has_disability'].forEach((id) => {
        const toggle = document.getElementById(id);
        if (!toggle) return;
        const prefix = id === 'profile_has_disability' ? 'profile_' : '';
        const related = [document.getElementById(`${prefix}disability_basis`), document.getElementById(`${prefix}disability_type`)].filter(Boolean);
        if (!related.length) return;
        const containers = related.map((field) => field.closest('div') || field);
        const handler = () => containers.forEach((container) => { container.hidden = toggle.value !== '1'; });
        toggle.addEventListener('change', handler); handler();
        this.disabilityToggles.push({ toggle, handler });
      });
    },
    initAttendanceCharts() {
      const dataElement = document.getElementById('attendance-dashboard-chart-data');
      if (!dataElement || typeof window.Chart === 'undefined') return;
      let chartData;
      try { chartData = JSON.parse(dataElement.textContent || '{}'); } catch (_) { return; }
      const rootStyle = getComputedStyle(document.documentElement);
      const ink = rootStyle.getPropertyValue('--ink').trim() || '#1f2933';
      const muted = rootStyle.getPropertyValue('--muted').trim() || '#52606d';
      const grid = 'rgba(82, 96, 109, 0.14)';
      const success = rootStyle.getPropertyValue('--success').trim() || '#17663a';
      const info = rootStyle.getPropertyValue('--info').trim() || '#2563eb';
      window.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
      window.Chart.defaults.color = muted;
      const lineOptions = (title) => ({ responsive: true, maintainAspectRatio: false, animation: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${title}: ${context.parsed.y}` } } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid }, title: { display: true, text: title, color: muted } } } });
      const definitions = [
        { id: 'attendance-trend-chart', config: { type: 'line', data: { labels: chartData.trend.labels, datasets: [{ label: 'Learners scanned', data: chartData.trend.values, borderColor: success, backgroundColor: 'rgba(23, 102, 58, 0.1)', borderWidth: 2, pointRadius: 2, fill: true, tension: 0.28 }] }, options: lineOptions('Learners scanned') } },
        { id: 'hourly-scan-chart', config: { type: 'line', data: { labels: chartData.hourly.labels, datasets: [{ label: 'Scan events', data: chartData.hourly.values, borderColor: info, backgroundColor: 'rgba(37, 99, 235, 0.08)', borderWidth: 2, pointRadius: 3, fill: true, tension: 0.25 }] }, options: lineOptions('Scan events') } },
        { id: 'attendance-status-chart', config: { type: 'doughnut', data: { labels: chartData.status.labels, datasets: [{ data: chartData.status.values, backgroundColor: chartData.status.colors, borderColor: '#ffffff', borderWidth: 2, hoverOffset: 5 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, cutout: '68%', plugins: { legend: { display: false } } } } },
        { id: 'grade-status-chart', config: { type: 'bar', data: { labels: chartData.gradeStatus.labels, datasets: chartData.gradeStatus.datasets.map((dataset) => ({ label: dataset.label, data: dataset.data, backgroundColor: dataset.color, borderRadius: 3, borderSkipped: false, stack: 'status' })) }, options: { responsive: true, maintainAspectRatio: false, animation: false, indexAxis: 'y', plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid }, title: { display: true, text: 'Saved records', color: muted } }, y: { stacked: true, grid: { display: false }, ticks: { color: ink } } } } } }
      ];
      definitions.forEach(({ id, config }) => { const canvas = document.getElementById(id); if (!canvas) return; try { new window.Chart(canvas, config); } catch (_) { canvas.hidden = true; } });
    }
  }
};

const root = document.createElement('div');
root.id = 'projectpulse-vue';
root.hidden = true;
document.body.appendChild(root);
createApp(SharedShell).mount(root);

if (document.body.dataset.healthPortal === 'true') {
  import('../health/portal.js');
}
