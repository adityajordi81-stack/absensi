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

    document.querySelectorAll('input[type="date"][name="end_date"]').forEach((endDate) => {
        const startDate = endDate.closest('form')?.querySelector('input[name="start_date"]');
        startDate?.addEventListener('change', () => {
            endDate.min = startDate.value;
            if (endDate.value && endDate.value < startDate.value) endDate.value = startDate.value;
        });
    });
});