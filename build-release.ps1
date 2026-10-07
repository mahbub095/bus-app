# ============================================================
# SonyaBus — CodeCanyon Release Packager
# Run from the repo root:  .\build-release.ps1
# Output: sonyabus-codecanyon-v1.0.0.zip in the repo root
# ============================================================

$VERSION   = "1.0.0"
$RELEASE   = "sonyabus-codecanyon-v$VERSION"
$DEST      = Join-Path $PSScriptRoot $RELEASE
$ZIP_OUT   = Join-Path $PSScriptRoot "$RELEASE.zip"
$ROOT      = $PSScriptRoot

Write-Host ""
Write-Host "=== SonyaBus Release Packager v$VERSION ===" -ForegroundColor Cyan
Write-Host ""

# ── Clean previous run ──────────────────────────────────────
if (Test-Path $DEST)    { Remove-Item -Recurse -Force $DEST }
if (Test-Path $ZIP_OUT) { Remove-Item -Force $ZIP_OUT }

New-Item -ItemType Directory -Path $DEST | Out-Null

# ── Helper: copy a directory excluding patterns ─────────────
function Copy-Excluding {
    param(
        [string]$Source,
        [string]$Destination,
        [string[]]$Exclude
    )
    $items = Get-ChildItem -Path $Source -Recurse -Force |
        Where-Object {
            $rel = $_.FullName.Substring($Source.Length).TrimStart('\','/')
            foreach ($pat in $Exclude) {
                # exact segment match from the start
                if ($rel -eq $pat)                    { return $false }
                if ($rel.StartsWith("$pat\"))          { return $false }
                if ($rel.StartsWith("$pat/"))          { return $false }
                # wildcard match on filename
                if ($_.Name -like $pat)               { return $false }
            }
            return $true
        }

    foreach ($item in $items) {
        $rel  = $item.FullName.Substring($Source.Length).TrimStart('\','/')
        $tgt  = Join-Path $Destination $rel
        if ($item.PSIsContainer) {
            New-Item -ItemType Directory -Path $tgt -Force | Out-Null
        } else {
            $parent = Split-Path $tgt -Parent
            if (!(Test-Path $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
            Copy-Item -Path $item.FullName -Destination $tgt -Force
        }
    }
}

# ── 1. backend/ (exclude vendor, .env, node_modules, tests are OK to ship) ──
Write-Host "  Copying backend..." -ForegroundColor Yellow
$backendSrc  = Join-Path $ROOT "backend"
$backendDest = Join-Path $DEST "backend"
New-Item -ItemType Directory -Path $backendDest | Out-Null

$backendExclude = @(
    "vendor",
    ".env",
    "node_modules",
    ".phpunit.result.cache",
    "storage\logs",
    "storage\framework\cache",
    "storage\framework\sessions",
    "storage\framework\views",
    "bootstrap\cache\*.php"
)
Copy-Excluding -Source $backendSrc -Destination $backendDest -Exclude $backendExclude

# Ensure required writable dirs exist with .gitkeep
$keepDirs = @(
    "storage\logs",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "bootstrap\cache"
)
foreach ($d in $keepDirs) {
    $full = Join-Path $backendDest $d
    if (!(Test-Path $full)) { New-Item -ItemType Directory -Path $full -Force | Out-Null }
    $gk = Join-Path $full ".gitkeep"
    if (!(Test-Path $gk)) { New-Item -ItemType File -Path $gk -Force | Out-Null }
}

# ── 2. frontend/ (exclude node_modules, dist, .env) ────────
Write-Host "  Copying frontend..." -ForegroundColor Yellow
$frontendSrc  = Join-Path $ROOT "frontend"
$frontendDest = Join-Path $DEST "frontend"
New-Item -ItemType Directory -Path $frontendDest | Out-Null

$frontendExclude = @(
    "node_modules",
    "dist",
    "dist-ssr",
    ".env"
)
Copy-Excluding -Source $frontendSrc -Destination $frontendDest -Exclude $frontendExclude

# ── 3. documentation/ ───────────────────────────────────────
Write-Host "  Copying documentation..." -ForegroundColor Yellow
$docSrc  = Join-Path $ROOT "documentation"
$docDest = Join-Path $DEST "documentation"
Copy-Item -Recurse -Path $docSrc -Destination $docDest -Force

# ── 4. Root files ────────────────────────────────────────────
Write-Host "  Copying root files..." -ForegroundColor Yellow
foreach ($f in @("changelog.txt", "license.txt")) {
    $src = Join-Path $ROOT $f
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $DEST $f) -Force
    }
}

# ── 5. Compress to ZIP ──────────────────────────────────────
Write-Host "  Creating ZIP..." -ForegroundColor Yellow
Compress-Archive -Path "$DEST\*" -DestinationPath $ZIP_OUT -Force

# ── 6. Clean up temp folder ─────────────────────────────────
Remove-Item -Recurse -Force $DEST

# ── 7. Report ───────────────────────────────────────────────
$size = [math]::Round((Get-Item $ZIP_OUT).Length / 1MB, 2)
Write-Host ""
Write-Host "=== Done! ===" -ForegroundColor Green
Write-Host "  Output : $ZIP_OUT"
Write-Host "  Size   : $size MB"
Write-Host ""
Write-Host "Pre-submission checklist:" -ForegroundColor Cyan
Write-Host "  1. Open the ZIP - verify backend/.env is NOT inside"
Write-Host "  2. Verify frontend/.env is NOT inside"
Write-Host "  3. Verify vendor/ is NOT inside"
Write-Host "  4. Verify node_modules/ is NOT inside"
Write-Host "  5. Open documentation/index.html in a browser"
Write-Host "  6. Upload to CodeCanyon as a new item"
Write-Host ""
