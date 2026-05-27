param(
    [Parameter(Mandatory = $true)]
    [string]$Version,
    [string]$ReleaseDir = "dist/release",
    [string]$ManifestPath = "update-manifest.json",
    [bool]$UpdateDevManifest = $true
)

$ErrorActionPreference = "Stop"

function Fail {
    param([string]$Message)
    throw $Message
}

function Normalize-Version {
    param([string]$Raw)
    if ([string]::IsNullOrWhiteSpace($Raw)) {
        return ""
    }

    $value = $Raw.Trim()
    if ($value.StartsWith("v")) {
        $value = $value.Substring(1)
    }

    return $value
}

function Get-JsonFile {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        Fail "File JSON non trovato: $Path"
    }

    try {
        return (Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json)
    } catch {
        Fail "JSON non valido: $Path"
    }
}

function Save-JsonFile {
    param(
        [string]$Path,
        [object]$Data
    )

    $compactJson = $Data | ConvertTo-Json -Depth 64 -Compress
    $tmpJson = [System.IO.Path]::GetTempFileName()
    $tmpOut = [System.IO.Path]::GetTempFileName()
    $tmpScript = [System.IO.Path]::GetTempFileName() + ".cjs"

    try {
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($tmpJson, $compactJson, $utf8NoBom)

        $nodeFormatter = @'
const fs = require('fs');
const inPath = process.argv[2];
const outPath = process.argv[3];
const raw = fs.readFileSync(inPath, 'utf8');
const parsed = JSON.parse(raw);
fs.writeFileSync(outPath, JSON.stringify(parsed, null, 2) + '\n', 'utf8');
'@
        [System.IO.File]::WriteAllText($tmpScript, $nodeFormatter, $utf8NoBom)

        & node $tmpScript $tmpJson $tmpOut
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $tmpOut -PathType Leaf)) {
            throw "Formatter JSON node non disponibile."
        }

        $formatted = [System.IO.File]::ReadAllText($tmpOut, [System.Text.Encoding]::UTF8)
        [System.IO.File]::WriteAllText($Path, $formatted, $utf8NoBom)
    } catch {
        # Fallback compatibile: evita blocchi release se node non fosse disponibile.
        $json = $Data | ConvertTo-Json -Depth 64
        $json = $json + "`r`n"
        Set-Content -LiteralPath $Path -Value $json -Encoding UTF8
    } finally {
        foreach ($tmp in @($tmpJson, $tmpOut, $tmpScript)) {
            if (Test-Path -LiteralPath $tmp) {
                Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Find-ReleaseAsset {
    param(
        [string]$Dir,
        [string[]]$Patterns,
        [string]$Label
    )

    if (-not (Test-Path -LiteralPath $Dir -PathType Container)) {
        Fail "Cartella release non trovata: $Dir"
    }

    $all = Get-ChildItem -LiteralPath $Dir -File -ErrorAction Stop
    foreach ($pattern in $Patterns) {
        $match = $all | Where-Object { $_.Name -like $pattern } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($null -ne $match) {
            return $match
        }
    }

    Fail "Asset '$Label' non trovato in '$Dir'. Pattern cercati: $($Patterns -join ', ')"
}

function Update-ManifestFile {
    param(
        [string]$Path,
        [string]$VersionNormalized,
        [object]$ReadyAsset,
        [object]$SourceAsset
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        Write-Host "Manifest non presente (skip): $Path"
        return
    }

    $manifest = Get-JsonFile -Path $Path
    $releases = @($manifest.releases)
    if ($releases.Count -eq 0) {
        Fail "Manifest senza release: $Path"
    }

    $targetRelease = $null
    foreach ($release in $releases) {
        if ($null -eq $release) {
            continue
        }
        $releaseVersion = Normalize-Version -Raw ([string]($release.version))
        if ($releaseVersion -eq $VersionNormalized) {
            $targetRelease = $release
            break
        }
    }

    if ($null -eq $targetRelease) {
        Fail "Release $VersionNormalized non trovata in manifest: $Path"
    }

    if ($null -eq $targetRelease.packages -or $null -eq $targetRelease.packages.ready -or $null -eq $targetRelease.packages.'source-dev') {
        Fail "Struttura packages incompleta in manifest: $Path"
    }

    $readyHash = Get-FileHash -LiteralPath $ReadyAsset.FullName -Algorithm SHA256
    $sourceHash = Get-FileHash -LiteralPath $SourceAsset.FullName -Algorithm SHA256

    $targetRelease.packages.ready.checksum_sha256 = $readyHash.Hash.ToLowerInvariant()
    $targetRelease.packages.ready.size_bytes = [int64]$ReadyAsset.Length
    $targetRelease.packages.'source-dev'.checksum_sha256 = $sourceHash.Hash.ToLowerInvariant()
    $targetRelease.packages.'source-dev'.size_bytes = [int64]$SourceAsset.Length

    Save-JsonFile -Path $Path -Data $manifest

    Write-Host "Manifest aggiornato: $Path"
    Write-Host (" - ready      : {0} ({1} bytes)" -f $ReadyAsset.Name, $ReadyAsset.Length)
    Write-Host (" - source-dev : {0} ({1} bytes)" -f $SourceAsset.Name, $SourceAsset.Length)
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$versionNormalized = Normalize-Version -Raw $Version
if ($versionNormalized -notmatch "^\d+\.\d+\.\d+$") {
    Fail "Versione non valida: '$Version'. Usa formato SemVer (es. 1.0.0 oppure v1.0.0)."
}

$releaseDirAbs = Join-Path $repoRoot $ReleaseDir
$manifestPathAbs = Join-Path $repoRoot $ManifestPath
$devManifestPathAbs = Join-Path $repoRoot "update-manifest.dev.json"

$readyAsset = Find-ReleaseAsset -Dir $releaseDirAbs -Patterns @("logeon-ready-$versionNormalized.zip", "*core-ready*.zip", "*ready*.zip") -Label "ready"
$sourceAsset = Find-ReleaseAsset -Dir $releaseDirAbs -Patterns @("logeon-source-$versionNormalized.zip", "*core-source-dev*.zip", "*source-dev*.zip", "*source*.zip") -Label "source-dev"

Update-ManifestFile -Path $manifestPathAbs -VersionNormalized $versionNormalized -ReadyAsset $readyAsset -SourceAsset $sourceAsset

if ($UpdateDevManifest) {
    Update-ManifestFile -Path $devManifestPathAbs -VersionNormalized $versionNormalized -ReadyAsset $readyAsset -SourceAsset $sourceAsset
}

Write-Host ""
Write-Host "Sync manifest completato."
