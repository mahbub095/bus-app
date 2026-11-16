{{--
    Browser tab icon.

    Resolves the admin-configured favicon (Site Settings → Favicon) to an
    absolute URL so it also works when the app is served from a sub-directory.
    Falls back to the bundled /favicon.svg when nothing has been uploaded.
--}}
@php
    $faviconSetting = trim((string) ($siteSettings['favicon_url'] ?? ''));

    $faviconHref = $faviconSetting === ''
        ? asset('favicon.svg')
        : (\Illuminate\Support\Str::startsWith($faviconSetting, ['http://', 'https://', '//'])
            ? $faviconSetting
            : asset(ltrim($faviconSetting, '/')));

    $faviconExtension = strtolower(pathinfo(parse_url($faviconHref, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

    $faviconType = [
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ][$faviconExtension] ?? null;
@endphp
<link rel="icon" href="{{ $faviconHref }}"@if($faviconType) type="{{ $faviconType }}"@endif>
<link rel="shortcut icon" href="{{ $faviconHref }}"@if($faviconType) type="{{ $faviconType }}"@endif>
