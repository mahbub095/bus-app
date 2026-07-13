/**
 * users.js
 *
 * User account edit form: populates the form with a selected user's data,
 * enforces role/email read-only rules, and provides a reset function.
 *
 * Data contract (set in users.blade.php before this file loads):
 *   window.AdminUsers.isSuperAdmin — boolean, whether the logged-in user is a super admin
 *
 * Public API (called from users.blade.php onclick handlers):
 *   editUser(config)   — populate the form for editing a specific user
 *   resetUserForm()    — clear the form back to its default empty state
 */

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

/**
 * Reset the user form back to its blank default state
 * (clears all inputs, unchecks permissions, re-enables role/email).
 */
function resetUserForm() {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.reset();
    form.action = '';

    const titleEl  = document.getElementById('user-form-title');
    const submitBtn = document.getElementById('user-form-submit');
    const cancelBtn = document.getElementById('user-form-cancel');
    const idInput   = form.querySelector('[name="_edit_id"]');

    if (titleEl)   titleEl.textContent   = 'Edit User Role & Permissions';
    if (submitBtn) submitBtn.textContent = 'Update User details';
    if (cancelBtn) cancelBtn.classList.remove('visible');
    if (idInput)   idInput.value = '';

    // Uncheck all permission checkboxes
    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = false;
    });

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
