# Upload the sanitized V11 source to the user's NEW (empty) GitHub repository.
# Run from the project folder, after creating the empty repository on GitHub.
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$Repo = 'https://github.com/superaidigital/sisaket-roster.git'
function RunGit {
  & git @args
  if ($LASTEXITCODE -ne 0) { throw "git $($args -join ' ') failed (exit $LASTEXITCODE)" }
}
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
  throw 'Git not found. Install Git for Windows from https://git-scm.com/download/win and rerun.'
}
if (-not (Test-Path -LiteralPath '.\README.md')) { throw 'Run in the extracted sisaket-roster project folder.' }
if (Test-Path -LiteralPath '.\config.php') { throw 'Refusing: local config.php exists. Use the GitHub-ready package, not your running XAMPP directory.' }
if (Test-Path -LiteralPath '.\.git') {
  $origin = (& git remote get-url origin 2>$null)
  if ($LASTEXITCODE -eq 0 -and $origin -and $origin -ne $Repo) { throw "Current origin is $origin. Refusing to change another repository." }
} else { RunGit init }
RunGit branch -M main
$existing = (& git remote get-url origin 2>$null)
if ($LASTEXITCODE -ne 0 -or -not $existing) { RunGit remote add origin $Repo }
elseif ($existing -ne $Repo) { throw "Origin mismatch: $existing" }
RunGit add --all
$files = @(& git diff --cached --name-only)
$blocked = @($files | Where-Object { $_ -match '(^|/)(config\.php|\.env(\..*)?|id_rsa|database_backup.*|.*\.pem|.*\.key|.*\.sqlite3?)$' })
if ($blocked.Count -gt 0) { throw "Sensitive paths staged: $($blocked -join ', ')" }
Write-Host "Repo: $Repo" -ForegroundColor Cyan
Write-Host "Staged files: $($files.Count)" -ForegroundColor Cyan
RunGit status --short
$confirm = Read-Host 'Type PUSH to commit and upload to this NEW GitHub repository'
if ($confirm -cne 'PUSH') { Write-Host 'Cancelled: no commit/push was made.'; exit 0 }
if ($files.Count -gt 0) { RunGit commit -m 'Initial import: Sisaket Roster Management V11' }
RunGit push -u origin main
Write-Host "Done: https://github.com/superaidigital/sisaket-roster" -ForegroundColor Green
