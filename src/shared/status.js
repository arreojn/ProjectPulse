export function mountVueStatus() {
  if (document.body.classList.contains('admin-dashboard')) return;
  if (document.querySelector('[data-vue-status]')) return;
  const host = document.querySelector('.topbar-actions, .admin-page-header .topbar-actions, .login-content');
  if (!host) return;
  const status = document.createElement('span');
  status.className = 'vue-status-badge';
  status.dataset.vueStatus = 'true';
  status.innerHTML = '<span class="vue-status-dot" aria-hidden="true"></span><span>Live controls ready</span>';
  status.title = 'Vue interactive controls are active on this page.';
  host.prepend(status);
}
