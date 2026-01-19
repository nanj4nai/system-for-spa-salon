let editingExtraProductId = null;
let pendingRemoveExtraProductId = null;
let pendingRemoveExtraProductName = "";

function applyExtraProductLockUI(container) {

    // 🔥 ALWAYS reset first
    container.classList.remove("opacity-50");

    container.querySelectorAll("button").forEach(btn => {
        btn.disabled = false;
        btn.classList.remove("opacity-50", "cursor-not-allowed");
    });

    // 🔒 Apply lock ONLY if locked
    if (!CashierState.transactionLocked) return;

    container.classList.add("opacity-50");

    container.querySelectorAll("button").forEach(btn => {
        btn.disabled = true;
        btn.classList.add("opacity-50", "cursor-not-allowed");
    });
}



function loadServiceProducts(serviceId) {
    const box = document.getElementById("serviceProductsPreview");
    box.innerHTML = "";
    box.classList.remove("hidden");

    fetch(`../php/cashier/center/products/get-service-products.php?service_id=${serviceId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.products.length) {
                box.innerHTML = `
                    <div class="text-xs italic text-gray-400">
                        No products linked to this service
                    </div>
                `;
                return;
            }

            const allOutOfStock = d.products.every(p => Number(p.stock) <= 0);

            if (allOutOfStock) {
                box.innerHTML = `
                    <div class="text-xs font-medium text-red-600">
                        ⚠ No products left for this service
                    </div>
                `;
                return;
            }

            box.innerHTML = `
                <div class="font-medium mb-1 text-xs">
                    Products to be used
                </div>

                <!-- Friendly reminder -->
                <div class="mb-2 text-[11px] text-gray-500 italic">
                    Tip: The quantity shown is only a guide.
                    You can change it later to match what was actually used.
                </div>

                ${d.products.map(p => `
                    <div class="flex justify-between items-center text-xs
                        ${p.stock <= 0 ? "opacity-50 line-through" : ""}">
                        <span>${p.name}</span>
                        <span class="text-gray-600 dark:text-gray-300">
                            ${p.default_qty}${p.unit}
                            <span class="opacity-60">
                                (stock: ${p.stock}${p.unit})
                            </span>
                        </span>
                    </div>
                `).join("")}
            `;
        });
}

function loadAllProducts() {
    const select = document.getElementById("extraProductSelect");
    select.innerHTML = `<option>Loading…</option>`;

    return fetch(
        `../php/cashier/center/products/get-available-extra-products.php?appointment_id=${CashierState.activeAppointmentId}`
    )
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.products.length) {
                select.innerHTML = `<option value="">No products available</option>`;
                return;
            }

            select.innerHTML = `
                <option value="">Select product</option>
                ${d.products.map(p => {
                const disabled = p.used_in_service ? "disabled" : "";
                const tooltip = p.used_in_service
                    ? "Already included in service"
                    : "";

                return `
                        <option
                            value="${p.id}"
                            ${disabled}
                            title="${tooltip}"
                            data-type="${p.product_type}"
                            data-unit="${p.unit}"
                            data-stock="${p.stock}"
                            data-unit-per-item="${p.unit_per_item || ""}"
                            data-price="${p.price}">
                        ${(() => {
                        if (p.used_in_service) {
                            return `🔒 ${p.name} — included in service`;
                        }

                        if (p.product_type === "consumable" && p.unit_per_item) {
                            const packs = Math.floor(p.stock / p.unit_per_item);
                            return `${p.name} — ${p.unit_per_item}${p.unit}/pack (${packs} packs)`;
                        }

                        if (p.product_type === "one_time") {
                            return `${p.name} (${p.stock} pcs)`;
                        }

                        // ✅ reusable
                        return `${p.name} (reusable — ${p.stock} available)`;
                    })()}

                        </option>
                    `;
            }).join("")}
            `;
        });
}


function removeExtraProduct(id) {
    if (CashierState.transactionLocked) {
        showToast("Transaction is locked", "error");
        return;
    }
    pendingRemoveExtraProductId = id;

    const card = document.querySelector(
        `[data-extra-product-id="${id}"]`
    );

    pendingRemoveExtraProductName =
        card?.querySelector(".font-medium")?.textContent || "Selected product";

    document.getElementById("removeExtraProductName").textContent =
        pendingRemoveExtraProductName;

    const modal = document.getElementById("removeExtraProductModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

document.getElementById("cancelRemoveExtraProductBtn")
    .addEventListener("click", () => {

        pendingRemoveExtraProductId = null;
        pendingRemoveExtraProductName = "";

        const modal = document.getElementById("removeExtraProductModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    });

document.getElementById("confirmRemoveExtraProductBtn")
    .addEventListener("click", () => {

        if (!pendingRemoveExtraProductId) return;

        const id = pendingRemoveExtraProductId;

        pendingRemoveExtraProductId = null;
        pendingRemoveExtraProductName = "";

        const modal = document.getElementById("removeExtraProductModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");

        fetch("../php/cashier/center/products/remove-extra-product.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        })
            .then(r => r.json())
            .then(d => {
                if (!d.success) {
                    showToast(d.error, "error");
                    return;
                }

                showToast("Product removed");
                loadExtraProducts();
                if (CashierState.activeTransactionId) {
                    loadTransaction(CashierState.activeTransactionId);
                }
            });
    });

function loadExtraProducts() {
    const box = document.getElementById("extraProductList");

    fetch(`../php/cashier/center/products/get-extra-products.php?appointment_id=${CashierState.activeAppointmentId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.products.length) {
                box.innerHTML = `<div class="text-xs italic text-gray-400">No extra products added</div>`;
                return;
            }

            box.innerHTML = d.products.map(p => `
                <div
                    class="flex justify-between items-center border p-2 rounded"
                    data-extra-product-id="${p.id}"
                >
                    <div>
                        <div class="font-medium">${p.name}</div>
                        <div class="text-xs text-gray-500">
                            ${p.quantity}${p.unit} × ₱${p.unit_price}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="text-red-500 text-xs"
                            onclick="removeExtraProduct(${p.id})">
                            Remove
                        </button>
                    </div>
                </div>
            `).join("");
            // 🔒 APPLY LOCK UI
            applyExtraProductLockUI(box);
        });
}


// open add extra product modal
document.getElementById("addExtraProductBtn").addEventListener("click", async () => {
    if (!CashierState.activeAppointmentId) {
        showToast("Select an appointment first", "error");
        return;
    }

    if (!hasAtLeastOneService()) {
        showToast("Add at least one service before adding extra products", "error");
        return;
    }

    editingExtraProductId = null;
    document.getElementById("extraProductQty").value = 1;

    await loadAllProducts();

    document.getElementById("extraProductModal").classList.remove("hidden");
    document.getElementById("extraProductModal").classList.add("flex");
});


// confirm adding extra product
document.getElementById("confirmExtraProductBtn").addEventListener("click", () => {
    const select = document.getElementById("extraProductSelect");
    const opt = select.selectedOptions[0];
    const qty = parseFloat(document.getElementById("extraProductQty").value);

    if (!opt) {
        showToast("Select a product", "error");
        return;
    }

    const type = opt.dataset.type;

    if (type !== "reusable" && (!qty || qty <= 0)) {
        showToast("Invalid quantity", "error");
        return;
    }

    fetch("../php/cashier/center/products/add-extra-product.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            appointment_id: CashierState.activeAppointmentId,
            product_id: opt.value,
            quantity: qty
        })
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            showToast("Product added");
            resetExtraProductModal();
            loadExtraProducts();
            if (CashierState.activeTransactionId) {
                loadTransaction(CashierState.activeTransactionId);
            }
        });
});

// close modal when clicking outside content
document.getElementById("extraProductModal").addEventListener("click", e => {
    if (e.target.id === "extraProductModal") {
        resetExtraProductModal();
    }
});
document.getElementById("cancelExtraProductBtn").addEventListener("click", e => {
    e.preventDefault();
    e.stopPropagation(); // prevent overlay click confusion
    resetExtraProductModal();
});

document.getElementById("extraProductSelect").addEventListener("change", e => {
    const opt = e.target.selectedOptions[0];
    if (!opt) return;

    const type = opt.dataset.type;
    const unit = opt.dataset.unit;
    const stock = opt.dataset.stock;

    const qtyWrapper = document.getElementById("extraQtyWrapper");
    const qtyInput = document.getElementById("extraProductQty");
    const info = document.getElementById("extraProductInfo");
    const label = document.getElementById("extraQtyLabel");

    info.classList.remove("hidden");

    if (type === "reusable") {
        qtyWrapper.classList.remove("hidden");
        qtyInput.step = 1;
        qtyInput.min = 1;
        qtyInput.max = stock; // 🔥 limit to available
        label.textContent = "Quantity (pcs)";
        info.textContent =
            `Reusable item — ${stock} available (charged per piece, not deducted from stock)`;
    }

    else if (type === "one_time") {
        // bottled water, food
        qtyWrapper.classList.remove("hidden");
        qtyInput.step = 1;
        qtyInput.min = 1;
        label.textContent = "Quantity (pcs)";
        info.textContent = `Available: ${stock} pcs`;
    }

    else {
        // consumable
        qtyWrapper.classList.remove("hidden");
        qtyInput.step = 0.01;
        qtyInput.min = 0.01;

        label.textContent = `Quantity (${unit})`;

        if (opt.dataset.unitPerItem) {
            const perItem = Number(opt.dataset.unitPerItem);
            const packs = Math.floor(stock / perItem);

            info.textContent =
                `${perItem}${unit} per pack • ${packs} packs available`;
        } else {
            info.textContent = `Available: ${stock}${unit}`;
        }
    }
});

function resetExtraProductModal() {
    editingExtraProductId = null;

    const modal = document.getElementById("extraProductModal");
    const select = document.getElementById("extraProductSelect");
    const qty = document.getElementById("extraProductQty");

    if (select) select.value = "";
    if (qty) qty.value = 1;

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function hasAtLeastOneService() {
    return document.querySelectorAll(".service-card").length > 0;
}

function updateExtraProductAvailability() {
    const btn = document.getElementById("addExtraProductBtn");
    const hint = document.getElementById("extraProductHint");

    if (!btn) return;

    if (CashierState.transactionLocked) {
        btn.disabled = true;
        btn.classList.add("opacity-40", "cursor-not-allowed");
        btn.title = "Transaction is locked";
        hint?.classList.add("hidden");
        return;
    }

    // existing logic
    if (!hasAtLeastOneService()) {
        btn.disabled = true;
        btn.classList.add("opacity-50", "cursor-not-allowed");
        btn.title = "Add a service first";
        hint?.classList.remove("hidden");
    } else {
        btn.disabled = false;
        btn.classList.remove("opacity-50", "cursor-not-allowed");
        btn.title = "";
        hint?.classList.add("hidden");
    }
}


async function updateTransactionTotals() {
    if (!CashierState.activeTransactionId) return;

    const res = await fetch(
        `../php/cashier/left/get-transaction-details.php?transaction_id=${CashierState.activeTransactionId}`,
        { cache: "no-store" }
    );

    const d = await res.json();
    if (!d.success) return;

    renderPaymentBreakdown(d);
}
