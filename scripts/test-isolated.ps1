param(
    [ValidateSet('baseline', 'findings')]
    [string] $Suite = 'baseline'
)

$ErrorActionPreference = 'Stop'
$auditRoot = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $auditRoot
try {
    $auditArguments = @('vendor/pestphp/pest/bin/pest', '--bootstrap', 'tests/isolated-bootstrap.php', '--colors=never')
    if ($Suite -eq 'findings') {
        $auditArguments += 'tests/Audit'
    }
    & php @auditArguments
    $auditExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}

exit $auditExitCode
