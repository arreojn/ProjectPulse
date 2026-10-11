export function mountVueStatus() {
  if (document.body.classList.contains('admin-dashboard')) return;
  if (document.querySelector('[data-vue-status]')) return;
  const host = document.querySelector('.topbar-actions, .admin-page-header .topbar-actions, .login-content');
  if (!host) return;
  const status = document.createElement('span');
  status.className = 'vue-status-badge mb-4 inline-flex items-center gap-[0.4rem] text-[0.78rem] font-semibold text-[#17663a]';
  status.dataset.vueStatus = 'true';
  status.innerHTML = '<span class="vue-status-dot h-[0.45rem] w-[0.45rem] rounded-full bg-[#17663a]" aria-hidden="true"></span><span>Live controls ready</span>';
  status.title = 'Vue interactive controls are active on this page.';
  host.prepend(status);
}
