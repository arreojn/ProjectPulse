(function () {
    const liveTime = document.getElementById('live-time');
    const liveDate = document.getElementById('live-date');
    const searchInput = document.getElementById('lrn-search');
    const learnerCard = document.getElementById('learner-card');

    if (!searchInput) {
        return;
    }

    const state = {
        activeLrn: '',
        lookupTimer: null,
        photoResetTimer: null,
        processing: false,
    };
    const scanModeState = Object.assign({
        key: 'strict_windows',
        label: 'Strict Time Windows',
        description: 'Follows the configured AM and PM attendance windows.',
        canEdit: false,
    }, window.ProjectPulse.scanMode || {});

    const fields = {
        name: document.getElementById('learner-name'),
        lrn: document.getElementById('learner-lrn'),
        grade: document.getElementById('learner-grade'),
        section: document.getElementById('learner-section'),
        schoolYear: document.getElementById('learner-school-year'),
        status: document.getElementById('learner-status'),
        amTimeIn: document.getElementById('learner-am-time-in'),
        amTimeOut: document.getElementById('learner-am-time-out'),
        pmTimeIn: document.getElementById('learner-pm-time-in'),
        pmTimeOut: document.getElementById('learner-pm-time-out'),
        attendanceLogsBody: document.getElementById('attendance-logs-body'),
        scanModeValue: document.getElementById('scan-mode-value'),
        scanModeDescription: document.getElementById('scan-mode-description'),
        scanModeToggle: document.getElementById('scan-mode-toggle'),
        scanModeFeedback: document.getElementById('scan-mode-feedback'),
        learnerPhoto: document.getElementById('learner-photo'),
    };
    const learnerPhotoExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    const defaultLearnerPhotoUrl = window.ProjectPulse.defaultLearnerPhotoUrl || '';

    const clearInputForNextScan = function () {
        searchInput.value = '';
        searchInput.focus();
    };

    const showInputMessage = function (message) {
        searchInput.setCustomValidity(message || '');

        if (message) {
            searchInput.reportValidity();
        }
    };

    const setScanModeFeedback = function (message, isError) {
        if (!fields.scanModeFeedback) {
            return;
        }

        fields.scanModeFeedback.textContent = message || '';
        fields.scanModeFeedback.classList.toggle('is-error', Boolean(isError));
        fields.scanModeFeedback.classList.toggle('is-success', Boolean(message) && !isError);
    };

    const renderScanMode = function () {
        if (fields.scanModeValue) {
            fields.scanModeValue.textContent = scanModeState.label;
        }

        if (fields.scanModeDescription) {
            fields.scanModeDescription.textContent = scanModeState.description;
        }

        if (fields.scanModeToggle) {
            fields.scanModeToggle.checked = scanModeState.key === 'am_pm_sequence';
            fields.scanModeToggle.disabled = !scanModeState.canEdit;
        }
    };

    const setDefaultLearnerPhoto = function () {
        if (fields.learnerPhoto && defaultLearnerPhotoUrl !== '') {
            fields.learnerPhoto.onerror = null;
            fields.learnerPhoto.src = defaultLearnerPhotoUrl;
        }
    };

    const clearPhotoResetTimer = function () {
        if (state.photoResetTimer !== null) {
            window.clearTimeout(state.photoResetTimer);
            state.photoResetTimer = null;
        }
    };

    const schedulePhotoResetAfterScanIdle = function () {
        clearPhotoResetTimer();

        state.photoResetTimer = window.setTimeout(function () {
            setDefaultLearnerPhoto();
            state.photoResetTimer = null;
        }, 2000);
    };

    const loadLearnerPhoto = function (lrn) {
        if (!fields.learnerPhoto || !window.ProjectPulse.learnerPhotoBaseUrl) {
            return;
        }

        let extensionIndex = 0;

        const tryNextPhoto = function () {
            if (extensionIndex >= learnerPhotoExtensions.length) {
                setDefaultLearnerPhoto();
                return;
            }

            const extension = learnerPhotoExtensions[extensionIndex];
            const photoUrl = window.ProjectPulse.learnerPhotoBaseUrl + encodeURIComponent(lrn) + '.' + extension;
            extensionIndex += 1;

            fields.learnerPhoto.onerror = tryNextPhoto;
            fields.learnerPhoto.onload = function () {
                fields.learnerPhoto.onerror = null;
            };
            fields.learnerPhoto.src = photoUrl;
        };

        tryNextPhoto();
    };

    const setLearnerEmpty = function (message) {
        learnerCard.classList.add('is-empty');
        fields.name.textContent = 'No learner selected';
        fields.lrn.textContent = message;
        fields.grade.textContent = '-';
        fields.section.textContent = '-';
        fields.schoolYear.textContent = '-';
        fields.status.textContent = 'No record yet';
        fields.amTimeIn.textContent = '-';
        fields.amTimeOut.textContent = '-';
        fields.pmTimeIn.textContent = '-';
        fields.pmTimeOut.textContent = '-';
        setDefaultLearnerPhoto();
        state.activeLrn = '';
    };

    const renderAttendanceLogs = function (rows) {
        if (!rows.length) {
            fields.attendanceLogsBody.innerHTML = '<tr><td colspan="6" class="empty-row">No attendance logs to display yet.</td></tr>';
            return;
        }

        fields.attendanceLogsBody.innerHTML = rows.map(function (row) {
            return [
                '<tr>',
                '<td>' + row.log_date + '</td>',
                '<td>' + row.log_time + '</td>',
                '<td>' + row.learner_name + '</td>',
                '<td>' + row.lrn + '</td>',
                '<td>' + row.grade_section + '</td>',
                '<td><span class="table-status">' + row.log_entry + '</span></td>',
                '</tr>',
            ].join('');
        }).join('');
    };

    const setLearnerData = function (learner) {
        learnerCard.classList.remove('is-empty');
        fields.name.textContent = learner.name;
        fields.lrn.textContent = 'LRN: ' + learner.lrn;
        fields.grade.textContent = learner.grade_level;
        fields.section.textContent = learner.section;
        fields.schoolYear.textContent = learner.school_year;
        fields.status.textContent = learner.attendance_status;
        fields.amTimeIn.textContent = learner.am_time_in || '-';
        fields.amTimeOut.textContent = learner.am_time_out || '-';
        fields.pmTimeIn.textContent = learner.pm_time_in || '-';
        fields.pmTimeOut.textContent = learner.pm_time_out || '-';
        loadLearnerPhoto(learner.lrn);
        state.activeLrn = learner.lrn;
    };

    const formatNow = function () {
        const now = new Date();

        liveTime.textContent = now.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });

        liveDate.textContent = now.toLocaleDateString([], {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const fetchLearner = function (lrn) {
        return fetch(window.ProjectPulse.lookupUrl + '?lrn=' + encodeURIComponent(lrn), {
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.message || 'Learner lookup failed.');
                }

                setLearnerData(result.data.learner);
                return result.data.learner;
            });
    };

    const fetchAttendanceLogs = function () {
        return fetch(window.ProjectPulse.attendanceLogsUrl, {
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.message || 'Unable to load attendance logs.');
                }

                renderAttendanceLogs(result.data.logs || []);
            })
            .catch(function () {
                renderAttendanceLogs([]);
            });
    };

    const processScan = function (lrn) {
        if (state.processing || lrn.length !== 12) {
            return;
        }

        clearPhotoResetTimer();
        state.processing = true;
        showInputMessage('');

        fetch(window.ProjectPulse.attendanceEventUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                lrn: lrn,
                csrf_token: window.ProjectPulse.csrfToken,
            }).toString(),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    const error = new Error(result.data.message || 'Scan failed.');
                    error.status = result.status;
                    throw error;
                }

                return Promise.all([
                    fetchLearner(lrn),
                    fetchAttendanceLogs(),
                ]).then(function () {
                    showInputMessage(result.data.message || '');
                    clearInputForNextScan();
                    schedulePhotoResetAfterScanIdle();
                });
            })
            .catch(function (error) {
                if (error.status !== 404) {
                    fetchLearner(lrn).catch(function () {
                        return null;
                    });
                } else {
                    setLearnerEmpty(error.message);
                }

                showInputMessage(error.message);
                searchInput.focus();
                searchInput.select();
            })
            .finally(function () {
                state.processing = false;
            });
    };

    const updateScanMode = function (mode) {
        if (!fields.scanModeToggle || !scanModeState.canEdit) {
            return;
        }

        fields.scanModeToggle.disabled = true;
        setScanModeFeedback('Updating scan mode...', false);

        fetch(window.ProjectPulse.scanModeUpdateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                mode: mode,
                csrf_token: window.ProjectPulse.csrfToken,
            }).toString(),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.message || 'Unable to update scan mode.');
                }

                scanModeState.key = result.data.mode;
                scanModeState.label = result.data.label;
                scanModeState.description = result.data.description;
                renderScanMode();
                setScanModeFeedback(result.data.message || 'Scan mode updated successfully.', false);
            })
            .catch(function (error) {
                renderScanMode();
                setScanModeFeedback(error.message, true);
            })
            .finally(function () {
                if (fields.scanModeToggle) {
                    fields.scanModeToggle.disabled = !scanModeState.canEdit;
                }
            });
    };

    searchInput.focus();
    formatNow();
    window.setInterval(formatNow, 1000);
    setLearnerEmpty('Search by LRN to load learner information.');
    renderScanMode();
    fetchAttendanceLogs();

    searchInput.addEventListener('input', function () {
        const value = searchInput.value.replace(/\D/g, '').slice(0, 12);
        searchInput.value = value;
        searchInput.setCustomValidity('');

        window.clearTimeout(state.lookupTimer);

        if (value.length === 12) {
            state.lookupTimer = window.setTimeout(function () {
                processScan(value);
            }, 120);
        }
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(state.lookupTimer);

            const value = searchInput.value.replace(/\D/g, '').slice(0, 12);
            searchInput.value = value;

            if (value.length === 12) {
                processScan(value);
            } else {
                showInputMessage('LRN must be exactly 12 digits.');
            }
        }
    });

    if (fields.scanModeToggle && scanModeState.canEdit) {
        fields.scanModeToggle.addEventListener('change', function () {
            updateScanMode(fields.scanModeToggle.checked ? 'am_pm_sequence' : 'strict_windows');
        });
    }
}());
