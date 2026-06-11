<div class="admin-sections-layout" style="grid-column: 1 / -1; {{ Auth::user()->isSuperAdmin() ? '' : 'grid-template-columns: 1fr;' }}">

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
                                    @if(Auth::user()->isSuperAdmin())
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="setCrudFormMode('user-form', {
                                                mode: 'edit',
                                                id: {{ $u->id }},
                                                action: '{{ route('admin.users.update', $u->id) }}',
                                                title: 'Edit User Account #{{ $u->id }}',
                                                submitLabel: 'Update User details',
                                                fields: {
                                                    name: {{ json_encode($u->name) }},
                                                    email: {{ json_encode($u->email) }},
                                                    role: {{ json_encode($u->role) }}
                                                }
                                            })">
                                            Edit
                                        </button>
                                    @endif
                                    @if(Auth::id() !== $u->id)
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user account? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                        </form>
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

    @if(Auth::user()->isSuperAdmin())
        <div class="booking-form-sidebar" id="user-sidebar-container">
            <h3 class="booking-summary-title" id="user-form-title">Edit User Role</h3>
            <div class="notice-info-box" style="margin-bottom: 15px; padding: 10px; font-size: 11px;">
                As a Super Admin, you can promote accounts to admin or demote them to regular users.
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
                    <input type="email" name="email" class="coupon-input" placeholder="Email Address" required>
                </div>
                
                <div class="input-group">
                    <label>Assigned Role</label>
                    <select name="role" class="coupon-input" required>
                        <option value="user">User (Frontend Only)</option>
                        <option value="admin">Admin (Staff dashboard)</option>
                        <option value="super_admin">Super Admin (Full access)</option>
                    </select>
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
            function resetUserForm() {
                const form = document.getElementById('user-form');
                if (!form) return;
                form.reset();
                const titleEl = document.getElementById('user-form-title');
                if (titleEl) titleEl.textContent = 'Edit User Role';
                const submitBtn = document.getElementById('user-form-submit');
                if (submitBtn) submitBtn.textContent = 'Update User details';
                const cancelBtn = document.getElementById('user-form-cancel');
                if (cancelBtn) cancelBtn.classList.remove('visible');
                form.action = '';
                const idInput = form.querySelector('[name="_edit_id"]');
                if (idInput) idInput.value = '';
            }
        </script>
    @endif

</div>
