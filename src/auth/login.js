import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';

const LoginController = {
  template: '<span aria-hidden="true"></span>',
  mounted() {
    this.form = document.querySelector('.login-form');
    this.password = document.getElementById('password');
    this.toggle = document.querySelector('.password-toggle');
    this.submit = this.form?.querySelector('button[type="submit"]');
    this.boundToggle = this.togglePassword.bind(this);
    this.boundSubmit = this.onSubmit.bind(this);
    this.toggle?.addEventListener('click', this.boundToggle);
    this.form?.addEventListener('submit', this.boundSubmit);
    mountVueStatus();
  },
  beforeUnmount() {
    this.toggle?.removeEventListener('click', this.boundToggle);
    this.form?.removeEventListener('submit', this.boundSubmit);
  },
  methods: {
    togglePassword() {
      const visible = this.password.type === 'text';
      this.password.type = visible ? 'password' : 'text';
      this.toggle.setAttribute('aria-pressed', String(!visible));
      this.toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
      const icon = this.toggle.querySelector('i');
      if (icon) icon.className = visible ? 'fa fa-eye-slash' : 'fa fa-eye';
    },
    onSubmit() {
      if (!this.form.checkValidity() || !this.submit) return;
      this.submit.disabled = true;
      this.submit.setAttribute('aria-busy', 'true');
      const label = this.submit.querySelector('span');
      if (label) label.textContent = 'Signing in…';
    }
  }
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(LoginController).mount(root);
