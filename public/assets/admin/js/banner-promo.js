"use strict";

(function () {
    const config = window.bannerPromoConfig || {};
    let groupCounters = {
        1: config.groupOneCount || 0,
        2: config.groupTwoCount || 0,
    };

    function initSelect2() {
        $(".js-select2-custom").each(function () {
            $.HSCore.components.HSSelect2.init($(this));
        });
    }

    function readImagePreview(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            $(".banner-image-preview").attr("src", e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }

    function updatePromotionTypeFields() {
        const type = $(".promotion-type-input:checked").val();
        const rewardInput = $("#reward-discount-value");
        const cheapestInput = $("#discount-cheapest-percent");
        const expensiveInput = $("#discount-expensive-percent");
        const label = $("#reward-discount-label");

        if (type === "bogo") {
            rewardInput.val(100).prop("readonly", true);
            cheapestInput.val(cheapestInput.val() || 100);
            expensiveInput.val(expensiveInput.val() || 100);
            label.text("Reward Discount (%)");
        } else if (type === "percent_off") {
            rewardInput.prop("readonly", false);
            label.text("Reward Discount (%)");
            if (!cheapestInput.val()) cheapestInput.val(rewardInput.val());
            if (!expensiveInput.val()) expensiveInput.val(rewardInput.val());
        } else {
            rewardInput.prop("readonly", false);
            label.text("Fixed Discount Amount");
            cheapestInput.val("");
            expensiveInput.val("");
        }
    }

    function toggleOrderTypeOptions() {
        const mode = $(".order-type-mode:checked").val();
        $("#order-type-options").toggle(mode === "custom");
    }

    function buildVariationFields(group, index, productName, productId, variations, savedVariations) {
        const savedMap = {};
        (savedVariations || []).forEach(function (variation) {
            savedMap[variation.name] = (variation.values && variation.values.label && variation.values.label[0]) || "";
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
                        ${options.map(function (option) {
                            const label = option.label || option.level || "";
                            return `<option value="${label}" ${label === selected ? "selected" : ""}>${label}</option>`;
                        }).join("")}
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

    function addProductToGroup(group, productId) {
        if (!productId) return;

        const container = document.getElementById(`group-${group}-items`);
        if (container.querySelector(`[data-product-id="${productId}"]`)) {
            return;
        }

        $.get(`${config.productVariationsUrl}/${productId}`, function (response) {
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
        });
    }

    $(document).ready(function () {
        initSelect2();
        updatePromotionTypeFields();
        toggleOrderTypeOptions();

        $(".banner-image-input").on("change", function () {
            readImagePreview(this);
        });

        $(".promotion-type-input").on("change", updatePromotionTypeFields);
        $("#reward-discount-value").on("input", function () {
            const type = $(".promotion-type-input:checked").val();
            if (type === "percent_off") {
                $("#discount-cheapest-percent").val($(this).val());
                $("#discount-expensive-percent").val($(this).val());
            }
        });

        $(".order-type-mode").on("change", toggleOrderTypeOptions);

        $("#group-1-product-picker").on("change", function () {
            addProductToGroup(1, $(this).val());
            $(this).val("").trigger("change");
        });

        $("#group-2-product-picker").on("change", function () {
            addProductToGroup(2, $(this).val());
            $(this).val("").trigger("change");
        });

        $(document).on("click", ".remove-group-item", function () {
            $(this).closest(".promo-group-item").remove();
        });
    });
})();
