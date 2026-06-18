# Minify plugin JavaScript with globally installed terser.
# Usage (from plugin root):  .\scripts\minify-js.ps1
# Requires:  npm install -g terser

$ErrorActionPreference = 'Stop'

if (-not (Get-Command terser -ErrorAction SilentlyContinue)) {
    Write-Error 'terser not found. Install with: npm install -g terser'
}

$root = Split-Path -Parent $PSScriptRoot
$jsDir = Join-Path $root 'assets\js'

$files = @(
    'vefg-shared-toast.js',
    'vms-elements-form-guard.js',
    'domains.js',
    'subscribe-guard.js',
    'contact-guard.js',
    'login-guard.js',
    'registration-guard.js',
    'auth-forms.js',
    'ai-admin.js',
    'admin-toast.js',
    'admin-page-picker.js',
    'admin-spam-tabs.js',
    'admin-error-messages.js',
    'admin-ai-settings.js',
    'admin-comment-blocks.js',
    'admin-auth-forms-settings.js',
    'users-actions.js',
    'whitelist.js'
)

foreach ($file in $files) {
    $src = Join-Path $jsDir $file
    if (-not (Test-Path $src)) {
        Write-Warning "Skip (missing): $file"
        continue
    }
    $out = Join-Path $jsDir ($file -replace '\.js$', '.min.js')
    Write-Host "Minifying $file -> $(Split-Path $out -Leaf)"
    & terser $src -o $out -c -m --comments false
}

Write-Host 'Done.'
