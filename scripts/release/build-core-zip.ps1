param(
    [ValidateSet("all", "ready", "source", "source-dev")]
    [string]$Variant = "all",
    [string]$Version = "",
    [string]$OutputRoot = "dist/release",
    [string]$StagingRoot = "dist/release/staging",
    [switch]$ReadyIncludeJsSource = $false,
    [switch]$SkipReadySmokeChecks = $false,
    [switch]$PortalReadyNoUpdater = $false,
    [bool]$StrictPublicAudit = $true
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$outputRootAbs = Join-Path $repoRoot $OutputRoot
$stagingRootAbs = Join-Path $repoRoot $StagingRoot

$excludeExactFiles = @(
    "configs/db.php",
    "configs/installed.php"
)

$excludePrefixDirsBase = @(
    ".git/",
    ".claude/",
    ".pr/",
    ".vscode/",
    "node_modules/",
    "dist/",
    "tmp/",
    "tmp/cache/",
    "tmp/twig-cache/",
    "tmp/uploads/",
    "tmp/uploader/",
    "tmp/build-meta/",
    "assets/imgs/uploads/",
    "logs/",
    "modules/"
)

$excludeRegexBase = @(
    "^\.env(\..+)?$",
    "\.log$"
)

$excludeFileNamesReady = @(
    ".gitignore",
    ".gitlab-ci.yml",
    ".gitattributes",
    ".editorconfig"
)

$publicForbiddenPrefixes = @(
    "docs/interno/",
    "docs/moduli/",
    "docs/sanity/",
    "docs/adr/",
    "docs/constraints/"
)

$publicForbiddenRegex = @(
    "^\.env(\..+)?$",
    "^configs/db\.php$",
    "^configs/installed\.php$"
)

function Normalize-RelativePath {
    param([string]$AbsolutePath, [string]$Root)

    $relative = $AbsolutePath.Substring($Root.Length).TrimStart("\", "/")
    return ($relative -replace "\\", "/")
}

function Should-Exclude {
    param(
        [string]$RelativePath,
        [hashtable]$Profile
    )

    $rel = $RelativePath.ToLowerInvariant()
    $includeVendor = [bool]($Profile.IncludeVendor)
    $excludePrefixDirs = @($Profile.ExcludePrefixDirs)
    $excludeRegex = @($Profile.ExcludeRegex)
    $excludeFileNamesAny = @($Profile.ExcludeFileNamesAny)
    $excludeExactFilesAny = @($Profile.ExcludeExactFiles)

    foreach ($exact in $excludeExactFiles) {
        if ($rel -eq $exact.ToLowerInvariant()) {
            return $true
        }
    }

    foreach ($exact in $excludeExactFilesAny) {
        if ($rel -eq $exact.ToLowerInvariant()) {
            return $true
        }
    }

    foreach ($prefix in $excludePrefixDirs) {
        if ($rel.StartsWith($prefix.ToLowerInvariant())) {
            return $true
        }
    }

    $fileName = [System.IO.Path]::GetFileName($rel)
    foreach ($excludedName in $excludeFileNamesAny) {
        if ($fileName -eq $excludedName.ToLowerInvariant()) {
            return $true
        }
    }

    if (-not $IncludeVendor -and $rel.StartsWith("vendor/")) {
        return $true
    }

    foreach ($pattern in $excludeRegex) {
        if ($rel -match $pattern) {
            return $true
        }
    }

    return $false
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

function Get-BundledModules {
    param([string]$RepoRoot)

    $modulesRoot = Join-Path $RepoRoot "modules"
    if (-not (Test-Path -LiteralPath $modulesRoot -PathType Container)) {
        return @()
    }

    $bundled = New-Object System.Collections.Generic.List[object]

    Get-ChildItem -LiteralPath $modulesRoot -Directory -Force | ForEach-Object {
        $moduleDir = $_
        $manifestPath = Join-Path $moduleDir.FullName "module.json"
        if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
            return
        }

        try {
            $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        } catch {
            Write-Warning "Manifest modulo non valido, skip: $manifestPath"
            return
        }

        $moduleClass = ""
        if ($null -ne $manifest.PSObject.Properties["class"]) {
            $moduleClass = [string]$manifest.class
        }
        if ($moduleClass.Trim().ToLowerInvariant() -ne "bundled") {
            return
        }

        $bundled.Add([PSCustomObject]@{
            Name = $moduleDir.Name
            SourcePath = $moduleDir.FullName
            RelativePath = "modules/$($moduleDir.Name)"
        })
    }

    return $bundled.ToArray()
}

function Resolve-ReleaseVersion {
    param(
        [string]$RawVersion,
        [string]$RepoRoot
    )

    $explicit = Normalize-Version -Raw $RawVersion
    if ($explicit -ne "") {
        if ($explicit -notmatch "^\d+\.\d+\.\d+$") {
            throw "Versione non valida: '$RawVersion'. Usa formato X.Y.Z o vX.Y.Z."
        }
        return $explicit
    }

    $manifestPath = Join-Path $RepoRoot "logeon.manifest.json"
    if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
        throw "File logeon.manifest.json non trovato. Passa -Version esplicita."
    }

    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    $fromManifest = Normalize-Version -Raw ([string]$manifest.version)
    if ($fromManifest -eq "" -or $fromManifest -notmatch "^\d+\.\d+\.\d+$") {
        throw "Versione non valida in logeon.manifest.json. Passa -Version esplicita."
    }

    return $fromManifest
}

function Assert-PackagePublicSurface {
    param(
        [string]$StagingPackageAbs,
        [string]$PackageName
    )

    if (-not $StrictPublicAudit) {
        return
    }

    $violations = New-Object System.Collections.Generic.List[string]

    Get-ChildItem -LiteralPath $StagingPackageAbs -Recurse -File -Force | ForEach-Object {
        $relativePath = Normalize-RelativePath -AbsolutePath $_.FullName -Root $StagingPackageAbs
        $relativeLower = $relativePath.ToLowerInvariant()

        foreach ($prefix in $publicForbiddenPrefixes) {
            if ($relativeLower.StartsWith($prefix)) {
                $violations.Add($relativePath)
                return
            }
        }

        foreach ($pattern in $publicForbiddenRegex) {
            if ($relativeLower -match $pattern) {
                $violations.Add($relativePath)
                return
            }
        }
    }

    if ($violations.Count -gt 0) {
        $preview = $violations | Select-Object -First 20
        $message = @(
            "Audit pubblico fallito per '$PackageName'.",
            "File non destinati al pubblico trovati nel pacchetto:",
            ($preview | ForEach-Object { " - $_" })
        )
        if ($violations.Count -gt 20) {
            $message += " - ... altri $($violations.Count - 20) file"
        }
        throw ($message -join "`n")
    }
}

function Build-Package {
    param(
        [string]$PackageName,
        [hashtable]$Profile,
        [object[]]$AllFiles,
        [object[]]$BundledModules
    )

    $stagingPackageAbs = Join-Path $stagingRootAbs $PackageName
    $zipPath = Join-Path $outputRootAbs ("{0}.zip" -f $PackageName)

    if (Test-Path -LiteralPath $stagingPackageAbs) {
        Remove-Item -Recurse -Force -LiteralPath $stagingPackageAbs -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -Force -LiteralPath $zipPath -ErrorAction SilentlyContinue
    }

    New-Item -ItemType Directory -Force -Path $stagingPackageAbs | Out-Null

    # Include cartelle runtime vuote (con sottocartelle), senza includere i file contenuti.
    $directorySkeletonRoots = @(
        "assets/imgs/uploads",
        "tmp"
    )
    foreach ($rootRel in $directorySkeletonRoots) {
        $sourceRootDir = Join-Path $repoRoot $rootRel
        if (-not (Test-Path -LiteralPath $sourceRootDir -PathType Container)) {
            continue
        }

        $targetRootDir = Join-Path $stagingPackageAbs $rootRel
        New-Item -ItemType Directory -Force -Path $targetRootDir | Out-Null

        Get-ChildItem -LiteralPath $sourceRootDir -Recurse -Directory -Force | ForEach-Object {
            $relativePath = Normalize-RelativePath -AbsolutePath $_.FullName -Root $repoRoot
            $targetDir = Join-Path $stagingPackageAbs $relativePath
            New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
        }
    }

    $copied = 0
    foreach ($file in $AllFiles) {
        $source = $file.FullName
        $relativePath = Normalize-RelativePath -AbsolutePath $source -Root $repoRoot

        if (Should-Exclude -RelativePath $relativePath -Profile $Profile) {
            continue
        }

        $destination = Join-Path $stagingPackageAbs $relativePath
        $destinationDir = Split-Path -Path $destination -Parent
        if (-not (Test-Path $destinationDir)) {
            New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
        }

        Copy-Item -LiteralPath $source -Destination $destination -Force
        $copied++
    }

    $bundledCopied = 0
    $modulesDir = Join-Path $stagingPackageAbs "modules"
    New-Item -ItemType Directory -Force -Path $modulesDir | Out-Null

    foreach ($module in $BundledModules) {
        $destinationModule = Join-Path $stagingPackageAbs $module.RelativePath
        Copy-Item -LiteralPath $module.SourcePath -Destination $destinationModule -Recurse -Force
        $bundledCopied++
    }

    if ($bundledCopied -eq 0) {
        New-Item -ItemType File -Force -Path (Join-Path $modulesDir ".gitkeep") | Out-Null
    }

    Assert-PackagePublicSurface -StagingPackageAbs $stagingPackageAbs -PackageName $PackageName

    Compress-Archive -Path (Join-Path $stagingPackageAbs "*") -DestinationPath $zipPath -Force

    Write-Host "Pacchetto creato: $zipPath"
    Write-Host "Staging: $stagingPackageAbs"
    Write-Host "File copiati: $copied"
    Write-Host "Moduli bundled inclusi: $bundledCopied"
}

function Force-ReadyFrontendBundleMode {
    param(
        [string]$StagingPackageAbs
    )

    $appConfigPath = Join-Path $StagingPackageAbs "configs/app.php"
    if (-not (Test-Path -LiteralPath $appConfigPath -PathType Leaf)) {
        Write-Host "[ready] configs/app.php non trovato, skip override pilot_bundle_mode."
        return
    }

    $content = Get-Content -LiteralPath $appConfigPath -Raw
    $updated = $content -replace "('pilot_bundle_mode'\s*=>\s*)'[^']+'", "`$1'on'"

    if ($updated -ne $content) {
        Set-Content -LiteralPath $appConfigPath -Value $updated -Encoding UTF8
        Write-Host "[ready] Override frontend applicato: pilot_bundle_mode='on' in configs/app.php"
    } else {
        Write-Host "[ready] Nessuna sostituzione effettuata su pilot_bundle_mode (formato inatteso)."
    }
}

function Force-ReadyProductionDefaults {
    param(
        [string]$StagingPackageAbs
    )

    $configPath = Join-Path $StagingPackageAbs "configs/config.php"
    if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
        Write-Host "[ready] configs/config.php non trovato, skip override produzione."
        return
    }

    $content = Get-Content -LiteralPath $configPath -Raw
    $updated = $content -replace "('debug'\s*=>\s*)true", "`$1false"

    if ($updated -ne $content) {
        Set-Content -LiteralPath $configPath -Value $updated -Encoding UTF8
        Write-Host "[ready] Override produzione applicato: debug=false in configs/config.php"
    } else {
        Write-Host "[ready] Nessuna sostituzione effettuata su debug (formato inatteso)."
    }
}

function Remove-PortalUpdaterSurface {
    param(
        [string]$StagingPackageAbs
    )

    $toRemove = @(
        "app/controllers/SystemUpdate.php",
        "app/Services/Update",
        "app/views/admin/pages/system-update.twig",
        "assets/js/app/features/admin/AdminUpdateNotice.js",
        "assets/js/app/features/admin/SystemUpdate.js",
        "assets/js/app/modules/admin/SystemUpdateModule.js",
        "update-manifest.json",
        "update-manifest.dev.json",
        "docs/guida-upgrade-versioni.md"
    )

    foreach ($relative in $toRemove) {
        $target = Join-Path $StagingPackageAbs $relative
        if (Test-Path -LiteralPath $target) {
            Remove-Item -LiteralPath $target -Recurse -Force -ErrorAction SilentlyContinue
            Write-Host "[ready-portal] Rimosso: $relative"
        }
    }

    $apiRoutes = Join-Path $StagingPackageAbs "app/routes/api.php"
    if (Test-Path -LiteralPath $apiRoutes -PathType Leaf) {
        $content = Get-Content -LiteralPath $apiRoutes -Raw
        $updated = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            '(?m)^\s*\$route->apiPost\(''/system/update/[^'']+'',\s*''SystemUpdate@[^'']+''\);\s*\r?\n?',
            ""
        )
        $updated = [System.Text.RegularExpressions.Regex]::Replace(
            $updated,
            '(?m)^.*SystemUpdate@.*\r?\n?',
            ""
        )
        $updated = [System.Text.RegularExpressions.Regex]::Replace(
            $updated,
            '(?m)^.*\/system\/update\/.*\r?\n?',
            ""
        )
        if ($updated -ne $content) {
            Set-Content -LiteralPath $apiRoutes -Value $updated -Encoding UTF8
            Write-Host "[ready-portal] Route updater rimosse da app/routes/api.php"
        }
    }

    $gameRoutes = Join-Path $StagingPackageAbs "app/routes/game.php"
    if (Test-Path -LiteralPath $gameRoutes -PathType Leaf) {
        $content = Get-Content -LiteralPath $gameRoutes -Raw
        if ($content -notmatch "Pagina non disponibile per questa distribuzione") {
            $needle = '$page = strtolower(trim((string) $page));'
            $pos = $content.IndexOf($needle)
            if ($pos -ge 0) {
                $insertPos = $pos + $needle.Length
                $insert = @'

    if ($page === 'system-update') {
        throw AppError::notFound('Pagina non disponibile per questa distribuzione');
    }
'@
                $updated = $content.Insert($insertPos, $insert)
                Set-Content -LiteralPath $gameRoutes -Value $updated -Encoding UTF8
                Write-Host "[ready-portal] Guardia 404 inserita in app/routes/game.php"
            }
        }
    }

    $adminAside = Join-Path $StagingPackageAbs "app/views/admin/layouts/aside.twig"
    if (Test-Path -LiteralPath $adminAside -PathType Leaf) {
        $content = Get-Content -LiteralPath $adminAside -Raw
        $updated = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            "(?ms)^\s*<a class=""nav-link \{\{ admin_page in \['system-update'\] \? 'active' : '' \}\}"" href=""/admin/system-update"".*?</a>\s*\r?\n?",
            ""
        )
        if ($updated -ne $content) {
            Set-Content -LiteralPath $adminAside -Value $updated -Encoding UTF8
            Write-Host "[ready-portal] Link updater rimosso in app/views/admin/layouts/aside.twig"
        }
    }

    $adminDashboard = Join-Path $StagingPackageAbs "app/views/admin/dashboard.twig"
    if (Test-Path -LiteralPath $adminDashboard -PathType Leaf) {
        $content = Get-Content -LiteralPath $adminDashboard -Raw
        $updated = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            "(?ms)^\s*\{% elseif current_page == 'system-update' %\}\s*\r?\n\s*\{% include 'admin/pages/system-update\.twig' %\}\s*\r?\n?",
            ""
        )
        if ($updated -ne $content) {
            Set-Content -LiteralPath $adminDashboard -Value $updated -Encoding UTF8
            Write-Host "[ready-portal] Blocco page system-update rimosso in app/views/admin/dashboard.twig"
        }
    }

    $adminCoreBundle = Join-Path $StagingPackageAbs "assets/js/dist/admin-core.bundle.js"
    if (Test-Path -LiteralPath $adminCoreBundle -PathType Leaf) {
        $content = Get-Content -LiteralPath $adminCoreBundle -Raw
        $updated = $content `
            -replace "/admin/system/update", "/admin/__disabled__/update" `
            -replace "/admin/system-update", "/admin/__disabled__" `
            -replace "admin\.system-update", "admin.__disabled-update__" `
            -replace """system-update""", """__disabled-update__""" `
            -replace "AdminSystemUpdate", "AdminPortalDisabledUpdate" `
            -replace "SystemUpdate@", "PortalDisabledUpdate@"
        if ($updated -ne $content) {
            Set-Content -LiteralPath $adminCoreBundle -Value $updated -Encoding UTF8
            Write-Host "[ready-portal] Token updater bonificati in assets/js/dist/admin-core.bundle.js"
        }
    }
}

function Assert-PortalNoUpdaterReferences {
    param(
        [string]$StagingPackageAbs,
        [string]$PackageName
    )

    $patterns = @(
        "/admin/system/update",
        "/admin/system-update",
        "SystemUpdate@",
        "AdminSystemUpdate",
        "admin.system-update",
        "App\\Services\\Update\\",
        "class SystemUpdate"
    )

    $violations = New-Object System.Collections.Generic.List[string]
    $scanExtensions = @(".php", ".twig", ".js", ".json", ".md", ".txt")

    Get-ChildItem -LiteralPath $StagingPackageAbs -Recurse -File -Force | ForEach-Object {
        $ext = $_.Extension.ToLowerInvariant()
        if ($scanExtensions -notcontains $ext) {
            return
        }
        $relativePath = Normalize-RelativePath -AbsolutePath $_.FullName -Root $StagingPackageAbs
        $raw = Get-Content -LiteralPath $_.FullName -Raw -ErrorAction SilentlyContinue
        if (-not [string]::IsNullOrEmpty($raw)) {
            foreach ($pattern in $patterns) {
                if ($raw.Contains($pattern)) {
                    $violations.Add("$relativePath -> $pattern")
                }
            }
        }
    }

    if ($violations.Count -gt 0) {
        $preview = $violations | Select-Object -First 30
        $message = @(
            "Audit portal-no-updater fallito per '$PackageName'.",
            "Riferimenti updater ancora presenti:",
            ($preview | ForEach-Object { " - $_" })
        )
        if ($violations.Count -gt 30) {
            $message += " - ... altri $($violations.Count - 30) riferimenti"
        }
        throw ($message -join "`n")
    }
}

New-Item -ItemType Directory -Force -Path $outputRootAbs | Out-Null
New-Item -ItemType Directory -Force -Path $stagingRootAbs | Out-Null

$allFiles = Get-ChildItem -Path $repoRoot -Recurse -File -Force
$bundledModules = Get-BundledModules -RepoRoot $repoRoot

$variantNormalized = if ($Variant -eq "source") { "source-dev" } else { $Variant }
$releaseVersion = Resolve-ReleaseVersion -RawVersion $Version -RepoRoot $repoRoot

$profiles = @{
    "ready" = @{
        IncludeVendor = $true
        ExcludeExactFiles = @(
            "composer.json",
            "composer.lock",
            "package.json",
            "package-lock.json",
            "phpstan.neon",
            "phpstan-no-baseline.neon",
            "phpstan-baseline.neon",
            "phpstan-bootstrap.php",
            ".php-cs-fixer.dist.php",
            ".php-cs-fixer.cache"
        )
        ExcludePrefixDirs = @($excludePrefixDirsBase + @(
            "scripts/",
            "tools/",
            "docs/interno/",
            "docs/moduli/",
            "docs/sanity/",
            "docs/adr/",
            "docs/constraints/",
            "assets/sass/"
        ))
        ExcludeRegex = @($excludeRegexBase)
        ExcludeFileNamesAny = @($excludeFileNamesReady)
    }
    "source-dev" = @{
        IncludeVendor = $false
        ExcludeExactFiles = @()
        ExcludePrefixDirs = @($excludePrefixDirsBase + @(
            "docs/adr/",
            "docs/constraints/",
            "docs/interno/",
            "docs/moduli/",
            "docs/sanity/"
        ))
        ExcludeRegex = @($excludeRegexBase)
        ExcludeFileNamesAny = @()
    }
}

$packages = @()
if ($variantNormalized -in @("all", "ready")) {
    if (-not (Test-Path (Join-Path $repoRoot "vendor"))) {
        throw "Cartella vendor assente: impossibile generare il pacchetto ready."
    }

    if (-not $ReadyIncludeJsSource) {
        $profiles["ready"].ExcludePrefixDirs += @(
            "assets/js/app/",
            "assets/js/components/",
            "assets/js/services/"
        )
    }

    $readySuffix = if ($PortalReadyNoUpdater) { "-portal" } else { "" }
    $packages += @{
        Name = "logeon-ready$readySuffix-$releaseVersion"
        Kind = "ready"
        Profile = $profiles["ready"]
        PortalNoUpdater = [bool]$PortalReadyNoUpdater
    }
}
if ($variantNormalized -in @("all", "source-dev")) {
    $packages += @{
        Name = "logeon-source-$releaseVersion"
        Kind = "source-dev"
        Profile = $profiles["source-dev"]
        PortalNoUpdater = $false
    }
}

foreach ($pkg in $packages) {
    Write-Host ""
    Write-Host "== Build $($pkg.Name) =="
    Build-Package -PackageName $pkg.Name -Profile ([hashtable]$pkg.Profile) -AllFiles $allFiles -BundledModules $bundledModules

    # Minifica i CSS solo nel pacchetto ready, mantenendo i nomi canonici (.css) + source map esterna.
    if ($pkg.Kind -eq "ready") {
        $readyStaging = Join-Path $stagingRootAbs $pkg.Name
        Force-ReadyFrontendBundleMode -StagingPackageAbs $readyStaging
        Force-ReadyProductionDefaults -StagingPackageAbs $readyStaging
        if ([bool]$pkg.PortalNoUpdater) {
            Remove-PortalUpdaterSurface -StagingPackageAbs $readyStaging
            Assert-PortalNoUpdaterReferences -StagingPackageAbs $readyStaging -PackageName $pkg.Name
        }

        $cssTarget = Join-Path (Join-Path $stagingRootAbs $pkg.Name) "assets/css"
        if (Test-Path $cssTarget) {
            Write-Host "[ready] Minificazione CSS in corso: $cssTarget"
            node scripts/build/css-minify.mjs --target "$cssTarget"
        }

        if ($SkipReadySmokeChecks) {
            Write-Host "[ready] Smoke checks saltati (-SkipReadySmokeChecks)."
        } elseif ($ReadyIncludeJsSource) {
            Write-Host "[ready] Smoke dist-only saltato: rollback JS source attivo (-ReadyIncludeJsSource)."
        } else {
            Write-Host "[ready] Smoke dist-only in corso: $readyStaging"
            node scripts/release/smoke-ready-dist-only-js.mjs --staging "$readyStaging"
        }

        $zipPath = Join-Path $outputRootAbs ("{0}.zip" -f $pkg.Name)
        if (Test-Path $zipPath) {
            Remove-Item -Force -LiteralPath $zipPath
        }
        Compress-Archive -Path (Join-Path (Join-Path $stagingRootAbs $pkg.Name) "*") -DestinationPath $zipPath -Force
        Write-Host "[ready] ZIP aggiornato dopo minificazione CSS: $zipPath"
    }
}
