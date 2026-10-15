# Safely move the EXISTING sisaket-roster-dev working tree to the reviewed Phase 2 branch.
# Preview-only by default. -Apply does NOT touch MySQL, config.php, or remote GitHub.
[CmdletBinding()]
param([switch]$Apply, [string]$ProjectPath='C:\xampp\htdocs\sisaket-roster-dev')

$ErrorActionPreference='Stop'
$branch='feature/duty-phase2-draft-ui-20261015'
$root=[System.IO.Path]::GetFullPath($ProjectPath)
if ((Split-Path -Leaf $root) -ne 'sisaket-roster-dev') {
    throw "SAFETY STOP: wrong folder $root"
}
if (!(Test-Path -LiteralPath (Join-Path $root '.git'))) {
    throw 'SAFETY STOP: the folder is not the Git repository. Do not initialize another repository.'
}
if (!(Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'Git for Windows not found in PATH.'
}
$remote=(& git -C $root remote get-url origin)
if ($LASTEXITCODE -ne 0 -or $remote -notmatch '(?i)(?:github\.com[:/])superaidigital/sisaket-roster(?:\.git)?$') {
    throw "SAFETY STOP: origin is not superaidigital/sisaket-roster. Received: $remote"
}
$state=@(& git -C $root status --porcelain=v1 --untracked-files=all)
if ($LASTEXITCODE -ne 0) { throw 'SAFETY STOP: cannot inspect Git working tree.' }
$current=(& git -C $root branch --show-current)
Write-Host "Project : $root"
Write-Host "Remote  : $remote"
Write-Host "Current : $current"
Write-Host "Target  : $branch"
Write-Host 'Database: untouched. Local config.php: not edited.'
if ($state.Count -gt 0) {
    Write-Host 'Uncommitted/staged/untracked paths:' -ForegroundColor Yellow
    $state | Select-Object -First 50 | ForEach-Object { Write-Host "  $_" }
    throw "SAFETY STOP: work tree has local changes. Inspect with git status, then create a backup/stash yourself BEFORE rerunning. NO files changed."
}
if (!$Apply) {
    Write-Host 'PREVIEW ONLY: no fetch, checkout, pull or file change. Use -Apply only after checking status.'
    exit 0
}
& git -C $root fetch origin $branch
# The refspec above is intentionally assembled in the call and must not force-switch local files.
if ($LASTEXITCODE -ne 0) { throw 'Fetch failed; local working tree not changed.' }
& git -C $root show-ref --verify --quiet "refs/heads/$branch"
$exists=($LASTEXITCODE -eq 0)
if ($exists) {
    & git -C $root switch $branch
} else {
    & git -C $root switch --track -c $branch "origin/$branch"
}
if ($LASTEXITCODE -ne 0) { throw 'Unable to switch branch; no forced reset was attempted.' }
& git -C $root merge --ff-only "origin/$branch"
if ($LASTEXITCODE -ne 0) { throw 'Branch diverged; resolve manually. No forced reset was attempted.' }
Write-Host 'Local source is now at Phase 2 branch. No MySQL operations executed.' -ForegroundColor Green
Write-Host 'Next: check config.php DB name, session_name=SSK_DUTY_PHASE2_SID, then run bin\duty_phase2_preflight.php.'
