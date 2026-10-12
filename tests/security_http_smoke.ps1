# Safe READ-ONLY smoke test. Does not authenticate or modify rosters.
param([string]$BaseUrl = 'http://localhost/sisaket-roster')
$ErrorActionPreference = 'Stop'
$checks = @(
    @{ Path = '/config.php'; Name = 'config.php'; Forbidden = $true },
    @{ Path = '/app/Core/Auth.php'; Name = 'Internal class'; Forbidden = $true },
    @{ Path = '/database/schema.sql'; Name = 'Schema SQL'; Forbidden = $true },
    @{ Path = '/bin/setup_local_admin.php'; Name = 'Account setup'; Forbidden = $true },
    @{ Path = '/public/index.php?c=roster&a=version_history&year=2026&month=10'; Name = 'Unauthenticated history'; Forbidden = $true }
)
$failed = 0
foreach ($c in $checks) {
    $url = $BaseUrl.TrimEnd('/') + $c.Path
    try {
        $resp = Invoke-WebRequest -Uri $url -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
        $status = [int]$resp.StatusCode
    } catch {
        $status = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
    }
    # History may return HTTP 302 to login (also safe). Never follow redirects in this probe.
    $allowed = if ($c.Name -eq 'Unauthenticated history') { @(301,302,303,401,403) } else { @(403,404) }
    if ($status -in $allowed) { Write-Host "PASS $($c.Name): HTTP $status" }
    else { Write-Host "FAIL $($c.Name): HTTP $status"; $failed++ }
}
if ($failed -gt 0) { throw "$failed security checks failed. Review Apache routing and public exposure." }
Write-Host 'PASS: Unauthenticated exposure checks; role-specific authenticated tests are still required.'
