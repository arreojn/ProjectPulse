import { createApp } from 'vue';
import { showAppAlert, showAppConfirm } from '../shared/dialogs.js';

const AdminWorkflowController = {
  mounted() {
    this.bindAjaxForms();
    this.bindReportSubmitState();
    this.bindAnnouncementSmsWarning();
  },
  beforeUnmount() {
    this.boundHandlers?.forEach(({ form, handler }) => form.removeEventListener('submit', handler));
    this.announcementSmsWarnings?.forEach(({ checkbox, handler }) => checkbox.removeEventListener('change', handler));
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
          if (confirmMessage) {
            event.preventDefault();
            const confirmed = await showAppConfirm(confirmMessage, {
              danger: form.dataset.adminActionType === 'delete',
              confirmLabel: form.dataset.adminActionType === 'delete' ? 'Delete' : 'Confirm',
            });
            if (!confirmed) return;
          }

          const endpoint = form.dataset.adminEndpoint;
          if (!endpoint) return;

          event.preventDefault();
          const feedback = this.ensureFeedback(form);
          const submitButton = this.getSubmitButton(form, event.submitter);
          const formAction = event.submitter instanceof HTMLButtonElement ? event.submitter.value : '';

          this.setBusyState(form, submitButton, feedback, formAction === 'test_sms_settings' ? 'Sending...' : 'Saving...');

          try {
            const body = new FormData(form);
            if (event.submitter instanceof HTMLButtonElement && event.submitter.name) {
              body.set(event.submitter.name, event.submitter.value);
            }

            const response = await fetch(endpoint, {
              method: form.method || 'POST',
              credentials: 'same-origin',
              body,
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
              throw new Error(result.message || 'Unable to complete this request.');
            }

            await showAppAlert(result.message || 'Action completed successfully.', {
              variant: result.notification_sent === false ? 'error' : 'success',
            });

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
            await showAppAlert(error instanceof Error ? error.message : 'Unable to complete this request.');
          } finally {
            this.clearBusyState(form, submitButton, feedback);
          }
        };

        form.addEventListener('submit', handler);
        form.dataset.adminWorkflowBound = 'true';
        this.boundHandlers.push({ form, handler });
      });
    },
    bindAnnouncementSmsWarning() {
      this.announcementSmsWarnings = [];
      document.querySelectorAll('[data-announcement-sms-warning]').forEach((checkbox) => {
        const handler = () => {
          if (checkbox.checked) {
            showAppAlert(
              'Sending this announcement to all parent/guardian contact numbers may take some time. Please wait for delivery processing to finish before leaving this page.',
              { variant: 'success', title: 'SMS delivery may take some time' },
            );
          }
        };

        checkbox.addEventListener('change', handler);
        this.announcementSmsWarnings.push({ checkbox, handler });
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
        submitButton.dataset.adminOriginalLabel = submitButton.tagName === 'INPUT'
          ? submitButton.value
          : submitButton.textContent || '';
        submitButton.dataset.adminWasDisabled = String(submitButton.disabled);
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
          submitButton.value = submitButton.dataset.adminOriginalLabel || '';
        } else {
          submitButton.textContent = submitButton.dataset.adminOriginalLabel || '';
        }
        submitButton.disabled = submitButton.dataset.adminWasDisabled === 'true';
        delete submitButton.dataset.adminOriginalLabel;
        delete submitButton.dataset.adminWasDisabled;
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
