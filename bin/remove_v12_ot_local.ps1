# Remove ONLY the locally installed V12 OT Phase 1 patch from sisaket-roster-dev.
# Safe by default: PREVIEW unless -Apply is passed. This script does NOT connect to MySQL.
[CmdletBinding()]
param([switch]$Apply,[string]$ProjectPath='')

$ErrorActionPreference='Stop'
if([string]::IsNullOrWhiteSpace($ProjectPath)){$ProjectPath=Split-Path -Parent $PSScriptRoot}
$root=[System.IO.Path]::GetFullPath($ProjectPath)
if((Split-Path $root -Leaf) -ne 'sisaket-roster-dev'){throw "SAFETY STOP: expected sisaket-roster-dev: $root"}
if(-not (Test-Path -LiteralPath (Join-Path $root '.git'))){throw 'SAFETY STOP: not a Git clone'}
if(-not (Get-Command git -ErrorAction SilentlyContinue)){throw 'Git for Windows is required'}

# SHA256 values from Sisaket_Roster_V12_OT_Phase1_PATCH.zip.
# Edited files are NOT automatically removed: preserve user customizations.
$onlyV12=[ordered]@{
  'database/migrations/20261014_v12_ot_schedule.sql'='253583369f150e57875678d47493282b328347c4720a3ea524ca42c8832a2b82'
  'app/Services/OtRules.php'='69737ecc8f7fc3e1351ee42ae2bcb01e9446cc01e8169ef0d452a78ffb7e884c'
  'app/Models/OtModel.php'='e3802c2dacfad34f94c8c4c2a4d657435d0e6a3f4d193fae0e77339256fcd01e'
  'app/Controllers/OtController.php'='527d9b4045d5e8bb8c713c27c8a23d55c0867f15315569830c851620c8e7b0a0'
  'app/Views/ot/index.php'='36d060040dc3d41f73a1e772d9e924546b2f1ec37e2723785e76a5446a67654d'
  'public/js/ot-v12.js'='d1b7f700585bb382ad62bef1d4077d60731e39b54415d9f56cc304dc9d46d311'
  'tests/ot_v12_test.php'='37140b4450cd138cf14765a7a072c6c608c58190e240eacc64e9cc532613e3e8'
  'UPGRADE_V12.md'='7f234d69e3c4c74cf85b43fbaa4ee4dae60cb8bf70d51a971b566f2c6999bef7'
}
$shared=[ordered]@{
  'public/index.php'='d86abfb1dd55e3262d407ded13618965d52b0be07d87999c4400f37fef397a4b'
  'app/Views/admin/_head.php'='e51eec97537334a8d3f1aee11f38d31c99bf77d5cf3b6cac1a397e9fcfd7ab92'
}
function PathFor([string]$p){Join-Path $root ($p.Replace('/',[System.IO.Path]::DirectorySeparatorChar))}
function ContainsOT([string]$p,[string]$s){
  if($p -eq 'public/index.php'){return $s.Contains("'ot'=>") -or $s.Contains('OtController')}
  return $s.Contains('index.php?c=ot')
}
$remove=@();$restore=@();$errors=@()
$all=@($onlyV12.Keys)+@($shared.Keys)
$staged=@(& git -C $root diff --cached --name-only -- $all)
if($LASTEXITCODE -ne 0){throw 'Unable to read staged Git changes'}
if($staged.Count -gt 0){throw "SAFETY STOP: staged changes exist in targeted files: $($staged -join ', ')"}
foreach($p in $onlyV12.Keys){
  $full=PathFor $p
  if(-not (Test-Path -LiteralPath $full -PathType Leaf)){continue}
  $hash=(Get-FileHash -LiteralPath $full -Algorithm SHA256).Hash.ToLowerInvariant()
  if($hash -eq $onlyV12[$p]){$remove+= $p}
  else{$errors+= "Edited/unknown OT file; review manually: $p"}
}
foreach($p in $shared.Keys){
  $full=PathFor $p
  if(-not (Test-Path -LiteralPath $full -PathType Leaf)){$errors+= "Missing shared file: $p";continue}
  $body=Get-Content -LiteralPath $full -Raw
  if(-not (ContainsOT $p $body)){continue}
  $hash=(Get-FileHash -LiteralPath $full -Algorithm SHA256).Hash.ToLowerInvariant()
  if($hash -ne $shared[$p]){$errors+= "Shared file edited after OT patch; review: $p";continue}
  $original=@(& git -C $root show "HEAD:$p") -join "\`n"
  if($LASTEXITCODE -ne 0 -or (ContainsOT $p $original)){$errors+= "Git HEAD is not a clean V11 source: $p";continue}
  $restore+= $p
}
Write-Host "Project: $root"
Write-Host 'DELETE OT files:';foreach($p in $remove){Write-Host "  $p"}
Write-Host 'RESTORE shared files from V11 Git HEAD:';foreach($p in $restore){Write-Host "  $p"}
Write-Host 'MySQL: NO DATABASE OPERATION; all ot_* tables and data stay unchanged.'
if($errors.Count -gt 0){
  foreach($e in $errors){Write-Warning $e}
  throw 'SAFETY STOP: no files changed; manual review required'
}
if(-not $Apply){Write-Host 'PREVIEW ONLY. Review, then rerun with -Apply.';exit 0}
if($remove.Count -eq 0 -and $restore.Count -eq 0){Write-Host 'Already clean';exit 0}
$xampp=Split-Path -Parent (Split-Path -Parent $root)
$backup=Join-Path $xampp ('backups\sisaket-roster-dev-v12-'+(Get-Date -Format 'yyyyMMdd-HHmmssfff'))
New-Item -ItemType Directory -Path $backup -Force|Out-Null
foreach($p in (@($remove)+@($restore))){
  $source=PathFor $p;$target=Join-Path $backup ($p.Replace('/',[System.IO.Path]::DirectorySeparatorChar))
  New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force|Out-Null
  Copy-Item -LiteralPath $source -Destination $target -ErrorAction Stop
}
Write-Host "Backup: $backup"
if($restore.Count -gt 0){
  & git -C $root restore --source=HEAD --worktree -- $restore
  if($LASTEXITCODE -ne 0){throw 'Git restore failed; backup retained; no OT-only files removed'}
}
foreach($p in $remove){Remove-Item -LiteralPath (PathFor $p) -Force -ErrorAction Stop}
Write-Host 'V12 OT Phase 1 sources removed. DB untouched. Check git status and V11 tests.'
