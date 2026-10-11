import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';
import { showAppAlert } from '../shared/dialogs.js';

const config = window.ProjectPulse || {};

const FaceEnrollment = {
  template: '<span aria-hidden="true"></span>',
  data() {
    return {
      stream: null,
      saving: false,
      training: false,
      cameraReady: false,
      photoSlots: Object.fromEntries((config.learners || []).map((learner) => [learner.lrn, learner.photoSlots || []])),
    };
  },
  mounted() {
    this.video = document.getElementById('video-feed'); this.canvas = document.getElementById('capture-canvas'); this.context = this.canvas?.getContext('2d');
    this.select = document.getElementById('learner_id'); this.captureButton = document.getElementById('capture-btn'); this.preview = document.getElementById('photo-preview'); this.captureFeedback = document.getElementById('capture-feedback'); this.captureProgress = document.getElementById('capture-progress');
    this.trainButton = document.getElementById('train-btn'); this.trainFeedback = document.getElementById('train-feedback'); this.trainLog = document.getElementById('train-log');
    this.boundLearnerChange = this.onLearnerChange.bind(this); this.boundCapturePhoto = this.capturePhoto.bind(this); this.boundTrainModel = this.trainModel.bind(this);
    this.select?.addEventListener('change', this.boundLearnerChange); this.captureButton?.addEventListener('click', this.boundCapturePhoto); this.trainButton?.addEventListener('click', this.boundTrainModel); this.updateCaptureControls(); this.startWebcam(); mountVueStatus();
  },
  beforeUnmount() { this.stream?.getTracks().forEach((track) => track.stop()); this.select?.removeEventListener('change', this.boundLearnerChange); this.captureButton?.removeEventListener('click', this.boundCapturePhoto); this.trainButton?.removeEventListener('click', this.boundTrainModel); },
  methods: {
    setFeedback(element, message, type = 'neutral') { if (type !== 'neutral') { element?.classList.add('hidden'); showAppAlert(message, { variant: type }); return; } if (!element) return; element.textContent = message; element.className = `alert ${type}`; element.classList.remove('hidden'); },
    async startWebcam() { try { this.stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false }); this.video.srcObject = this.stream; this.video.onloadedmetadata = () => { const size = Math.min(this.video.videoWidth, this.video.videoHeight); this.canvas.width = size; this.canvas.height = size; this.cameraReady = true; this.updateCaptureControls(); }; } catch (_) { this.setFeedback(this.captureFeedback, 'Could not access webcam. Please grant permission.', 'error'); } },
    nextPhotoNumber(lrn) { const slots = this.photoSlots[lrn] || []; return Array.from({ length: config.maxPhotos }, (_, index) => index + 1).find((slot) => !slots.includes(slot)) || null; },
    updateCaptureControls() {
      const lrn = this.select?.value || '';
      const slots = this.photoSlots[lrn] || [];
      const nextPhoto = this.nextPhotoNumber(lrn);
      if (this.captureButton) {
        this.captureButton.textContent = nextPhoto ? `Capture Photo ${nextPhoto} of ${config.maxPhotos}` : `${config.maxPhotos} Photos Saved`;
        this.captureButton.disabled = !lrn || !nextPhoto || !this.cameraReady || this.saving;
      }
      if (this.captureProgress) {
        this.captureProgress.textContent = !lrn
          ? 'Select a learner to begin.'
          : nextPhoto
            ? `${slots.length} of ${config.maxPhotos} photos saved. Change your angle slightly between captures.`
            : `All ${config.maxPhotos} photos are saved. Train the model to use the updated images.`;
      }
    },
    onLearnerChange() { this.preview.classList.add('hidden'); this.video.classList.remove('hidden'); this.captureFeedback?.classList.add('hidden'); this.updateCaptureControls(); },
    async capturePhoto() {
      const lrn = this.select?.value || '';
      const imageNumber = this.nextPhotoNumber(lrn);
      if (this.saving || !lrn || !imageNumber || !this.context || !this.canvas.width || !this.canvas.height) return;
      this.saving = true; this.updateCaptureControls();
      const cropSize = Math.min(this.video.videoWidth, this.video.videoHeight);
      const cropX = (this.video.videoWidth - cropSize) / 2;
      const cropY = (this.video.videoHeight - cropSize) / 2;
      this.context.drawImage(this.video, cropX, cropY, cropSize, cropSize, 0, 0, this.canvas.width, this.canvas.height);
      const imageData = this.canvas.toDataURL('image/jpeg');
      try {
        const response = await fetch(config.enrollmentUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ lrn, image_number: imageNumber, image_data: imageData, csrf_token: config.csrfToken }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Failed to save photo.');
        this.photoSlots[lrn] = result.photo_slots || [...(this.photoSlots[lrn] || []), imageNumber];
        this.setFeedback(this.captureFeedback, `Photo ${imageNumber} of ${config.maxPhotos} saved.`, 'neutral');
      } catch (error) {
        this.setFeedback(this.captureFeedback, error.message, 'error');
      } finally {
        this.saving = false;
        this.updateCaptureControls();
      }
    },
    async trainModel() {
      if (this.training || !this.trainButton) return;
      this.training = true; this.trainButton.disabled = true; this.trainLog?.classList.add('hidden'); this.setFeedback(this.trainFeedback, 'Training in progress... This may take a moment.');
      try { const formData = new FormData(); formData.append('csrf_token', config.csrfToken); const response = await fetch(config.trainUrl, { method: 'POST', body: formData }); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Failed to train model.'); this.setFeedback(this.trainFeedback, 'Training completed successfully!', 'success'); this.trainLog.textContent = Array.isArray(result.log) ? result.log.join('\n') : ''; this.trainLog.classList.remove('hidden'); } catch (error) { this.setFeedback(this.trainFeedback, error.message, 'error'); } finally { this.training = false; this.trainButton.disabled = false; }
    }
  }
};

const root = document.createElement('div'); root.hidden = true; document.body.appendChild(root); createApp(FaceEnrollment).mount(root);
