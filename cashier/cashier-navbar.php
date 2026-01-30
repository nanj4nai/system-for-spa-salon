    <!-- ================= TOP BAR ================= -->
    <header class="h-14 bg-white dark:bg-gray-800 shadow flex items-center justify-between px-6">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-3">
                <img
                    src="<?= htmlspecialchars('../' . $company_logo) ?>?v=<?= time() ?>"
                    class="h-8 w-8 rounded-lg object-cover"
                    alt="Company Logo" />

                <span class="font-semibold text-lg">
                    <?= htmlspecialchars($company_name) ?>
                </span>

                <!-- SHIFT BADGE -->
                <span id="shiftBadge"
                    class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">
                    NO SHIFT
                </span>
                <!-- DATE & TIME -->
                <div id="dateTime"
                    class="hidden sm:flex items-center gap-2
            text-xs px-3 py-1 rounded-full
            bg-gray-100 dark:bg-gray-700
            text-gray-700 dark:text-gray-200">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <span id="dateTimeText">--</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 text-sm">
            <!-- HELP / TIPS -->
            <button
                id="helpToggle"
                class="flex items-center gap-1 text-xs px-3 py-1 rounded-full
                bg-teal-100 text-teal-700
                dark:bg-teal-900/40 dark:text-teal-300
                hover:opacity-90 transition">

                <i id="helpIcon" data-lucide="circle-question-mark" class="w-4 h-4"></i>
                Tips
            </button>
            <!-- REFRESH -->
            <button id="refreshBtn"
                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                title="Refresh page">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>

            <!-- THEME -->
            <button id="themeToggle"
                class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                aria-label="Toggle theme">
                <span id="themeIcon"></span>
            </button>

            <!-- USER -->
            <div class="flex items-center gap-2 text-sm">
                <i data-lucide="user" class="w-4 h-4"></i>
                <span class="opacity-70">Cashier</span>
                <span class="font-medium"><?= htmlspecialchars($username) ?></span>
            </div>

            <!-- LOGOUT -->
            <form action="../php/logout.php" method="POST">
                <button
                    type="submit"
                    class="flex items-center gap-1 text-xs px-3 py-1 rounded-full
               bg-gray-200 text-gray-700
               hover:bg-red-100 hover:text-red-600
               dark:bg-gray-700 dark:text-gray-200
               dark:hover:bg-red-900/40 transition"
                    title="Logout">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    Logout
                </button>
            </form>


            <!-- CLOSE SHIFT -->
            <button id="closeShiftBtn"
                class="hidden text-xs px-3 py-1 rounded bg-red-500 text-white">
                Close Shift
            </button>
        </div>

    </header>