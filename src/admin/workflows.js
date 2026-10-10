import { createApp } from 'vue';

const AdminWorkflowController = {
  mounted() {
    this.bindAjaxForms();
    this.bindReportSubmitState();
  },
  beforeUnmount() {
    this.boundHandlers?.forEach(({ form, handler }) => form.removeEventListener('submit', handler));
    this.reportForms?.forEach(({ form, handler }) => {
      form.removeEventListener('submit', handler);
      form.removeEventListener('reset', handler.resetHandler);
    });
  },
  methods: {
    bindAjaxForms() {
      this.boundHandlers = [];
      document.querySelectorAll('form[data-vue-admin-form]').forEach((form) => {
        if (form.dataset.adminWorkflowBound === 'true') return;
        const handler = async (event) => {
          const confirmMessage = form.dataset.adminConfirm;
          if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();
            return;
          }

          const endpoint = form.dataset.adminEndpoint;
          if (!endpoint) return;

          event.preventDefault();
          const feedback = this.ensureFeedback(form);
          const submitButton = this.getSubmitButton(form, event.submitter);

          this.setBusyState(form, submitButton, feedback, 'Saving...');

          try {
            const response = await fetch(endpoint, {
              method: form.method || 'POST',
              credentials: 'same-origin',
              body: new FormData(form),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
              throw new Error(result.message || 'Unable to complete this request.');
            }

            feedback.textContent = result.message || 'Action completed successfully.';
            feedback.classList.remove('is-error');
            feedback.classList.add('is-success');

            if (result.redirect) {
              window.setTimeout(() => {
                window.location.assign(result.redirect);
              }, 150);
              return;
            }

            if (form.dataset.adminReload === 'true' || form.dataset.adminActionType === 'delete' || form.dataset.adminActionType === 'reset') {
              window.setTimeout(() => window.location.reload(), 150);
            }
          } catch (error) {
            feedback.textContent = error instanceof Error ? error.message : 'Unable to complete this request.';
            feedback.classList.remove('is-success');
            feedback.classList.add('is-error');
          } finally {
            this.clearBusyState(form, submitButton, feedback);
          }
        };

        form.addEventListener('submit', handler);
        form.dataset.adminWorkflowBound = 'true';
        this.boundHandlers.push({ form, handler });
      });
    },
    bindReportSubmitState() {
      this.reportForms = [];
      document.querySelectorAll('form[data-vue-admin-report-form]').forEach((form) => {
        if (form.dataset.adminReportBound === 'true') return;

        const setLoading = () => {
          const button = form.querySelector('button[type="submit"]');
          if (!button) return;
          button.dataset.originalLabel = button.textContent || button.value || 'Generate Report';
          button.disabled = true;
          button.textContent = 'Generating...';
          button.setAttribute('aria-live', 'polite');
        };
        const restore = () => {
          const button = form.querySelector('button[type="submit"]');
          if (!button) return;
          button.disabled = false;
          button.textContent = button.dataset.originalLabel || 'Generate Report';
        };

        form.addEventListener('submit', setLoading);
        form.addEventListener('reset', restore);
        form.dataset.adminReportBound = 'true';
        this.reportForms.push({ form, handler: setLoading, resetHandler: restore });
      });
    },
    ensureFeedback(form) {
      let feedback = form.querySelector('[data-admin-feedback]');
      if (feedback) return feedback;

      feedback = document.createElement('small');
      feedback.className = 'admin-workflow-feedback';
      feedback.setAttribute('data-admin-feedback', 'true');
      feedback.setAttribute('aria-live', 'polite');
      const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitter) {
        submitter.insertAdjacentElement('afterend', feedback);
      } else {
        form.appendChild(feedback);
      }

      return feedback;
    },
    getSubmitButton(form, trigger) {
      if (trigger instanceof HTMLElement) return trigger;
      return form.querySelector('button[type="submit"], input[type="submit"]');
    },
    setBusyState(form, submitButton, feedback, label) {
      feedback.textContent = 'Working...';
      feedback.classList.remove('is-error', 'is-success');
      form.setAttribute('aria-busy', 'true');
      if (submitButton) {
        submitButton.disabled = true;
        if (submitButton.tagName === 'INPUT') {
          submitButton.value = label;
        } else {
          submitButton.textContent = label;
        }
      }
    },
    clearBusyState(form, submitButton, feedback) {
      form.removeAttribute('aria-busy');
      if (submitButton) {
        if (submitButton.tagName === 'INPUT') {
          submitButton.value = submitButton.dataset.originalValue || submitButton.value;
        } else {
          submitButton.textContent = submitButton.dataset.originalLabel || submitButton.textContent;
        }
        submitButton.disabled = false;
      }
      if (feedback && feedback.textContent.trim() === 'Working...') {
        feedback.textContent = '';
      }
    },
  },
};

export function mountAdminWorkflows() {
  if (document.body.dataset.adminWorkflowMounted === 'true') {
    return;
  }

  const root = document.createElement('div');
  root.hidden = true;
  document.body.appendChild(root);
  createApp(AdminWorkflowController).mount(root);
  document.body.dataset.adminWorkflowMounted = 'true';
}
