let dialogQueue = Promise.resolve();

function createDialog() {
  const dialog = document.createElement('dialog');
  dialog.className = 'app-dialog';
  dialog.setAttribute('aria-labelledby', 'app-dialog-title');
  dialog.setAttribute('aria-describedby', 'app-dialog-message');
  dialog.innerHTML = `
    <div class="app-dialog-icon" aria-hidden="true"></div>
    <p class="app-dialog-eyebrow"></p>
    <h2 class="app-dialog-title" id="app-dialog-title"></h2>
    <p class="app-dialog-message" id="app-dialog-message"></p>
    <div class="app-dialog-actions"></div>
  `;
  document.body.appendChild(dialog);
  return dialog;
}

function enqueueDialog(options) {
  const result = dialogQueue.then(() => new Promise((resolve) => {
    const dialog = createDialog();
    const icon = dialog.querySelector('.app-dialog-icon');
    const eyebrow = dialog.querySelector('.app-dialog-eyebrow');
    const title = dialog.querySelector('.app-dialog-title');
    const message = dialog.querySelector('.app-dialog-message');
    const actions = dialog.querySelector('.app-dialog-actions');
    const isConfirm = options.kind === 'confirm';
    const isError = options.variant === 'error';
    let settled = false;

    icon.textContent = isError ? '!' : (isConfirm ? '?' : '✓');
    icon.classList.toggle('is-error', isError);
    icon.classList.toggle('is-confirm', isConfirm);
    eyebrow.textContent = isConfirm ? 'Confirmation required' : (isError ? 'Action needed' : 'ProjectPulse notification');
    title.textContent = options.title || (isConfirm ? 'Are you sure?' : (isError ? 'Something went wrong' : 'Success'));
    message.textContent = options.message;
    dialog.setAttribute('role', 'alertdialog');

    const finish = (value) => {
      if (settled) return;
      settled = true;
      dialog.close();
      dialog.remove();
      resolve(value);
    };

    if (isConfirm) {
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'app-dialog-button app-dialog-cancel';
      cancel.textContent = options.cancelLabel || 'Cancel';
      cancel.addEventListener('click', () => finish(false));
      actions.appendChild(cancel);
    }

    const accept = document.createElement('button');
    accept.type = 'button';
    accept.className = `app-dialog-button app-dialog-accept${options.danger ? ' is-danger' : ''}`;
    accept.textContent = isConfirm ? (options.confirmLabel || 'Confirm') : 'Continue';
    accept.addEventListener('click', () => finish(isConfirm));
    actions.appendChild(accept);

    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      finish(false);
    });
    dialog.showModal();
    accept.focus();
  }));

  dialogQueue = result.then(() => undefined, () => undefined);
  return result;
}

export function showAppAlert(message, { variant = 'error', title } = {}) {
  return enqueueDialog({ kind: 'alert', message: String(message), variant, title });
}

export function showAppConfirm(message, { title, confirmLabel, cancelLabel, danger = false } = {}) {
  return enqueueDialog({ kind: 'confirm', message: String(message), title, confirmLabel, cancelLabel, danger });
}

export function presentPageAlerts() {
  document.querySelectorAll('.alert.error, .alert.success, .login-alert[role="alert"]').forEach((alert) => {
    const message = alert.textContent.trim();
    if (!message) return;

    const variant = alert.classList.contains('success') ? 'success' : 'error';
    alert.hidden = true;
    showAppAlert(message, { variant });
  });
}
