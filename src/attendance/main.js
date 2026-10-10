import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';

const config = window.ProjectPulse || {};

const requestJson = async (url, options = {}) => {
  const response = await fetch(url, { credentials: 'same-origin', ...options });
  const data = await response.json();
  if (!response.ok || !data.success) {
    const error = new Error(data.message || 'Request failed.');
    error.status = response.status;
    throw error;
  }
  return data;
};

const App = {
  template: `
    <section class="dashboard-grid expanded">
      <article class="status-panel compact">
        <div class="picture-box"><img class="learner-photo" :src="photoUrl" alt="Learner photo" @error="useDefaultPhoto"></div>
        <div class="clock-panel"><p class="meta-label">Current Time</p><p class="clock-value">{{ time }}</p><p class="date-value">{{ date }}</p></div>
        <div class="legend-panel"><p class="meta-label dark">Attendance Legend</p><p class="scan-mode-note">Present requires all four scans. A complete AM or PM session counts as 0.5 day; arrivals after 7:30 AM or 1:00 PM are late.</p><div class="legend-list"><span class="legend-chip success">P Present</span><span class="legend-chip warning">L Late</span><span class="legend-chip danger">A Absent</span><span class="legend-chip info">E Excused</span></div></div>
      </article>
      <article class="scanner-panel expanded">
        <section class="scan-head"><div class="panel-heading no-gap"><h2>Scan LRN</h2><p>Keep focus here while learner details stay visible.</p></div><div class="search-wrap inline-search"><label for="lrn-search">LRN</label><input id="lrn-search" ref="searchInput" v-model="lrn" type="text" inputmode="numeric" autocomplete="off" placeholder="Enter or scan learner LRN" maxlength="12" autofocus @input="onInput" @keydown.enter.prevent="submitScan"></div></section>
        <section class="scan-mode-panel"><div class="scan-mode-copy"><p class="meta-label dark">Attendance Scan Mode</p><div class="scan-mode-summary"><strong>{{ scanMode.label }}</strong><p>{{ scanMode.description }}</p></div><p class="scan-mode-note">{{ scanMode.canEdit ? 'Admin control: switch modes for all attendance stations.' : 'Admin-controlled setting. Attendance users can view the current mode only.' }}</p></div><div class="scan-mode-toggle-wrap"><label class="mode-switch" :class="{ 'is-readonly': !scanMode.canEdit }" for="scan-mode-toggle"><input id="scan-mode-toggle" class="mode-switch-input" type="checkbox" :checked="scanMode.key === 'am_pm_sequence'" :disabled="!scanMode.canEdit || modeUpdating" @change="updateScanMode"><span class="mode-switch-track"><span class="mode-switch-thumb"></span></span><span class="mode-switch-labels"><span>Strict</span><span>AM/PM</span></span></label><p class="scan-mode-feedback" :class="{ 'is-error': modeError, 'is-success': modeFeedback && !modeError }">{{ modeFeedback }}</p></div></section>
        <section class="learner-card full-panel" :class="{ 'is-empty': !learner }"><div class="learner-header-row"><div class="learner-summary"><h3>{{ learner ? learner.name : 'No learner selected' }}</h3><p>{{ learner ? 'LRN: ' + learner.lrn : message }}</p></div></div><dl class="detail-grid wide"><div><dt>Grade Level</dt><dd>{{ learner?.grade_level || '-' }}</dd></div><div><dt>Section</dt><dd>{{ learner?.section || '-' }}</dd></div><div><dt>School Year</dt><dd>{{ learner?.school_year || '-' }}</dd></div><div><dt>Today's Status</dt><dd>{{ learner?.attendance_status || 'No record yet' }}</dd></div><div><dt>AM Time In</dt><dd>{{ learner?.am_time_in || '-' }}</dd></div><div><dt>AM Time Out</dt><dd>{{ learner?.am_time_out || '-' }}</dd></div><div><dt>PM Time In</dt><dd>{{ learner?.pm_time_in || '-' }}</dd></div><div><dt>PM Time Out</dt><dd>{{ learner?.pm_time_out || '-' }}</dd></div></dl><div class="record-grid"><section class="record-panel"><div class="panel-heading compact-heading"><h2>Recent Attendance</h2><p>The latest records remain visible after every scan.</p></div><div class="table-shell attendance-log-shell"><table class="records-table attendance-log-table"><thead><tr><th>Date</th><th>Time</th><th>Learner</th><th>LRN</th><th>Grade / Section</th><th>Log Entry</th></tr></thead><tbody><tr v-if="logsLoading"><td colspan="6" class="empty-row">Loading attendance logs...</td></tr><tr v-else-if="!logs.length"><td colspan="6" class="empty-row">{{ logsError || 'No attendance logs to display yet.' }}</td></tr><tr v-for="row in logs" :key="row.log_date + row.log_time + row.lrn + row.log_entry"><td>{{ row.log_date }}</td><td>{{ row.log_time }}</td><td>{{ row.learner_name }}</td><td>{{ row.lrn }}</td><td>{{ row.grade_section }}</td><td><span class="table-status">{{ row.log_entry }}</span></td></tr></tbody></table></div></section></div></section>
      </article>
    </section>`,
  data() { return { lrn: '', learner: null, logs: [], logsLoading: true, logsError: '', processing: false, message: 'Search by LRN to load learner information.', time: '', date: '', photoUrl: config.defaultLearnerPhotoUrl || '', modeFeedback: '', modeError: false, modeUpdating: false, scanMode: { ...(config.scanMode || {}) } }; },
  mounted() { this.updateClock(); this.clockTimer = window.setInterval(this.updateClock, 1000); this.loadLogs(); mountVueStatus(); this.$nextTick(() => this.$refs.searchInput?.focus()); },
  beforeUnmount() { window.clearInterval(this.clockTimer); },
  methods: {
    updateClock() { const now = new Date(); this.time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }); this.date = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }); },
    onInput() { this.lrn = this.lrn.replace(/\D/g, '').slice(0, 12); this.message = ''; if (this.lrn.length === 12) window.setTimeout(() => this.submitScan(), 120); },
    async lookup(lrn) { const data = await requestJson(`${config.lookupUrl}?lrn=${encodeURIComponent(lrn)}`); this.learner = data.learner; this.photoUrl = `${config.learnerPhotoBaseUrl}${encodeURIComponent(lrn)}`; },
    async loadLogs() { this.logsLoading = true; this.logsError = ''; try { this.logs = (await requestJson(config.attendanceLogsUrl)).logs || []; } catch (error) { this.logsError = error.message || 'Unable to load attendance logs.'; } finally { this.logsLoading = false; } },
    async submitScan() { if (this.processing || this.lrn.length !== 12) { if (this.lrn.length > 0 && this.lrn.length !== 12) this.message = 'LRN must be exactly 12 digits.'; return; } this.processing = true; this.message = ''; try { const body = new URLSearchParams({ lrn: this.lrn, csrf_token: config.csrfToken }); const result = await requestJson(config.attendanceEventUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body }); await Promise.all([this.lookup(this.lrn), this.loadLogs()]); this.message = result.message || 'Scan recorded successfully.'; this.lrn = ''; } catch (error) { this.message = error.message || 'Scan failed.'; if (error.status === 404) { this.learner = null; this.photoUrl = config.defaultLearnerPhotoUrl || ''; } else { try { await this.lookup(this.lrn); } catch (_) { /* Keep the original scan error visible. */ } } this.$nextTick(() => { this.$refs.searchInput?.focus(); this.$refs.searchInput?.select(); }); } finally { this.processing = false; } },
    useDefaultPhoto() { this.photoUrl = config.defaultLearnerPhotoUrl || ''; },
    async updateScanMode(event) { if (!this.scanMode.canEdit) return; this.modeUpdating = true; this.modeError = false; this.modeFeedback = 'Updating scan mode...'; try { const body = new URLSearchParams({ mode: event.target.checked ? 'am_pm_sequence' : 'strict_windows', csrf_token: config.csrfToken }); const result = await requestJson(config.scanModeUpdateUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body }); this.scanMode = { ...this.scanMode, key: result.mode, label: result.label, description: result.description }; this.modeFeedback = result.message || 'Attendance scan mode updated successfully.'; } catch (error) { this.modeError = true; this.modeFeedback = error.message || 'Unable to update scan mode.'; } finally { this.modeUpdating = false; } }
  }
};

createApp(App).mount('#attendance-app');
