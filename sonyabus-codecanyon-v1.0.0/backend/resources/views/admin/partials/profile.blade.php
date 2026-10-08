<div class="admin-sections-layout">
    <div class="admin-panel">
        <h2 class="admin-panel-title">Admin Profile</h2>
        <div class="notice-info-box">
            You are signed in as <strong>{{ Auth::user()->email }}</strong>. Update your password below to secure your admin dashboard account.
        </div>
    </div>

    <aside class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="profile-form-title">Update Password</h3>
        <form class="booking-form-fields" action="{{ route('admin.profile.password') }}" method="POST">
            @csrf
            <div class="input-group">
                <label for="admin-current-password">Current Password</label>
                <input
                    id="admin-current-password"
                    type="password"
                    name="current_password"
                    class="coupon-input"
                    required
                    minlength="6"
                    autocomplete="current-password"
                >
            </div>

            <div class="input-group">
                <label for="admin-new-password">New Password</label>
                <input
                    id="admin-new-password"
                    type="password"
                    name="password"
                    class="coupon-input"
                    required
                    minlength="6"
                    autocomplete="new-password"
                >
            </div>

            <div class="input-group">
                <label for="admin-new-password-confirmation">Confirm New Password</label>
                <input
                    id="admin-new-password-confirmation"
                    type="password"
                    name="password_confirmation"
                    class="coupon-input"
                    required
                    minlength="6"
                    autocomplete="new-password"
                >
            </div>

            <button class="btn btn-primary" type="submit">
                Save New Password
            </button>
        </form>
    </aside>
</div>
