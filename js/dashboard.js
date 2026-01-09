document.addEventListener("DOMContentLoaded", () => {

    /* -------------------- ELEMENTS -------------------- */
    const totalServicesEl = document.getElementById("totalServices");     // total spa services
    const activeBookingsEl = document.getElementById("activeBookings");   // currently ongoing sessions
    const upcomingBookingsEl = document.getElementById("upcomingBookings"); // upcoming appointments
    const dueSoonList = document.getElementById("dueSoonList");           // upcoming sessions list
    const loadingSpinner = document.getElementById("loadingSpinner");
    const dueFilter = document.getElementById("dueFilter");

    const navToggle = document.getElementById("navToggle");
    const navMenu = document.getElementById("navMenu");

    let bookingChart, serviceChart;



    /* -------------------- FETCH DASHBOARD DATA -------------------- */
    async function fetchDashboardData(dueDays = 7) {
        loadingSpinner.classList.remove("hidden");

        try {
            const res = await fetch(`php/dashboard_data.php?due_in=${dueDays}`);
            const data = await res.json();

            // Top stats
            totalServicesEl.textContent = data.total_services ?? 0;
            activeBookingsEl.textContent = data.active_bookings ?? 0;
            upcomingBookingsEl.textContent = data.upcoming_bookings ?? 0;

            // Upcoming appointments list
            dueSoonList.innerHTML = "";

            if (data.due_soon?.length) {
                data.due_soon.forEach(b => {
                    const li = document.createElement("li");
                    li.className =
                        "bg-green-50 dark:bg-gray-700 border-l-4 border-green-500 dark:border-green-400 p-4 rounded-lg shadow-sm flex justify-between items-center hover:shadow-md transition-shadow duration-200";

                    const leftDiv = document.createElement("div");
                    leftDiv.className = "flex flex-col";

                    const clientName = document.createElement("span");
                    clientName.textContent = b.client_name;
                    clientName.className = "font-semibold text-gray-800 dark:text-gray-100";

                    const serviceName = document.createElement("span");
                    serviceName.textContent = b.service_name;
                    serviceName.className = "text-gray-600 dark:text-gray-300 text-sm";

                    leftDiv.appendChild(clientName);
                    leftDiv.appendChild(serviceName);

                    const dateObj = new Date(b.appointment_time);
                    const today = new Date();
                    const todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    const dueMidnight = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
                    const diffDays = Math.round((dueMidnight - todayMidnight) / (1000 * 60 * 60 * 24));

                    let displayDate = "";
                    if (diffDays === 0) displayDate = "Today";
                    else if (diffDays === 1) displayDate = "Tomorrow";
                    else {
                        displayDate = dateObj.toLocaleDateString("en-US", {
                            month: "short",
                            day: "numeric",
                            year: "numeric",
                        });
                    }

                    const appointmentDate = document.createElement("span");
                    appointmentDate.textContent = displayDate;
                    appointmentDate.className = "text-sm font-medium text-green-700 dark:text-green-400";

                    li.appendChild(leftDiv);
                    li.appendChild(appointmentDate);

                    dueSoonList.appendChild(li);
                });

            } else {
                dueSoonList.innerHTML =
                    `<li class="text-gray-500 dark:text-gray-400">No appointments in this period.</li>`;
            }


            /* -------------------- RENDER CHARTS -------------------- */
            renderCharts(data.booking_trends, data.service_distribution);

        } catch (err) {
            console.error("Error loading dashboard data:", err);
        } finally {
            loadingSpinner.classList.add("hidden");
        }
    }



    /* -------------------- CHARTS -------------------- */
    function renderCharts(bookingTrends, serviceDist) {
        const bookingCtx = document.getElementById("bookingChart")?.getContext("2d");
        const serviceCtx = document.getElementById("serviceChart")?.getContext("2d");

        bookingTrends = bookingTrends?.length ? bookingTrends : [{ date: "Jan 1", count: 0 }];
        serviceDist = serviceDist?.length ? serviceDist : [{ category: "None", count: 1 }];

        // BOOKINGS PER DAY (bar chart)
        if (bookingChart) {
            bookingChart.data.labels = bookingTrends.map(b => b.date);
            bookingChart.data.datasets[0].data = bookingTrends.map(b => b.count);
            bookingChart.update();
        } else if (bookingCtx) {
            bookingChart = new Chart(bookingCtx, {
                type: "bar",
                data: {
                    labels: bookingTrends.map(b => b.date),
                    datasets: [
                        {
                            label: "Bookings per Day",
                            data: bookingTrends.map(b => b.count),
                            backgroundColor: "rgba(59,130,246,0.7)",
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, precision: 0 } },
                },
            });
        }


        // SERVICE CATEGORY DISTRIBUTION (pie chart)
        if (serviceChart) {
            serviceChart.data.labels = serviceDist.map(s => s.category);
            serviceChart.data.datasets[0].data = serviceDist.map(s => s.count);
            serviceChart.update();

        } else if (serviceCtx) {
            serviceChart = new Chart(serviceCtx, {
                type: "pie",
                data: {
                    labels: serviceDist.map(s => s.category),
                    datasets: [
                        {
                            data: serviceDist.map(s => s.count),
                            backgroundColor: [
                                "rgba(16,185,129,0.7)",
                                "rgba(249,115,22,0.7)",
                                "rgba(239,68,68,0.7)",
                                "rgba(139,92,246,0.7)",
                            ],
                        },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }
    }



    /* -------------------- INITIAL LOAD -------------------- */
    fetchDashboardData();


    /* -------------------- FILTER -------------------- */
    if (dueFilter) {
        dueFilter.addEventListener("change", (e) => {
            fetchDashboardData(parseInt(e.target.value));
        });
    }


    /* -------------------- MOBILE NAV -------------------- */
    if (navToggle && navMenu) {
        navToggle.addEventListener("click", () => {
            navMenu.classList.toggle("open");
            navToggle.classList.toggle("active");
        });
    }

});
