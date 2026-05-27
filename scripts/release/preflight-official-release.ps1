param(
    [Parameter(Mandatory = $true)]
    [string]$Version,
    [string]$ReleaseDir = "dist/release",
    [switch]$SyncManifest = $true
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "== $Message =="
}

function Fail {
    param([string]$Message)
    throw $Message
}

function Normalize-Version {
    param([string]$Raw)
    if ([string]::IsNullOrWhiteSpace($Raw)) {
        $value = ""
    } else {
        $value = $Raw.Trim()
    }
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
    $raw = Get-Content -LiteralPath $Path -Raw
    try {
        return ($raw | ConvertFrom-Json)
    } catch {
        Fail "JSON non valido: $Path"
    }
}

function Find-ReleaseAsset {
    param(
        [string]$Dir,
        [string[]]$Patterns,
        [string]$Label
    )

    $all = Get-ChildItem -LiteralPath $Dir -File -ErrorAction Stop
    foreach ($pattern in $Patterns) {
        $match = $all | Where-Object { $_.Name -like $pattern } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($null -ne $match) {
            return $match
        }
    }

    Fail "Asset '$Label' non trovato in '$Dir'. Pattern cercati: $($Patterns -join ', ')"
}

function Get-GitText {
    param([string[]]$GitArgs)
    $output = & git @GitArgs 2>$null
    if ($null -eq $output) {
        return ""
    }
    return [string]::Join("`n", $output).Trim()
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$releaseDirAbs = Join-Path $repoRoot $ReleaseDir
$versionNormalized = Normalize-Version -Raw $Version
$versionTag = "v$versionNormalized"

if ($versionNormalized -notmatch "^\d+\.\d+\.\d+$") {
    Fail "Versione non valida: '$Version'. Usa formato SemVer (es. 1.0.0 oppure v1.0.0)."
}

if (-not (Test-Path -LiteralPath $releaseDirAbs -PathType Container)) {
    Fail "Cartella release non trovata: $releaseDirAbs"
}

Write-Step "Contesto repository"
$branch = Get-GitText -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")
$headCommit = Get-GitText -GitArgs @("rev-parse", "HEAD")
$workingTreeStatus = Get-GitText -GitArgs @("status", "--short")

Write-Host "Repository: $repoRoot"
Write-Host "Branch corrente: $branch"
Write-Host "Commit HEAD: $headCommit"
if ([string]::IsNullOrWhiteSpace($workingTreeStatus)) {
    Write-Host "Working tree: pulita"
} else {
    Write-Host "Working tree: con modifiche (verifica manuale consigliata)"
}

Write-Step "Controllo tag"
$existingTag = Get-GitText -GitArgs @("tag", "--list", $versionTag)
if (-not [string]::IsNullOrWhiteSpace($existingTag)) {
    Fail "Il tag $versionTag esiste gia. Scegli una nuova versione o rimuovi il tag errato."
}
Write-Host "Tag disponibile: $versionTag"

Write-Step "Controllo manifest locali"
$coreManifestPath = Join-Path $repoRoot "logeon.manifest.json"
$coreManifest = Get-JsonFile -Path $coreManifestPath
$coreManifestVersion = Normalize-Version -Raw ([string]($coreManifest.version))
if ($coreManifestVersion -ne $versionNormalized) {
    Fail "Versione mismatch in logeon.manifest.json. Atteso: $versionNormalized, trovato: $($coreManifest.version)"
}
Write-Host "logeon.manifest.json OK (versione: $($coreManifest.version))"

$updateManifestPath = Join-Path $repoRoot "update-manifest.json"
if (-not (Test-Path -LiteralPath $updateManifestPath -PathType Leaf)) {
    $fallbackManifestPath = Join-Path $repoRoot "update-manifest.dev.json"
    if (Test-Path -LiteralPath $fallbackManifestPath -PathType Leaf) {
        $updateManifestPath = $fallbackManifestPath
    }
}

if (Test-Path -LiteralPath $updateManifestPath -PathType Leaf) {
    $updateManifestName = Split-Path -Leaf $updateManifestPath
    $updateManifest = Get-JsonFile -Path $updateManifestPath

    $latestReady = ""
    $latestSourceDev = ""
    if ($null -ne $updateManifest.latest) {
        $latestReady = Normalize-Version -Raw ([string]($updateManifest.latest.ready))
        $latestSourceDev = Normalize-Version -Raw ([string]($updateManifest.latest.'source-dev'))
    }

    if (-not [string]::IsNullOrWhiteSpace($latestReady) -and $latestReady -ne $versionNormalized) {
        Fail "Versione mismatch in $updateManifestName (latest.ready). Atteso: $versionNormalized, trovato: $latestReady"
    }
    if (-not [string]::IsNullOrWhiteSpace($latestSourceDev) -and $latestSourceDev -ne $versionNormalized) {
        Fail "Versione mismatch in $updateManifestName (latest.source-dev). Atteso: $versionNormalized, trovato: $latestSourceDev"
    }

    $hasReleaseVersion = $false
    $releases = @($updateManifest.releases)
    foreach ($release in $releases) {
        if ($null -eq $release) {
            continue
        }
        $releaseVersion = Normalize-Version -Raw ([string]($release.version))
        if ($releaseVersion -eq $versionNormalized) {
            $hasReleaseVersion = $true
            break
        }
    }

    if (-not $hasReleaseVersion) {
        Fail "Release $versionNormalized non trovata in $updateManifestName (array releases)."
    }

    Write-Host "$updateManifestName OK (latest.ready=$latestReady, latest.source-dev=$latestSourceDev, release=$versionNormalized)"
} else {
    Write-Host "update-manifest.json / update-manifest.dev.json non presenti (skip)"
}

Write-Step "Controllo artefatti zip"
$readyZip = Find-ReleaseAsset -Dir $releaseDirAbs -Patterns @("logeon-ready-$versionNormalized.zip", "*ready*.zip") -Label "ready"
$sourceZip = Find-ReleaseAsset -Dir $releaseDirAbs -Patterns @("logeon-source-$versionNormalized.zip", "*source-dev*.zip", "*source*.zip") -Label "source-dev"

Write-Host "Ready zip:  $($readyZip.Name)"
Write-Host "Source zip: $($sourceZip.Name)"

$readyHash = Get-FileHash -LiteralPath $readyZip.FullName -Algorithm SHA256
$sourceHash = Get-FileHash -LiteralPath $sourceZip.FullName -Algorithm SHA256

$checksumsPath = Join-Path $releaseDirAbs ("checksums-{0}.txt" -f $versionTag)
$checksumsContent = @(
    "# Logeon release asset checksums",
    "version: $versionTag",
    "",
    "$($readyHash.Hash.ToLowerInvariant())  $($readyZip.Name)",
    "$($sourceHash.Hash.ToLowerInvariant())  $($sourceZip.Name)"
)
Set-Content -LiteralPath $checksumsPath -Value $checksumsContent -Encoding UTF8

Write-Host "SHA256 ready:  $($readyHash.Hash)"
Write-Host "SHA256 source: $($sourceHash.Hash)"
Write-Host "File checksum scritto: $checksumsPath"

if ($SyncManifest) {
    Write-Step "Sync manifest update (checksum + size)"
    $syncScriptPath = Join-Path $repoRoot "scripts/release/sync-update-manifest.ps1"
    if (-not (Test-Path -LiteralPath $syncScriptPath -PathType Leaf)) {
        Fail "Script sync manifest non trovato: $syncScriptPath"
    }

    & powershell -ExecutionPolicy Bypass -File $syncScriptPath -Version $versionTag -ReleaseDir $ReleaseDir
    if ($LASTEXITCODE -ne 0) {
        Fail "Sync manifest fallito. Correggere e rieseguire il preflight."
    }
}

Write-Step "Promemoria rilascio ufficiale"
Write-Host "1) Copia nel repository ufficiale i contenuti approvati (source-dev pronto)."
Write-Host "2) Commit nel repository ufficiale."
Write-Host "3) Crea tag $versionTag sulla stessa commit del commit release."
Write-Host "4) Crea release GitHub e allega:"
Write-Host "   - $($readyZip.Name)"
Write-Host "   - $($sourceZip.Name)"
Write-Host "   - $(Split-Path -Leaf $checksumsPath)"
Write-Host ""
Write-Host "Preflight completato."
