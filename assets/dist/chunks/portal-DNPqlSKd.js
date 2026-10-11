import{c as f}from"./vue.esm-bundler-DlDjmn_t.js";import{m as b}from"./status-zckm43l8.js";import{s as v,a as g}from"./dialogs-s1Vz-R5c.js";const y=()=>{const r=document.getElementById("health-portal-config");if(!r)return{};try{return JSON.parse(r.textContent||"{}")}catch{return{}}},d=r=>{try{return JSON.parse(r)}catch{return null}},w={mounted(){this.config=y(),this.searchInputs=Array.from(document.querySelectorAll("[data-health-learner-search]")),this.forms=Array.from(document.querySelectorAll("[data-vue-health-action]")),this.importForms=Array.from(document.querySelectorAll("[data-vue-health-import]")),this.chartData=document.getElementById("health-dashboard-chart-data"),this.boundSearch=this.handleSearch.bind(this),this.boundAction=this.handleAction.bind(this),this.boundImport=this.handleImport.bind(this),this.searchInputs.forEach(r=>r.addEventListener("input",this.boundSearch)),this.forms.forEach(r=>r.addEventListener("submit",this.boundAction)),this.importForms.forEach(r=>r.addEventListener("submit",this.boundImport)),this.renderDashboardCharts(),b()},beforeUnmount(){var r,t,l;(r=this.searchInputs)==null||r.forEach(e=>e.removeEventListener("input",this.boundSearch)),(t=this.forms)==null||t.forEach(e=>e.removeEventListener("submit",this.boundAction)),(l=this.importForms)==null||l.forEach(e=>e.removeEventListener("submit",this.boundImport))},methods:{handleSearch(r){var s,h;const t=r.currentTarget,e=(t.closest("article")||t.closest("section")||((s=t.closest("form"))==null?void 0:s.parentElement)||document).querySelector("table");if(!e)return;const o=t.value.trim().toLowerCase(),i=Array.from(e.querySelectorAll("tbody tr"));let a=0;i.forEach(c=>{const m=c.querySelector(".empty-row")||o===""||c.textContent.toLowerCase().includes(o);c.hidden=!m,m&&(a+=1)});const n=(h=t.parentElement)==null?void 0:h.querySelector("[data-health-learner-search-status]");n&&(n.textContent=o===""?`Showing ${a} learner(s).`:`Showing ${a} learner(s) matching "${t.value.trim()}".`)},async handleAction(r){var n,s,h;const t=r.currentTarget;if(!(t instanceof HTMLFormElement))return;r.preventDefault();const l=t.dataset.vueHealthAction||t.getAttribute("data-vue-health-action")||((n=t.elements.namedItem("form_action"))==null?void 0:n.value);if(t.dataset.appConfirm&&!await g(t.dataset.appConfirm,{danger:!0,confirmLabel:"Remove"}))return;const e=this.config.workflowUrl||"api/health_workflow.php",o=(s=t.elements.namedItem("csrf_token"))==null?void 0:s.value;if(!l||!o)return;const i=t.querySelector('button[type="submit"], input[type="submit"]'),a=i&&(i.textContent||i.value)||"Save";t.setAttribute("aria-busy","true"),i&&(i.disabled=!0,i.textContent="Saving…");try{const c=await fetch(e,{method:"POST",credentials:"same-origin",body:new FormData(t)}),u=d(await c.text());if(!c.ok||!u||!u.success)throw new Error((u==null?void 0:u.message)||"Unable to save the health workflow.");if(await this.showAlert(u.message||"Health record updated.",!0),l==="save_measurement"&&u.bmi!==void 0){const m=(h=t.closest("tr"))==null?void 0:h.querySelector("[data-vue-bmi-output]");m&&(m.textContent=Number(u.bmi).toFixed(2))}if(l==="remove_feeding_recipient"||l==="add_feeding_recipients"){window.location.reload();return}}catch(c){await this.showAlert(c instanceof Error?c.message:"Unable to save the health workflow.",!1)}finally{t.removeAttribute("aria-busy"),i&&(i.disabled=!1,i.textContent=a)}},async handleImport(r){const t=r.currentTarget;if(!(t instanceof HTMLFormElement))return;r.preventDefault();const l=t.querySelector('input[type="file"]');if(!l||!(l instanceof HTMLInputElement)||!l.files||l.files.length===0){await this.showAlert("Choose a CSV file before importing measurements.",!1);return}const e=this.config.workflowUrl||"api/health_workflow.php",o=t.querySelector('button[type="submit"], input[type="submit"]'),i=o&&(o.textContent||o.value)||"Import",a=new FormData(t);t.setAttribute("aria-busy","true"),o&&(o.disabled=!0,o.textContent="Importing…");try{const n=await fetch(e,{method:"POST",credentials:"same-origin",body:a}),s=d(await n.text());if(!n.ok||!s||!s.success)throw new Error((s==null?void 0:s.message)||"Unable to import measurements.");await this.showAlert(s.message||"Measurements imported successfully.",!0),window.location.reload()}catch(n){await this.showAlert(n instanceof Error?n.message:"Unable to import measurements.",!1)}finally{t.removeAttribute("aria-busy"),o&&(o.disabled=!1,o.textContent=i)}},showAlert(r,t){return v(r,{variant:t?"success":"error"})},renderDashboardCharts(){const r=document.getElementById("health-portal-visualizations"),t=this.chartData?d(this.chartData.textContent||"{}"):null;if(!r||!t||typeof t!="object")return;const l=[{title:"BMI Remarks Distribution",type:"donut",total:(t.bmi||[]).reduce((e,o)=>e+Number(o.value||0),0),items:t.bmi||[]},{title:"Deworming Status",type:"bar",total:(t.deworming||[]).reduce((e,o)=>e+Number(o.value||0),0),items:t.deworming||[]},{title:"Feeding Program Status",type:"donut",total:(t.feeding||[]).reduce((e,o)=>e+Number(o.value||0),0),items:t.feeding||[]}];r.innerHTML=l.map(e=>{if(e.type==="bar")return Math.max(1,...e.items.map(a=>Number(a.value||0))),`
            <article class="chart-card">
              <h3 class="chart-title">${e.title}</h3>
              <div class="bar-chart-container">
                ${e.items.map(a=>{const n=Number(a.value||0),s=e.total>0?n/Math.max(e.total,1)*100:0;return`
                    <div class="bar-chart-bar" style="height: ${Math.max(8,s)}%; background-color: ${a.color};">
                      <span>${n}</span>
                    </div>
                  `}).join("")}
              </div>
              <div style="display: flex; justify-content: space-around; width: 100%; margin-top: 5px;">
                ${e.items.map(a=>`<div class="bar-chart-label">${a.label}</div>`).join("")}
              </div>
              <div class="chart-legend">
                ${e.items.map(a=>{const n=Number(a.value||0),s=e.total>0?Math.round(n/e.total*100):0;return`
                    <div class="chart-legend-item">
                      <span><span class="chart-legend-color" style="background-color: ${a.color};"></span>${a.label}</span>
                      <strong>${n} (${s}%)</strong>
                    </div>
                  `}).join("")}
              </div>
            </article>
          `;const o=[];let i=0;return e.items.forEach(a=>{const n=Number(a.value||0),s=e.total>0?i+n/e.total*100:0;o.push(`${a.color} ${i}% ${s}%`),i=s}),`
          <article class="chart-card">
            <h3 class="chart-title">${e.title}</h3>
            <div class="pie-chart" style="background: conic-gradient(${o.join(", ")});">
              <span>${e.total} Learners</span>
            </div>
            <div class="chart-legend">
              ${e.items.map(a=>{const n=Number(a.value||0),s=e.total>0?Math.round(n/e.total*100):0;return`
                  <div class="chart-legend-item">
                    <span><span class="chart-legend-color" style="background-color: ${a.color};"></span>${a.label}</span>
                    <strong>${n} (${s}%)</strong>
                  </div>
                `}).join("")}
            </div>
          </article>
        `}).join("")}}},p=document.createElement("div");p.hidden=!0;document.body.appendChild(p);f(w).mount(p);
