"use strict";

(function () {
    const config = window.bannerPromoConfig || {};
    let groupCounters = {
        1: config.groupOneCount || 0,
        2: config.groupTwoCount || 0,
    };

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $all(selector, root) {
        return Array.from((root || document).querySelectorAll(selector));
    }

    function initSelect2() {
        if (typeof window.jQuery === "undefined" || !window.jQuery.HSCore) {
            return;
        }

        window.jQuery(".js-select2-custom").each(function () {
            window.jQuery.HSCore.components.HSSelect2.init(window.jQuery(this));
        });
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

    function buildVariationFields(group, index, productName, productId, variations, savedVariations) {
        const savedMap = {};
        (savedVariations || []).forEach(function (variation) {
            savedMap[variation.name] =
                (variation.values && variation.values.label && variation.values.label[0]) || "";
        });

        let variationHtml = "";
        (variations || []).forEach(function (variation, vIndex) {
            const options = variation.values || [];
            const selected = savedMap[variation.name] || (options[0] && options[0].label) || "";
            variationHtml += `
                <div class="form-group mb-2">
                    <label class="input-label">${variation.name}</label>
                    <input type="hidden" name="group_${group}[${index}][variations][${vIndex}][name]" value="${variation.name}">
                    <select name="group_${group}[${index}][variations][${vIndex}][values][label][]" class="form-control" required>
                        ${options
                            .map(function (option) {
                                const label = option.label || option.level || "";
                                return `<option value="${label}" ${label === selected ? "selected" : ""}>${label}</option>`;
                            })
                            .join("")}
                    </select>
                </div>
            `;
        });

        return `
            <div class="promo-group-item card mb-2" data-product-id="${productId}">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <strong>${productName}</strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-group-item">${window.translateRemove || "Remove"}</button>
                    </div>
                    <input type="hidden" name="group_${group}[${index}][product_id]" value="${productId}">
                    <div class="variation-fields">${variationHtml}</div>
                </div>
            </div>
        `;
    }

    function resetPicker(group) {
        const picker = document.getElementById(`group-${group}-product-picker`);
        if (!picker) return;

        picker.value = "";
        if (typeof window.jQuery !== "undefined") {
            window.jQuery(picker).val("").trigger("change");
        }
    }

    function addProductToGroup(group, productId) {
        if (!productId) return;

        const container = document.getElementById(`group-${group}-items`);
        if (!container || container.querySelector(`[data-product-id="${productId}"]`)) {
            return;
        }

        const url = `${config.productVariationsUrl}/${productId}`;
        fetch(url, {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Failed to load product variations");
                }
                return response.json();
            })
            .then(function (response) {
                const index = groupCounters[group]++;
                const html = buildVariationFields(
                    group,
                    index,
                    response.product_name,
                    response.product_id,
                    response.variations,
                    []
                );
                container.insertAdjacentHTML("beforeend", html);
            })
            .catch(function () {
                alert("Could not load product variations. Please try again.");
            });
    }

    function bindProductPicker(group) {
        const picker = document.getElementById(`group-${group}-product-picker`);
        if (!picker) return;

        picker.addEventListener("change", function () {
            const productId = picker.value;
            if (!productId) return;
            addProductToGroup(group, productId);
            resetPicker(group);
        });
    }

    function validatePromoForm(event) {
        const groupOneCount = document.querySelectorAll("#group-1-items .promo-group-item").length;
        const groupTwoCount = document.querySelectorAll("#group-2-items .promo-group-item").length;

        if (groupOneCount < 1 || groupTwoCount < 1) {
            event.preventDefault();
            alert("Please add at least one product to Group 1 and Group 2.");
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        initSelect2();
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

        bindProductPicker(1);
        bindProductPicker(2);

        document.addEventListener("click", function (event) {
            const removeButton = event.target.closest(".remove-group-item");
            if (!removeButton) return;
            const item = removeButton.closest(".promo-group-item");
            if (item) item.remove();
        });

        const form = document.getElementById("promo-banner-form");
        if (form) {
            form.addEventListener("submit", validatePromoForm);
        }
    });
})();
