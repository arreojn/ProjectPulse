import { mountVueStatus } from '../shared/status.js';

function buildStatusBox(form) {
  const existing = form.querySelector('[data-auth-status]');
  if (existing) return existing;

  const status = document.createElement('div');
  status.className = 'alert neutral';
  status.setAttribute('data-auth-status', 'true');
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  form.insertBefore(status, form.firstChild);
  return status;
}

function setStatus(form, message, type = 'neutral') {
  const status = buildStatusBox(form);
  status.textContent = message;
  status.className = `alert ${type}`;
}

function makePayload(form) {
  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());
  payload.action = form.dataset.authAction || 'change';
  payload.csrf_token = payload.csrf_token || form.querySelector('[name="csrf_token"]')?.value || '';
  return payload;
}

async function submitPasswordForm(form) {
  const action = form.dataset.authAction || 'change';
  const submitButton = form.querySelector('button[type="submit"]');
  const originalLabel = submitButton?.textContent || '';

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const endpoint = form.dataset.authEndpoint || 'api/auth_password.php';
  const payload = makePayload(form);

  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = action === 'forgot' ? 'Requesting…' : 'Saving…';
  }

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(payload),
      credentials: 'same-origin'
    });

    const data = await response.json().catch(() => ({ success: false, message: 'Unable to process the request right now.' }));

    if (!response.ok || data.success === false) {
      throw new Error(data.message || 'Unable to process the request right now.');
    }

    setStatus(form, data.message || 'Request submitted.', 'success');
    form.reset();

    if (action === 'change') {
      window.setTimeout(() => {
        const fallback = form.dataset.redirect || '';
        if (fallback) window.location.assign(fallback);
      }, 700);
    }
  } catch (error) {
    setStatus(form, error.message || 'Unable to process the request right now.', 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalLabel;
    }
  }
}

export function attachPasswordWorkflow() {
  const forms = document.querySelectorAll('[data-auth-action]');
  if (!forms.length) return;

  forms.forEach((form) => {
    if (form.dataset.passwordBound === 'true') return;
    form.dataset.passwordBound = 'true';
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      await submitPasswordForm(form);
    });
  });

  mountVueStatus();
}
