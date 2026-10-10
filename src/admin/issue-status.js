import { createApp } from 'vue';

const IssueStatusController = {
  mounted() {
    this.forms = Array.from(document.querySelectorAll('[data-vue-issue-status]'));
    this.boundSubmit = this.submitStatus.bind(this);
    this.forms.forEach((form) => form.addEventListener('submit', this.boundSubmit));
  },
  beforeUnmount() {
    this.forms?.forEach((form) => form.removeEventListener('submit', this.boundSubmit));
  },
  methods: {
    async submitStatus(event) {
      const form = event.currentTarget;
      if (!(form instanceof HTMLFormElement)) return;

      event.preventDefault();
      const select = form.elements.namedItem('status');
      const issueId = form.elements.namedItem('issue_id')?.value;
      const csrfToken = form.elements.namedItem('csrf_token')?.value;
      const feedback = form.querySelector('[data-issue-status-feedback]');
      const label = form.closest('tr')?.querySelector('[data-issue-status-label]');
      const submitButton = form.querySelector('button[type="submit"]');
      const previousStatus = form.dataset.currentStatus || select.value;

      if (!(select instanceof HTMLSelectElement) || !feedback || !issueId || !csrfToken) return;

      form.setAttribute('aria-busy', 'true');
      select.disabled = true;
      if (submitButton) submitButton.disabled = true;
      feedback.textContent = 'Saving status...';
      feedback.classList.remove('is-error', 'is-success');

      try {
        const response = await fetch(form.dataset.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({
            issue_id: issueId,
            status: select.value,
            csrf_token: csrfToken,
          }).toString(),
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Unable to update issue status.');
        }

        form.dataset.currentStatus = result.status;
        if (label) label.textContent = this.statusLabel(result.status);
        feedback.textContent = result.message;
        feedback.classList.add('is-success');
      } catch (error) {
        select.value = previousStatus;
        feedback.textContent = error instanceof Error ? error.message : 'Unable to update issue status.';
        feedback.classList.add('is-error');
      } finally {
        form.removeAttribute('aria-busy');
        select.disabled = false;
        if (submitButton) submitButton.disabled = false;
      }
    },
    statusLabel(status) {
      return ({ open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed' })[status] || status;
    },
  },
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(IssueStatusController).mount(root);
