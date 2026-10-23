document.addEventListener('DOMContentLoaded', () => {
    const clock = document.querySelector('[data-clock]');
    if (clock) {
        const updateClock = () => {
            clock.textContent = new Intl.DateTimeFormat('id-ID', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
            }).format(new Date());
        };
        updateClock();
        window.setInterval(updateClock, 1000);
    }

    const sidebar = document.querySelector('#sidebar');
    const scrim = document.querySelector('[data-menu-close]');
    document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
        sidebar?.classList.toggle('sidebar-open');
        scrim?.classList.toggle('scrim-visible');
    });
    scrim?.addEventListener('click', () => {
        sidebar?.classList.remove('sidebar-open');
        scrim.classList.remove('scrim-visible');
    });

    document.querySelectorAll('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.alert')?.remove());
    });
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('form[data-static-demo]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            let notice = form.querySelector('[data-demo-notice]');
            if (!notice) {
                notice = document.createElement('p');
                notice.className = 'alert alert-success';
                notice.dataset.demoNotice = '';
                notice.setAttribute('role', 'status');
                form.append(notice);
            }
            notice.textContent = 'Mode pratinjau statis: perubahan ini tidak disimpan.';
        });
    });

    document.querySelectorAll('[data-export-csv]').forEach((button) => {
        button.addEventListener('click', () => {
            const table = button.closest('.panel')?.querySelector('table');
            if (!table) return;
            const rows = Array.from(table.rows, (row) => Array.from(row.cells, (cell) => {
                const value = cell.textContent.trim().replace(/\s+/g, ' ');
                return `"${value.replace(/"/g, '""')}"`;
            }).join(','));
            const blob = new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'laporan-ruanghadir-demo.csv';
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 0);
        });
    });

    document.querySelectorAll('input[type="date"][name="end_date"]').forEach((endDate) => {
        const startDate = endDate.closest('form')?.querySelector('input[name="start_date"]');
        startDate?.addEventListener('change', () => {
            endDate.min = startDate.value;
            if (endDate.value && endDate.value < startDate.value) endDate.value = startDate.value;
        });
    });
});