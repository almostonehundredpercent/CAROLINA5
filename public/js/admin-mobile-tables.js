(() => {
    const enhanceTables = () => {
        document.querySelectorAll('.admin-table-wrap table:not(.booking-table):not(.staff-access-table), .report-table-wrap table').forEach(table => {
            const headerRow = table.tHead?.rows?.[table.tHead.rows.length - 1];
            const headers = headerRow ? [...headerRow.cells].map(cell => cell.textContent.trim()) : [];
            if (!headers.length) return;

            table.classList.add('mobile-stack-table');
            table.parentElement?.classList.add('mobile-stack-wrap');

            table.querySelectorAll('tbody tr').forEach(row => {
                const cells = [...row.cells];
                if (cells.length === 1 && cells[0].colSpan > 1) {
                    row.classList.add('mobile-table-empty');
                    return;
                }

                let columnIndex = 0;
                cells.forEach(cell => {
                    if (!cell.dataset.label && headers[columnIndex]) {
                        cell.dataset.label = headers[columnIndex];
                    }
                    columnIndex += cell.colSpan || 1;
                });
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceTables, { once: true });
    } else {
        enhanceTables();
    }
})();
