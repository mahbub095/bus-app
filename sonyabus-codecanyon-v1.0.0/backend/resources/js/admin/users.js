/**
 * users.js
 *
 * User account edit form: populates the form with a selected user's data,
 * enforces role/email read-only rules, validates menu permissions, and
 * provides a reset function.
 *
 * Data contract (set in users.blade.php before this file loads):
 *   window.AdminUsers.isSuperAdmin — boolean, whether the logged-in user is a super admin
 *
 * Validation rule:
 *   Admin-role users must have at least one menu permission selected.
 *   Attempting to save with zero permissions shows an inline error and
 *   blocks submission. The server enforces the same rule as a second layer.
 *
 * Public API (called from users.blade.php onclick handlers):
 *   editUser(config)   — populate the form for editing a specific user
 *   resetUserForm()    — clear the form back to its default empty state
 */

// ─── Permission validation helpers ───────────────────────────────────────────

/**
 * Return true when the current role value requires at least one permission.
 * Only 'admin' is constrained — super_admin bypasses permissions entirely,
 * and 'user' role has no dashboard access regardless of permissions.
 */
function roleRequiresPermissions() {
    const roleSelect = document.getElementById('user-role-select');
    const hiddenRole = document.querySelector('#user-form input[type="hidden"][name="role"]');
    const role = (roleSelect?.disabled ? hiddenRole?.value : roleSelect?.value) || '';
    return role === 'admin';
}

/** Return true when at least one permission checkbox is checked. */
function hasPermissionChecked() {
    return [...document.querySelectorAll('#user-form input[name="menu_permissions[]"]')]
        .some(cb => cb.checked);
}

/**
 * Show or hide the permissions error banner and highlight the checkbox area.
 * Also toggles the "(required for Admin role)" hint next to the label.
 */
function setPermissionsError(show) {
    const errorEl = document.getElementById('user-permissions-error');
    const hintEl  = document.getElementById('user-permissions-required-hint');
    const boxEl   = document.getElementById('user-permissions-box');

    if (errorEl) errorEl.style.display = show ? 'block' : 'none';

    // The hint is only relevant for admin-role users — always derive from
    // the current role, not from the `show` flag.
    if (hintEl) hintEl.style.display = roleRequiresPermissions() ? 'inline' : 'none';

    // Always reset the border first; only apply the error colour when
    // we are actively showing an error AND the role actually requires permissions.
    if (boxEl) {
        boxEl.style.borderColor = (show && roleRequiresPermissions())
            ? 'rgba(239,68,68,0.6)'
            : 'var(--border-color)';
    }
}

/**
 * Validate permissions on the fly whenever a checkbox changes.
 * Shows the error when the last box is unchecked (for admin role),
 * and clears it as soon as at least one box is checked.
 */
function bindPermissionLiveValidation() {
    document.querySelectorAll('#user-form input[name="menu_permissions[]"]').forEach(cb => {
        cb.addEventListener('change', () => {
            if (roleRequiresPermissions()) {
                setPermissionsError(!hasPermissionChecked());
            }
        });
    });
}

// ─── Form submit guard ────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        if (roleRequiresPermissions() && !hasPermissionChecked()) {
            e.preventDefault();
            setPermissionsError(true);

            // Scroll the error into view smoothly
            document.getElementById('user-permissions-error')
                ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    bindPermissionLiveValidation();
});

// ─── Edit form ────────────────────────────────────────────────────────────────

/**
 * Populate the user form with an existing user's data.
 *
 * @param {{
 *   id:               number,
 *   name:             string,
 *   email:            string,
 *   role:             string,
 *   menu_permissions: string[],
 *   action:           string,
 * }} config
 */
function editUser(config) {
    // Use the shared CRUD helper from layout.js to handle title/button/action
    setCrudFormMode('user-form', {
        mode:        'edit',
        id:          config.id,
        action:      config.action,
        title:       `Edit User Account #${config.id}`,
        submitLabel: 'Update User details',
        fields: {
            name:  config.name,
            email: config.email,
            role:  config.role,
        },
    });

    const form = document.getElementById('user-form');
    if (!form) return;

    // ── Menu permission checkboxes ────────────────────────────────────────────
    const permissions = config.menu_permissions || [];
    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = permissions.includes(cb.value);
    });

    // Clear any stale validation state from a previous edit session
    setPermissionsError(false);

    // ── Role selector restrictions ────────────────────────────────────────────
    const roleSelect   = document.getElementById('user-role-select');
    const isSuperAdmin = window.AdminUsers.isSuperAdmin;
    const targetRole   = config.role;

    // Role is locked if the target user is an admin/super_admin, or if the
    // currently logged-in user is not a super admin (staff can't self-promote).
    const roleLocked = targetRole === 'super_admin' || targetRole === 'admin' || !isSuperAdmin;

    if (roleLocked) {
        roleSelect.disabled = true;
        roleSelect.setAttribute('title', 'This role is not editable');

        // Add a hidden input to carry the role value since disabled selects are excluded from POST
        let hiddenRole = form.querySelector('input[type="hidden"][name="role"]');
        if (!hiddenRole) {
            hiddenRole = document.createElement('input');
            hiddenRole.type = 'hidden';
            hiddenRole.name = 'role';
            form.appendChild(hiddenRole);
        }
        hiddenRole.value = targetRole;
    } else {
        roleSelect.disabled = false;
        roleSelect.removeAttribute('title');
        form.querySelector('input[type="hidden"][name="role"]')?.remove();
    }

    // Show the "(required for Admin role)" hint when editing an admin user
    const hintEl = document.getElementById('user-permissions-required-hint');
    if (hintEl) hintEl.style.display = targetRole === 'admin' ? 'inline' : 'none';

    // ── Email field restrictions ───────────────────────────────────────────────
    // Super admin email is immutable — changing it could lock out the account.
    const emailInput = document.getElementById('user-email-input');
    if (emailInput) {
        const emailLocked = targetRole === 'super_admin';
        emailInput.readOnly      = emailLocked;
        emailInput.style.opacity = emailLocked ? '0.7' : '1';
        emailInput.style.cursor  = emailLocked ? 'not-allowed' : 'text';
        if (emailLocked) {
            emailInput.setAttribute('title', 'Super Admin email address is not editable');
        } else {
            emailInput.removeAttribute('title');
        }
    }
}

// ─── Reset form ───────────────────────────────────────────────────────────────

/**
 * Reset the user form back to its blank default state
 * (clears all inputs, unchecks permissions, re-enables role/email).
 */
function resetUserForm() {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.reset();
    form.action = '';

    const titleEl   = document.getElementById('user-form-title');
    const submitBtn = document.getElementById('user-form-submit');
    const cancelBtn = document.getElementById('user-form-cancel');
    const idInput   = form.querySelector('[name="_edit_id"]');

    if (titleEl)   titleEl.textContent   = 'Edit User Role & Permissions';
    if (submitBtn) submitBtn.textContent = 'Update User details';
    if (cancelBtn) cancelBtn.classList.remove('visible');
    if (idInput)   idInput.value = '';

    // Uncheck all permission checkboxes and clear validation state
    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = false;
    });
    setPermissionsError(false);

    // Re-enable the role selector and remove any lingering hidden role input
    const roleSelect = document.getElementById('user-role-select');
    if (roleSelect) {
        roleSelect.disabled = false;
        roleSelect.removeAttribute('title');
    }
    form.querySelector('input[type="hidden"][name="role"]')?.remove();

    // Re-enable the email field
    const emailInput = document.getElementById('user-email-input');
    if (emailInput) {
        emailInput.readOnly      = false;
        emailInput.style.opacity = '1';
        emailInput.style.cursor  = 'text';
        emailInput.removeAttribute('title');
    }
}

// ─── Global exports (required for inline onclick handlers in blade templates) ──
window.editUser      = editUser;
window.resetUserForm = resetUserForm;
