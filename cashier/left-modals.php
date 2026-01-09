<!-- CHECK-IN CONFIRM MODAL -->
<div id="checkinModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

        <h3 class="text-lg font-semibold mb-2">
            Confirm Check-in
        </h3>

        <p class="text-sm text-gray-500 mb-4">
            Mark <span id="checkinClientName" class="font-medium"></span>
            as arrived and start a transaction?
        </p>

        <div class="flex justify-end gap-2">
            <button id="cancelCheckinBtn"
                class="px-4 py-2 text-sm rounded
                           bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>

            <button id="confirmCheckinBtn"
                class="px-4 py-2 text-sm rounded
                           bg-green-600 text-white">
                Check-in
            </button>
        </div>
    </div>
</div>
<!-- STATUS CONFIRM MODAL -->
<div id="statusModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

        <h3 id="statusModalTitle"
            class="text-lg font-semibold mb-2">
            Confirm Action
        </h3>

        <p class="text-sm text-gray-500 mb-4">
            Are you sure you want to mark
            <span id="statusClientName" class="font-medium"></span>
            as
            <span id="statusActionLabel" class="font-medium"></span>?
        </p>
        <p id="refundInfo" class="text-xs text-gray-500 mb-3"></p>
        <div class="flex justify-end gap-2">
            <button id="cancelStatusBtn"
                class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>

            <button id="confirmStatusBtn"
                class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- WALK-IN MODAL -->
<div id="walkinModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold mb-4">New Walk-in</h3>

        <div class="space-y-3">
            <input
                id="walkinSearch"
                type="text"
                placeholder="Search client name or contact"
                class="w-full px-4 py-2 rounded-lg border dark:bg-gray-700 mb-3">

            <div id="clientResults" class="space-y-2 max-h-40 overflow-y-auto"></div>

            <div class="border-t my-4"></div>
            <input type="hidden" id="walkinClientId">

            <p class="text-xs text-gray-400 mb-2">Or create new client</p>
            <input id="walkinName"
                type="text"
                placeholder="Client name *"
                class="w-full px-4 py-2 rounded-lg border dark:bg-gray-700">

            <input id="walkinContact"
                type="text"
                placeholder="Contact number (optional)"
                class="w-full px-4 py-2 rounded-lg border dark:bg-gray-700">
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <button id="cancelWalkin"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>
            <button id="confirmWalkin"
                class="px-4 py-2 text-sm rounded bg-green-600 text-white">
                Create
            </button>
        </div>
    </div>
</div>

<!-- WALK-IN CONFIRMATION MODAL -->
<div id="walkinConfirmModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

        <h3 class="text-lg font-semibold mb-2">
            Confirm Walk-in Creation
        </h3>

        <p class="text-sm text-gray-500 mb-4">
            Creating a walk-in will:
        </p>

        <ul class="text-sm text-gray-600 dark:text-gray-300 mb-4 list-disc pl-5 space-y-1">
            <li>Create an appointment marked as <b>Checked-in</b></li>
            <li>Create a transaction immediately</li>
            <li>This action cannot be undone</li>
        </ul>

        <div class="flex justify-end gap-2">
            <button id="cancelWalkinConfirm"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>

            <button id="confirmWalkinConfirm"
                class="px-4 py-2 text-sm rounded bg-green-600 text-white">
                Yes, Create Walk-in
            </button>
        </div>
    </div>
</div>