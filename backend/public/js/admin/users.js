function editUser(config) {
    setCrudFormMode('user-form', {
        mode: 'edit',
        id: config.id,
        action: config.action,
        title: 'Edit User Account #' + config.id,
        submitLabel: 'Update User details',
        fields: {
            name: config.name,
            email: config.email,
            role: config.role
        }
    });

    const form = document.getElementById('user-form');
    if (!form) return;

    // Set menu permissions checkboxes
    const permissions = config.menu_permissions || [];
    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = permissions.includes(cb.value);
    });

    const roleSelect = document.getElementById('user-role-select');
    const isSuperAdmin = window.AdminUsers.isSuperAdmin;
    const targetRole = config.role;

    // Disable role select if target user is super_admin/admin OR the logged-in user is not super_admin
    if (targetRole === 'super_admin' || targetRole === 'admin' || !isSuperAdmin) {
        roleSelect.disabled = true;
        roleSelect.setAttribute('title', 'This role is not editable');
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
        const hiddenRole = form.querySelector('input[type="hidden"][name="role"]');
        if (hiddenRole) hiddenRole.remove();
    }

    // Readonly email for super_admin
    const emailInput = document.getElementById('user-email-input');
    if (emailInput) {
        if (targetRole === 'super_admin') {
            emailInput.readOnly = true;
            emailInput.style.opacity = '0.7';
            emailInput.style.cursor = 'not-allowed';
            emailInput.setAttribute('title', 'Super Admin email address is not editable');
        } else {
            emailInput.readOnly = false;
            emailInput.style.opacity = '1';
            emailInput.style.cursor = 'text';
            emailInput.removeAttribute('title');
        }
    }
}

function resetUserForm() {
    const form = document.getElementById('user-form');
    if (!form) return;

    form.reset();

    const titleEl = document.getElementById('user-form-title');
    if (titleEl) titleEl.textContent = 'Edit User Role & Permissions';

    const submitBtn = document.getElementById('user-form-submit');
    if (submitBtn) submitBtn.textContent = 'Update User details';

    const cancelBtn = document.getElementById('user-form-cancel');
    if (cancelBtn) cancelBtn.classList.remove('visible');

    form.action = '';
    const idInput = form.querySelector('[name="_edit_id"]');
    if (idInput) idInput.value = '';

    form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
        cb.checked = false;
    });

    const roleSelect = document.getElementById('user-role-select');
    if (roleSelect) {
        roleSelect.disabled = false;
        roleSelect.removeAttribute('title');
    }

    const hiddenRole = form.querySelector('input[type="hidden"][name="role"]');
    if (hiddenRole) hiddenRole.remove();

    const emailInput = document.getElementById('user-email-input');
    if (emailInput) {
        emailInput.readOnly = false;
        emailInput.style.opacity = '1';
        emailInput.style.cursor = 'text';
        emailInput.removeAttribute('title');
    }
}
