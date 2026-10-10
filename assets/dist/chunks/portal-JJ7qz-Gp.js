import{c as f}from"./vue.esm-bundler-DlDjmn_t.js";import{m as b}from"./status-zckm43l8.js";const v=()=>{const o=document.getElementById("health-portal-config");if(!o)return{};try{return JSON.parse(o.textContent||"{}")}catch{return{}}},d=o=>{try{return JSON.parse(o)}catch{return null}},y={mounted(){this.config=v(),this.searchInputs=Array.from(document.querySelectorAll("[data-health-learner-search]")),this.forms=Array.from(document.querySelectorAll("[data-vue-health-action]")),this.importForms=Array.from(document.querySelectorAll("[data-vue-health-import]")),this.chartData=document.getElementById("health-dashboard-chart-data"),this.boundSearch=this.handleSearch.bind(this),this.boundAction=this.handleAction.bind(this),this.boundImport=this.handleImport.bind(this),this.searchInputs.forEach(o=>o.addEventListener("input",this.boundSearch)),this.forms.forEach(o=>o.addEventListener("submit",this.boundAction)),this.importForms.forEach(o=>o.addEventListener("submit",this.boundImport)),this.renderDashboardCharts(),b()},beforeUnmount(){var o,t,i;(o=this.searchInputs)==null||o.forEach(e=>e.removeEventListener("input",this.boundSearch)),(t=this.forms)==null||t.forEach(e=>e.removeEventListener("submit",this.boundAction)),(i=this.importForms)==null||i.forEach(e=>e.removeEventListener("submit",this.boundImport))},methods:{handleSearch(o){var s,m;const t=o.currentTarget,e=(t.closest("article")||t.closest("section")||((s=t.closest("form"))==null?void 0:s.parentElement)||document).querySelector("table");if(!e)return;const r=t.value.trim().toLowerCase(),l=Array.from(e.querySelectorAll("tbody tr"));let n=0;l.forEach(c=>{const h=c.querySelector(".empty-row")||r===""||c.textContent.toLowerCase().includes(r);c.hidden=!h,h&&(n+=1)});const a=(m=t.parentElement)==null?void 0:m.querySelector("[data-health-learner-search-status]");a&&(a.textContent=r===""?`Showing ${n} learner(s).`:`Showing ${n} learner(s) matching "${t.value.trim()}".`)},async handleAction(o){var a,s,m;const t=o.currentTarget;if(!(t instanceof HTMLFormElement))return;o.preventDefault();const i=t.dataset.vueHealthAction||t.getAttribute("data-vue-health-action")||((a=t.elements.namedItem("form_action"))==null?void 0:a.value),e=this.config.workflowUrl||"api/health_workflow.php",r=(s=t.elements.namedItem("csrf_token"))==null?void 0:s.value;if(!i||!r)return;const l=t.querySelector('button[type="submit"], input[type="submit"]'),n=l&&(l.textContent||l.value)||"Save";t.setAttribute("aria-busy","true"),l&&(l.disabled=!0,l.textContent="Saving…");try{const c=await fetch(e,{method:"POST",credentials:"same-origin",body:new FormData(t)}),u=d(await c.text());if(!c.ok||!u||!u.success)throw new Error((u==null?void 0:u.message)||"Unable to save the health workflow.");if(this.showAlert(u.message||"Health record updated.",!0),i==="save_measurement"&&u.bmi!==void 0){const h=(m=t.closest("tr"))==null?void 0:m.querySelector("[data-vue-bmi-output]");h&&(h.textContent=Number(u.bmi).toFixed(2))}if(i==="remove_feeding_recipient"||i==="add_feeding_recipients"){window.setTimeout(()=>window.location.reload(),200);return}}catch(c){this.showAlert(c instanceof Error?c.message:"Unable to save the health workflow.",!1)}finally{t.removeAttribute("aria-busy"),l&&(l.disabled=!1,l.textContent=n)}},async handleImport(o){const t=o.currentTarget;if(!(t instanceof HTMLFormElement))return;o.preventDefault();const i=t.querySelector('input[type="file"]');if(!i||!(i instanceof HTMLInputElement)||!i.files||i.files.length===0){this.showAlert("Choose a CSV file before importing measurements.",!1);return}const e=this.config.workflowUrl||"api/health_workflow.php",r=t.querySelector('button[type="submit"], input[type="submit"]'),l=r&&(r.textContent||r.value)||"Import",n=new FormData(t);t.setAttribute("aria-busy","true"),r&&(r.disabled=!0,r.textContent="Importing…");try{const a=await fetch(e,{method:"POST",credentials:"same-origin",body:n}),s=d(await a.text());if(!a.ok||!s||!s.success)throw new Error((s==null?void 0:s.message)||"Unable to import measurements.");this.showAlert(s.message||"Measurements imported successfully.",!0),window.setTimeout(()=>window.location.reload(),300)}catch(a){this.showAlert(a instanceof Error?a.message:"Unable to import measurements.",!1)}finally{t.removeAttribute("aria-busy"),r&&(r.disabled=!1,r.textContent=l)}},showAlert(o,t){const i=document.querySelector(".alert");if(i){i.className=`alert ${t?"success":"error"}`,i.textContent=o;return}const e=document.createElement("div");e.className=`alert ${t?"success":"error"}`,e.textContent=o;const r=document.querySelector(".admin-main-panel");r&&r.insertBefore(e,r.firstChild)},renderDashboardCharts(){const o=document.getElementById("health-portal-visualizations"),t=this.chartData?d(this.chartData.textContent||"{}"):null;if(!o||!t||typeof t!="object")return;const i=[{title:"BMI Remarks Distribution",type:"donut",total:(t.bmi||[]).reduce((e,r)=>e+Number(r.value||0),0),items:t.bmi||[]},{title:"Deworming Status",type:"bar",total:(t.deworming||[]).reduce((e,r)=>e+Number(r.value||0),0),items:t.deworming||[]},{title:"Feeding Program Status",type:"donut",total:(t.feeding||[]).reduce((e,r)=>e+Number(r.value||0),0),items:t.feeding||[]}];o.innerHTML=i.map(e=>{if(e.type==="bar")return Math.max(1,...e.items.map(n=>Number(n.value||0))),`
            <article class="chart-card">
              <h3 class="chart-title">${e.title}</h3>
              <div class="bar-chart-container">
                ${e.items.map(n=>{const a=Number(n.value||0),s=e.total>0?a/Math.max(e.total,1)*100:0;return`
                    <div class="bar-chart-bar" style="height: ${Math.max(8,s)}%; background-color: ${n.color};">
                      <span>${a}</span>
                    </div>
                  `}).join("")}
              </div>
              <div style="display: flex; justify-content: space-around; width: 100%; margin-top: 5px;">
                ${e.items.map(n=>`<div class="bar-chart-label">${n.label}</div>`).join("")}
              </div>
              <div class="chart-legend">
                ${e.items.map(n=>{const a=Number(n.value||0),s=e.total>0?Math.round(a/e.total*100):0;return`
                    <div class="chart-legend-item">
                      <span><span class="chart-legend-color" style="background-color: ${n.color};"></span>${n.label}</span>
                      <strong>${a} (${s}%)</strong>
                    </div>
                  `}).join("")}
              </div>
            </article>
          `;const r=[];let l=0;return e.items.forEach(n=>{const a=Number(n.value||0),s=e.total>0?l+a/e.total*100:0;r.push(`${n.color} ${l}% ${s}%`),l=s}),`
          <article class="chart-card">
            <h3 class="chart-title">${e.title}</h3>
            <div class="pie-chart" style="background: conic-gradient(${r.join(", ")});">
              <span>${e.total} Learners</span>
            </div>
            <div class="chart-legend">
              ${e.items.map(n=>{const a=Number(n.value||0),s=e.total>0?Math.round(a/e.total*100):0;return`
                  <div class="chart-legend-item">
                    <span><span class="chart-legend-color" style="background-color: ${n.color};"></span>${n.label}</span>
                    <strong>${a} (${s}%)</strong>
                  </div>
                `}).join("")}
            </div>
          </article>
        `}).join("")}}},p=document.createElement("div");p.hidden=!0;document.body.appendChild(p);f(y).mount(p);
