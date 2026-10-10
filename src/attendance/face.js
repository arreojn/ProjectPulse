import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';

const config = window.ProjectPulse || {};

const FaceAttendance = {
  template: '<span aria-hidden="true"></span>',
  data() { return { processing: false, stream: null, clockTimer: null, summaryTimer: null, frameTimer: null }; },
  mounted() {
    this.video = document.getElementById('video-feed');
    this.canvas = document.getElementById('capture-canvas');
    this.feedback = document.getElementById('scan-feedback');
    this.attendanceBody = document.getElementById('today-attendance-body');
    this.context = this.canvas?.getContext('2d');
    this.updateClock();
    this.clockTimer = window.setInterval(() => this.updateClock(), 1000);
    this.startWebcam();
    this.updateAttendanceList();
    this.summaryTimer = window.setInterval(() => this.updateAttendanceList(), 15000);
    this.frameTimer = window.setInterval(() => this.processFrame(), 1200);
    mountVueStatus();
  },
  beforeUnmount() {
    window.clearInterval(this.clockTimer); window.clearInterval(this.summaryTimer); window.clearInterval(this.frameTimer);
    this.stream?.getTracks().forEach((track) => track.stop());
  },
  methods: {
    setFeedback(message, type = 'neutral') { if (!this.feedback) return; this.feedback.textContent = message; this.feedback.className = `alert ${type}`; },
    async startWebcam() {
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
        this.video.srcObject = this.stream;
        this.video.onloadedmetadata = () => { this.canvas.width = this.video.videoWidth; this.canvas.height = this.video.videoHeight; this.setFeedback('Awaiting scan...', 'neutral'); };
      } catch (_) { this.setFeedback('Could not access webcam. Please grant permission.', 'error'); }
    },
    async processFrame() {
      if (this.processing || !this.video || this.video.paused || this.video.ended || !this.video.srcObject || !this.context) return;
      this.processing = true;
      this.context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
      try {
        const response = await fetch(config.faceApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ image_data: this.canvas.toDataURL('image/jpeg', 0.72), csrf_token: config.csrfToken }) });
        const result = await response.json();
        if (response.ok && result.success) this.setFeedback(result.message, 'success');
        else if (response.status !== 404) this.setFeedback(result.message || 'Face recognition failed.', 'error');
        else this.setFeedback('Searching for a recognized face...', 'neutral');
      } catch (_) { this.setFeedback('API connection error.', 'error'); }
      finally { await this.updateAttendanceList(); window.setTimeout(() => { this.processing = false; }, 700); }
    },
    async updateAttendanceList() {
      try {
        const response = await fetch(config.attendanceSummaryUrl, { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || !data.success || !Array.isArray(data.records)) return;
        this.attendanceBody.replaceChildren();
        if (!data.records.length) { const row = this.attendanceBody.insertRow(); const cell = row.insertCell(); cell.colSpan = 5; cell.className = 'empty-row'; cell.textContent = 'No attendance records for today yet.'; return; }
        data.records.forEach((record) => {
          const row = this.attendanceBody.insertRow();
          const learnerCell = row.insertCell(); learnerCell.className = 'learner-name-cell';
          const name = document.createElement('strong'); name.textContent = record.learner_name;
          const lrn = document.createElement('small'); lrn.textContent = `LRN ${record.lrn}`;
          learnerCell.append(name, lrn);
          ['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out'].forEach((key) => { row.insertCell().textContent = record[key]; });
        });
      } catch (_) { /* Keep the latest successful list visible. */ }
    },
    updateClock() { const now = new Date(); document.getElementById('live-time').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }); document.getElementById('live-date').textContent = now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }); }
  }
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(FaceAttendance).mount(root);
