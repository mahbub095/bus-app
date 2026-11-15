/**
 * users.js
 *
 * User account edit form: populates the form with a selected user's data,
 * enforces role/email read-only rules, validates menu permissions, and
 * provides a reset function.
 *
 * Data contract (set in users.blade.php before this file loads):
 *   window.AdminUsers.isSuperAdmin — boolean, whether the logged-in user is a super admin
 *   window.AdminUsers.canCreate    — boolean, whether this operator may create accounts
 *   window.AdminUsers.storeUrl     — POST endpoint for creating a new user
 *
 * Two form modes share one form element:
 *   create — POST to storeUrl, password fields visible + required
 *   edit   — PUT to the per-row update URL, password fields hidden + disabled
 *
 * Validation rule:
 *   Admin-role users must have at least one menu permission selected.
 *   Attempting to save with zero permissions shows an inline error and
 *   blocks submission. The server enforces the same rule as a second layer.
 *
 * Role-driven permission states (syncPermissionState):
 *   super_admin — every checkbox ticked + locked (super admin always has all menus)
 *   admin       — checkboxes editable, at least one required
 *   user        — the whole Menu Permissions block is hidden (frontend-only account)
 *
 * Public API (called from users.blade.php onclick handlers):
 *   editUser(config)       — populate the form for editing a specific user
 *   openCreateUserForm()   — switch the form to "add new user" mode
 *   resetUserForm()        — cancel the current edit (back to create mode when allowed)
 */

// ─── Permission validation helpers ───────────────────────────────────────────

/**
 * Return the role that will be submitted, taking the "locked role" pattern
 * into account (a disabled select posts through a hidden input instead).
 */
function getEffectiveRole() {
    const roleSelect = document.getElementById('user-role-select');
    const hiddenRole = document.querySelector('#user-form input[type="hidden"][name="role"]');
    return (roleSelect?.disabled ? hiddenRole?.value : roleSelect?.value) || '';
}

/**
 * Return true when the current role value requires at least one permission.
 * Only 'admin' is constrained — super_admin bypasses permissions entirely,
 * and 'user' role has no dashboard access regardless of permissions.
 */
function roleRequiresPermissions() {
    return getEffectiveRole() === 'admin';
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
 * Apply the permission UI for the currently selected role:
 *   user        → hide the block entirely and lock every checkbox
 *   super_admin → tick and lock every checkbox (implicit full access)
 *   admin       → keep the block visible and editable
 */
function syncPermissionState() {
    const group = document.getElementById('user-permissions-group');
    const note  = document.getElementById('user-permissions-superadmin-note');
    const boxes = [...document.querySelectorAll('#user-form input[name="menu_permissions[]"]')];
    if (!group || boxes.length === 0) return;

    const role = getEffectiveRole();

    // Plain users only use the customer frontend — no dashboard permissions.
    if (role === 'user') {
        group.style.display = 'none';
        boxes.forEach(cb => { cb.checked = false; cb.disabled = true; });
        if (note) note.style.display = 'none';
        setPermissionsError(false);
        return;
    }

    group.style.display = '';

    // Super admins always have every menu, so show them all as active + locked.
    if (role === 'super_admin') {
        boxes.forEach(cb => { cb.checked = true; cb.disabled = true; });
        if (note) note.style.display = 'block';
        setPermissionsError(false);
        return;
    }

    // Admin — editable (selections come from the edited user or the operator).
    boxes.forEach(cb => { cb.disabled = false; });
    if (note) note.style.display = 'none';
    setPermissionsError(false);
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

// ─── Form mode helpers ───────────────────────────────────────────────

/** True when the form is in "add new user" mode (method POST). */
function isCreateMode() {
    const methodInput = document.querySelector('#user-form input[name="_method"]');
    return (methodInput?.value || 'POST').toUpperCase() === 'POST';
}

/**
 * Show or hide the password fields. They are disabled (and therefore not
 * submitted) while editing, since the update endpoint never touches passwords.
 */
function setPasswordGroupVisible(show) {
    const group = document.getElementById('user-password-group');
    if (!group) return;

    group.style.display = show ? 'flex' : 'none';
    group.querySelectorAll('input').forEach(input => {
        input.disabled = !show;
        input.required = show;
        if (!show) input.value = '';
    });
}

/**
 * Switch the form into "add new user" mode: blank fields, POST to the store
 * endpoint, password visible, role editable and defaulted to plain User.
 */
function openCreateUserForm() {
    const form = document.getElementById('user-form');
    if (!form || !window.AdminUsers?.canCreate) return;

    form.reset();
    form.action = window.AdminUsers.storeUrl;

    const methodInput = form.querySelector('input[name="_method"]');
    if (methodInput) methodInput.value = 'POST';

    const idInput = form.querySelector('input[name="_edit_id"]');
    if (idInput) idInput.value = '';

    const titleEl = document.getElementById('user-form-title');
    if (titleEl) titleEl.textContent = 'Add New User';

    const submitBtn = document.getElementById('user-form-submit');
    if (submitBtn) submitBtn.textContent = 'Create User';

    // Nothing to cancel out of — creating is the form's default mode
    const cancelBtn = document.getElementById('user-form-cancel');
    if (cancelBtn) cancelBtn.classList.remove('visible');

    // Role must be selectable again (a previous edit may have locked it)
    const roleSelect = document.getElementById('user-role-select');
    if (roleSelect) {
        roleSelect.disabled = false;
        roleSelect.value = 'user';
        roleSelect.removeAttribute('title');
    }
    form.querySelector('input[type="hidden"][name="role"]')?.remove();

    const emailInput = document.getElementById('user-email-input');
    if (emailInput) {
        emailInput.readOnly = false;
        emailInput.style.opacity = '1';
        emailInput.style.cursor = 'text';
        emailInput.removeAttribute('title');
    }

    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => { cb.checked = false; });
    setPermissionsError(false);
    setPasswordGroupVisible(true);
    syncPermissionState();

    form.querySelector('input[name="name"]')?.focus();
}

// ─── Form submit guard ────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        // Guard: no target user picked in edit mode (staff admin, empty form)
        // NOTE: read the attribute — the IDL property resolves "" to the page URL.
        if (!isCreateMode() && !(form.getAttribute('action') || '').trim()) {
            e.preventDefault();
            alert('Pick a user from the table to edit first.');
            return;
        }

        if (roleRequiresPermissions() && !hasPermissionChecked()) {
            e.preventDefault();
            setPermissionsError(true);

            // Scroll the error into view smoothly
            document.getElementById('user-permissions-error')
                ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        // Client-side password match check for new accounts
        if (isCreateMode()) {
            const password = form.querySelector('input[name="password"]');
            const confirm  = form.querySelector('input[name="password_confirmation"]');

            if (password && confirm && password.value !== confirm.value) {
                e.preventDefault();
                alert('The two passwords do not match.');
                confirm.focus();
            }
        }
    });

    bindPermissionLiveValidation();

    // Re-apply the permission UI whenever the role selection changes
    document.getElementById('user-role-select')?.addEventListener('change', () => {
        syncPermissionState();
    });

    // Initial state follows the default role of the empty form (user)
    syncPermissionState();

    // Password fields only apply to the create mode rendered by the server
    setPasswordGroupVisible(isCreateMode() && !!window.AdminUsers?.canCreate);
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
 *   menu_permissions_all: boolean,  // true when menu_permissions is NULL (= full access)
 *   is_self:          boolean,      // true when editing your own account
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

    // Editing never touches the password, so hide + disable those inputs
    setPasswordGroupVisible(false);

    // ── Menu permission checkboxes ────────────────────────────────
    // A NULL menu_permissions column means "full access" in the database, so
    // pre-tick every box to keep that account's access intact on save.
    const permissions    = config.menu_permissions || [];
    const allPermissions = !!config.menu_permissions_all;
    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = allPermissions || permissions.includes(cb.value);
    });

    // Clear any stale validation state from a previous edit session
    setPermissionsError(false);

    // ── Role selector restrictions ────────────────────────────────────────────
    const roleSelect   = document.getElementById('user-role-select');
    const isSuperAdmin = window.AdminUsers.isSuperAdmin;
    const targetRole   = config.role;

    // A Super Admin may re-assign anyone's role — including admins and other
    // Super Admins — except their own. Demoting yourself would remove the only
    // account able to manage roles. Staff admins can never change a role.
    const roleLocked = !isSuperAdmin || config.is_self === true;

    if (roleLocked) {
        roleSelect.disabled = true;
        roleSelect.setAttribute('title',
            config.is_self === true ? 'You cannot change your own role' : 'This role is not editable');

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

    // Show the "(required for Admin role)" hint when editing an admin user,
    // and lock/hide the permission block for super admin and user roles.
    const hintEl = document.getElementById('user-permissions-required-hint');
    if (hintEl) hintEl.style.display = targetRole === 'admin' ? 'inline' : 'none';

    syncPermissionState();

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
 * Cancel the current edit.
 *
 * Super Admins drop back into "add new user" mode (the sidebar's natural
 * state for them); everyone else gets a neutral, unbound form.
 */
function resetUserForm() {
    if (window.AdminUsers?.canCreate) {
        openCreateUserForm();
        return;
    }

    const form = document.getElementById('user-form');
    if (!form) return;

    form.reset();
    form.action = '';

    const methodInput = form.querySelector('input[name="_method"]');
    if (methodInput) methodInput.value = 'PUT';

    const titleEl   = document.getElementById('user-form-title');
    const submitBtn = document.getElementById('user-form-submit');
    const cancelBtn = document.getElementById('user-form-cancel');
    const idInput   = form.querySelector('[name="_edit_id"]');

    if (titleEl)   titleEl.textContent   = 'Edit User Role & Permissions';
    if (submitBtn) submitBtn.textContent = 'Update User details';
    if (cancelBtn) cancelBtn.classList.remove('visible');
    if (idInput)   idInput.value = '';

    // Password fields only ever exist for Super Admins in create mode
    setPasswordGroupVisible(false);

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

    // Default role is "user" → the permission block must stay hidden
    syncPermissionState();
}

// ─── Global exports (required for inline onclick handlers in blade templates) ──
window.editUser           = editUser;
window.openCreateUserForm = openCreateUserForm;
window.resetUserForm      = resetUserForm;
