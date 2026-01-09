document.addEventListener("DOMContentLoaded", () => {
    let deletedVariantIds = [];

    const categoryIdInput = document.getElementById("category_id_input");
    const confirmModal = document.getElementById("confirmModal");
    const confirmMessage = document.getElementById("confirmModalMessage");
    const confirmBtn = document.getElementById("confirmBtn");
    const cancelConfirmBtn = document.getElementById("cancelConfirmBtn");

    const showConfirmModal = (message) => {
        confirmMessage.textContent = message;
        confirmModal.classList.remove("hidden");

        return new Promise((resolve) => {
            const cleanUp = () => {
                confirmModal.classList.add("hidden");
                confirmBtn.onclick = null;
                cancelConfirmBtn.onclick = null;
            };

            confirmBtn.onclick = () => { cleanUp(); resolve(true); };
            cancelConfirmBtn.onclick = () => { cleanUp(); resolve(false); };
        });
    };
    // ==== TOAST HELPER ====
    const toastContainer = document.createElement("div");
    toastContainer.className = `
        fixed top-4 right-4
        flex flex-col gap-3
        z-50
        w-[90%] sm:w-auto
    `;
    document.body.appendChild(toastContainer);

    const showToast = (
        message,
        type = "success",
        { duration = 4000, actionText = null, onAction = null } = {}
    ) => {
        const styles = {
            success: "bg-green-600",
            error: "bg-red-600",
            warning: "bg-yellow-500 text-black",
            info: "bg-blue-600",
            delete: "bg-rose-600"
        };

        const icons = {
            success: "check-circle",
            error: "x-circle",
            warning: "alert-triangle",
            info: "info",
            delete: "trash-2"
        };

        const toast = document.createElement("div");
        toast.className = `
            flex items-start gap-3
            px-4 py-3 rounded-xl shadow-xl
            text-white
            animate-slideIn
            ${styles[type] || styles.info}
        `;

        toast.innerHTML = `
            <i data-lucide="${icons[type] || icons.info}" class="w-5 h-5 mt-0.5"></i>
            <div class="flex-1 text-sm">
                ${message}
            </div>
            ${actionText ? `
                <button class="underline text-sm font-medium ml-2">
                    ${actionText}
                </button>
            ` : ""}
        `;

        toastContainer.appendChild(toast);
        lucide.createIcons();

        if (actionText && onAction) {
            toast.querySelector("button").onclick = () => {
                onAction();
                toast.remove();
            };
        }

        setTimeout(() => {
            toast.classList.add("opacity-0", "transition-opacity", "duration-500");
            setTimeout(() => toast.remove(), 500);
        }, duration);
    };

    // ===== Service Delete (Mobile/Desktop) =====
    const handleServiceDelete = async (id, index) => {
        const deletedService = { ...services[index] };

        const confirmed = await showConfirmModal(`Delete service "${deletedService.name}"?`);
        if (!confirmed) return;

        const res = await fetch(`php/services_crud.php?action=delete_service&id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || "Delete failed", "error");
            return;
        }

        // After deleting service
        services.splice(index, 1);

        // Ensure current page is valid
        const source = filteredServices.length ? filteredServices : services;
        const totalPages = Math.ceil(source.length / rowsPerPage);
        if (currentPage > totalPages) currentPage = totalPages || 1;

        applyServiceFilters(); // will call renderServicesTable()
        showToast("Service deleted", "delete", {
            actionText: "Undo",
            onAction: () => {
                services.splice(index, 0, deletedService);
                applyServiceFilters();
                showToast("Service restored", "success");
            }
        });
    };

    // ===== Category Delete =====
    const handleCategoryDelete = async (id, index) => {
        const deletedCategory = { ...categories[index] };

        const confirmed = await showConfirmModal(`Delete category "${deletedCategory.name}"?`);
        if (!confirmed) return;

        const res = await fetch(`php/categories_crud.php?action=delete&id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || "Delete failed", "error");
            return;
        }

        categories.splice(index, 1);
        if (categoryPage > Math.ceil(categories.length / categoriesPerPage)) {
            categoryPage = Math.ceil(categories.length / categoriesPerPage) || 1;
        }
        renderCategoriesTable();
        renderServicesTable();

        showToast("Category deleted", "delete", {
            actionText: "Undo",
            onAction: () => {
                categories.splice(index, 0, deletedCategory);
                renderCategoriesTable();
                renderServicesTable();
                showToast("Category restored", "success");
            }
        });
    };

    // ==== MODAL HELPER ====
    const toggleModal = (modal, show) => modal.classList.toggle("hidden", !show);

    // ==== NOTES MODAL ====
    const notesModal = document.getElementById("notesModal");
    const notesContent = document.getElementById("notesContent");
    document.getElementById("closeNotesBtn").onclick = () => toggleModal(notesModal, false);
    const attachNotes = () => {
        document.querySelectorAll("td[data-full]").forEach(td => {
            td.onclick = () => {
                if (!td.textContent.trim()) return;
                notesContent.textContent = td.dataset.full;
                toggleModal(notesModal, true);
            };
        });
    };
    // Update category dropdown in service modal dynamically
    const updateServiceCategoryOptions = () => {
        const categorySelect = document.getElementById("serviceForm").category_id;
        if (!categorySelect) return;

        // preserve currently selected value
        const selected = categorySelect.value;

        categorySelect.innerHTML = '<option value="">Select Category</option>';
        categories.forEach(cat => {
            const opt = document.createElement("option");
            opt.value = String(cat.id); // <-- make sure value is string
            opt.textContent = cat.name;
            categorySelect.appendChild(opt);
        });

        categorySelect.value = String(selected); // restore selected value
    };



    // ==== DATA ARRAYS ====
    let categories = [];
    let services = [];
    // ==== PAGINATION STATE ====
    let currentPage = 1;
    const rowsPerPage = 8; // change to 5 / 10 / 15 if you want
    let filteredServices = [];
    let serviceSearchTerm = "";
    let selectedCategory = "";
    let categoryPage = 1;
    const categoriesPerPage = 6;
    let products = [];



    // ==== FETCH INITIAL DATA ====
    const fetchData = async () => {
        const res = await fetch("php/get_services.php");
        const data = await res.json();
        if (!data.success) return showToast("Failed to fetch data", "error");

        categories = data.categories;
        products = data.products; // fetch products as well
        services = data.services.map(s => {
            // ensure variants array exists
            s.variants = s.variants || [];
            return s;
        });

        renderCategoriesTable();
        renderServicesTable();

        const categoryFilter = document.getElementById("categoryFilter");
        categoryFilter.innerHTML = `<option value="">All Categories</option>`;
        categories.forEach(cat => {
            const opt = document.createElement("option");
            opt.value = cat.id;
            opt.textContent = cat.name;
            categoryFilter.appendChild(opt);
        });
    };


    const applyServiceFilters = () => {
        filteredServices = services.filter(s => {
            const matchesSearch =
                s.name.toLowerCase().includes(serviceSearchTerm) ||
                (s.description || "").toLowerCase().includes(serviceSearchTerm);

            const matchesCategory =
                !selectedCategory || String(s.category_id) === String(selectedCategory);

            return matchesSearch && matchesCategory;
        });

        currentPage = 1;
        renderServicesTable();
    };
    document.getElementById("serviceSearch").addEventListener("input", e => {
        serviceSearchTerm = e.target.value.toLowerCase();
        applyServiceFilters();
    });

    document.getElementById("categoryFilter").addEventListener("change", e => {
        selectedCategory = e.target.value;
        applyServiceFilters();
    });


    // ==== RENDER FUNCTIONS ====
    const renderCategoriesTable = () => {
        const tbody = document.querySelector("div.mt-8 table tbody");
        tbody.innerHTML = "";

        const start = (categoryPage - 1) * categoriesPerPage;
        const end = start + categoriesPerPage;
        const pageCategories = categories.slice(start, end);

        pageCategories.forEach(cat => {
            const tr = document.createElement("tr");
            tr.className = "border-b border-gray-200 dark:border-gray-700";
            tr.innerHTML = `
            <td class="px-4 py-2">${cat.name}</td>
            <td class="px-4 py-2">
                <button class="editCategoryBtn px-2 py-1 bg-yellow-400 rounded text-white" data-id="${cat.id}">Edit</button>
                <button class="deleteCategoryBtn px-2 py-1 bg-red-500 rounded text-white" data-id="${cat.id}">Delete</button>
            </td>
        `;
            tbody.appendChild(tr);
        });

        const totalPages = Math.ceil(categories.length / categoriesPerPage);
        document.getElementById("categoryPageInfo").textContent =
            `Page ${categoryPage} of ${totalPages || 1}`;

        document.getElementById("prevCategoryPage").disabled = categoryPage === 1;
        document.getElementById("nextCategoryPage").disabled = categoryPage === totalPages;
    };

    const renderServiceProducts = (products = []) => {
        if (!products.length) return "";

        return `
        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
            <strong>Uses:</strong><br>
            ${products.map(p => {
            let qtyLabel = p.quantity;

            if (p.product_type === "consumable" && p.unit) {
                qtyLabel = `${p.quantity} ${p.unit}`;
            }

            if (p.product_type === "reusable") {
                qtyLabel = `${p.quantity} item(s)`;
            }

            if (p.product_type === "one_time") {
                qtyLabel = `1 use`;
            }

            return `• ${p.name} — ${qtyLabel}`;
        }).join("<br>")}
        </div>
    `;
    };
    const calculateServiceProductCost = (products = []) => {
        return products.reduce((total, p) => {
            if (!p.product_type || !p.price) return total;

            const price = parseFloat(p.price);
            const qty = parseFloat(p.quantity || 0);
            const unitPerItem = parseFloat(p.unit_per_item || 1);

            if (p.product_type === "consumable") {
                return total + (qty / unitPerItem) * price;
            }

            if (p.product_type === "one_time") {
                return total + price;
            }

            return total; // reusable
        }, 0);
    };


    const renderServicesTable = () => {
        const isMobile = window.innerWidth < 768;

        const table = document.getElementById("servicesTable");
        const mobile = document.getElementById("servicesMobile");

        if (table && mobile) {
            table.classList.toggle("hidden", isMobile);
            mobile.classList.toggle("hidden", !isMobile);
        }

        if (isMobile) {
            renderServicesMobile();
            renderPaginationControls();
            return;
        }
        const tbody = document.querySelector("#servicesTable tbody");
        tbody.innerHTML = "";

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const source = filteredServices.length ? filteredServices : services;
        const paginatedServices = source.slice(start, end);


        paginatedServices.forEach(service => {
            const categoryName = categories.find(c => c.id == service.category_id)?.name || "Uncategorized";

            const descFull = service.description || "";
            const descText = descFull.length > 50 ? descFull.substr(0, 50) + "..." : descFull;

            const tr = document.createElement("tr");
            tr.className = "border-b border-gray-200 dark:border-gray-700";
            tr.innerHTML = `
                <td class="px-4 py-2">${service.name}</td>
                <td class="px-4 py-2">${categoryName}</td>
                <td class="px-4 py-2">
                    ${service.variants.length
                    ? service.variants
                        .map(v => `${v.name} (${v.duration_minutes} mins) ₱${parseFloat(v.price).toFixed(2)}`)
                        .join("<br>")
                    : `₱${parseFloat(service.base_price || 0).toFixed(2)}`
                }

                    ${renderServiceProducts(service.products)}

                    ${service.products?.length
                    ? `
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <strong>Est. Product Cost / Service:</strong>
                                ₱${calculateServiceProductCost(service.products).toFixed(2)}
                            </div>
                        `
                    : ""
                }
                </td>
                <td class="px-4 py-2">${parseFloat(service.default_commission_percent || 0).toFixed(2)}%</td>
                <td class="px-4 py-2 cursor-pointer" data-full="${descFull}">${descText}</td>
                <td class="px-4 py-2">
                    <button class="editServiceBtn px-2 py-1 bg-yellow-400 rounded text-white" data-id="${service.id}">Edit</button>
                    <button class="deleteServiceBtn px-2 py-1 bg-red-500 rounded text-white" data-id="${service.id}">Delete</button>
                </td>
        `;
            tbody.appendChild(tr);
        });

        attachNotes();
        renderPaginationControls();
    };

    const renderServicesMobile = () => {
        const container = document.getElementById("servicesMobile");
        container.innerHTML = "";

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const source = filteredServices.length ? filteredServices : services;
        const paginatedServices = source.slice(start, end);

        paginatedServices.forEach(service => {
            const categoryName = categories.find(c => c.id == service.category_id)?.name || "Uncategorized";

            const descFull = service.description || "";
            const descText = descFull.length > 80
                ? descFull.substring(0, 80) + "..."
                : descFull;

            const card = document.createElement("div");
            card.className = `
            bg-white dark:bg-gray-800
            rounded-xl shadow
            p-4 space-y-2
        `;

            card.innerHTML = `
            <div class="flex justify-between items-start">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-100">
                    ${service.name}
                </h3>
                <span class="text-sm px-2 py-1 rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200">
                    ${categoryName}
                </span>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Price / Variants:</strong><br>
                ${service.variants.length
                    ? service.variants
                        .map(v => `${v.name} (${v.duration_minutes} mins) ₱${parseFloat(v.price).toFixed(2)}`)
                        .join("<br>")
                    : `₱${parseFloat(service.base_price || 0).toFixed(2)}`
                }

                ${service.products?.length
                    ? `
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <strong>Est. Product Cost:</strong>
                            ₱${calculateServiceProductCost(service.products).toFixed(2)}
                        </div>
                    `
                    : ""
                }

                <div class="mt-1">
                    Commission: <strong>${parseFloat(service.default_commission_percent || 0).toFixed(2)}%</strong>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 cursor-pointer"
               data-full="${descFull}">
                ${descText}
            </p>

            <div class="flex gap-2 pt-2">
                <button
                    class="editServiceBtn flex-1 bg-yellow-400 text-white py-1 rounded"
                    data-id="${service.id}">
                    Edit
                </button>
                <button
                    class="deleteServiceBtn flex-1 bg-red-500 text-white py-1 rounded"
                    data-id="${service.id}">
                    Delete
                </button>
            </div>
        `;

            container.appendChild(card);
        });

        attachNotes(); // reuse your notes modal
    };

    const renderPaginationControls = () => {
        const source = filteredServices.length ? filteredServices : services;
        const totalPages = Math.ceil(source.length / rowsPerPage);

        const pageNumbers = document.getElementById("pageNumbers");
        const info = document.getElementById("paginationInfo");

        pageNumbers.innerHTML = "";
        info.textContent = `Page ${currentPage} of ${totalPages || 1} (${source.length} results)`;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement("button");
            btn.textContent = i;
            btn.className = `
            px-3 py-1 rounded
            ${i === currentPage
                    ? "bg-purple-600 text-white"
                    : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300"}
        `;
            btn.onclick = () => {
                currentPage = i;
                renderServicesTable();
            };
            pageNumbers.appendChild(btn);
        }

        document.getElementById("prevPage").disabled = currentPage === 1;
        document.getElementById("nextPage").disabled = currentPage === totalPages;
    };
    document.getElementById("prevPage").onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            renderServicesTable();
        }
    };

    document.getElementById("nextPage").onclick = () => {
        const source = filteredServices.length ? filteredServices : services;
        const totalPages = Math.ceil(source.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderServicesTable();
        }
    };

    document.getElementById("prevCategoryPage").onclick = () => {
        if (categoryPage > 1) {
            categoryPage--;
            renderCategoriesTable();
        }
    };

    document.getElementById("nextCategoryPage").onclick = () => {
        const totalPages = Math.ceil(categories.length / categoriesPerPage);
        if (categoryPage < totalPages) {
            categoryPage++;
            renderCategoriesTable();
        }
    };
    const getSelectedProductIds = () => {
        return Array.from(
            document.querySelectorAll(".product-select")
        )
            .map(sel => sel.value)
            .filter(Boolean);
    };

    const updateAllProductOptions = () => {
        const selectedIds = getSelectedProductIds();

        document.querySelectorAll(".product-select").forEach(select => {
            const currentValue = select.value;

            select.querySelectorAll("option").forEach(opt => {
                if (!opt.value) return;

                // hide option if selected in another row
                opt.hidden =
                    opt.value !== currentValue &&
                    selectedIds.includes(opt.value);
            });
        });
    };


    const productContainer = document.getElementById("productContainer");
    const addProductBtn = document.getElementById("addProductBtn");

    const createProductRow = (productId = "", qty = 1) => {

        // Prevent adding more rows than available products
        const selectedCount = getSelectedProductIds().length;
        if (selectedCount >= products.length) {
            showToast("All available products are already added.", "warning");
            return;
        }

        const div = document.createElement("div");
        div.className = "flex gap-2 mb-2 product-row";

        const options = products.map(p => {
            let meta = "";

            // CONSUMABLE
            if (p.product_type === "consumable") {
                const unit = p.unit || "";
                const unitPerItem = p.unit_per_item || 1;
                const price = parseFloat(p.price || 0);

                meta = `${p.stock} ${unit} available • ₱${price.toFixed(2)} / ${unitPerItem} ${unit}`;
            }

            // REUSABLE
            if (p.product_type === "reusable") {
                meta = "Reusable item";
            }

            // ONE-TIME
            if (p.product_type === "one_time") {
                meta = `One-time use • ₱${parseFloat(p.price || 0).toFixed(2)}`;
            }

            return `
        <option value="${p.id}">
            ${p.name} — ${meta}
        </option>
    `;
        }).join("");


        div.innerHTML = `
            <select class="product-select flex-1 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white">
                <option value="">Select product</option>
                ${options}
            </select>

            <div class="flex flex-col flex-1">
                <div class="relative">
                    <input type="number"
                        class="product-qty w-full px-2 py-1 pr-12 border rounded dark:bg-gray-500 dark:text-white"
                        value="${qty}" min="1">

                    <!-- unit badge -->
                    <span class="product-unit absolute right-2 top-1/2 -translate-y-1/2
                        text-xs text-gray-500 dark:text-gray-300">
                        pcs
                    </span>
                </div>

                <!-- helper text -->
                <span class="product-hint text-xs text-gray-500 mt-1"></span>
            </div>

            <button type="button"
                class="removeProductBtn bg-red-500 text-white px-2 rounded">
                X
            </button>
        `;

        const productSelect = div.querySelector(".product-select");
        const qtyInput = div.querySelector(".product-qty");

        // 🧠 Handle product change (no duplicates)
        productSelect.onchange = () => {
            const product = products.find(p => p.id == productSelect.value);
            if (!product) return;

            // reset defaults
            qtyInput.disabled = false;
            qtyInput.min = 1;
            qtyInput.step = 1;

            const unitBadge = div.querySelector(".product-unit");
            const hint = div.querySelector(".product-hint");

            // CONSUMABLE
            if (product.product_type === "consumable") {
                qtyInput.step = "0.01";
                qtyInput.disabled = false;

                // default to per-package if available
                if (product.unit_per_item) {
                    qtyInput.value = product.unit_per_item;
                } else {
                    qtyInput.value = qtyInput.value || 0;
                }

                unitBadge.textContent = product.unit || "";

                hint.textContent = product.unit_per_item
                    ? `${product.unit_per_item}${product.unit || ""} used per package`
                    : `Amount used per service (${product.unit})`;
            }

            // REUSABLE
            if (product.product_type === "reusable") {
                qtyInput.step = 1;
                qtyInput.value = Math.max(1, Math.floor(qtyInput.value || 1));
                unitBadge.textContent = "pcs";
                hint.textContent = "Number of reusable items required";
            }

            // ONE-TIME
            if (product.product_type === "one_time") {
                qtyInput.value = 1;
                qtyInput.disabled = true;
                unitBadge.textContent = "use";
                hint.textContent = "Automatically set (one-time use)";
            }

            updateAllProductOptions();
        };

        // 🚨 Quantity vs stock validation
        qtyInput.oninput = () => {
            const product = products.find(p => p.id == productSelect.value);
            if (!product || product.product_type !== "consumable") return;

            const val = parseFloat(qtyInput.value || 0);

            // one-time: hard lock
            if (product.product_type === "one_time") {
                qtyInput.value = 1;
                return;
            }

            // reusable: whole numbers only
            if (product.product_type === "reusable") {
                qtyInput.value = Math.max(1, Math.floor(val));
                return;
            }

            // consumable: stock-based
            if (product.product_type === "consumable") {
                if (val > product.stock) {
                    showToast(
                        `Only ${product.stock}${product.unit || ""} left for "${product.name}".`,
                        "warning"
                    );
                    qtyInput.value = product.stock;
                }
            }
        };

        // ❌ Remove row
        div.querySelector(".removeProductBtn").onclick = () => {
            div.remove();
            updateAllProductOptions();
        };

        // Apply rules immediately if productId exists
        if (productId) {
            productSelect.value = String(productId); // ✅ SET VALUE FIRST
            productSelect.dispatchEvent(new Event("change")); // then apply rules
        }


        productContainer.appendChild(div);
        updateAllProductOptions();
    };

    addProductBtn.onclick = () => createProductRow();


    // ==== MODALS ====
    const serviceFormContainer = document.getElementById("serviceFormContainer");
    const serviceForm = document.getElementById("serviceForm");
    const openServiceModal = (title, data = {}) => {
        deletedVariantIds = [];
        serviceForm.reset();
        document.getElementById("formTitle").textContent = title;
        productContainer.innerHTML = "";

        // existing service products
        (data.products || []).forEach(sp => {
            createProductRow(sp.product_id, sp.quantity);
        });
        updateServiceCategoryOptions(); // ensure dropdown is up-to-date
        serviceForm.service_id.value = data.id || "";
        serviceForm.name.value = data.name || "";
        serviceForm.category_id.value = data.category_id !== undefined ? String(data.category_id) : "";
        serviceForm.base_price.value = data.base_price || "";
        serviceForm.default_commission_percent.value = data.default_commission_percent || "";
        serviceForm.description.value = data.description || "";

        const variantContainer = document.getElementById("variantContainer");
        variantContainer.innerHTML = "";

        // Combine existing variants from data.variants and JS updates
        const serviceVariants = data.variants || [];

        serviceVariants.forEach(v => {
            const div = document.createElement("div");
            div.className = "flex gap-2 mb-2";

            div.innerHTML = `
                <input type="text"
                    class="variant-name flex-1 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    value="${v.name}"
                    placeholder="Variant Name">

                <input type="number"
                    class="variant-duration w-24 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    value="${v.duration_minutes}"
                    placeholder="Mins">

                <input type="number"
                    step="0.01"
                    class="variant-price w-24 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    value="${v.price}"
                    placeholder="Price">

                <input type="hidden"
                    class="variant-id"
                    value="${v.id || ''}">

                <button type="button"
                    class="removeVariantBtn bg-red-500 text-white px-2 rounded">
                    X
                </button>
            `;

            div.querySelector(".removeVariantBtn").onclick = () => {
                if (v.id) deletedVariantIds.push(v.id);
                div.remove();
            };

            variantContainer.appendChild(div);
        });


        // Function to add a new variant dynamically
        const addNewVariant = () => {
            const div = document.createElement("div");
            div.className = "flex gap-2 mb-2";

            div.innerHTML = `
                <input type="text"
                    class="variant-name flex-1 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    placeholder="Variant Name">

                <input type="number"
                    class="variant-duration w-24 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    placeholder="Mins">

                <input type="number"
                    step="0.01"
                    class="variant-price w-24 px-2 py-1 border rounded dark:bg-gray-500 dark:text-white"
                    placeholder="Price">

                <input type="hidden"
                    class="variant-id"
                    value="">

                <button type="button"
                    class="removeVariantBtn bg-red-500 text-white px-2 rounded">
                    X
                </button>
            `;

            div.querySelector(".removeVariantBtn").onclick = () => div.remove();
            variantContainer.appendChild(div);
        };

        document.getElementById("addVariantBtn").onclick = addNewVariant;

        // Attach remove buttons for all existing variants
        const attachRemoveHandlers = () => {
            variantContainer.querySelectorAll(".removeVariantBtn").forEach(btn => {
                btn.onclick = () => {
                    const hiddenId = btn.parentElement.querySelector('input[name^="variant_id_"]')?.value;
                    if (hiddenId) {
                        deletedVariantIds.push(hiddenId);
                    }
                    btn.parentElement.remove();
                };

            });
        };
        attachRemoveHandlers();

        toggleModal(serviceFormContainer, true);
    };


    document.getElementById("addServiceBtn").onclick = () => openServiceModal("Add Service");
    document.getElementById("cancelServiceBtn").onclick = () => toggleModal(serviceFormContainer, false);

    const categoryFormContainer = document.getElementById("categoryFormContainer");
    const categoryForm = document.getElementById("categoryForm");
    const openCategoryModal = (title, data = {}) => {
        categoryForm.reset();
        categoryIdInput.value = data.id || "";
        categoryForm.name.value = data.name || "";
        document.getElementById("categoryFormTitle").textContent = title;
        toggleModal(categoryFormContainer, true);
    };
    document.getElementById("addCategoryBtn").onclick = () => openCategoryModal("Add Category");
    document.getElementById("cancelCategoryBtn").onclick = () => toggleModal(categoryFormContainer, false);

    // Submit handler remains mostly the same, but now it sends variant IDs
    serviceForm.addEventListener("submit", async e => {
        e.preventDefault();

        const formData = new FormData(serviceForm);
        const id = formData.get("service_id");

        const serviceProducts = [];

        const variantContainer = document.getElementById("variantContainer");
        const variants = [];

        if (variantContainer) {
            Array.from(variantContainer.children).forEach(row => {
                const name = row.querySelector(".variant-name")?.value || "";
                const duration = parseInt(row.querySelector(".variant-duration")?.value || 0);
                const price = parseFloat(row.querySelector(".variant-price")?.value || 0);
                const v_id = row.querySelector(".variant-id")?.value || "";

                formData.append("variant_name[]", name);
                formData.append("duration_minutes[]", duration);
                formData.append("price[]", price);
                formData.append("variant_id[]", v_id);

                if (name) {
                    variants.push({ id: v_id, name, duration_minutes: duration, price });
                }
            });

        }

        document.querySelectorAll(".product-row").forEach(row => {
            const productId = row.querySelector(".product-select").value;
            const qty = row.querySelector(".product-qty").value;

            if (productId) {
                serviceProducts.push({ product_id: productId, quantity: qty });
            }
        });

        formData.append("service_products", JSON.stringify(serviceProducts));

        deletedVariantIds.forEach(id => {
            formData.append("deleted_variant_ids[]", id);
        });


        formData.append("action", "create_or_update");

        const res = await fetch("php/services_crud.php", { method: "POST", body: formData });
        const data = await res.json();

        showToast(data.message, data.success ? "success" : "error");
        if (!data.success) return;

        toggleModal(serviceFormContainer, false);

        // Properly handle category_id
        const catIdRaw = formData.get("category_id");
        const prevCategoryId = id ? services.find(s => s.id == id)?.category_id : null;
        const cat_id = catIdRaw !== null && catIdRaw !== "" ? Number(catIdRaw) : (id ? Number(services.find(s => s.id == id)?.category_id) : 0);

        const enrichedServiceProducts = serviceProducts.map(sp => {
            const product = products.find(p => p.id == sp.product_id);
            return {
                product_id: sp.product_id,
                quantity: sp.quantity,
                name: product?.name || "",
                price: product?.price || 0,
                product_type: product?.product_type || "",
                unit_per_item: product?.unit_per_item || 1
            };
        });

        const serviceObj = {
            id: data.id,
            name: formData.get("name"),
            category_id: cat_id,
            base_price: parseFloat(formData.get("base_price") || 0),
            default_commission_percent: parseFloat(formData.get("default_commission_percent") || 0),
            description: formData.get("description") || "",
            variants,
            products: enrichedServiceProducts // ✅ ADD THIS
        };

        if (id) {
            const index = services.findIndex(s => s.id == id);
            if (index > -1) services[index] = serviceObj;
        } else {
            services.unshift(serviceObj);
        }
        deletedVariantIds = []; // reset after submission

        // If category changed, we may need to reapply filters
        applyServiceFilters(); // refresh table immediately
    });


    categoryForm.addEventListener("submit", async e => {
        e.preventDefault();
        const formData = new FormData(categoryForm);
        const id = formData.get("category_id");
        const res = await fetch("php/categories_crud.php", { method: "POST", body: formData });
        const data = await res.json();
        showToast(data.message, data.success ? "success" : "error");
        if (!data.success) return;

        if (id) {
            const index = categories.findIndex(c => c.id == id);
            if (index > -1) categories[index].name = formData.get("name");
        } else {
            categories.unshift({ id: data.id, name: formData.get("name") });
        }

        renderCategoriesTable();
        renderServicesTable();
        updateServiceCategoryOptions(); // <-- refresh service modal dropdown
        toggleModal(categoryFormContainer, false);
    });


    // ==== EVENT DELEGATION ====
    document.getElementById("servicesMobile").addEventListener("click", e => {
        const btn = e.target.closest("button");
        if (!btn) return;
        const id = btn.dataset.id;
        const index = services.findIndex(s => s.id == id);
        if (index === -1) return;

        if (btn.classList.contains("editServiceBtn")) openServiceModal("Edit Service", services[index]);
        if (btn.classList.contains("deleteServiceBtn")) handleServiceDelete(id, index);
    });

    document.querySelector("#servicesTable tbody").addEventListener("click", e => {
        const btn = e.target.closest("button");
        if (!btn) return;
        const id = btn.dataset.id;
        const index = services.findIndex(s => s.id == id);
        if (index === -1) return;

        if (btn.classList.contains("editServiceBtn")) openServiceModal("Edit Service", services[index]);
        if (btn.classList.contains("deleteServiceBtn")) handleServiceDelete(id, index);
    });

    document.querySelector("div.mt-8 table tbody").addEventListener("click", e => {
        const btn = e.target.closest("button");
        if (!btn) return;
        const id = btn.dataset.id;
        const index = categories.findIndex(c => c.id == id);
        if (index === -1) return;

        if (btn.classList.contains("editCategoryBtn")) openCategoryModal("Edit Category", categories[index]);
        if (btn.classList.contains("deleteCategoryBtn")) handleCategoryDelete(id, index);
    });

    fetchData(); // Initial load
    window.addEventListener("resize", () => {
        renderServicesTable();
    });

});

