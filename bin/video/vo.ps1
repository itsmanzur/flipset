# Placeholder voiceover with the built-in Windows voices.
# powershell -File bin/video/vo.ps1 -Json lines.json -OutDir out/vo [-Voice "Microsoft Zira Desktop"] [-Rate 0]
# lines.json: [{ "id": "hook", "text": "..." }, ...]  ->  <OutDir>/<id>.wav
param(
	[Parameter(Mandatory = $true)][string]$Json,
	[Parameter(Mandatory = $true)][string]$OutDir,
	[string]$Voice = 'Microsoft Zira Desktop',
	[int]$Rate = 0
)

Add-Type -AssemblyName System.Speech
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

$synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
$synth.SelectVoice($Voice)
$synth.Rate = $Rate

$lines = Get-Content -Raw -Encoding UTF8 $Json | ConvertFrom-Json
foreach ($line in $lines) {
	$file = Join-Path (Resolve-Path $OutDir) ($line.id + '.wav')
	$synth.SetOutputToWaveFile($file)
	$synth.Speak($line.text)
	$synth.SetOutputToNull()
	Write-Output ($line.id + ' -> ' + $file)
}
$synth.Dispose()
