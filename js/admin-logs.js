document.addEventListener("DOMContentLoaded", () => {
    const logsTableBody = document.getElementById("logsTableBody");
    const paginationControls = document.getElementById("paginationControls");
    const searchInput = document.getElementById("searchLogs");
    const filterAction = document.getElementById("filterAction");
    const startDate = document.getElementById("startDate");
    const endDate = document.getElementById("endDate");
    const exportBtn = document.getElementById("exportLogs");

    let currentPage = 1;
    let totalPages = 1;

    // Populate action filter dropdown
    fetch('php/fetch-log-actions.php')
        .then(res => res.json())
        .then(data => {
            data.actions.forEach(action => {
                const option = document.createElement('option');
                option.value = action;
                option.textContent = action;
                filterAction.appendChild(option);
            });
        });

    function fetchLogs(page = 1) {
        currentPage = page;

        const params = new URLSearchParams({
            search: searchInput.value,
            action: filterAction.value,
            startDate: startDate.value,
            endDate: endDate.value,
            page
        });

        fetch(`php/fetch-logs.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                logsTableBody.innerHTML = '';
                if (data.logs.length === 0) {
                    logsTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">No logs found.</td></tr>`;
                } else {
                    data.logs.forEach(log => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">${log.id}</td>
                            <td class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">${log.username || 'System'}</td>
                            <td class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">${log.action}</td>
                            <td class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">${log.description}</td>
                            <td class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">${log.created_at}</td>
                        `;
                        logsTableBody.appendChild(tr);
                    });
                }

                // Pagination
                totalPages = data.totalPages;
                paginationControls.innerHTML = '';
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.textContent = i;
                    btn.className = `px-3 py-1 rounded ${i === currentPage ? 'bg-purple-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'} hover:bg-purple-400 transition`;
                    btn.onclick = () => fetchLogs(i);
                    paginationControls.appendChild(btn);
                }
            });
    }

    // Event listeners for filters
    [searchInput, filterAction, startDate, endDate].forEach(el => {
        el.addEventListener('change', () => fetchLogs(1));
    });

    // CSV Export in JS
    exportBtn.addEventListener('click', async () => {
        const params = new URLSearchParams({
            search: searchInput.value,
            action: filterAction.value,
            startDate: startDate.value,
            endDate: endDate.value,
            page: 1,        // fetch first page
            limit: 0        // special flag to fetch ALL logs (update PHP to respect limit=0)
        });

        const res = await fetch(`php/fetch-logs.php?${params.toString()}`);
        const data = await res.json();

        if (!data.logs.length) {
            alert("No logs to export.");
            return;
        }

        // Convert logs to CSV string
        const headers = ['ID', 'User', 'Action', 'Description', 'Timestamp'];
        const csvRows = [headers.join(',')];

        data.logs.forEach(log => {
            const row = [
                log.id,
                `"${log.username || 'System'}"`,
                `"${log.action}"`,
                `"${log.description.replace(/"/g, '""')}"`,
                log.created_at
            ];
            csvRows.push(row.join(','));
        });

        const csvString = csvRows.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = `activity_logs_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    });

    fetchLogs();
});
