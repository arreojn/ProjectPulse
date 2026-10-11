import { createApp } from 'vue';
import { mountVueStatus } from '../shared/status.js';
import { showAppAlert, showAppConfirm } from '../shared/dialogs.js';

const readConfig = () => {
  const element = document.getElementById('health-portal-config');
  if (!element) return {};

  try {
    return JSON.parse(element.textContent || '{}');
  } catch (_error) {
    return {};
  }
};

const parseJson = (value) => {
  try {
    return JSON.parse(value);
  } catch (_error) {
    return null;
  }
};

const HealthPortalApp = {
  mounted() {
    this.config = readConfig();
    this.searchInputs = Array.from(document.querySelectorAll('[data-health-learner-search]'));
    this.forms = Array.from(document.querySelectorAll('[data-vue-health-action]'));
    this.importForms = Array.from(document.querySelectorAll('[data-vue-health-import]'));
    this.chartData = document.getElementById('health-dashboard-chart-data');

    this.boundSearch = this.handleSearch.bind(this);
    this.boundAction = this.handleAction.bind(this);
    this.boundImport = this.handleImport.bind(this);

    this.searchInputs.forEach((input) => input.addEventListener('input', this.boundSearch));
    this.forms.forEach((form) => form.addEventListener('submit', this.boundAction));
    this.importForms.forEach((form) => form.addEventListener('submit', this.boundImport));

    this.renderDashboardCharts();
    mountVueStatus();
  },
  beforeUnmount() {
    this.searchInputs?.forEach((input) => input.removeEventListener('input', this.boundSearch));
    this.forms?.forEach((form) => form.removeEventListener('submit', this.boundAction));
    this.importForms?.forEach((form) => form.removeEventListener('submit', this.boundImport));
  },
  methods: {
    handleSearch(event) {
      const input = event.currentTarget;
      const container = input.closest('article') || input.closest('section') || input.closest('form')?.parentElement || document;
      const table = container.querySelector('table');
      if (!table) return;

      const query = input.value.trim().toLowerCase();
      const rows = Array.from(table.querySelectorAll('tbody tr'));
      let visible = 0;

      rows.forEach((row) => {
        const emptyRow = row.querySelector('.empty-row');
        const matches = emptyRow || query === '' || row.textContent.toLowerCase().includes(query);
        row.hidden = !matches;
        if (matches) visible += 1;
      });

      const status = input.parentElement?.querySelector('[data-health-learner-search-status]');
      if (status) {
        status.textContent = query === ''
          ? `Showing ${visible} learner(s).`
          : `Showing ${visible} learner(s) matching "${input.value.trim()}".`;
      }
    },
    async handleAction(event) {
      const form = event.currentTarget;
      if (!(form instanceof HTMLFormElement)) return;

      event.preventDefault();

      const action = form.dataset.vueHealthAction || form.getAttribute('data-vue-health-action') || form.elements.namedItem('form_action')?.value;
      if (form.dataset.appConfirm) {
        const confirmed = await showAppConfirm(form.dataset.appConfirm, { danger: true, confirmLabel: 'Remove' });
        if (!confirmed) return;
      }

      const endpoint = this.config.workflowUrl || 'api/health_workflow.php';
      const csrfToken = form.elements.namedItem('csrf_token')?.value;

      if (!action || !csrfToken) {
        return;
      }

      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      const previousLabel = submitButton ? (submitButton.textContent || submitButton.value || 'Save') : 'Save';
      form.setAttribute('aria-busy', 'true');

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving…';
      }

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          body: new FormData(form),
        });
        const payload = parseJson(await response.text());

        if (!response.ok || !payload || !payload.success) {
          throw new Error(payload?.message || 'Unable to save the health workflow.');
        }

        await this.showAlert(payload.message || 'Health record updated.', true);

        if (action === 'save_measurement' && payload.bmi !== undefined) {
          const output = form.closest('tr')?.querySelector('[data-vue-bmi-output]');
          if (output) {
            output.textContent = Number(payload.bmi).toFixed(2);
          }
        }

        if (action === 'remove_feeding_recipient' || action === 'add_feeding_recipients') {
          window.location.reload();
          return;
        }
      } catch (error) {
        await this.showAlert(error instanceof Error ? error.message : 'Unable to save the health workflow.', false);
      } finally {
        form.removeAttribute('aria-busy');
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = previousLabel;
        }
      }
    },
    async handleImport(event) {
      const form = event.currentTarget;
      if (!(form instanceof HTMLFormElement)) return;

      event.preventDefault();

      const input = form.querySelector('input[type="file"]');
      if (!input || !(input instanceof HTMLInputElement) || !input.files || input.files.length === 0) {
        await this.showAlert('Choose a CSV file before importing measurements.', false);
        return;
      }

      const endpoint = this.config.workflowUrl || 'api/health_workflow.php';
      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      const previousLabel = submitButton ? (submitButton.textContent || submitButton.value || 'Import') : 'Import';
      const formData = new FormData(form);
      form.setAttribute('aria-busy', 'true');

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Importing…';
      }

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });
        const payload = parseJson(await response.text());

        if (!response.ok || !payload || !payload.success) {
          throw new Error(payload?.message || 'Unable to import measurements.');
        }

        await this.showAlert(payload.message || 'Measurements imported successfully.', true);
        window.location.reload();
      } catch (error) {
        await this.showAlert(error instanceof Error ? error.message : 'Unable to import measurements.', false);
      } finally {
        form.removeAttribute('aria-busy');
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = previousLabel;
        }
      }
    },
    showAlert(message, isSuccess) {
      return showAppAlert(message, { variant: isSuccess ? 'success' : 'error' });
    },
    renderDashboardCharts() {
      const wrapper = document.getElementById('health-portal-visualizations');
      const rawData = this.chartData ? parseJson(this.chartData.textContent || '{}') : null;
      if (!wrapper || !rawData || typeof rawData !== 'object') {
        return;
      }

      const cards = [
        {
          title: 'BMI Remarks Distribution',
          type: 'donut',
          total: (rawData.bmi || []).reduce((sum, item) => sum + Number(item.value || 0), 0),
          items: rawData.bmi || [],
        },
        {
          title: 'Deworming Status',
          type: 'bar',
          total: (rawData.deworming || []).reduce((sum, item) => sum + Number(item.value || 0), 0),
          items: rawData.deworming || [],
        },
        {
          title: 'Feeding Program Status',
          type: 'donut',
          total: (rawData.feeding || []).reduce((sum, item) => sum + Number(item.value || 0), 0),
          items: rawData.feeding || [],
        },
      ];

      wrapper.innerHTML = cards.map((card) => {
        if (card.type === 'bar') {
          const max = Math.max(1, ...card.items.map((item) => Number(item.value || 0)));
          return `
            <article class="flex min-w-[300px] max-w-[45%] flex-1 flex-col items-center rounded-[28px] border border-[var(--line)] bg-[var(--surface-strong)] p-5 shadow-[var(--shadow)]">
              <h3 class="mb-[15px] text-[1.2rem] font-bold text-[var(--ink)]">${card.title}</h3>
              <div class="flex h-[150px] w-full items-end gap-2.5 border-b border-[var(--line)] pb-2.5">
                ${card.items.map((item) => {
                  const value = Number(item.value || 0);
                  const percent = card.total > 0 ? (value / Math.max(card.total, 1)) * 100 : 0;
                  return `
                    <div class="relative flex flex-1 items-end justify-center rounded-t-[5px] text-[0.8rem] font-bold text-[var(--surface-strong)] transition-[height] duration-500 ease-out h-[var(--bar-height)] bg-[var(--chart-color)]" style="--bar-height: ${Math.max(8, percent)}%; --chart-color: ${item.color};">
                      <span class="absolute -top-5 text-[0.75rem] font-normal text-[var(--ink)]">${value}</span>
                    </div>
                  `;
                }).join('')}
              </div>
              <div class="mt-[5px] flex w-full justify-around">
                ${card.items.map((item) => `<div class="mt-[5px] text-center text-[0.8rem] text-[var(--muted)]">${item.label}</div>`).join('')}
              </div>
              <div class="mt-5 w-full">
                ${card.items.map((item) => {
                  const value = Number(item.value || 0);
                  const percent = card.total > 0 ? Math.round((value / card.total) * 100) : 0;
                  return `
                    <div class="flex justify-between py-[5px] text-[0.9rem]">
                      <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-[3px] bg-[var(--chart-color)]" style="--chart-color: ${item.color};"></span>${item.label}</span>
                      <strong>${value} (${percent}%)</strong>
                    </div>
                  `;
                }).join('')}
              </div>
            </article>
          `;
        }

        const strokeStops = [];
        let start = 0;
        card.items.forEach((item) => {
          const value = Number(item.value || 0);
          const end = card.total > 0 ? start + (value / card.total) * 100 : 0;
          strokeStops.push(`${item.color} ${start}% ${end}%`);
          start = end;
        });

        return `
          <article class="flex min-w-[300px] max-w-[45%] flex-1 flex-col items-center rounded-[28px] border border-[var(--line)] bg-[var(--surface-strong)] p-5 shadow-[var(--shadow)]">
            <h3 class="mb-[15px] text-[1.2rem] font-bold text-[var(--ink)]">${card.title}</h3>
            <div class="relative flex h-[180px] w-[180px] items-center justify-center rounded-full bg-[var(--chart-gradient)] text-[0.9rem] font-semibold text-[var(--surface-strong)] before:absolute before:h-3/5 before:w-3/5 before:rounded-full before:bg-[var(--surface-strong)] before:content-[''] [&>span]:relative [&>span]:z-[1] [&>span]:text-[var(--ink)]" style="--chart-gradient: conic-gradient(${strokeStops.join(', ')});">
              <span>${card.total} Learners</span>
            </div>
            <div class="mt-5 w-full">
              ${card.items.map((item) => {
                const value = Number(item.value || 0);
                const percent = card.total > 0 ? Math.round((value / card.total) * 100) : 0;
                return `
                  <div class="flex justify-between py-[5px] text-[0.9rem]">
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-[3px] bg-[var(--chart-color)]" style="--chart-color: ${item.color};"></span>${item.label}</span>
                    <strong>${value} (${percent}%)</strong>
                  </div>
                `;
              }).join('')}
            </div>
          </article>
        `;
      }).join('');
    },
  },
};

const root = document.createElement('div');
root.hidden = true;
document.body.appendChild(root);
createApp(HealthPortalApp).mount(root);
