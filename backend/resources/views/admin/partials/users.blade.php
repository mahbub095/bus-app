<div class="admin-sections-layout" style="grid-column: 1 / -1; {{ Auth::user()->isAdmin() ? '' : 'grid-template-columns: 1fr;' }}">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Registered User Accounts</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined Date</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>#{{ $u->id }}</td>
                            <td style="font-weight: bold; color: #fff;">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>
                                @if($u->role === 'super_admin')
                                    <span class="badge-status pending" style="background-color: rgba(167, 139, 250, 0.15); color: #C084FC;">Super Admin</span>
                                @elseif($u->role === 'admin')
                                    <span class="badge-status paid">Admin</span>
                                @else
                                    <span class="badge-status" style="background-color: rgba(156, 163, 175, 0.15); color: #D1D5DB;">User</span>
                                @endif
                            </td>
                            <td style="color: var(--text-secondary)">{{ $u->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="action-btns">
                                    @if(Auth::user()->isAdmin())
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="editUser({
                                                id: {{ $u->id }},
                                                name: {{ json_encode($u->name) }},
                                                email: {{ json_encode($u->email) }},
                                                role: {{ json_encode($u->role) }},
                                                menu_permissions: {{ json_encode($u->menu_permissions ?? []) }},
                                                action: '{{ route('admin.users.update', $u->id) }}'
                                            })">
                                            Edit
                                        </button>
                                    @endif
                                    @if(Auth::id() !== $u->id)
                                        @if(Auth::user()->isSuperAdmin() || !in_array($u->role, ['super_admin', 'admin']))
                                            <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user account? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                            </form>
                                        @endif
                                    @else
                                        <span style="color: var(--text-muted); font-size: 11px;">(Active Account)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted)">No registered users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(Auth::user()->isAdmin())
        <div class="booking-form-sidebar" id="user-sidebar-container">
            <h3 class="booking-summary-title" id="user-form-title">Edit User Role & Permissions</h3>
            <div class="notice-info-box" style="margin-bottom: 15px; padding: 10px; font-size: 11px;">
                Update account details, role, and menu-based dashboard permissions.
            </div>
            <form class="booking-form-fields" id="user-form" action="" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_edit_id" value="">
                
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="coupon-input" placeholder="Full Name" required>
                </div>
                
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="user-email-input" class="coupon-input" placeholder="Email Address" required>
                </div>
                
                <div class="input-group" id="role-select-container">
                    <label>Assigned Role</label>
                    <select name="role" id="user-role-select" class="coupon-input" required>
                        <option value="user">User (Frontend Only)</option>
                        <option value="admin">Admin (Staff dashboard)</option>
                        <option value="super_admin">Super Admin (Full access)</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Menu Permissions</label>
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px; background: var(--bg-panel-alt); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 12px;">
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="coach-services" style="accent-color: var(--primary);">
                            Coach Services
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="bookings" style="accent-color: var(--primary);">
                            Bookings Logs
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="cancel-requests" style="accent-color: var(--primary);">
                            Cancel Requests
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="stations" style="accent-color: var(--primary);">
                            Stations
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="buses" style="accent-color: var(--primary);">
                            Coaches
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="routes" style="accent-color: var(--primary);">
                            Routes
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="schedules" style="accent-color: var(--primary);">
                            Schedules
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="promotions" style="accent-color: var(--primary);">
                            Coupons
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="users" style="accent-color: var(--primary);">
                            Users & Roles
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="reports" style="accent-color: var(--primary);">
                            Ticket Reports
                        </label>
                    </div>
                </div>
                
                <button class="btn btn-primary" id="user-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                    Update User details
                </button>
                <button type="button" class="btn btn-secondary form-cancel-btn" id="user-form-cancel"
                    onclick="resetUserForm()">
                    Cancel Edit
                </button>
            </form>
        </div>

        <script>
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
                const isSuperAdmin = {{ Auth::user()->isSuperAdmin() ? 'true' : 'false' }};
                const targetRole = config.role;

                // Disable role select if target user is super_admin/admin OR the logged in user is not super_admin
                if (targetRole === 'super_admin' || targetRole === 'admin' || !isSuperAdmin) {
                    roleSelect.disabled = true;
                    roleSelect.setAttribute('title', 'This role is not editable');
                    // Add a hidden input to submit the role value
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
                    if (hiddenRole) {
                        hiddenRole.remove();
                    }
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

                // Uncheck checkboxes
                form.querySelectorAll('input[name="menu_permissions[]"]').forEach(cb => {
                    cb.checked = false;
                });

                // Reset role dropdown state
                const roleSelect = document.getElementById('user-role-select');
                if (roleSelect) {
                    roleSelect.disabled = false;
                    roleSelect.removeAttribute('title');
                }
                const hiddenRole = form.querySelector('input[type="hidden"][name="role"]');
                if (hiddenRole) {
                    hiddenRole.remove();
                }

                // Reset email input readOnly status
                const emailInput = document.getElementById('user-email-input');
                if (emailInput) {
                    emailInput.readOnly = false;
                    emailInput.style.opacity = '1';
                    emailInput.style.cursor = 'text';
                    emailInput.removeAttribute('title');
                }
            }
        </script>
    @endif

</div>
