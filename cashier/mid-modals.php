<!-- ADD SERVICE MODAL -->
<div id="serviceModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Add Service</h3>

        <div class="space-y-4">
            <!-- Service -->
            <div>
                <label class="text-xs text-gray-500">Service</label>
                <select id="serviceSelect"
                    class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                    <option value="">Select service</option>
                </select>
            </div>

            <!-- Variant (hidden by default) -->
            <div id="variantWrapper" class="hidden">
                <label class="text-xs text-gray-500">Variant</label>
                <select id="variantSelect"
                    class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                </select>
            </div>
            <!-- Service Products Preview -->
            <div id="serviceProductsPreview"
                class="hidden rounded-lg border bg-gray-50 dark:bg-gray-700 p-3 text-xs space-y-1">
            </div>
            <!-- Staff -->
            <div>
                <label class="text-xs text-gray-500">Staff</label>
                <select id="staffSelect"
                    class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                    <option value="">Select staff</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <button id="cancelServiceBtn"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>
            <button id="confirmAddServiceBtn"
                class="px-4 py-2 text-sm rounded bg-blue-600 text-white">
                Add Service
            </button>
        </div>
    </div>
</div>

<!-- ADD EXTRA PRODUCT MODAL -->
<div id="extraProductModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Add Extra Product</h3>

        <div class="space-y-4">
            <!-- Product -->
            <div>
                <label class="text-xs text-gray-500">Product</label>
                <select id="extraProductSelect"
                    class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                </select>
            </div>

            <!-- Quantity wrapper -->
            <div id="extraQtyWrapper">
                <label id="extraQtyLabel" class="text-xs text-gray-500">Quantity</label>
                <input id="extraProductQty"
                    type="number"
                    min="0.01"
                    step="0.01"
                    value="1"
                    class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
            </div>

            <!-- Info -->
            <div id="extraProductInfo"
                class="text-xs text-gray-500 hidden">
            </div>
            <p class="text-[11px] text-gray-400 mt-1">
                ! Some products are disabled because they are already included in a service
            </p>
            <p id="extraProductHint"
                class="text-[11px] text-gray-400 mt-1">
                Add a service first to enable extra products
            </p>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <button id="cancelExtraProductBtn"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>
            <button id="confirmExtraProductBtn"
                class="px-4 py-2 text-sm rounded bg-emerald-600 text-white">
                Add Product
            </button>
        </div>
    </div>
</div>


<!-- REMOVE EXTRA PRODUCT MODAL -->
<div id="removeExtraProductModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

        <h3 class="text-lg font-semibold mb-2 text-red-600">
            Remove Product
        </h3>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            You are about to remove:
        </p>

        <div class="mb-3 px-3 py-2 rounded bg-gray-100 dark:bg-gray-700">
            <span id="removeExtraProductName"
                class="font-medium text-sm">
                —
            </span>
        </div>

        <p class="text-xs text-red-500 mb-4">
            This cannot be undone. The product will be removed from the transaction.
        </p>

        <div class="flex justify-end gap-2">
            <button id="cancelRemoveExtraProductBtn"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>

            <button id="confirmRemoveExtraProductBtn"
                class="px-4 py-2 text-sm rounded bg-red-600 text-white">
                Remove
            </button>
        </div>
    </div>
</div>


<!-- REMOVE SERVICE MODAL -->
<div id="removeServiceModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

        <h3 class="text-lg font-semibold mb-2 text-red-600">
            Remove Service
        </h3>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            You are about to remove:
        </p>

        <div class="mb-3 px-3 py-2 rounded bg-gray-100 dark:bg-gray-700">
            <span id="removeServiceName"
                class="font-medium text-sm">
                —
            </span>
        </div>

        <p class="text-xs text-red-500 mb-4">
            This cannot be undone. All related product usage will be removed.
        </p>

        <div class="flex justify-end gap-2">
            <button id="cancelRemoveServiceBtn"
                class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                Cancel
            </button>

            <button id="confirmRemoveServiceBtn"
                class="px-4 py-2 text-sm rounded bg-red-600 text-white">
                Remove
            </button>
        </div>
    </div>
</div>