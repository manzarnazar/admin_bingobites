<style>
    #addModifierTemplateModal .modal-dialog {
        max-width: 920px;
    }
    .template-items-header {
        display: grid;
        grid-template-columns: minmax(200px, 2fr) 72px 72px 110px 44px;
        gap: .75rem;
        padding: 0 .25rem .5rem;
        font-size: .75rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .template-item-row {
        display: grid;
        grid-template-columns: minmax(200px, 2fr) 72px 72px 110px 44px;
        gap: .75rem;
        align-items: start;
        padding: .75rem;
        margin-bottom: .75rem;
        border: 1px solid #e7eaf3;
        border-radius: .5rem;
        background: #fafbfc;
    }
    .template-item-row .template-addon-cell {
        min-width: 0;
    }
    .template-item-row .new-addon-fields {
        display: grid;
        grid-template-columns: 1fr 90px;
        gap: .5rem;
        margin-top: .5rem;
    }
    .template-item-row .template-sort-input {
        max-width: 70px;
    }
    .template-item-row .template-toggles {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        padding-top: .35rem;
    }
    .template-toggle-label {
        display: flex;
        align-items: center;
        gap: .35rem;
        white-space: nowrap;
        margin-bottom: 0;
        font-size: .875rem;
    }
    .template-item-row .template-delete-btn {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: .15rem;
    }
    @media (max-width: 767px) {
        .template-items-header {
            display: none;
        }
        .template-item-row {
            grid-template-columns: 1fr;
        }
        .template-item-row .template-toggles {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .template-item-row .template-delete-btn {
            justify-content: flex-start;
        }
    }
</style>
