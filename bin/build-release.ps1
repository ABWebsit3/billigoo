param( [string]$Version = "" )
$ErrorActionPreference = "Stop"
$PluginRoot = Split-Path $PSScriptRoot -Parent

if ( -not $Version ) {
    $header = Get-Content "$PluginRoot\billigoo.php" -Raw
    if ( $header -match '\* Version:\s*(.+)' ) { $Version = $Matches[1].Trim() }
    else { Write-Error "Version not found in billigoo.php"; exit 1 }
}

$ZipName = "billigoo-$Version.zip"
$ZipPath = Join-Path (Split-Path $PluginRoot -Parent) $ZipName

Write-Host ""
Write-Host "=== Billigoo $Version - build release ===" -ForegroundColor Cyan

# 1. composer install --no-dev
Write-Host "`n[1/4] composer install --no-dev -o ..." -ForegroundColor Yellow
Push-Location $PluginRoot
try {
    & composer install --no-dev -o --ignore-platform-req=ext-gd
    if ( $LASTEXITCODE -ne 0 ) { throw "composer install failed (exit $LASTEXITCODE)" }
} finally { Pop-Location }
Write-Host "      done." -ForegroundColor Green

# 2. Clean vendor/
Write-Host "`n[2/4] Nettoyage vendor/ ..." -ForegroundColor Yellow
Get-ChildItem "$PluginRoot\vendor" -Recurse -Directory -Filter ".git" -ErrorAction SilentlyContinue | ForEach-Object { Remove-Item $_.FullName -Recurse -Force }
foreach ( $name in @("samples","tests","test","doc","docs",".github") ) {
    Get-ChildItem "$PluginRoot\vendor" -Recurse -Directory -Filter $name -ErrorAction SilentlyContinue | ForEach-Object { Remove-Item $_.FullName -Recurse -Force }
}
$fontsDir = "$PluginRoot\vendor\mpdf\mpdf\ttfonts"
if ( Test-Path $fontsDir ) {
    Get-ChildItem $fontsDir -File | Where-Object { $_.Name -notlike "DejaVu*" } | Remove-Item -Force
}
$vendorMB = [math]::Round(( Get-ChildItem "$PluginRoot\vendor" -Recurse -File | Measure-Object -Property Length -Sum ).Sum / 1MB, 1)
Write-Host "      vendor/ = $vendorMB MB" -ForegroundColor Green

# 3. Create zip
Write-Host "`n[3/4] Creation du zip -> $ZipName ..." -ForegroundColor Yellow
$ExcludeNames = @('.git','bin','tests','BILLIGOO_PLAN.md','PROJECT_STATUS.md','.gitignore','.github','.distignore')
if ( Test-Path $ZipPath ) { Remove-Item $ZipPath }
Add-Type -Assembly System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open( $ZipPath, 'Create' )
Get-ChildItem $PluginRoot -Recurse -File | ForEach-Object {
    $relative = $_.FullName.Substring( $PluginRoot.Length + 1 )
    $parts = $relative -split '\\'
    $skip = $false
    foreach ( $p in $parts ) { if ( $ExcludeNames -contains $p ) { $skip = $true; break } }
    if ( -not $skip ) {
        $entry = "billigoo/" + ( $relative -replace '\\','/' )
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile( $zip, $_.FullName, $entry, 'Optimal' ) | Out-Null
    }
}
$zip.Dispose()
$sizeMB = [math]::Round( (Get-Item $ZipPath).Length / 1MB, 1 )
Write-Host "      $sizeMB MB" -ForegroundColor Green

# 4. Verify
Write-Host "`n[4/4] Verification ..." -ForegroundColor Yellow
$verify = [System.IO.Compression.ZipFile]::OpenRead( $ZipPath )
$hasMain   = $verify.Entries | Where-Object { $_.FullName -eq "billigoo/billigoo.php" }
$hasVendor = $verify.Entries | Where-Object { $_.FullName -like "billigoo/vendor/*" }
$verify.Dispose()
if ( $hasMain -and $hasVendor ) { Write-Host "      billigoo.php OK   vendor/ OK" -ForegroundColor Green }
else { Write-Error "Zip incomplet"; exit 1 }

Write-Host ""
Write-Host "=== $ZipName ready ($sizeMB MB) ===" -ForegroundColor Cyan
Write-Host "    $ZipPath" -ForegroundColor Gray
Write-Host ""
