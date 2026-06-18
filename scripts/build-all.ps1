# Build distributable zips + brand PNGs for Free and Pro.
# Usage: .\scripts\build-all.ps1

$ErrorActionPreference = 'Stop'
$here       = $PSScriptRoot
$pluginRoot = Split-Path -Parent $here
$pluginsDir = Split-Path -Parent $pluginRoot
$proRoot    = Join-Path $pluginsDir 'vms-elements-form-guard-pro'

& ( Join-Path $here 'render-brand-assets.ps1' ) -Variant Free
& ( Join-Path $proRoot 'scripts\render-brand-assets.ps1' ) -Variant Pro
& ( Join-Path $here 'build-plugin-zip.ps1' )
& ( Join-Path $here 'build-assets-zip.ps1' )
& ( Join-Path $proRoot 'scripts\build-plugin-zip.ps1' )

Write-Host ''
Write-Host 'Done. Zips:'
Write-Host "  $( Join-Path $pluginRoot 'dist\vms-elements-form-guard.zip' )"
Write-Host "  $( Join-Path $pluginRoot 'dist\vms-elements-form-guard-assets.zip' )"
Write-Host "  $( Join-Path $proRoot 'dist\vms-elements-form-guard-pro.zip' )"
