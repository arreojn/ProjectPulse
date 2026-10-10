import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';

const TeacherWorkflowController = {
  mounted() {
    this.forms = Array.from(document.querySelectorAll('[data-vue-teacher-form], [data-vue-teacher-report-form]'));
    if (this.forms.length === 0) return;

    this.boundSubmit = this.submitWorkflow.bind(this);
    this.forms.forEach((form) => form.addEventListener('submit', this.boundSubmit));
    mountVueStatus();
  },
  beforeUnmount() {
    this.forms?.forEach((form) => form.removeEventListener('submit', this.boundSubmit));
  },
  methods: {
    async submitWorkflow(event) {
      const form = event.currentTarget;
      if (!(form instanceof HTMLFormElement)) return;

      const endpoint = (form.dataset.vueTeacherForm || form.dataset.endpoint || '').trim();
      const formAction = form.elements.namedItem('form_action')?.value;
      if (!endpoint || !formAction) return;

      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method === 'get') {
        event.preventDefault();
        const query = new URLSearchParams(new FormData(form));
        const target = new URL(form.action || window.location.href, window.location.origin);
        target.search = query.toString();
        window.location.assign(target.toString());
        return;
      }

      event.preventDefault();
      const submitControls = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
      const previousLabels = new Map();
      submitControls.forEach((control) => {
        previousLabels.set(control, control.textContent || control.value || '');
        if (control.tagName === 'INPUT') control.value = 'Saving…';
        else control.textContent = 'Saving…';
        control.disabled = true;
      });

      form.setAttribute('aria-busy', 'true');
      try {
        const payload = new FormData(form);
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          body: payload,
        });

        const result = await response.json().catch(() => ({ success: false, message: 'Unable to parse the response.' }));
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Unable to complete the request.');
        }

        if (result.redirect) {
          window.location.assign(result.redirect);
          return;
        }

        if (result.message) {
          const status = form.querySelector('[data-vue-teacher-status]');
          if (status) {
            status.textContent = result.message;
            status.classList.add('is-success');
          }
        }
      } catch (error) {
        const message = error instanceof Error ? error.message : 'Unable to complete the request.';
        const status = form.querySelector('[data-vue-teacher-status]');
        if (status) {
          status.textContent = message;
          status.classList.add('is-error');
        } else {
          window.alert(message);
        }
      } finally {
        form.removeAttribute('aria-busy');
        submitControls.forEach((control) => {
          const previousLabel = previousLabels.get(control) || '';
          if (control.tagName === 'INPUT') control.value = previousLabel;
          else control.textContent = previousLabel;
          control.disabled = false;
        });
      }
    },
  },
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(TeacherWorkflowController).mount(root);
