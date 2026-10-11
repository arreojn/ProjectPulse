import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';
import { showAppAlert } from '../shared/dialogs.js';

const config = window.ProjectPulse || {};

const FaceEnrollment = {
  template: '<span aria-hidden="true"></span>',
  data() { return { stream: null, saving: false, training: false }; },
  mounted() {
    this.video = document.getElementById('video-feed'); this.canvas = document.getElementById('capture-canvas'); this.context = this.canvas?.getContext('2d');
    this.select = document.getElementById('learner_id'); this.captureButton = document.getElementById('capture-btn'); this.preview = document.getElementById('photo-preview'); this.captureFeedback = document.getElementById('capture-feedback');
    this.trainButton = document.getElementById('train-btn'); this.trainFeedback = document.getElementById('train-feedback'); this.trainLog = document.getElementById('train-log');
    this.boundLearnerChange = this.onLearnerChange.bind(this); this.boundCapturePhoto = this.capturePhoto.bind(this); this.boundTrainModel = this.trainModel.bind(this);
    this.select?.addEventListener('change', this.boundLearnerChange); this.captureButton?.addEventListener('click', this.boundCapturePhoto); this.trainButton?.addEventListener('click', this.boundTrainModel); this.startWebcam(); mountVueStatus();
  },
  beforeUnmount() { this.stream?.getTracks().forEach((track) => track.stop()); this.select?.removeEventListener('change', this.boundLearnerChange); this.captureButton?.removeEventListener('click', this.boundCapturePhoto); this.trainButton?.removeEventListener('click', this.boundTrainModel); },
  methods: {
    setFeedback(element, message, type = 'neutral') { if (type !== 'neutral') { if (element) element.style.display = 'none'; showAppAlert(message, { variant: type }); return; } if (!element) return; element.textContent = message; element.className = `alert ${type}`; element.style.display = 'block'; },
    async startWebcam() { try { this.stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false }); this.video.srcObject = this.stream; this.video.onloadedmetadata = () => { this.canvas.width = this.video.videoWidth; this.canvas.height = this.video.videoHeight; }; } catch (_) { this.setFeedback(this.captureFeedback, 'Could not access webcam. Please grant permission.', 'error'); } },
    onLearnerChange() { this.captureButton.disabled = this.select.value === ''; this.preview.style.display = 'none'; this.video.style.display = 'block'; if (this.captureFeedback) this.captureFeedback.style.display = 'none'; },
    async capturePhoto() {
      if (this.saving || !this.select.value || !this.context) return;
      this.saving = true; this.captureButton.disabled = true; this.context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height); const imageData = this.canvas.toDataURL('image/jpeg'); this.preview.src = imageData; this.video.style.display = 'none'; this.preview.style.display = 'block';
      try { const response = await fetch(config.enrollmentUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ lrn: this.select.value, image_data: imageData, csrf_token: config.csrfToken }) }); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Failed to save photo.'); this.setFeedback(this.captureFeedback, result.message, 'success'); } catch (error) { this.setFeedback(this.captureFeedback, error.message, 'error'); } finally { this.saving = false; this.captureButton.disabled = false; }
    },
    async trainModel() {
      if (this.training || !this.trainButton) return;
      this.training = true; this.trainButton.disabled = true; if (this.trainLog) this.trainLog.style.display = 'none'; this.setFeedback(this.trainFeedback, 'Training in progress... This may take a moment.');
      try { const formData = new FormData(); formData.append('csrf_token', config.csrfToken); const response = await fetch(config.trainUrl, { method: 'POST', body: formData }); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Failed to train model.'); this.setFeedback(this.trainFeedback, 'Training completed successfully!', 'success'); this.trainLog.textContent = Array.isArray(result.log) ? result.log.join('\n') : ''; this.trainLog.style.display = 'block'; } catch (error) { this.setFeedback(this.trainFeedback, error.message, 'error'); } finally { this.training = false; this.trainButton.disabled = false; }
    }
  }
};

const root = document.createElement('div'); root.hidden = true; document.body.appendChild(root); createApp(FaceEnrollment).mount(root);
