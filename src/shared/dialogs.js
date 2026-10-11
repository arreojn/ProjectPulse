let dialogQueue = Promise.resolve();

function createDialog() {
  const dialog = document.createElement('dialog');
  dialog.className = 'app-dialog m-auto w-[min(420px,calc(100%-32px))] max-w-full rounded-[28px] border border-white/80 bg-[var(--surface-strong,_#fff)] p-[30px] text-center text-[var(--ink,_#1c2420)] shadow-[0_28px_80px_rgba(15,23,42,0.28)] backdrop:bg-[rgba(29,42,57,0.58)] backdrop:backdrop-blur-[7px]';
  dialog.setAttribute('aria-labelledby', 'app-dialog-title');
  dialog.setAttribute('aria-describedby', 'app-dialog-message');
  dialog.innerHTML = `
    <div class="app-dialog-icon mx-auto mb-4 grid h-[58px] w-[58px] place-items-center rounded-full bg-[color-mix(in_srgb,var(--success,_#2d8c3b)_13%,white)] text-[1.8rem] font-extrabold text-[var(--success,_#2d8c3b)]"></div>
    <p class="app-dialog-eyebrow mb-2 text-[0.7rem] font-bold uppercase tracking-[0.13em] text-[#7b837e]"></p>
    <h2 class="app-dialog-title mb-2.5 text-[1.55rem] font-extrabold leading-[1.2]" id="app-dialog-title"></h2>
    <p class="app-dialog-message m-0 break-words text-[0.95rem] leading-[1.6] text-[#7b837e]"></p>
    <div class="app-dialog-actions mt-6 flex justify-center gap-2.5"></div>
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
    if (isError) {
      icon.classList.add(
        'bg-[color-mix(in_srgb,var(--danger,_#a12b2b)_12%,white)]',
        'text-[var(--danger,_#a12b2b)]',
      );
    } else if (isConfirm) {
      icon.classList.add(
        'bg-[color-mix(in_srgb,var(--accent,_#2d8c3b)_13%,white)]',
        'text-[var(--accent,_#2d8c3b)]',
      );
    }
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
      cancel.className = 'app-dialog-button app-dialog-cancel min-h-[46px] min-w-[120px] rounded-full px-5 py-2.5 text-[0.9rem] font-extrabold transition duration-[140ms] hover:-translate-y-px hover:brightness-105 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#45ad53]/40 focus-visible:outline-offset-[3px]';
      cancel.textContent = options.cancelLabel || 'Cancel';
      cancel.addEventListener('click', () => finish(false));
      actions.appendChild(cancel);
    }

    const accept = document.createElement('button');
    accept.type = 'button';
    accept.className = `app-dialog-button app-dialog-accept min-h-[46px] min-w-[120px] rounded-full px-5 py-2.5 text-[0.9rem] font-extrabold transition duration-[140ms] hover:-translate-y-px hover:brightness-105 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#45ad53]/40 focus-visible:outline-offset-[3px]${options.danger ? ' app-dialog-accept-danger' : ''}`;
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
