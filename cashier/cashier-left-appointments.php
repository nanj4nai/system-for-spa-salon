<aside class="w-72 bg-white dark:bg-gray-800 rounded-xl
    border border-gray-200 dark:border-gray-700
    p-4 overflow-y-auto shadow-sm">

    <!-- Tip -->
    <div class="panel-tip hidden text-xs mb-4
        bg-teal-50 dark:bg-gray-700
        text-teal-700 dark:text-teal-300
        p-3 rounded-lg flex items-start gap-2">

        <i data-lucide="corner-left-up" class="w-4 h-4 mt-0.5"></i>
        <span>
            1. Start here: select an appointment or create a walk-in client.
        </span>
    </div>

    <!-- Header -->
    <div class="mb-3">
        <h2 class="font-semibold leading-tight">
            Appointments & Walk-ins
        </h2>
        <p class="text-xs text-gray-400">
            Select a client or create a walk-in
        </p>
    </div>

    <!-- Walk-in Button -->
    <button id="walkinBtn"
        class="w-full flex items-center justify-center gap-2
        bg-teal-500 hover:bg-teal-600
        text-white text-sm font-medium
        py-2 rounded-lg transition mb-4">

        <i data-lucide="user-plus" class="w-4 h-4"></i>
        <span>Create Walk-in</span>
    </button>

    <!-- Appointments -->
    <div id="appointmentsList" class="space-y-3"></div>
</aside>