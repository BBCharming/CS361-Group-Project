// assets/js/ajax-poll.js
// Victim case-tracker poller. Targets the table/stat elements on
// victim-dashboard.html; hits victim/dashboard.php every 30s so a
// reporter sees their case status update without refreshing the page.
(function () {
    const POLL_MS = 30000;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function statusBadgeClass(status) {
        if (status === 'pending') return 'status-warning';
        if (status === 'investigating') return 'status-info';
        return 'status-success'; // resolved / closed
    }

    function renderCases(incidents) {
        const tbody = document.getElementById('pollTableBody');
        const countPill = document.getElementById('pollCountPill');
        const statTotal = document.getElementById('pollStatTotal');
        const statOpen = document.getElementById('pollStatOpen');
        const statResolved = document.getElementById('pollStatResolved');

        if (!tbody) return; // not on a page that uses this poller

        if (countPill) {
            countPill.textContent = incidents.length + (incidents.length === 1 ? ' case' : ' cases');
        }

        if (statTotal) statTotal.textContent = incidents.length;
        if (statOpen) {
            statOpen.textContent = incidents.filter(function (i) {
                return i.incident_status === 'pending' || i.incident_status === 'investigating';
            }).length;
        }
        if (statResolved) {
            statResolved.textContent = incidents.filter(function (i) {
                return i.incident_status === 'resolved' || i.incident_status === 'closed';
            }).length;
        }

        if (incidents.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No reports filed yet.</td></tr>';
            return;
        }

        tbody.innerHTML = incidents.map(function (inc) {
            return '<tr>' +
                '<td>' + escapeHtml(inc.title) + '</td>' +
                '<td>' + escapeHtml(inc.category_name) + '</td>' +
                '<td><span class="status-badge ' + statusBadgeClass(inc.incident_status) + '">' + escapeHtml(inc.incident_status) + '</span></td>' +
                '<td>' + (inc.evidence_count != null ? inc.evidence_count : 0) + '</td>' +
                '<td>' + escapeHtml(inc.updated_at) + '</td>' +
                '</tr>';
        }).join('');
    }

    function poll() {
        fetch('victim/dashboard.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (res.status === 401 || res.status === 403) {
                    window.location.href = 'login.html';
                    return null;
                }
                return res.json();
            })
            .then(function (data) {
                if (!data) return;
                if (data.success) {
                    renderCases(data.incidents || []);
                } else {
                    const tbody = document.getElementById('pollTableBody');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">' +
                            escapeHtml(data.error || 'Could not load your cases.') + '</td></tr>';
                    }
                }
            })
            .catch(function () {
                const tbody = document.getElementById('pollTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Network error loading your cases.</td></tr>';
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('pollTableBody')) return; // only run where it's used
        poll();
        setInterval(poll, POLL_MS);
    });

    // Lets other scripts on the page (e.g. the report-case form) force
    // an immediate refresh right after a new report is filed, instead
    // of waiting for the next 30s tick.
    window.zatcherRefreshCaseTracker = poll;
})();
