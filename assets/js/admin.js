(function () {
    const toggleButton = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const mobileQuery = window.matchMedia('(max-width: 1200px)');
    const storageKey = 'adminSidebarCollapsed';
    const sidebarLabel = toggleButton ? (toggleButton.getAttribute('data-sidebar-label') || 'menu') : 'menu';

    if (!toggleButton || !sidebar || !backdrop) {
        return;
    }

    function setMobileState(isOpen) {
        document.body.classList.toggle('sidebar-open', isOpen);
        backdrop.hidden = !isOpen;
        sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }

    function setDesktopState(isCollapsed) {
        document.body.classList.toggle('sidebar-collapsed', isCollapsed);
        sidebar.setAttribute('aria-hidden', isCollapsed ? 'true' : 'false');
        localStorage.setItem(storageKey, isCollapsed ? 'true' : 'false');
    }

    function syncForViewport() {
        if (mobileQuery.matches) {
            document.body.classList.remove('sidebar-collapsed');
            toggleButton.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open') ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', document.body.classList.contains('sidebar-open') ? 'Close ' + sidebarLabel : 'Open ' + sidebarLabel);

            if (!document.body.classList.contains('sidebar-open')) {
                backdrop.hidden = true;
                sidebar.setAttribute('aria-hidden', 'true');
            }

            return;
        }

        setMobileState(false);

        const isCollapsed = localStorage.getItem(storageKey) === 'true';
        setDesktopState(isCollapsed);
        toggleButton.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        toggleButton.setAttribute('aria-label', isCollapsed ? 'Expand ' + sidebarLabel : 'Collapse ' + sidebarLabel);
    }

    toggleButton.addEventListener('click', function () {
        if (mobileQuery.matches) {
            const isOpening = !document.body.classList.contains('sidebar-open');
            setMobileState(isOpening);
            toggleButton.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', isOpening ? 'Close ' + sidebarLabel : 'Open ' + sidebarLabel);
            return;
        }

        const isCollapsed = !document.body.classList.contains('sidebar-collapsed');
        setDesktopState(isCollapsed);
        toggleButton.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        toggleButton.setAttribute('aria-label', isCollapsed ? 'Expand ' + sidebarLabel : 'Collapse ' + sidebarLabel);
    });

    backdrop.addEventListener('click', function () {
        setMobileState(false);
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Open ' + sidebarLabel);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            setMobileState(false);
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.setAttribute('aria-label', 'Open ' + sidebarLabel);
        }
    });

    sidebar.addEventListener('click', function (event) {
        if (!mobileQuery.matches) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest('a')) {
            setMobileState(false);
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.setAttribute('aria-label', 'Open ' + sidebarLabel);
        }
    });

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', syncForViewport);
    } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(syncForViewport);
    }

    const reportTypeSelect = document.getElementById('report_type');
    const reportFilterForm = document.getElementById('report-filter-form');

    function syncReportFilters() {
        if (!reportTypeSelect || !reportFilterForm) {
            return;
        }

        const rawMap = reportFilterForm.getAttribute('data-report-filter-map');
        if (!rawMap) {
            return;
        }

        let filterMap = {};

        try {
            filterMap = JSON.parse(rawMap);
        } catch (error) {
            return;
        }

        const visibleFilters = filterMap[reportTypeSelect.value] || [];
        const filterFields = reportFilterForm.querySelectorAll('[data-report-filter-key]');

        filterFields.forEach(function (field) {
            const filterKey = field.getAttribute('data-report-filter-key');
            const shouldShow = filterKey === 'report_type' || visibleFilters.includes(filterKey);
            field.hidden = !shouldShow;

            const controls = field.querySelectorAll('input, select');
            controls.forEach(function (control) {
                if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
                    if (control.name === 'module' || control.name === 'report_type') {
                        control.disabled = false;
                        return;
                    }

                    control.disabled = !shouldShow;
                }
            });
        });
    }

    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', syncReportFilters);
        syncReportFilters();
    }

    function parseIsoDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!match) {
            return null;
        }

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);

        if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
            return null;
        }

        return { year, month, day };
    }

    function calculateAgeOnReferenceDate(birthdateValue, referenceDateValue) {
        const birthdate = parseIsoDate(birthdateValue);
        const referenceDate = parseIsoDate(referenceDateValue);

        if (!birthdate || !referenceDate) {
            return null;
        }

        if (
            birthdate.year > referenceDate.year ||
            (birthdate.year === referenceDate.year && birthdate.month > referenceDate.month) ||
            (birthdate.year === referenceDate.year && birthdate.month === referenceDate.month && birthdate.day > referenceDate.day)
        ) {
            return null;
        }

        let age = referenceDate.year - birthdate.year;

        if (
            referenceDate.month < birthdate.month ||
            (referenceDate.month === birthdate.month && referenceDate.day < birthdate.day)
        ) {
            age -= 1;
        }

        return age >= 0 ? age : null;
    }

    function syncAgeDisplays() {
        const ageInputs = document.querySelectorAll('input[type="date"][data-age-target][data-age-reference-date]');

        ageInputs.forEach(function (input) {
            const targetId = input.getAttribute('data-age-target');
            const referenceDate = input.getAttribute('data-age-reference-date');

            if (!targetId || !referenceDate) {
                return;
            }

            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }

            const render = function () {
                const age = calculateAgeOnReferenceDate(input.value, referenceDate);
                target.textContent = age === null ? '-' : String(age);
            };

            input.addEventListener('input', render);
            input.addEventListener('change', render);
            render();
        });
    }

    syncAgeDisplays();
    syncForViewport();

    function initAnnouncementModal() {
        const modal = document.getElementById('announcement-modal');
        const closeButton = document.getElementById('close-announcement-modal');

        if (!modal || !closeButton) {
            return;
        }

        function closeModal() {
            modal.classList.remove('is-open');
        }

        closeButton.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    initAnnouncementModal();
})();
