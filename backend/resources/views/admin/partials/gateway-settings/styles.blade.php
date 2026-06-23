<style>
    .gateway-settings-intro {
        color: var(--text-secondary);
        font-size: 13px;
        margin-bottom: 24px;
        line-height: 1.6;
    }

    .gateway-settings-panel .settings-section {
        background-color: #121223;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        padding: 24px;
        margin-bottom: 20px;
    }

    .gateway-settings-panel .settings-section-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .gateway-settings-panel .settings-section-icon {
        font-size: 24px;
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.15);
        border-radius: 10px;
    }

    .gateway-settings-panel .settings-section-title {
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px;
    }

    .gateway-settings-panel .settings-section-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.5;
    }

    .gateway-settings-panel .settings-fields-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .gateway-fields-2 {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .gateway-fields-2-wide {
        grid-template-columns: 1.6fr 1fr;
    }

    .gateway-fields-3 {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }

    .gateway-settings-panel .mt-16 {
        margin-top: 16px;
    }

    .gateway-field-hint {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 4px;
    }

    .gateway-field-hint code {
        color: var(--primary);
    }

    .gateway-form-actions {
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .gateway-form-actions-end {
        justify-content: flex-end;
    }

    .gateway-toggle-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .gateway-save-btn {
        padding: 8px 20px;
        font-size: 13px;
    }

    .gateway-settings-panel .toggle-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
        flex-shrink: 0;
    }

    .gateway-settings-panel .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .gateway-settings-panel .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #374151;
        border-radius: 28px;
        transition: var(--transition);
    }

    .gateway-settings-panel .toggle-slider::before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background-color: #fff;
        border-radius: 50%;
        transition: var(--transition);
    }

    .gateway-settings-panel .toggle-switch input:checked + .toggle-slider {
        background-color: var(--success);
        box-shadow: 0 0 12px rgba(34, 197, 94, 0.35);
    }

    .gateway-settings-panel .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(24px);
    }

    .gateway-settings-panel .toggle-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .gateway-diagnostics {
        border-color: rgba(99, 102, 241, 0.3);
        background-color: rgba(99, 102, 241, 0.02);
    }

    .gateway-diagnostics-icon {
        background-color: rgba(99, 102, 241, 0.12);
        color: var(--primary);
    }

    .gateway-diagnostics-title {
        color: #A5B4FC;
    }

    .gateway-diagnostics-grid {
        grid-template-columns: 1fr 1fr;
    }

    .gateway-test-card {
        background-color: rgba(0, 0, 0, 0.2);
        border: 1px solid var(--border-color);
        padding: 18px;
        border-radius: 8px;
    }

    .gateway-test-title {
        font-size: 13px;
        text-transform: uppercase;
        color: var(--text-primary);
        margin: 0 0 12px;
    }

    .gateway-test-card .input-group {
        margin-bottom: 12px;
    }

    .gateway-test-btn {
        width: 100%;
        height: 36px;
        margin-top: 4px;
    }

    @media (max-width: 768px) {
        .gateway-fields-2-wide,
        .gateway-diagnostics-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
