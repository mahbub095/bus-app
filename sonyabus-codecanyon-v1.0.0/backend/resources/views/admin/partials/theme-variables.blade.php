:root,
[data-theme="light"] {
    color-scheme: light;

    --font-sans: 'Inter', system-ui, sans-serif;
    --font-display: 'Outfit', sans-serif;

    --bg-main: #F4F6FB;
    --bg-card: #FFFFFF;
    --bg-card-hover: #F8FAFC;
    --bg-header: rgba(255, 255, 255, 0.94);
    --bg-panel-alt: #F8FAFC;
    --bg-input: #FFFFFF;
    --bg-input-elevated: #F1F5F9;
    --bg-btn-secondary: #FFFFFF;
    --bg-btn-secondary-hover: #F1F5F9;
    --bg-footer: #FFFFFF;
    --bg-overlay: rgba(15, 23, 42, 0.5);
    --bg-terminal: #F8FAFC;
    --bg-terminal-header: #EEF2FF;
    --bg-seat-panel: #F1F5F9;
    --bg-seat-map: #FFFFFF;

    --border-color: #E2E8F0;
    --border-active: #6366F1;
    --border-seat: #CBD5E1;

    --primary: #6366F1;
    --primary-hover: #4F46E5;
    --primary-glow: rgba(99, 102, 241, 0.18);

    --accent: #8B5CF6;
    --accent-glow: rgba(139, 92, 246, 0.14);

    --gold: #D97706;

    --text-primary: #0F172A;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --text-inverse: #FFFFFF;

    --title-gradient-start: #0F172A;
    --title-gradient-end: #475569;

    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;

    --sidebar-hover: rgba(99, 102, 241, 0.06);
    --sidebar-active-bg: rgba(99, 102, 241, 0.1);

    --chart-grid: rgba(15, 23, 42, 0.08);
    --chart-border: #FFFFFF;

    --designer-canvas-bg: #F8FAFC;
    --designer-grid-bg: #FFFFFF;
    --designer-control-bg: #F8FAFC;
    --designer-cell-slot-bg: #EEF2FF;
    --designer-cell-border: #CBD5E1;
    --designer-aisle-color: #94A3B8;
    --designer-empty-color: #CBD5E1;

    --border-radius: 12px;
    --border-radius-sm: 8px;
    --border-radius-lg: 20px;

    --shadow-lg: 0 12px 32px rgba(15, 23, 42, 0.08);
    --shadow-neon: 0 0 0 1px rgba(99, 102, 241, 0.12), 0 8px 20px rgba(99, 102, 241, 0.12);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] {
    color-scheme: dark;

    --bg-main: #0B0B14;
    --bg-card: #141424;
    --bg-card-hover: #1C1C34;
    --bg-header: rgba(11, 11, 20, 0.92);
    --bg-panel-alt: #18182E;
    --bg-input: #1A1A2E;
    --bg-input-elevated: #1F1F38;
    --bg-btn-secondary: #202038;
    --bg-btn-secondary-hover: #2B2B4C;
    --bg-footer: #08080E;
    --bg-overlay: rgba(11, 11, 20, 0.85);
    --bg-terminal: #05050A;
    --bg-terminal-header: #121223;
    --bg-seat-panel: #121221;
    --bg-seat-map: #0A0A12;

    --border-color: rgba(255, 255, 255, 0.08);
    --border-active: #6366F1;
    --border-seat: #2A2A44;

    --primary: #6366F1;
    --primary-hover: #4F46E5;
    --primary-glow: rgba(99, 102, 241, 0.25);

    --accent: #A855F7;
    --accent-glow: rgba(168, 85, 247, 0.2);

    --gold: #F59E0B;

    --text-primary: #F3F4F6;
    --text-secondary: #9CA3AF;
    --text-muted: #6B7280;
    --text-inverse: #FFFFFF;

    --title-gradient-start: #FFFFFF;
    --title-gradient-end: #D1D5DB;

    --success: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;

    --sidebar-hover: rgba(255, 255, 255, 0.03);
    --sidebar-active-bg: rgba(99, 102, 241, 0.1);

    --chart-grid: rgba(255, 255, 255, 0.06);
    --chart-border: #141424;

    --designer-canvas-bg: #0A0A12;
    --designer-grid-bg: #0D0D19;
    --designer-control-bg: rgba(255, 255, 255, 0.02);
    --designer-cell-slot-bg: rgba(255, 255, 255, 0.02);
    --designer-cell-border: rgba(255, 255, 255, 0.15);
    --designer-aisle-color: rgba(255, 255, 255, 0.2);
    --designer-empty-color: rgba(255, 255, 255, 0.1);

    --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.7);
    --shadow-neon: 0 0 15px rgba(99, 102, 241, 0.4);
}
