{{-- Site Settings Admin Panel --}}
<div class="admin-panel">
    <div class="admin-panel-title">
        <span>⚙️ Site Settings Management</span>
    </div>

    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px; line-height: 1.6;">
        Manage your website identity, footer content, maintenance mode, and SEO settings from this panel.
        All changes will be reflected on the customer-facing website in real-time.
    </p>

    {{-- Favicon Upload Form (separate due to enctype) --}}
    <div class="settings-section">
        <div class="settings-section-header">
            <span class="settings-section-icon">🖼️</span>
            <div>
                <h3 class="settings-section-title">Favicon</h3>
                <p class="settings-section-desc">Upload a custom favicon (ICO, PNG, SVG, max 512KB). This icon appears in browser tabs.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
            @if(!empty($siteSettings['favicon_url']))
                <div class="favicon-preview">
                    <img src="{{ $siteSettings['favicon_url'] }}" alt="Current Favicon"
                         style="width: 32px; height: 32px; border-radius: 4px;">
                    <span style="font-size: 12px; color: var(--text-secondary);">Current: {{ $siteSettings['favicon_url'] }}</span>
                </div>
            @endif
        </div>
        <form action="{{ route('admin.site-settings.favicon') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="admin_tab" value="site-settings">
            <div style="display: flex; align-items: end; gap: 12px; flex-wrap: wrap;">
                <div class="input-group" style="flex: 1; min-width: 200px;">
                    <label for="favicon">Upload New Favicon</label>
                    <input type="file" name="favicon" id="favicon" class="coupon-input" accept=".ico,.png,.svg,.jpg,.jpeg,.gif,.webp"
                           style="padding: 8px 12px;">
                </div>
                <button type="submit" class="btn btn-secondary" style="height: 42px; white-space: nowrap;">
                    📤 Upload Favicon
                </button>
            </div>
        </form>
    </div>

    {{-- Main Settings Form --}}
    <form action="{{ route('admin.site-settings.update') }}" method="POST" id="site-settings-form">
        @csrf
        <input type="hidden" name="admin_tab" value="site-settings">

        {{-- Section 1: Website Identity --}}
        <div class="settings-section">
            <div class="settings-section-header">
                <span class="settings-section-icon">🌐</span>
                <div>
                    <h3 class="settings-section-title">Website Identity</h3>
                    <p class="settings-section-desc">Set your website title that appears in browser tabs and search results.</p>
                </div>
            </div>
            <div class="settings-fields-grid">
                <div class="input-group">
                    <label for="site_title">Website Title</label>
                    <input type="text" name="site_title" id="site_title" class="coupon-input"
                           value="{{ $siteSettings['site_title'] ?? 'SonyaBus | Premium Bus Ticket Reservation' }}"
                           required>
                </div>
            </div>
        </div>

        {{-- Section 2: Footer Management --}}
        <div class="settings-section">
            <div class="settings-section-header">
                <span class="settings-section-icon">📝</span>
                <div>
                    <h3 class="settings-section-title">Footer Management</h3>
                    <p class="settings-section-desc">Customize the footer displayed on the customer website and admin dashboard.</p>
                </div>
            </div>
            <div class="settings-fields-grid">
                <div class="input-group">
                    <label for="footer_company_name">Company Name</label>
                    <input type="text" name="footer_company_name" id="footer_company_name" class="coupon-input"
                           value="{{ $siteSettings['footer_company_name'] ?? 'SonyaBus Enterprise' }}" required>
                </div>
                <div class="input-group">
                    <label for="footer_copyright">Copyright Text</label>
                    <input type="text" name="footer_copyright" id="footer_copyright" class="coupon-input"
                           value="{{ $siteSettings['footer_copyright'] ?? '© 2026 SonyaBus Enterprise Ltd. All rights reserved.' }}" required>
                </div>
            </div>

            <div class="input-group" style="margin-top: 16px;">
                <label>Footer Links (JSON Array)</label>
                <textarea name="footer_links" id="footer_links" class="coupon-input" rows="4"
                          style="font-family: monospace; font-size: 12px; resize: vertical;"
                >{{ $siteSettings['footer_links'] ?? '[{"label":"Search Buses","tab":"home"},{"label":"My Tickets","tab":"cancel"},{"label":"Special Promotions","tab":"offers"},{"label":"My Profile","tab":"profile"}]' }}</textarea>
                <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                    Format: <code style="color: var(--primary);">[{"label":"Link Text","tab":"home"},...]</code> — Tabs: home, cancel, offers, profile. Or use <code style="color: var(--primary);">"url"</code> for external links.
                </p>
            </div>
        </div>

        {{-- Section 3: Maintenance Mode --}}
        <div class="settings-section">
            <div class="settings-section-header">
                <span class="settings-section-icon">🔧</span>
                <div>
                    <h3 class="settings-section-title">Maintenance Mode</h3>
                    <p class="settings-section-desc">When enabled, visitors will see a maintenance page. The admin dashboard remains accessible.</p>
                </div>
            </div>

            <div class="maintenance-toggle-row">
                <label class="toggle-switch">
                    <input type="checkbox" name="maintenance_mode" value="true"
                           {{ ($siteSettings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : '' }}
                           id="maintenance_toggle">
                    <span class="toggle-slider"></span>
                </label>
                <div>
                    <span class="toggle-label" id="maintenance_status">
                        {{ ($siteSettings['maintenance_mode'] ?? 'false') === 'true' ? '🔴 Maintenance Mode is ACTIVE' : '🟢 Website is LIVE' }}
                    </span>
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        Toggle to enable/disable maintenance mode for the customer website.
                    </p>
                </div>
            </div>

            <div class="input-group" style="margin-top: 16px;">
                <label for="maintenance_message">Maintenance Message</label>
                <textarea name="maintenance_message" id="maintenance_message" class="coupon-input" rows="3"
                          style="resize: vertical;"
                          placeholder="Enter the message visitors will see during maintenance..."
                >{{ $siteSettings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Our booking platform will be back online shortly. Thank you for your patience.' }}</textarea>
            </div>
        </div>

        {{-- Section 4: SEO Settings --}}
        <div class="settings-section">
            <div class="settings-section-header">
                <span class="settings-section-icon">🔍</span>
                <div>
                    <h3 class="settings-section-title">SEO Settings</h3>
                    <p class="settings-section-desc">Optimize your website for search engines. These meta tags help improve visibility on Google, Facebook, and other platforms.</p>
                </div>
            </div>
            <div class="settings-fields-grid">
                <div class="input-group">
                    <label for="seo_meta_description">Meta Description</label>
                    <textarea name="seo_meta_description" id="seo_meta_description" class="coupon-input" rows="2"
                              style="resize: vertical;"
                              placeholder="A brief description of your website for search engines..."
                    >{{ $siteSettings['seo_meta_description'] ?? '' }}</textarea>
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        Recommended: 150-160 characters. This appears in Google search results.
                    </p>
                </div>
                <div class="input-group">
                    <label for="seo_meta_keywords">Meta Keywords</label>
                    <input type="text" name="seo_meta_keywords" id="seo_meta_keywords" class="coupon-input"
                           value="{{ $siteSettings['seo_meta_keywords'] ?? '' }}"
                           placeholder="bus tickets, Bangladesh, SonyaBus, online booking">
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        Comma-separated keywords related to your business.
                    </p>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <p style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary); margin-bottom: 14px;">
                    Open Graph (Social Media Sharing)
                </p>
                <div class="settings-fields-grid">
                    <div class="input-group">
                        <label for="seo_og_title">OG Title</label>
                        <input type="text" name="seo_og_title" id="seo_og_title" class="coupon-input"
                               value="{{ $siteSettings['seo_og_title'] ?? '' }}"
                               placeholder="Title shown when shared on Facebook/Twitter">
                    </div>
                    <div class="input-group">
                        <label for="seo_og_description">OG Description</label>
                        <textarea name="seo_og_description" id="seo_og_description" class="coupon-input" rows="2"
                                  style="resize: vertical;"
                                  placeholder="Description shown when shared on social media..."
                        >{{ $siteSettings['seo_og_description'] ?? '' }}</textarea>
                    </div>
                    <div class="input-group">
                        <label for="seo_og_image">OG Image URL</label>
                        <input type="text" name="seo_og_image" id="seo_og_image" class="coupon-input"
                               value="{{ $siteSettings['seo_og_image'] ?? '' }}"
                               placeholder="https://example.com/og-image.jpg">
                        <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                            Recommended size: 1200×630 pixels. Used when page is shared on social media.
                        </p>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <p style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary); margin-bottom: 14px;">
                    Analytics Integration
                </p>
                <div class="input-group">
                    <label for="seo_google_analytics_id">Google Analytics Measurement ID</label>
                    <input type="text" name="seo_google_analytics_id" id="seo_google_analytics_id" class="coupon-input"
                           value="{{ $siteSettings['seo_google_analytics_id'] ?? '' }}"
                           placeholder="G-XXXXXXXXXX">
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        Enter your GA4 Measurement ID (e.g., G-XXXXXXXXXX). Leave blank to disable.
                    </p>
                </div>
            </div>
        </div>

        {{-- Submit Button --}}
        <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-size: 14px;">
                💾 Save All Settings
            </button>
        </div>
    </form>
</div>

{{-- Styles for settings sections --}}
<style>
    .settings-section {
        background-color: var(--bg-panel-alt);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        padding: 24px;
        margin-bottom: 20px;
    }

    .settings-section-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .settings-section-icon {
        font-size: 24px;
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.15);
        border-radius: 10px;
    }

    .settings-section-title {
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px;
    }

    .settings-section-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.5;
    }

    .settings-fields-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .favicon-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        background-color: var(--sidebar-hover);
        padding: 10px 16px;
        border-radius: var(--border-radius-sm);
        border: 1px solid var(--border-color);
    }

    /* Toggle Switch */
    .maintenance-toggle-row {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
        flex-shrink: 0;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #374151;
        border-radius: 28px;
        transition: var(--transition);
    }

    .toggle-slider::before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background-color: #fff;
        border-radius: 50%;
        transition: var(--transition);
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--danger);
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
    }

    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(24px);
    }

    .toggle-label {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }
</style>

{{-- Script for toggle label --}}
<script src="{{ asset('js/admin/site-settings.js') }}"></script>
