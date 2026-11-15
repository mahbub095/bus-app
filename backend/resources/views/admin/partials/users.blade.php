<div class="admin-sections-layout" style="grid-column: 1 / -1; {{ Auth::user()->isAdmin() ? '' : 'grid-template-columns: 1fr;' }}">

    <div class="admin-panel">
        <h3 class="admin-panel-title">
            Registered User Accounts
            @if(Auth::user()->isSuperAdmin())
                <button type="button" class="btn btn-primary btn-sm" id="user-add-new-btn"
                        onclick="openCreateUserForm()"
                        style="height:32px; padding:0 14px; font-size:12px; font-weight:600;">
                    + Add New User
                </button>
            @endif
        </h3>
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
                                    {{-- Staff admins may only edit plain customer accounts; --}}
                                    {{-- admin / super_admin rows are Super Admin territory.   --}}
                                    @if(Auth::user()->isSuperAdmin() || $u->isUser())
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="editUser({
                                                id: {{ $u->id }},
                                                name: {{ json_encode($u->name) }},
                                                email: {{ json_encode($u->email) }},
                                                role: {{ json_encode($u->role) }},
                                                menu_permissions: {{ json_encode($u->menu_permissions ?? []) }},
                                                menu_permissions_all: {{ is_null($u->menu_permissions) ? 'true' : 'false' }},
                                                is_self: {{ Auth::id() === $u->id ? 'true' : 'false' }},
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
        {{ $users->links('admin.partials.pagination') }}
    </div>

    @if(Auth::user()->isAdmin())
        {{-- Super Admins land on the "create" state; staff admins start neutral. --}}
        @php($canCreateUsers = Auth::user()->isSuperAdmin())
        <div class="booking-form-sidebar" id="user-sidebar-container">
            <h3 class="booking-summary-title" id="user-form-title">
                {{ $canCreateUsers ? 'Add New User' : 'Edit User Role & Permissions' }}
            </h3>
            <div class="notice-info-box" style="margin-bottom: 15px; padding: 10px; font-size: 11px;">
                @if($canCreateUsers)
                    Create an account, then pick its role and which menus it can open.
                @else
                    Update account details, role, and menu-based dashboard permissions.
                @endif
            </div>
            <form class="booking-form-fields" id="user-form"
                  action="{{ $canCreateUsers ? route('admin.users.store') : '' }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="user-form-method" value="{{ $canCreateUsers ? 'POST' : 'PUT' }}">
                <input type="hidden" name="_edit_id" value="">
                
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="coupon-input" placeholder="Full Name" required>
                </div>
                
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="user-email-input" class="coupon-input" placeholder="Email Address" required>
                </div>

                @if($canCreateUsers)
                    {{-- Password only exists while creating; the JS disables it in edit mode --}}
                    <div id="user-password-group" style="display:flex; flex-direction:column; gap:12px;">
                        <div class="input-group">
                            <label>Password</label>
                            <input type="password" name="password" class="coupon-input"
                                   placeholder="Minimum 8 characters" autocomplete="new-password">
                        </div>
                        <div class="input-group">
                            <label>Confirm Password</label>
                            <input type="password" name="password_confirmation" class="coupon-input"
                                   placeholder="Repeat the password" autocomplete="new-password">
                        </div>
                    </div>
                @endif
                
                <div class="input-group" id="role-select-container">
                    <label>Assigned Role</label>
                    <select name="role" id="user-role-select" class="coupon-input" required>
                        <option value="user">User (Frontend Only)</option>
                        <option value="admin">Admin (Staff dashboard)</option>
                        <option value="super_admin">Super Admin (Full access)</option>
                    </select>
                </div>

                <div class="input-group" id="user-permissions-group">
                    <label>Menu Permissions
                        <span id="user-permissions-required-hint"
                              style="display:none; color:var(--danger); font-size:11px; font-weight:normal; margin-left:6px;">
                            (required for Admin role)
                        </span>
                    </label>
                    <div id="user-permissions-box"
                         style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px; background: var(--bg-panel-alt); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 12px;">
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

                        <div style="border-top: 1px solid var(--border-color); margin-top: 4px; padding-top: 10px; font-size: 10px; letter-spacing: .6px; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">
                            System
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="license" style="accent-color: var(--primary);">
                            License
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="site-settings" style="accent-color: var(--primary);">
                            Site Settings
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="gateways" style="accent-color: var(--primary);">
                            Integrations & Gateways
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-primary); text-transform: none; font-size: 13px; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="menu_permissions[]" value="profile" style="accent-color: var(--primary);">
                            Profile
                        </label>
                    </div>
                    <div id="user-permissions-superadmin-note"
                         style="display:none; color:var(--text-secondary); font-size:11px; margin-top:6px; padding: 8px 10px;
                                background:rgba(167,139,250,0.08); border:1px solid rgba(167,139,250,0.3);
                                border-radius:var(--border-radius-sm);">
                        Super Admin always has every permission — no selection needed.
                    </div>
                    <div id="user-permissions-error"
                         style="display:none; color:var(--danger); font-size:12px; margin-top:6px; padding: 8px 10px;
                                background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3);
                                border-radius:var(--border-radius-sm);">
                        At least one menu permission must be selected for Admin users.
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
            window.AdminUsers = {
                isSuperAdmin: {{ Auth::user()->isSuperAdmin() ? 'true' : 'false' }},
                canCreate: {{ $canCreateUsers ? 'true' : 'false' }},
                storeUrl: @json(route('admin.users.store')),
            };
        </script>
        @vite('resources/js/admin/users.js')
    @endif

</div>
