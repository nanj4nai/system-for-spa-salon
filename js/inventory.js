// js/inventory.js
document.addEventListener("DOMContentLoaded", () => {
    // Inventory pagination
    let inventoryCurrentPage = 1;
    const inventoryPageSize = 10; // items per page
    let inventoryData = [];

    // Category pagination
    let categoryCurrentPage = 1;
    const categoryPageSize = 10; // categories per page
    let categoryData = [];


    const addItemBtn = document.getElementById("addItemBtn");
    const itemFormContainer = document.getElementById("itemFormContainer");
    const itemForm = document.getElementById("itemForm");
    const itemIdInput = document.getElementById("itemId");
    const itemNameInput = document.getElementById("itemName");
    const itemCategorySelect = document.getElementById("itemCategory");
    const itemStockInput = document.getElementById("itemStock");
    const itemPriceInput = document.getElementById("itemPrice");
    const itemImageInput = document.getElementById("itemImage");
    const previewImage = document.getElementById("previewImage");
    const cancelForm = document.getElementById("cancelForm");
    const formTitle = document.getElementById("formTitle");
    const inventoryTableBody = document.getElementById("inventoryTableBody");
    const itemProductType = document.getElementById("itemProductType");
    const itemUnit = document.getElementById("itemUnit");
    const itemUnitPerItem = document.getElementById("itemUnitPerItem");
    const unitPerItemContainer = document.getElementById("unitPerItemContainer");
    const stockLabel = document.getElementById("stockLabel");
    const packageCountContainer = document.getElementById("packageCountContainer");
    const itemPackageCount = document.getElementById("itemPackageCount");

    // Category modal
    const manageCategoryBtn = document.getElementById("manageCategoryBtn");
    const categoryModal = document.getElementById("categoryModal");
    const categoryForm = document.getElementById("categoryForm");
    const categoryIdInput = document.getElementById("categoryId");
    const categoryNameInput = document.getElementById("categoryName");
    const categoryTable = document.getElementById("categoryTable");
    const closeCategoryModal = document.getElementById("closeCategoryModal");
    const prevPageInventory = document.getElementById("prevPageInventory");
    const nextPageInventory = document.getElementById("nextPageInventory");
    const inventoryPageInfo = document.getElementById("inventoryPageInfo");
    const prevPageCategory = document.getElementById("prevPageCategory");
    const nextPageCategory = document.getElementById("nextPageCategory");
    const categoryPageInfo = document.getElementById("categoryPageInfo");


    // Toast and UI controls
    const successToast = document.getElementById("successToast");

    // API endpoints
    const CATEGORIES_API = "php/categories.php";
    const INVENTORY_API = "php/inventory_actions.php";

    // -- Helper: Toast
    let toastTimer = null;
    function showToast(message, success = true) {
        successToast.textContent = message;
        successToast.classList.remove("bg-green-500", "bg-red-500");
        successToast.classList.add(success ? "bg-green-500" : "bg-red-500");
        successToast.style.opacity = 1;
        successToast.style.pointerEvents = "auto";
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            successToast.style.opacity = 0;
            successToast.style.pointerEvents = "none";
        }, 3000);
    }


    function attachTableImagePreview() {
        document.querySelectorAll(".tableImage").forEach(img => {
            img.addEventListener("click", () => {
                const tableModal = document.getElementById("tableImageModal");
                const tableModalImg = document.getElementById("tableModalImage");
                tableModalImg.src = img.src;
                tableModal.classList.remove("hidden");
            });
        });

        const tableModal = document.getElementById("tableImageModal");
        tableModal.addEventListener("click", (e) => {
            if (e.target === tableModal) {
                tableModal.classList.add("hidden");
                document.getElementById("tableModalImage").src = "";
            }
        });
    }

    prevPageInventory.addEventListener("click", () => {
        if (inventoryCurrentPage > 1) {
            inventoryCurrentPage--;
            renderInventoryPage();
        }
    });
    nextPageInventory.addEventListener("click", () => {
        if (inventoryCurrentPage < Math.ceil(inventoryData.length / inventoryPageSize)) {
            inventoryCurrentPage++;
            renderInventoryPage();
        }
    });

    function updateInventoryPaginationInfo() {
        const totalPages = Math.ceil(inventoryData.length / inventoryPageSize) || 1;
        inventoryPageInfo.textContent = `Page ${inventoryCurrentPage} of ${totalPages}`;
    }

    prevPageCategory.addEventListener("click", () => {
        if (categoryCurrentPage > 1) {
            categoryCurrentPage--;
            renderCategoryPage();
        }
    });
    nextPageCategory.addEventListener("click", () => {
        if (categoryCurrentPage < Math.ceil(categoryData.length / categoryPageSize)) {
            categoryCurrentPage++;
            renderCategoryPage();
        }
    });

    function updateCategoryPaginationInfo() {
        const totalPages = Math.ceil(categoryData.length / categoryPageSize) || 1;
        categoryPageInfo.textContent = `Page ${categoryCurrentPage} of ${totalPages}`;
    }

    function attachInventoryButtons() {
        document.querySelectorAll(".editItemBtn").forEach(btn =>
            btn.addEventListener("click", () => openEditForm(btn.dataset.id))
        );

        document.querySelectorAll(".deleteItemBtn").forEach(btn =>
            btn.addEventListener("click", async () => {
                const id = btn.dataset.id;
                if (!confirm("Delete this product?")) return;
                const fd = new FormData();
                fd.append("action", "delete");
                fd.append("id", id);
                try {
                    const res = await fetch(INVENTORY_API, { method: "POST", body: fd });
                    const data = await res.json();
                    if (data.success) {
                        await refreshAll();
                        showToast("Product deleted");
                    } else {
                        showToast("Failed to delete product", false);
                    }
                } catch (err) {
                    console.error(err);
                    showToast("Error deleting product", false);
                }
            })
        );
    }
    function updateProductTypeUI() {
        const type = itemProductType.value;

        if (type === "consumable") {
            unitPerItemContainer.classList.remove("hidden");
            packageCountContainer.classList.remove("hidden");

            itemUnit.disabled = false;

            stockLabel.textContent = "Total Available Amount";
            stockHelp.textContent =
                "Automatically calculated from packages × amount per package";
        }
        if (type === "one_time") {
            unitPerItemContainer.classList.add("hidden");

            itemUnit.value = "pcs";
            itemUnit.disabled = true;

            stockLabel.textContent = "Total Available Pieces";
            stockHelp.textContent =
                "Number of items available (used once per service)";
        }
        if (type === "reusable") {
            unitPerItemContainer.classList.add("hidden");

            itemUnit.value = "pcs";
            itemUnit.disabled = true;

            stockLabel.textContent = "Owned Quantity";
            stockHelp.textContent =
                "How many reusable items you own (not deducted per service)";
        }
    }

    itemProductType.addEventListener("change", updateProductTypeUI);

    function autoCalculateTotalStock() {
        if (itemProductType.value !== "consumable") return;

        const perPackage = parseFloat(itemUnitPerItem.value);
        const packages = parseInt(itemPackageCount.value);

        if (isNaN(perPackage) || isNaN(packages)) return;

        const total = perPackage * packages;

        itemStock.value = total.toFixed(2);
    }

    itemUnitPerItem.addEventListener("input", autoCalculateTotalStock);
    itemPackageCount.addEventListener("input", autoCalculateTotalStock);

    function attachCategoryButtons() {
        document.querySelectorAll(".editCategoryBtn").forEach(btn => {
            btn.addEventListener("click", () => {
                categoryIdInput.value = btn.dataset.id;
                categoryNameInput.value = btn.dataset.name;
                categoryNameInput.focus();
            });
        });

        document.querySelectorAll(".deleteCategoryBtn").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.dataset.id;
                if (!confirm("Delete this category? Products under this category will be set to no category.")) return;
                try {
                    const fd = new FormData();
                    fd.append("action", "delete");
                    fd.append("id", id);
                    const res = await fetch(CATEGORIES_API, { method: "POST", body: fd });
                    const data = await res.json();
                    if (data.success) {
                        await refreshAll();
                        showToast("Category deleted");
                    } else {
                        showToast("Failed to delete category", false);
                    }
                } catch (err) {
                    console.error(err);
                    showToast("Error deleting category", false);
                }
            });
        });
    }

    // -- Populate category table
    function populateCategoryTable(categories) {
        categoryData = categories;
        renderCategoryPage();
    }

    function renderCategoryPage() {
        const start = (categoryCurrentPage - 1) * categoryPageSize;
        const end = start + categoryPageSize;
        const pageItems = categoryData.slice(start, end);

        categoryTable.innerHTML = "";
        if (!pageItems || pageItems.length === 0) {
            const tr = document.createElement("tr");
            tr.innerHTML = `<td class="px-2 py-1 text-center text-gray-500" colspan="2">No categories found</td>`;
            categoryTable.appendChild(tr);
            return;
        }

        pageItems.forEach(cat => {
            const tr = document.createElement("tr");
            tr.className =
                "border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition";
            tr.innerHTML = `
                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                    ${escapeHtml(cat.name)}
                </td>

                <td class="px-3 py-2 flex gap-2">
                    <button
                        data-id="${cat.id}"
                        data-name="${escapeHtml(cat.name)}"
                        class="editCategoryBtn
                            px-3 py-1 rounded-lg text-sm font-medium
                            bg-gray-200 text-gray-800
                            hover:bg-gray-300
                            dark:bg-gray-700 dark:text-gray-200
                            dark:hover:bg-gray-600
                            focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Edit
                    </button>

                    <button
                        data-id="${cat.id}"
                        class="deleteCategoryBtn
                            px-3 py-1 rounded-lg text-sm font-medium
                            bg-red-500 text-white
                            hover:bg-red-600
                            dark:bg-red-600 dark:hover:bg-red-700
                            focus:outline-none focus:ring-2 focus:ring-red-400">
                        Delete
                    </button>
                </td>
            `;
            categoryTable.appendChild(tr);
        });

        attachCategoryButtons();
        updateCategoryPaginationInfo();
    }

    // -- Fetch categories & populate selects and category table
    async function fetchCategories() {
        try {
            const res = await fetch(`${CATEGORIES_API}?action=fetch`);
            const categories = await res.json();
            populateCategorySelect(categories);
            populateCategoryTable(categories); // now it's defined!
            return categories;
        } catch (err) {
            console.error("Failed to fetch categories:", err);
            showToast("Failed to load categories", false);
            return [];
        }
    }

    function populateCategorySelect(categories) {
        // clear existing options except placeholder
        itemCategorySelect.innerHTML = `<option value="">-- Select Category --</option>`;
        categories.forEach(cat => {
            const opt = document.createElement("option");
            opt.value = cat.id;
            opt.textContent = cat.name;
            itemCategorySelect.appendChild(opt);
        });
    }

    // -- Category save
    categoryForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const id = categoryIdInput.value;
        const name = categoryNameInput.value.trim();
        if (name === "") {
            showToast("Category name required", false);
            return;
        }
        try {
            const fd = new FormData();
            fd.append("action", "save");
            fd.append("name", name);
            if (id) fd.append("id", id);
            const res = await fetch(CATEGORIES_API, { method: "POST", body: fd });
            const data = await res.json();
            if (data.success || !data.error) {
                categoryIdInput.value = "";
                categoryNameInput.value = "";
                await fetchCategories();
                showToast("Category saved");
            } else {
                showToast("Failed to save category", false);
            }
        } catch (err) {
            console.error(err);
            showToast("Error saving category", false);
        }
    });

    // -- Category modal open/close
    manageCategoryBtn.addEventListener("click", () => {
        categoryModal.classList.remove("hidden");
    });
    closeCategoryModal.addEventListener("click", () => {
        categoryModal.classList.add("hidden");
        categoryIdInput.value = "";
        categoryNameInput.value = "";
    });
    // close modal when clicking outside inner card
    categoryModal.addEventListener("click", (e) => {
        if (e.target === categoryModal) {
            categoryModal.classList.add("hidden");
        }
    });

    // -- Fetch products and render table
    async function fetchProducts() {
        try {
            const res = await fetch(`${INVENTORY_API}?action=fetch`);
            const products = await res.json();
            populateProductsTable(products);
            return products;
        } catch (err) {
            console.error("Failed to fetch products:", err);
            showToast("Failed to load products", false);
            return [];
        }
    }

    function populateProductsTable(products) {
        inventoryData = products; // store all data for pagination
        renderInventoryPage();
    }

    function renderInventoryPage() {
        const start = (inventoryCurrentPage - 1) * inventoryPageSize;
        const end = start + inventoryPageSize;
        const pageItems = inventoryData.slice(start, end);

        inventoryTableBody.innerHTML = "";

        if (!pageItems || pageItems.length === 0) {
            const tr = document.createElement("tr");
            tr.innerHTML = `
            <td class="px-4 py-2 text-center text-gray-500" colspan="6">
                No records found
            </td>`;
            inventoryTableBody.appendChild(tr);
            return;
        }

        pageItems.forEach(p => {
            const tr = document.createElement("tr");

            /* IMAGE */
            const imgCell = `
            <td class="px-4 py-2">
                <img src="${p.image ? escapeAttr(p.image) : 'assets/no-image.png'}"
                    alt="${escapeAttr(p.name)}"
                    class="h-12 w-12 object-contain rounded cursor-pointer tableImage">
            </td>`;

            /* NAME + PRODUCT TYPE */
            let typeLabel = "Consumable";
            let typeClass = "bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100";

            if (p.product_type === "one_time") {
                typeLabel = "One-time";
                typeClass = "bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100";
            }

            if (p.product_type === "reusable") {
                typeLabel = "Reusable";
                typeClass = "bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100";
            }

            const nameCell = `
            <td class="px-4 py-2">
                <div class="font-medium">${escapeHtml(p.name)}</div>
                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs ${typeClass}">
                    ${typeLabel}
                </span>
            </td>`;

            /* CATEGORY */
            const categoryCell = `
            <td class="px-4 py-2">
                ${p.category ? escapeHtml(p.category) : '<span class="text-sm opacity-70">—</span>'}
            </td>`;

            /* STOCK */
            let stockDisplay = `${Number(p.stock)} ${p.unit || "pcs"}`;

            if (p.product_type === "reusable") {
                stockDisplay += " (owned)";
            }

            let stockSubtext = "";
            if (p.product_type === "consumable" && p.unit_per_item) {
                stockSubtext = `
                <div class="text-xs text-gray-500">
                    ${p.unit_per_item} ${p.unit} per package
                </div>`;
            }

            const stockCell = `
            <td class="px-4 py-2">
                <div>${stockDisplay}</div>
                ${stockSubtext}
            </td>`;

            /* PRICE */
            const priceCell = `
            <td class="px-4 py-2">
                ₱ ${Number(p.price).toFixed(2)}
            </td>`;

            /* ACTIONS */
            const actionsCell = `
            <td class="px-4 py-2">
                <button data-id="${p.id}"
                    class="editItemBtn px-2 py-1 mr-2 rounded bg-blue-200 dark:bg-blue-700 text-sm">
                    Edit
                </button>
                <button data-id="${p.id}"
                    class="deleteItemBtn px-2 py-1 rounded bg-red-200 dark:bg-red-700 text-sm">
                    Delete
                </button>
            </td>`;

            tr.innerHTML =
                imgCell +
                nameCell +
                categoryCell +
                stockCell +
                priceCell +
                actionsCell;

            inventoryTableBody.appendChild(tr);
        });

        attachInventoryButtons();
        attachTableImagePreview();
        updateInventoryPaginationInfo();
    }

    // -- Open add form
    addItemBtn.addEventListener("click", () => {
        openAddForm();
    });

    function openAddForm() {
        formTitle.textContent = "Add Product";
        itemForm.reset();
        itemIdInput.value = "";
        previewImage.src = "";
        previewImage.classList.add("hidden");
        itemFormContainer.classList.remove("hidden");
        itemNameInput.focus();
        itemProductType.value = "consumable";
        itemUnit.value = "pcs";
        itemUnitPerItem.value = "";

        updateProductTypeUI();
        showCategoryTooltip();
    }

    // -- Open edit form
    async function openEditForm(id) {
        try {
            // fetch products and find the product
            const res = await fetch(`${INVENTORY_API}?action=fetch`);
            const products = await res.json();
            const product = products.find(p => Number(p.id) === Number(id));
            if (!product) {
                showToast("Product not found", false);
                return;
            }
            if (itemFormContainer) showCategoryTooltip();
            formTitle.textContent = "Edit Product";
            itemIdInput.value = product.id;
            itemNameInput.value = product.name;
            itemStockInput.value = product.stock ?? 0;
            itemPriceInput.value = product.price ?? 0;
            itemCategorySelect.value = product.category_id ?? "";
            itemProductType.value = ["consumable", "one_time", "reusable"].includes(product.product_type)
                ? product.product_type
                : "consumable";
            itemUnit.value = product.unit || "pcs";
            itemUnitPerItem.value = product.unit_per_item || "";
            updateProductTypeUI();

            if (product.image) {
                previewImage.src = product.image;
                previewImage.classList.remove("hidden");
            } else {
                previewImage.src = "";
                previewImage.classList.add("hidden");
            }
            itemFormContainer.classList.remove("hidden");
            itemNameInput.focus();
        } catch (err) {
            console.error(err);
            showToast("Error opening product", false);
        }
    }

    // -- Cancel form
    cancelForm.addEventListener("click", (e) => {
        e.preventDefault();
        itemForm.reset();
        itemIdInput.value = "";
        previewImage.src = "";
        previewImage.classList.add("hidden");
        itemFormContainer.classList.add("hidden");
    });

    // -- Image preview
    itemImageInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (!file) {
            previewImage.src = "";
            previewImage.classList.add("hidden");
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            previewImage.src = ev.target.result;
            previewImage.classList.remove("hidden");
        };
        reader.readAsDataURL(file);
    });


    itemForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const id = itemIdInput.value.trim();
        const name = itemNameInput.value.trim();
        const category_id = itemCategorySelect.value.trim();
        const stock = itemStockInput.value.trim();
        const price = itemPriceInput.value.trim();

        // ---- Client-side validation ----
        if (!name) return showToast("Product name is required", false);
        if (!category_id) return showToast("Category is required", false);
        if (price === "") return showToast("Price is required", false);

        // ---- Visual feedback: saving ----
        showToast("Saving product...", true);

        try {
            const fd = new FormData();
            fd.append("action", "save");

            if (id) fd.append("id", id);
            fd.append("name", name);
            fd.append("category_id", category_id);
            fd.append("stock", stock || 0);
            fd.append("price", price);
            fd.append("product_type", itemProductType.value);
            fd.append("unit", itemUnit.value);

            if (itemProductType.value === "consumable") {
                fd.append("unit_per_item", itemUnitPerItem.value || "");
            }

            if (itemImageInput.files.length > 0) {
                fd.append("image", itemImageInput.files[0]);
            }

            const res = await fetch(INVENTORY_API, {
                method: "POST",
                body: fd
            });

            // ---- Handle bad HTTP responses ----
            if (!res.ok) {
                throw new Error(`Server error (${res.status})`);
            }

            const data = await res.json();

            if (data.success) {
                itemForm.reset();
                itemIdInput.value = "";
                previewImage.src = "";
                previewImage.classList.add("hidden");
                itemFormContainer.classList.add("hidden");

                await refreshAll();

                showToast(
                    id ? "Product updated successfully" : "Product added successfully",
                    true
                );
            } else {
                showToast(data.error || "Failed to save product", false);
            }
        } catch (err) {
            console.error(err);
            showToast("Something went wrong while saving. Please try again.", false);
        }
    });


    function showCategoryTooltip() {
        const tooltip = document.getElementById("manageCategoryTooltip");
        if (!tooltip) return;

        tooltip.classList.remove("opacity-0");
        tooltip.classList.add("opacity-100");

        // Hide after 3 seconds
        setTimeout(() => {
            tooltip.classList.remove("opacity-100");
            tooltip.classList.add("opacity-0");
        }, 3000);
    }

    // -- Refresh both lists
    async function refreshAll() {
        await fetchCategories();
        await fetchProducts();
    }

    // -- Utility: escape HTML to avoid XSS
    function escapeHtml(str) {
        if (str === null || str === undefined) return "";
        return String(str)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }
    function escapeAttr(str) {
        if (str === null || str === undefined) return "";
        return String(str).replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    }

    // -- Initial load
    refreshAll();
});
