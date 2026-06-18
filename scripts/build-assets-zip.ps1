# Builds a WordPress.org SVN assets zip (flat file names at zip root).
# Upload contents to the top-level assets/ folder in your plugin SVN repo.
# Usage: .\scripts\build-assets-zip.ps1

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginRoot = Split-Path -Parent $PSScriptRoot
$assetsDir  = Join-Path $pluginRoot 'assets\images'
$distDir    = Join-Path $pluginRoot 'dist'
$zipPath    = Join-Path $distDir 'vms-elements-form-guard-assets.zip'

if (-not (Test-Path $assetsDir)) {
	throw "Assets source folder not found: $assetsDir"
}

New-Item -ItemType Directory -Path $distDir -Force | Out-Null

if (Test-Path $zipPath) {
	Remove-Item -Path $zipPath -Force
}

$fileCount = 0
$archive   = [System.IO.Compression.ZipFile]::Open( $zipPath, [System.IO.Compression.ZipArchiveMode]::Create )

try {
	Get-ChildItem -Path $assetsDir -File | ForEach-Object {
		[void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
			$archive,
			$_.FullName,
			$_.Name,
			[System.IO.Compression.CompressionLevel]::Optimal
		)
		$fileCount++
	}
}
finally {
	$archive.Dispose()
}

if ( $fileCount -eq 0 ) {
	throw 'No asset files found in assets/images.'
}

$sizeMb = [math]::Round( ( Get-Item $zipPath ).Length / 1MB, 2 )
Write-Host "Created $zipPath ($sizeMb MB, $fileCount files)"
