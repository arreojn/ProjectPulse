import { createApp } from 'vue';

const setFeedback = (form, message, type = 'error') => {
  let feedback = form.querySelector('[data-guidance-feedback]');
  if (!feedback) {
    feedback = document.createElement('div');
    feedback.setAttribute('data-guidance-feedback', 'true');
    form.appendChild(feedback);
  }

  feedback.className = type === 'success' ? 'alert success' : 'alert error';
  feedback.textContent = message;
};

const applyTableFilter = (form) => {
  const selector = form.dataset.vueGuidanceTable || '';
  const table = selector ? document.querySelector(selector) : null;
  if (!table) return;

  const keyword = (form.querySelector('[name="keyword"]')?.value || '').trim().toLowerCase();
  const status = (form.querySelector('[name="case_status"]')?.value || '').trim().toLowerCase();
  const startDate = (form.querySelector('[name="date_from"]')?.value || '').trim();
  const endDate = (form.querySelector('[name="date_to"]')?.value || '').trim();

  table.querySelectorAll('tbody tr').forEach((row) => {
    const emptyRow = row.querySelector('.empty-row');
    if (emptyRow) {
      row.hidden = false;
      return;
    }

    const cells = Array.from(row.querySelectorAll('td')).map((cell) => cell.textContent.trim());
    const rowText = cells.join(' ').toLowerCase();
    const caseStatus = (cells[2] || '').trim().toLowerCase();
    const openDate = (cells[3] || '').trim();

    const matchesKeyword = keyword === '' || rowText.includes(keyword);
    const matchesStatus = status === '' || caseStatus === status;
    const matchesFrom = startDate === '' || openDate === '' || openDate >= startDate;
    const matchesTo = endDate === '' || openDate === '' || openDate <= endDate;

    row.hidden = !(matchesKeyword && matchesStatus && matchesFrom && matchesTo);
  });
};

const bindFilterForms = () => {
  document.querySelectorAll('[data-vue-guidance-filter]').forEach((form) => {
    const apply = () => applyTableFilter(form);
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      apply();
    });
    form.querySelectorAll('input, select').forEach((control) => {
      control.addEventListener('input', apply);
      control.addEventListener('change', apply);
    });
    apply();
  });
};

const submitGuidanceForm = async (event) => {
  const form = event.currentTarget;
  if (!(form instanceof HTMLFormElement)) return;

  event.preventDefault();
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const endpoint = form.dataset.endpoint || '';
  const button = form.querySelector('button[type="submit"], input[type="submit"]');
  const previousLabel = button ? (button.textContent || button.value || 'Save') : 'Save';

  form.setAttribute('aria-busy', 'true');
  if (button) {
    button.disabled = true;
    if (button.tagName === 'INPUT') button.value = 'Saving…';
    else button.textContent = 'Saving…';
  }

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(new FormData(form)).toString(),
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Unable to complete the request.');
    }

    if (result.redirect) {
      window.location.href = result.redirect;
      return;
    }

    setFeedback(form, result.message || 'Saved successfully.', 'success');
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unable to complete the request.';
    setFeedback(form, message, 'error');
    if (form.dataset.vueGuidanceForm === 'password') {
      form.reset();
    }
  } finally {
    form.removeAttribute('aria-busy');
    if (button) {
      button.disabled = false;
      if (button.tagName === 'INPUT') button.value = previousLabel;
      else button.textContent = previousLabel;
    }
  }
};

const GuidanceWorkflowController = {
  mounted() {
    bindFilterForms();

    this.forms = Array.from(document.querySelectorAll('[data-vue-guidance-form]'));
    this.boundSubmit = submitGuidanceForm.bind(this);
    this.forms.forEach((form) => form.addEventListener('submit', this.boundSubmit));
  },
  beforeUnmount() {
    this.forms?.forEach((form) => form.removeEventListener('submit', this.boundSubmit));
  },
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(GuidanceWorkflowController).mount(root);
