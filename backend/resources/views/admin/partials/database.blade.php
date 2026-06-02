<div class="admin-panel" style="grid-column: 1 / -1;">
    <h3 class="admin-panel-title" style="color: #F87171;">System Database Operations</h3>
    
    <div class="notice-info-box">
        <strong>⚠️ WARNING: Critical Actions</strong><br>
        Running database operations will run table configurations and seed data directly on the SQLite database file. Action is irreversible.
    </div>

    <!-- Database operations grids -->
    <div class="db-actions-list">
        
        <!-- Action 1: Migrate -->
        <div class="db-action-card">
            <h4 style="font-family: var(--font-display); font-size: 16px; color: #fff;">1. Run Tables Migration</h4>
            <p style="font-size: 12px; color: var(--text-secondary);">Executes pending table migrations in the backend without losing existing logs.</p>
            <form action="{{ route('admin.system.migrate') }}" method="POST" style="margin-top: 10px;">
                @csrf
                <button type="submit" class="btn btn-secondary w-full" style="width: 100%;">
                    Execute php artisan migrate
                </button>
            </form>
        </div>

        <!-- Action 2: Seed -->
        <div class="db-action-card">
            <h4 style="font-family: var(--font-display); font-size: 16px; color: #fff;">2. Run Database Seeder</h4>
            <p style="font-size: 12px; color: var(--text-secondary);">Populates database models with default dummy coaches, routes, promotions, and active schedules.</p>
            <form action="{{ route('admin.system.seed') }}" method="POST" style="margin-top: 10px;">
                @csrf
                <button type="submit" class="btn btn-secondary w-full" style="width: 100%;">
                    Execute php artisan db:seed
                </button>
            </form>
        </div>

        <!-- Action 3: Fresh Seed -->
        <div class="db-action-card" style="border-color: rgba(239, 68, 68, 0.25);">
            <h4 style="font-family: var(--font-display); font-size: 16px; color: #EF4444;">3. Reset Database & Seed</h4>
            <p style="font-size: 12px; color: var(--text-secondary);">Drops all active database tables and executes a fresh migration and seeder instantiation from scratch.</p>
            <form action="{{ route('admin.system.migrate-fresh-seed') }}" method="POST" style="margin-top: 10px;" onsubmit="return confirm('WARNING: This will wipe out all existing bookings logs. Proceed?');">
                @csrf
                <button type="submit" class="btn btn-danger w-full" style="width: 100%;">
                    Execute migrate:fresh --seed
                </button>
            </form>
        </div>

    </div>

    <!-- Console Output Terminal Window -->
    @if(session('console_output'))
        <div class="terminal-window">
            <div class="terminal-header">
                <span class="terminal-dot red"></span>
                <span class="terminal-dot yellow"></span>
                <span class="terminal-dot green"></span>
                <span class="terminal-title">Artisan Command Console Output logs</span>
            </div>
            <pre class="terminal-body">{{ session('console_output') }}</pre>
        </div>
    @endif

</div>
