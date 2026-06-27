"use strict";

(function () {
    const config = window.bannerPromoConfig || {};
    const products = config.products || [];
    let groupCounters = {
        1: config.groupOneCount || 0,
        2: config.groupTwoCount || 0,
    };
    let activeGroup = null;
    let selectedProduct = null;
    let selectedProductDetails = null;

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $all(selector, root) {
        return Array.from((root || document).querySelectorAll(selector));
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function readImagePreview(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const preview = $(".banner-image-preview");
            if (preview) {
                preview.setAttribute("src", e.target.result);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }

    function updatePromotionTypeFields() {
        const checked = document.querySelector(".promotion-type-input:checked");
        const type = checked ? checked.value : "bogo";
        const rewardInput = $("#reward-discount-value");
        const cheapestInput = $("#discount-cheapest-percent");
        const expensiveInput = $("#discount-expensive-percent");
        const label = $("#reward-discount-label");

        if (!rewardInput || !label) return;

        if (type === "bogo") {
            rewardInput.value = "100";
            rewardInput.readOnly = true;
            if (cheapestInput && !cheapestInput.value) cheapestInput.value = "100";
            if (expensiveInput && !expensiveInput.value) expensiveInput.value = "100";
            label.textContent = "Reward Discount (%)";
        } else if (type === "percent_off") {
            rewardInput.readOnly = false;
            label.textContent = "Reward Discount (%)";
            if (cheapestInput && !cheapestInput.value) cheapestInput.value = rewardInput.value;
            if (expensiveInput && !expensiveInput.value) expensiveInput.value = rewardInput.value;
        } else {
            rewardInput.readOnly = false;
            label.textContent = "Fixed Discount Amount";
            if (cheapestInput) cheapestInput.value = "";
            if (expensiveInput) expensiveInput.value = "";
        }
    }

    function toggleOrderTypeOptions() {
        const checked = document.querySelector(".order-type-mode:checked");
        const options = $("#order-type-options");
        if (options) {
            options.style.display = checked && checked.value === "custom" ? "" : "none";
        }
    }

    function filterVariations(variations) {
        return (variations || []).filter(function (variation) {
            return variation && typeof variation === "object" && !Object.prototype.hasOwnProperty.call(variation, "price");
        });
    }

    function buildVariationFieldsHtml(group, index, variations, savedVariations, fieldClass) {
        const savedMap = {};
        (savedVariations || []).forEach(function (variation) {
            savedMap[variation.name] =
                (variation.values && variation.values.label && variation.values.label[0]) || "";
        });

        let variationHtml = "";
        filterVariations(variations).forEach(function (variation, vIndex) {
            const options = variation.values || [];
            const selected = savedMap[variation.name] || (options[0] && (options[0].label || options[0].level)) || "";
            variationHtml += `
                <div class="form-group mb-2">
                    <label class="input-label mb-1">${escapeHtml(variation.name)}</label>
                    <input type="hidden" name="group_${group}[${index}][variations][${vIndex}][name]" value="${escapeHtml(variation.name)}">
                    <select name="group_${group}[${index}][variations][${vIndex}][values][label][]" class="form-control form-control-sm ${fieldClass || ""}" required>
                        ${options
                            .map(function (option) {
                                const label = option.label || option.level || "";
                                return `<option value="${escapeHtml(label)}" ${label === selected ? "selected" : ""}>${escapeHtml(label)}</option>`;
                            })
                            .join("")}
                    </select>
                </div>
            `;
        });

        return variationHtml;
    }

    function buildGroupItemHtml(group, index, productName, productId, productImage, variations, savedVariations) {
        const variationHtml = buildVariationFieldsHtml(group, index, variations, savedVariations, "");
        const fallbackImage = config.fallbackImage || "";

        return `
            <div class="promo-group-item card mb-2" data-product-id="${productId}">
                <div class="card-body py-3">
                    <div class="d-flex gap-3">
                        <img src="${escapeHtml(productImage || fallbackImage)}" alt="${escapeHtml(productName)}" class="promo-group-item-thumb rounded border">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <strong class="text-wrap">${escapeHtml(productName)}</strong>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-group-item flex-shrink-0">${escapeHtml(window.translateRemove || "Remove")}</button>
                            </div>
                            <input type="hidden" name="group_${group}[${index}][product_id]" value="${productId}">
                            <div class="variation-fields">${variationHtml}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function showPickerStep(step) {
        const productsStep = $("#promo-picker-step-products");
        const variationsStep = $("#promo-picker-step-variations");
        const confirmButton = $("#promo-picker-confirm-add");

        if (productsStep) productsStep.classList.toggle("d-none", step !== "products");
        if (variationsStep) variationsStep.classList.toggle("d-none", step !== "variations");
        if (confirmButton) confirmButton.classList.toggle("d-none", step !== "variations");
    }

    function renderProductGrid(query) {
        const grid = $("#promo-picker-product-grid");
        const emptyState = $("#promo-picker-empty");
        if (!grid) return;

        const normalizedQuery = (query || "").trim().toLowerCase();
        const filtered = products.filter(function (product) {
            return !normalizedQuery || String(product.name || "").toLowerCase().includes(normalizedQuery);
        });

        grid.innerHTML = filtered
            .map(function (product) {
                return `
                    <button type="button" class="promo-product-card promo-picker-product" data-product-id="${product.id}">
                        <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}">
                        <span class="promo-product-card__name">${escapeHtml(product.name)}</span>
                    </button>
                `;
            })
            .join("");

        if (emptyState) {
            emptyState.classList.toggle("d-none", filtered.length > 0);
        }
    }

    function openProductPicker(group) {
        activeGroup = group;
        selectedProduct = null;
        selectedProductDetails = null;

        const modalTitle = $("#promoProductPickerLabel");
        if (modalTitle) {
            modalTitle.textContent =
                group === 1
                    ? window.translateGroupOneTitle || "Items Group 1"
                    : window.translateGroupTwoTitle || "Items Group 2";
        }

        const searchInput = $("#promo-picker-search");
        if (searchInput) {
            searchInput.value = "";
        }

        renderProductGrid("");
        showPickerStep("products");

        if (typeof window.jQuery !== "undefined") {
            window.jQuery("#promo-product-picker-modal").modal("show");
        }
    }

    function closeProductPicker() {
        if (typeof window.jQuery !== "undefined") {
            window.jQuery("#promo-product-picker-modal").modal("hide");
        }
    }

    function loadProductDetails(productId) {
        const url = `${config.productVariationsUrl}/${productId}`;

        return fetch(url, {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Failed to load product");
            }
            return response.json();
        });
    }

    function renderVariationStep(details) {
        const image = $("#promo-picker-selected-image");
        const name = $("#promo-picker-selected-name");
        const fields = $("#promo-picker-variation-fields");
        const noVariations = $("#promo-picker-no-variations");
        const variations = filterVariations(details.variations);

        if (image) image.setAttribute("src", details.product_image || "");
        if (name) name.textContent = details.product_name || "";

        if (!fields || !noVariations) return;

        if (variations.length === 0) {
            fields.innerHTML = "";
            noVariations.classList.remove("d-none");
            return;
        }

        noVariations.classList.add("d-none");
        fields.innerHTML = variations
            .map(function (variation, vIndex) {
                const options = variation.values || [];
                const defaultLabel = options[0] ? options[0].label || options[0].level || "" : "";
                return `
                    <div class="form-group mb-2">
                        <label class="input-label mb-1">${escapeHtml(variation.name)}</label>
                        <select class="form-control promo-picker-variation-select" data-variation-name="${escapeHtml(variation.name)}" data-variation-index="${vIndex}" required>
                            ${options
                                .map(function (option) {
                                    const label = option.label || option.level || "";
                                    return `<option value="${escapeHtml(label)}" ${label === defaultLabel ? "selected" : ""}>${escapeHtml(label)}</option>`;
                                })
                                .join("")}
                        </select>
                    </div>
                `;
            })
            .join("");
    }

    function collectPickerVariations() {
        const variations = [];
        $all(".promo-picker-variation-select").forEach(function (select) {
            variations.push({
                name: select.getAttribute("data-variation-name") || "",
                values: { label: [select.value] },
            });
        });
        return variations;
    }

    function addProductToGroup(group, details, savedVariations) {
        const container = document.getElementById(`group-${group}-items`);
        if (!container) return;

        const index = groupCounters[group]++;
        const html = buildGroupItemHtml(
            group,
            index,
            details.product_name,
            details.product_id,
            details.product_image,
            details.variations,
            savedVariations || collectPickerVariations()
        );
        container.insertAdjacentHTML("beforeend", html);
    }

    function handleProductCardClick(productId) {
        const product = products.find(function (item) {
            return String(item.id) === String(productId);
        });
        if (!product) return;

        selectedProduct = product;

        loadProductDetails(productId)
            .then(function (details) {
                selectedProductDetails = details;
                renderVariationStep(details);
                showPickerStep("variations");
            })
            .catch(function () {
                alert(window.translateLoadFailed || "Could not load product details. Please try again.");
            });
    }

    function confirmAddProduct() {
        if (!activeGroup || !selectedProductDetails) return;

        addProductToGroup(activeGroup, selectedProductDetails, collectPickerVariations());
        closeProductPicker();
    }

    function countGroupItems(group) {
        const container = document.getElementById(`group-${group}-items`);
        if (!container) return 0;
        return container.querySelectorAll(".promo-group-item").length;
    }

    function validatePromoForm(event) {
        event.preventDefault();
        const form = event.target;
        const groupOneCount = countGroupItems(1);
        const groupTwoCount = countGroupItems(2);

        if (groupOneCount < 1 || groupTwoCount < 1) {
            alert(
                window.translateGroupProductsRequired ||
                    "Please add at least one product to Group 1 and Group 2."
            );
            return;
        }

        form.removeEventListener("submit", validatePromoForm);
        form.submit();
    }

    document.addEventListener("DOMContentLoaded", function () {
        updatePromotionTypeFields();
        toggleOrderTypeOptions();

        $all(".banner-image-input").forEach(function (input) {
            input.addEventListener("change", function () {
                readImagePreview(input);
            });
        });

        $all(".promotion-type-input").forEach(function (input) {
            input.addEventListener("change", updatePromotionTypeFields);
        });

        const rewardInput = $("#reward-discount-value");
        if (rewardInput) {
            rewardInput.addEventListener("input", function () {
                const checked = document.querySelector(".promotion-type-input:checked");
                if (checked && checked.value === "percent_off") {
                    const cheapest = $("#discount-cheapest-percent");
                    const expensive = $("#discount-expensive-percent");
                    if (cheapest) cheapest.value = rewardInput.value;
                    if (expensive) expensive.value = rewardInput.value;
                }
            });
        }

        $all(".order-type-mode").forEach(function (input) {
            input.addEventListener("change", toggleOrderTypeOptions);
        });

        $all(".open-product-picker").forEach(function (button) {
            button.addEventListener("click", function () {
                openProductPicker(Number(button.getAttribute("data-group")));
            });
        });

        const searchInput = $("#promo-picker-search");
        if (searchInput) {
            searchInput.addEventListener("input", function () {
                renderProductGrid(searchInput.value);
            });
        }

        document.addEventListener("click", function (event) {
            const productCard = event.target.closest(".promo-picker-product");
            if (productCard) {
                handleProductCardClick(productCard.getAttribute("data-product-id"));
                return;
            }

            const removeButton = event.target.closest(".remove-group-item");
            if (removeButton) {
                const item = removeButton.closest(".promo-group-item");
                if (item) item.remove();
                return;
            }
        });

        const backButton = $("#promo-picker-back");
        if (backButton) {
            backButton.addEventListener("click", function () {
                showPickerStep("products");
            });
        }

        const confirmButton = $("#promo-picker-confirm-add");
        if (confirmButton) {
            confirmButton.addEventListener("click", confirmAddProduct);
        }

        const form = document.getElementById("promo-banner-form");
        if (form) {
            form.addEventListener("submit", validatePromoForm);
        }
    });
})();
