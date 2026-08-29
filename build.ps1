<#
.SYNOPSIS
    Local plugin build + package. Mirrors .github/workflows/build.yml.

.DESCRIPTION
    1. Builds both bundles (root public script + frontend/ admin console).
    2. Resolves the plugin slug from the GitHub CLI (`gh repo view`),
       falling back to the git remote, then the folder name.
    3. Reads the version from the main plugin PHP header.
    4. Stages the plugin into ./plugin-build/<slug>/ using the EXACT
       --exclude list parsed out of the workflow file (so this script
       never drifts from CI), plus a few local-only excludes.
    5. Zips it to ./<slug>_<version>.zip .

.EXAMPLE
    pwsh -File ./build.ps1
    pwsh -File ./build.ps1 -NoInstall        # skip `yarn install`
    pwsh -File ./build.ps1 -NoBuild          # repackage only, no rebuild
#>
[CmdletBinding()]
param(
    [switch]$NoInstall,
    [switch]$NoBuild
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
Set-Location $root

$workflow = Join-Path $root '.github/workflows/build.yml'
$mainPhp  = Get-ChildItem -Path $root -Filter '*.php' -File |
    Where-Object { (Get-Content $_.FullName -TotalCount 20) -match 'Plugin Name:' } |
    Select-Object -First 1
if (-not $mainPhp) { throw "Main plugin PHP file not found in $root" }

# --- slug: gh CLI -> git remote -> folder name -------------------------------
function Resolve-Slug {
    try {
        $n = (& gh repo view --json name -q .name 2>$null)
        if ($LASTEXITCODE -eq 0 -and $n) { return $n.Trim() }
    } catch {}
    try {
        $url = (& git remote get-url origin 2>$null)
        if ($url) { return ([IO.Path]::GetFileNameWithoutExtension(($url -replace '/$',''))) }
    } catch {}
    return Split-Path $root -Leaf
}
$slug = Resolve-Slug

# --- version: `Version:` header in the main PHP file ------------------------
$version = (Select-String -Path $mainPhp.FullName -Pattern 'Version:\s*(.+)' |
    Select-Object -First 1).Matches[0].Groups[1].Value.Trim()
if (-not $version) { $version = Get-Date -Format 'yyyy.MM.dd-HHmmss' }

Write-Host "slug    : $slug"      -ForegroundColor Cyan
Write-Host "version : $version"   -ForegroundColor Cyan

# --- build -----------------------------------------------------------------
if (-not $NoBuild) {
    if (-not $NoInstall) {
        Write-Host "`n== yarn install (root) ==" -ForegroundColor Yellow
        & yarn install --frozen-lockfile
        Write-Host "`n== yarn install (frontend) ==" -ForegroundColor Yellow
        & yarn --cwd frontend install --frozen-lockfile
    }
    Write-Host "`n== build: frontend/ (admin console) ==" -ForegroundColor Yellow
    & yarn --cwd frontend build
    Write-Host "`n== build: root (public load-more script) ==" -ForegroundColor Yellow
    & yarn build
}

# --- excludes: parsed from the workflow + local-only ----------------------
$wfExcludes = @()
if (Test-Path $workflow) {
    $wfExcludes = [regex]::Matches((Get-Content $workflow -Raw), "--exclude='([^']+)'") |
        ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique
}
if (-not $wfExcludes) {
    Write-Warning "No --exclude entries parsed from $workflow; using built-in fallback."
    $wfExcludes = 'node_modules','.git','.github','yarn.lock','vite.config.js',
                  'package.json','frontend','src','.gitignore','.editorconfig',
                  '.prettierignore','*.md','*.map','plugin-build'
}
$localExcludes = '.idea','.vscode','.DS_Store','*.zip','changelog.txt',
                 (Split-Path $PSCommandPath -Leaf)
$excludes = @($wfExcludes + $localExcludes) | Sort-Object -Unique
Write-Host "`nexcludes: $($excludes -join ', ')" -ForegroundColor DarkGray

function Test-Excluded([string]$name) {
    foreach ($p in $excludes) { if ($name -like $p) { return $true } }
    return $false
}

# --- stage ---------------------------------------------------------------
$stageRoot = Join-Path $root 'plugin-build'
$dest      = Join-Path $stageRoot $slug
if (Test-Path $stageRoot) { Remove-Item $stageRoot -Recurse -Force }
New-Item -ItemType Directory -Force -Path $dest | Out-Null

Get-ChildItem -Path $root -Force | Where-Object { -not (Test-Excluded $_.Name) } | ForEach-Object {
    Copy-Item $_.FullName -Destination $dest -Recurse -Force
}
# apply wildcard excludes (e.g. *.md, *.map) at every nesting level
$wildcards = $excludes | Where-Object { $_ -match '[\*\?]' }
foreach ($w in $wildcards) {
    Get-ChildItem -Path $dest -Recurse -Force -Filter $w -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force
}

# --- zip ---------------------------------------------------------------
$zip = Join-Path $root "${slug}_${version}.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path $dest -DestinationPath $zip

$sizeKb = [math]::Round((Get-Item $zip).Length / 1KB, 1)
Write-Host "`n[OK] $([IO.Path]::GetFileName($zip))  (${sizeKb} KB)" -ForegroundColor Green
Get-ChildItem -Path $dest -Recurse -File |
    ForEach-Object { $_.FullName.Substring($dest.Length + 1) -replace '\\','/' } |
    Sort-Object
