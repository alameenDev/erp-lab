# Digital Lab - local Windows transfer wizard.
# No credentials are written to a file or environment variable.
# The converter receives one JSON document through standard input.

param(
    [switch]$UiSmokeTest,
    [string]$SmokeFixtureRoot,
    [string]$SmokeOutputPath
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()
[System.Windows.Forms.Application]::SetCompatibleTextRenderingDefault($false)

$script:ToolRoot = $PSScriptRoot
$script:ManifestPath = Join-Path $PSScriptRoot 'data\manifest.json'
$script:SqlPath = Join-Path $PSScriptRoot 'data\online.sql.br'
$script:CliPath = Join-Path $PSScriptRoot 'src\migrate.cjs'
if ($UiSmokeTest) {
    if ($env:DLM_SYNTHETIC_TEST -ne '1' -or -not $SmokeFixtureRoot -or -not $SmokeOutputPath) {
        throw 'UI smoke mode requires an explicitly supplied synthetic fixture.'
    }
    $testRoot = [IO.Path]::GetFullPath($SmokeFixtureRoot)
    $realDataRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot 'data'))
    if ($testRoot.Equals($realDataRoot, [StringComparison]::OrdinalIgnoreCase) -or
        $testRoot.StartsWith($realDataRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'UI smoke mode cannot use the deliverable data directory.'
    }
    $script:ManifestPath = Join-Path $testRoot 'manifest.json'
    $script:SqlPath = Join-Path $testRoot 'online.sql.br'
}
$script:Running = $false
$script:Process = $null
$script:ReadTask = $null
$script:ErrorReadTask = $null
$script:StdoutEnded = $false
$script:StderrEnded = $false
$script:FinalResult = $null
$script:CompletedOutput = $null
$script:SelectedOutput = $null
$script:UnexpectedOutput = $false
$script:Rows = New-Object System.Collections.Generic.List[System.Windows.Forms.Control]
$script:InputControls = New-Object System.Collections.Generic.List[System.Windows.Forms.Control]
$script:NewLine = [Environment]::NewLine

function Get-PropertyValue {
    param($Value, [string]$Name, $Default = $null)
    if ($null -eq $Value) { return $Default }
    $property = $Value.PSObject.Properties[$Name]
    if ($null -eq $property) { return $Default }
    return $property.Value
}

function Get-EncryptedValueCount {
    param($Value)
    if ($null -eq $Value) { return [long]0 }
    if ($Value -is [System.Collections.IEnumerable] -and $Value -isnot [string] -and $Value -isnot [pscustomobject]) {
        [long]$sum = 0
        foreach ($item in $Value) { $sum += Get-EncryptedValueCount $item }
        return $sum
    }
    [long]$total = 0
    foreach ($property in $Value.PSObject.Properties) {
        if ($property.Name -eq 'nonNullCount') {
            [long]$parsed = 0
            if ([long]::TryParse([string]$property.Value, [ref]$parsed) -and $parsed -gt 0) { $total += $parsed }
        } elseif ($property.Value -is [pscustomobject] -or $property.Value -is [array]) {
            $total += Get-EncryptedValueCount $property.Value
        }
    }
    return $total
}

function Show-Notice {
    param([string]$Message, [string]$Title = 'نقل بيانات المختبر الرقمي')
    [void][System.Windows.Forms.MessageBox]::Show(
        $script:Form, $Message, $Title,
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Information,
        [System.Windows.Forms.MessageBoxDefaultButton]::Button1,
        [System.Windows.Forms.MessageBoxOptions]::RightAlign -bor [System.Windows.Forms.MessageBoxOptions]::RtlReading
    )
}

try {
    foreach ($required in @($script:ManifestPath, $script:SqlPath, $script:CliPath)) {
        if (-not [IO.File]::Exists($required)) { throw 'PACKAGE_INCOMPLETE' }
    }
    $script:Manifest = [IO.File]::ReadAllText($script:ManifestPath, [Text.Encoding]::UTF8) | ConvertFrom-Json
    if ($UiSmokeTest -and (Get-PropertyValue (Get-PropertyValue $script:Manifest 'source') 'synthetic' $false) -ne $true) {
        throw 'UI smoke mode accepts generated synthetic data only.'
    }
    $script:EncryptedCount = Get-EncryptedValueCount (Get-PropertyValue $script:Manifest 'encryptedColumns')
} catch {
    if ($UiSmokeTest) { throw }
    [void][System.Windows.Forms.MessageBox]::Show(
        'ملفات أداة النقل غير مكتملة أو ملف تعريف البيانات غير صالح. فك ضغط الحزمة كاملة، ثم افتح Start-Transfer.cmd من داخل المجلد.',
        'تعذر فتح أداة النقل', 'OK', 'Error'
    )
    exit 1
}

function Find-DesktopInstallation {
    $candidates = New-Object System.Collections.Generic.List[string]
    foreach ($key in @(
        'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKCU:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*'
    )) {
        foreach ($entry in @(Get-ItemProperty -Path $key -ErrorAction SilentlyContinue)) {
            $displayName = [string](Get-PropertyValue $entry 'DisplayName' '')
            if ($displayName -notlike '*Digital Lab Desktop*') { continue }
            $location = [string](Get-PropertyValue $entry 'InstallLocation' '')
            if ($location) { $candidates.Add($location) }
            $icon = [string](Get-PropertyValue $entry 'DisplayIcon' '')
            if ($icon -match '^"?(.+?\.exe)"?(?:,\s*-?\d+)?$') {
                try { $candidates.Add([IO.Path]::GetDirectoryName($Matches[1])) } catch {}
            }
        }
    }
    if ($env:LOCALAPPDATA) {
        foreach ($name in @('Digital Lab Desktop', 'digital-lab-desktop', 'DigitalLabDesktop')) {
            $candidates.Add((Join-Path $env:LOCALAPPDATA ('Programs\' + $name)))
        }
        $programs = Join-Path $env:LOCALAPPDATA 'Programs'
        foreach ($directory in @(Get-ChildItem -LiteralPath $programs -Directory -ErrorAction SilentlyContinue)) {
            if ($directory.Name -like '*Digital*Lab*') { $candidates.Add($directory.FullName) }
        }
    }
    foreach ($candidate in $candidates) {
        if ($candidate -and [IO.File]::Exists((Join-Path $candidate 'Digital Lab Desktop.exe'))) {
            return [IO.Path]::GetFullPath($candidate)
        }
    }
    return ''
}

$script:Colors = @{
    Background = [Drawing.ColorTranslator]::FromHtml('#F4F7FA')
    Ink = [Drawing.ColorTranslator]::FromHtml('#183044')
    Muted = [Drawing.ColorTranslator]::FromHtml('#586C7B')
    Accent = [Drawing.ColorTranslator]::FromHtml('#087F8C')
    Pale = [Drawing.ColorTranslator]::FromHtml('#E9F4F4')
    Warning = [Drawing.ColorTranslator]::FromHtml('#866222')
    Error = [Drawing.ColorTranslator]::FromHtml('#A52A38')
}
$script:Form = New-Object System.Windows.Forms.Form
$script:Form.Text = 'Digital Lab | نقل البيانات إلى الدسكتوب'
$script:Form.Font = [Drawing.Font]::new('Segoe UI', 10)
$script:Form.BackColor = $script:Colors.Background
$script:Form.ForeColor = $script:Colors.Ink
$script:Form.RightToLeft = [System.Windows.Forms.RightToLeft]::Yes
$script:Form.StartPosition = [System.Windows.Forms.FormStartPosition]::CenterScreen
$script:Form.AutoScaleMode = [System.Windows.Forms.AutoScaleMode]::Dpi
$workingArea = [System.Windows.Forms.Screen]::PrimaryScreen.WorkingArea
$script:Form.ClientSize = [Drawing.Size]::new([Math]::Min(880, $workingArea.Width - 80), [Math]::Min(850, $workingArea.Height - 90))
$script:Form.MinimumSize = [Drawing.Size]::new(720, 640)

$header = New-Object System.Windows.Forms.Panel
$header.Dock = 'Top'
$header.Height = 100
$header.Padding = [System.Windows.Forms.Padding]::new(24, 16, 24, 8)
$header.BackColor = [Drawing.Color]::White
$title = New-Object System.Windows.Forms.Label
$title.Dock = 'Top'
$title.Height = 38
$title.Text = 'نقل جميع المختبرات إلى الدسكتوب'
$title.Font = [Drawing.Font]::new('Segoe UI', 17, [Drawing.FontStyle]::Bold)
$title.TextAlign = 'MiddleRight'
$subtitle = New-Object System.Windows.Forms.Label
$subtitle.Dock = 'Top'
$subtitle.Height = 34
$subtitle.Text = 'تجهيز ملف استعادة محلي من نسخة قاعدة البيانات المرفقة.'
$subtitle.ForeColor = $script:Colors.Muted
$subtitle.TextAlign = 'MiddleRight'
$header.Controls.Add($subtitle)
$header.Controls.Add($title)

$footer = New-Object System.Windows.Forms.Panel
$footer.Dock = 'Bottom'
$footer.Height = 226
$footer.Padding = [System.Windows.Forms.Padding]::new(24, 12, 24, 14)
$footer.BackColor = [Drawing.Color]::White
$script:StartButton = New-Object System.Windows.Forms.Button
$script:StartButton.Text = 'إنشاء ملف الاستعادة'
$script:StartButton.Location = [Drawing.Point]::new(24, 12)
$script:StartButton.Size = [Drawing.Size]::new(210, 42)
$script:StartButton.BackColor = $script:Colors.Accent
$script:StartButton.ForeColor = [Drawing.Color]::White
$script:StartButton.FlatStyle = 'Flat'
$script:StartButton.FlatAppearance.BorderSize = 0
$script:StartButton.Font = [Drawing.Font]::new('Segoe UI', 11, [Drawing.FontStyle]::Bold)
$script:StartButton.Anchor = 'Top,Right'
$script:ProgressBar = New-Object System.Windows.Forms.ProgressBar
$script:ProgressBar.Minimum = 0
$script:ProgressBar.Maximum = 100
$script:ProgressBar.Location = [Drawing.Point]::new(24, 65)
$script:ProgressBar.Size = [Drawing.Size]::new(800, 14)
$script:ProgressBar.Anchor = 'Top,Left,Right'
$script:StatusLabel = New-Object System.Windows.Forms.Label
$script:StatusLabel.Location = [Drawing.Point]::new(24, 86)
$script:StatusLabel.Size = [Drawing.Size]::new(800, 29)
$script:StatusLabel.Anchor = 'Top,Left,Right'
$script:StatusLabel.Text = 'جاهز. اختر مسار البرنامج ومكان حفظ ملف الاستعادة.'
$script:StatusLabel.TextAlign = 'MiddleRight'
$script:DetailsLabel = New-Object System.Windows.Forms.Label
$script:DetailsLabel.Location = [Drawing.Point]::new(24, 119)
$script:DetailsLabel.Size = [Drawing.Size]::new(800, 90)
$script:DetailsLabel.Anchor = 'Top,Left,Right'
$script:DetailsLabel.Text = 'تنشئ الأداة ملفاً مستقلاً بصيغة .dlbackup. بعد اكتماله، تستعيده من برنامج الدسكتوب.' + $script:NewLine + 'قد تستغرق العملية عدة دقائق بسبب حجم البيانات.'
$script:DetailsLabel.ForeColor = $script:Colors.Muted
$script:DetailsLabel.TextAlign = 'TopRight'
$script:OpenFolderButton = New-Object System.Windows.Forms.Button
$script:OpenFolderButton.Text = 'فتح مجلد الملف'
$script:OpenFolderButton.Location = [Drawing.Point]::new(24, 12)
$script:OpenFolderButton.Size = [Drawing.Size]::new(150, 42)
$script:OpenFolderButton.FlatStyle = 'Flat'
$script:OpenFolderButton.Enabled = $false
$footer.Controls.AddRange(@($script:StartButton, $script:OpenFolderButton, $script:ProgressBar, $script:StatusLabel, $script:DetailsLabel))

$scroll = New-Object System.Windows.Forms.Panel
$scroll.Dock = 'Fill'
$scroll.AutoScroll = $true
$scroll.Padding = [System.Windows.Forms.Padding]::new(24, 14, 24, 14)
$script:Content = New-Object System.Windows.Forms.Panel
$script:Content.Location = [Drawing.Point]::new(24, 12)
$script:Content.Width = 800
$script:Content.Height = 500
$scroll.Controls.Add($script:Content)
$script:Form.Controls.Add($scroll)
$script:Form.Controls.Add($footer)
$script:Form.Controls.Add($header)

function New-Row {
    param([int]$Height)
    $row = New-Object System.Windows.Forms.Panel
    $row.Height = $Height
    $row.Width = $script:Content.Width
    $row.Anchor = 'Top,Left,Right'
    $script:Rows.Add($row)
    $script:Content.Controls.Add($row)
    return $row
}

function New-FieldLabel {
    param([System.Windows.Forms.Control]$Parent, [string]$Text)
    $label = New-Object System.Windows.Forms.Label
    $label.Text = $Text
    $label.Location = [Drawing.Point]::new(0, 0)
    $label.Size = [Drawing.Size]::new($Parent.Width, 26)
    $label.Anchor = 'Top,Left,Right'
    $label.TextAlign = 'MiddleRight'
    $label.Font = [Drawing.Font]::new('Segoe UI', 10, [Drawing.FontStyle]::Bold)
    $Parent.Controls.Add($label)
    return $label
}

function New-PathField {
    param([string]$Label)
    $row = New-Row 78
    [void](New-FieldLabel $row $Label)
    $box = New-Object System.Windows.Forms.TextBox
    $box.Location = [Drawing.Point]::new(104, 30)
    $box.Size = [Drawing.Size]::new($row.Width - 104, 30)
    $box.Anchor = 'Top,Left,Right'
    $box.RightToLeft = 'No'
    $box.Font = [Drawing.Font]::new('Segoe UI', 10)
    $button = New-Object System.Windows.Forms.Button
    $button.Text = 'استعراض'
    $button.Location = [Drawing.Point]::new(0, 28)
    $button.Size = [Drawing.Size]::new(94, 32)
    $button.FlatStyle = 'Flat'
    $row.Controls.AddRange(@($box, $button))
    $script:InputControls.Add($box)
    $script:InputControls.Add($button)
    return @{ Row = $row; Box = $box; Button = $button }
}

$infoRow = New-Row 54
$infoLabel = New-Object System.Windows.Forms.Label
$infoLabel.Dock = 'Fill'
$infoLabel.Padding = [System.Windows.Forms.Padding]::new(12, 7, 12, 7)
$infoLabel.BackColor = $script:Colors.Pale
$infoLabel.TextAlign = 'MiddleRight'
$infoLabel.Text = 'المصدر: نسخة قاعدة البيانات المرفقة • النطاق: جميع المختبرات' + $script:NewLine + 'ملف الاستعادة سيحافظ على الحسابات والسجلات التي تجتاز فحوص النقل.'
$infoRow.Controls.Add($infoLabel)

$ownerNoteRow = New-Row 40
$ownerNote = New-Object System.Windows.Forms.Label
$ownerNote.Dock = 'Fill'
$ownerNote.TextAlign = 'MiddleRight'
$ownerNote.ForeColor = $script:Colors.Muted
$ownerNote.Text = 'تُحفظ بيانات بوابة المالك، لكن واجهتها لا تتوفر في إصدار الدسكتوب V1.'
$ownerNoteRow.Controls.Add($ownerNote)

$installationField = New-PathField '1. مجلد تثبيت Digital Lab Desktop'
$script:InstallationBox = $installationField.Box
$script:InstallationBox.Text = Find-DesktopInstallation
$installationField.Button.Add_Click({
    $dialog = New-Object System.Windows.Forms.OpenFileDialog
    $dialog.Title = 'اختر Digital Lab Desktop.exe من مجلد البرنامج المثبت'
    $dialog.Filter = 'Digital Lab Desktop|Digital Lab Desktop.exe'
    $dialog.CheckFileExists = $true
    if ([IO.Directory]::Exists($script:InstallationBox.Text)) { $dialog.InitialDirectory = $script:InstallationBox.Text }
    if ($dialog.ShowDialog($script:Form) -eq 'OK') { $script:InstallationBox.Text = [IO.Path]::GetDirectoryName($dialog.FileName) }
    $dialog.Dispose()
})

$storageField = New-PathField '2. مجلد storage/app من الموقع — اختياري'
$script:StorageBox = $storageField.Box
$storageField.Button.Add_Click({
    $dialog = New-Object System.Windows.Forms.FolderBrowserDialog
    $dialog.Description = 'اختر مجلد app الموجود داخل storage في نسخة ملفات الموقع'
    $dialog.ShowNewFolderButton = $false
    if ([IO.Directory]::Exists($script:StorageBox.Text)) { $dialog.SelectedPath = $script:StorageBox.Text }
    if ($dialog.ShowDialog($script:Form) -eq 'OK') { $script:StorageBox.Text = $dialog.SelectedPath }
    $dialog.Dispose()
})
$ackRow = New-Row 70
$script:StorageNote = New-Object System.Windows.Forms.Label
$script:StorageNote.Location = [Drawing.Point]::new(0, 0)
$script:StorageNote.Size = [Drawing.Size]::new($ackRow.Width, 30)
$script:StorageNote.Anchor = 'Top,Left,Right'
$script:StorageNote.Text = 'بدون هذا المجلد، لن تُنسخ ملفات الصور والمرفقات الموجودة على الموقع.'
$script:StorageNote.ForeColor = $script:Colors.Warning
$script:StorageNote.TextAlign = 'MiddleRight'
$script:NoStorageAcknowledgement = New-Object System.Windows.Forms.CheckBox
$script:NoStorageAcknowledgement.Location = [Drawing.Point]::new(0, 34)
$script:NoStorageAcknowledgement.Size = [Drawing.Size]::new($ackRow.Width, 30)
$script:NoStorageAcknowledgement.Anchor = 'Top,Left,Right'
$script:NoStorageAcknowledgement.Text = 'أفهم أن النسخة ستكون بيانات فقط، والمرفقات الخارجية غير مضمّنة.'
$script:NoStorageAcknowledgement.CheckAlign = 'MiddleRight'
$ackRow.Controls.AddRange(@($script:StorageNote, $script:NoStorageAcknowledgement))
$script:InputControls.Add($script:NoStorageAcknowledgement)
$script:StorageBox.Add_TextChanged({
    $missing = [string]::IsNullOrWhiteSpace($script:StorageBox.Text)
    $script:StorageNote.Visible = $missing
    $script:NoStorageAcknowledgement.Visible = $missing
    if (-not $missing) { $script:NoStorageAcknowledgement.Checked = $false }
})

$outputField = New-PathField '3. مكان حفظ ملف الاستعادة الجديد'
$script:OutputBox = $outputField.Box
$documents = [Environment]::GetFolderPath('MyDocuments')
if (-not $documents) { $documents = $PSScriptRoot }
$script:OutputBox.Text = Join-Path $documents ('DigitalLab-AllLabs-' + (Get-Date -Format 'yyyyMMdd-HHmm') + '.dlbackup')
$outputField.Button.Add_Click({
    $dialog = New-Object System.Windows.Forms.SaveFileDialog
    $dialog.Title = 'حفظ ملف الاستعادة'
    $dialog.Filter = 'Digital Lab Backup (*.dlbackup)|*.dlbackup'
    $dialog.DefaultExt = 'dlbackup'
    $dialog.AddExtension = $true
    $dialog.OverwritePrompt = $false
    try {
        $dialog.FileName = [IO.Path]::GetFileName($script:OutputBox.Text)
        $parent = [IO.Path]::GetDirectoryName($script:OutputBox.Text)
        if ([IO.Directory]::Exists($parent)) { $dialog.InitialDirectory = $parent }
    } catch {}
    if ($dialog.ShowDialog($script:Form) -eq 'OK') { $script:OutputBox.Text = $dialog.FileName }
    $dialog.Dispose()
})

$passwordRow = New-Row 86
$passwordLeft = New-Object System.Windows.Forms.Panel
$passwordRight = New-Object System.Windows.Forms.Panel
$passwordLeft.Location = [Drawing.Point]::new(0, 0)
$passwordLeft.Size = [Drawing.Size]::new(380, 80)
$passwordRight.Location = [Drawing.Point]::new(400, 0)
$passwordRight.Size = [Drawing.Size]::new(400, 80)
[void](New-FieldLabel $passwordRight '4. كلمة سر ملف الاستعادة — 12 حرفاً على الأقل')
[void](New-FieldLabel $passwordLeft 'تأكيد كلمة السر')
$script:PasswordBox = New-Object System.Windows.Forms.TextBox
$script:PasswordBox.Location = [Drawing.Point]::new(0, 32)
$script:PasswordBox.Size = [Drawing.Size]::new($passwordRight.Width, 30)
$script:PasswordBox.Anchor = 'Top,Left,Right'
$script:PasswordBox.RightToLeft = 'No'
$script:PasswordBox.UseSystemPasswordChar = $true
$script:PasswordBox.MaxLength = 256
$script:ConfirmBox = New-Object System.Windows.Forms.TextBox
$script:ConfirmBox.Location = [Drawing.Point]::new(0, 32)
$script:ConfirmBox.Size = [Drawing.Size]::new($passwordLeft.Width, 30)
$script:ConfirmBox.Anchor = 'Top,Left,Right'
$script:ConfirmBox.RightToLeft = 'No'
$script:ConfirmBox.UseSystemPasswordChar = $true
$script:ConfirmBox.MaxLength = 256
$passwordRight.Controls.Add($script:PasswordBox)
$passwordLeft.Controls.Add($script:ConfirmBox)
$passwordRow.Controls.AddRange(@($passwordLeft, $passwordRight))
$script:InputControls.Add($script:PasswordBox)
$script:InputControls.Add($script:ConfirmBox)
$passwordRow.Add_Resize({
    $half = [int](($passwordRow.Width - 20) / 2)
    $passwordLeft.Width = $half
    $passwordRight.Left = $half + 20
    $passwordRight.Width = $passwordRow.Width - $half - 20
})

$keyRow = New-Row 112
[void](New-FieldLabel $keyRow 'مفتاح APP_KEY للموقع — مطلوب للإعدادات المشفّرة')
$script:SourceKeyBox = New-Object System.Windows.Forms.TextBox
$script:SourceKeyBox.Location = [Drawing.Point]::new(0, 30)
$script:SourceKeyBox.Size = [Drawing.Size]::new($keyRow.Width, 30)
$script:SourceKeyBox.Anchor = 'Top,Left,Right'
$script:SourceKeyBox.RightToLeft = 'No'
$script:SourceKeyBox.UseSystemPasswordChar = $true
$script:SourceKeyBox.MaxLength = 512
$keyNote = New-Object System.Windows.Forms.Label
$keyNote.Location = [Drawing.Point]::new(0, 66)
$keyNote.Size = [Drawing.Size]::new($keyRow.Width, 40)
$keyNote.Anchor = 'Top,Left,Right'
$keyNote.ForeColor = $script:Colors.Muted
$keyNote.TextAlign = 'TopRight'
$keyNote.Text = 'ألصق قيمة المفتاح وحدها. يُستخدم أثناء النقل لفك الإعدادات وإعادة تشفيرها محلياً، ولا يُحفظ في ملف إعدادات الأداة.'
$keyRow.Controls.AddRange(@($script:SourceKeyBox, $keyNote))
$keyRow.Visible = ($script:EncryptedCount -gt 0)
$script:InputControls.Add($script:SourceKeyBox)

$passwordNoteRow = New-Row 50
$passwordNote = New-Object System.Windows.Forms.Label
$passwordNote.Dock = 'Fill'
$passwordNote.TextAlign = 'TopRight'
$passwordNote.ForeColor = $script:Colors.Muted
$passwordNote.Text = 'احتفظ بكلمة سر ملف الاستعادة؛ ستحتاجها عند فتحه في الدسكتوب. أثناء التنفيذ تبقى النافذة مفتوحة حتى تنتهي الأداة من إغلاق خدماتها المؤقتة.'
$passwordNoteRow.Controls.Add($passwordNote)

function Update-Layout {
    $script:Content.Width = [Math]::Max(620, $scroll.ClientSize.Width - 55)
    [int]$top = 0
    foreach ($row in $script:Rows) {
        $row.Width = $script:Content.Width
        if ($row -eq $keyRow -and $script:EncryptedCount -le 0) { continue }
        $row.Top = $top
        $top += $row.Height + 7
    }
    $script:Content.Height = $top
    $footerWidth = $footer.ClientSize.Width - 48
    $script:StartButton.Left = $footer.ClientSize.Width - $script:StartButton.Width - 24
    $script:ProgressBar.Width = $footerWidth
    $script:StatusLabel.Width = $footerWidth
    $script:DetailsLabel.Width = $footerWidth
}
$scroll.Add_Resize({ Update-Layout })
$footer.Add_Resize({ Update-Layout })

$script:PhaseNames = @{
    preflight = 'مراجعة إعدادات النقل'
    verify_source = 'فحص سلامة ملف قاعدة البيانات'
    prepare_runtime = 'تجهيز قاعدة محلية مؤقتة'
    import_sql = 'استيراد بيانات جميع المختبرات'
    validate_import = 'مطابقة الجداول والسجلات'
    postprocess = 'تهيئة البيانات للعمل محلياً'
    copy_storage = 'نسخ الصور والمرفقات'
    backup = 'إنشاء ملف الاستعادة المشفّر'
    verify_backup = 'فحص ملف الاستعادة'
    publish = 'حفظ ملف الاستعادة المعتمد'
    cleanup = 'إغلاق الخدمات المؤقتة وتنظيف ملفات العمل'
}
$script:ErrorMessages = @{
    INPUT_INVALID = 'إحدى قيم الإدخال غير صالحة. راجع المسارات وكلمة السر.'
    CONFIG_INVALID = 'إعدادات النقل غير صالحة. راجع المسارات المدخلة.'
    MANIFEST_INVALID = 'ملف تعريف البيانات غير صالح. أعد فك الحزمة الأصلية كاملة.'
    SOURCE_MISSING = 'لم يُعثر على ملف قاعدة البيانات المرفق.'
    SOURCE_HASH_MISMATCH = 'بصمة ملف البيانات لا تطابق النسخة الأصلية. أعد تنزيل الحزمة وفكها كاملة.'
    SOURCE_SIZE_MISMATCH = 'ملف البيانات غير مكتمل. أعد تنزيل الحزمة وفكها كاملة.'
    SOURCE_MISMATCH = 'ملف البيانات لا يطابق حزمة النقل.'
    INSTALLATION_INVALID = 'مجلد البرنامج غير صالح. اختر ملف Digital Lab Desktop.exe للنسخة المطلوبة.'
    INSTALL_NOT_FOUND = 'لم يُعثر على البرنامج المثبت.'
    DESKTOP_VERSION_MISMATCH = 'إصدار البرنامج المثبت لا يطابق إصدار حزمة النقل.'
    RUNTIME_VERSION_MISMATCH = 'إصدار قاعدة البيانات المضمّنة غير متوافق مع هذه الحزمة.'
    RUNTIME_MISSING = 'بعض ملفات البرنامج المثبت ناقصة.'
    PHP_RUNTIME_NOT_READY = 'نسخة V1 تحتاج مسار تثبيت إنكليزي لتشغيل مكوّناتها. أعد تثبيت البرنامج في مسار إنكليزي، أو اختر نسخة مثبتة في مسار إنكليزي، ثم أعد المحاولة.'
    DB_START_FAILED = 'تعذر تشغيل قاعدة البيانات المؤقتة. تأكد من توفر مساحة كافية وصلاحية الكتابة.'
    SQL_IMPORT_FAILED = 'لم يكتمل استيراد قاعدة البيانات. لم يتم اعتماد ملف استعادة.'
    SCHEMA_MISMATCH = 'بنية القاعدة لا تطابق ما تتوقعه حزمة النقل.'
    VALIDATION_FAILED = 'فحص البيانات بعد الاستيراد لم ينجح. لم يتم اعتماد ملف استعادة.'
    MISSING_APP_KEY = 'مفتاح APP_KEY للموقع مطلوب لقراءة الإعدادات المشفّرة.'
    INVALID_APP_KEY = 'قيمة APP_KEY غير صالحة. ألصق قيمة مفتاح الموقع فقط.'
    DECRYPTION_FAILED = 'لم ينجح فك الإعدادات المشفّرة. تحقق من مفتاح APP_KEY للموقع.'
    STORAGE_INVALID = 'مجلد المرفقات غير صالح أو يحتوي ملفات غير مسموح بها.'
    STORAGE_PATH_INVALID = 'مسار مجلد المرفقات غير صالح.'
    NO_SPACE = 'المساحة الحرة غير كافية لتجهيز النسخة. وفر مساحة إضافية ثم أعد المحاولة.'
    OUTPUT_EXISTS = 'يوجد ملف بالاسم المختار. اختر اسماً جديداً لحفظ النسخة.'
    PASSWORD_INVALID = 'كلمة سر ملف الاستعادة يجب أن تكون بين 12 و256 حرفاً.'
    BACKUP_FAILED = 'تعذر إنشاء ملف الاستعادة.'
    BACKUP_VERIFY_FAILED = 'فحص ملف الاستعادة لم ينجح. لا تستخدم الملف الناتج.'
    CLEANUP_FAILED = 'تعذر إكمال تنظيف ملفات العمل المؤقتة.'
    INVALID_CONFIG = 'إعدادات النقل غير صالحة. راجع المسارات المدخلة.'
    INVALID_MANIFEST = 'ملف تعريف البيانات غير صالح. أعد فك الحزمة الأصلية كاملة.'
    INVALID_PASSWORD = 'كلمة سر ملف الاستعادة يجب أن تكون بين 12 و256 حرفاً.'
    SOURCE_KEY_REQUIRED = 'مفتاح APP_KEY للموقع مطلوب لقراءة الإعدادات المشفّرة.'
    INVALID_SOURCE_KEY = 'قيمة APP_KEY غير صالحة. ألصق قيمة مفتاح الموقع فقط.'
    ENCRYPTED_DATA_INVALID = 'لم ينجح فك الإعدادات المشفّرة. تحقق من مفتاح APP_KEY للموقع.'
    INCOMPATIBLE_DESKTOP = 'إصدار البرنامج المثبت لا يطابق إصدار حزمة النقل.'
    DATABASE_VERSION_MISMATCH = 'إصدار قاعدة البيانات المضمّنة غير متوافق مع هذه الحزمة.'
    DESKTOP_SCHEMA_MISMATCH = 'نسخة البيانات لا تحتوي البنية التي تحتاجها نسخة الدسكتوب الحالية.'
    TABLE_SET_MISMATCH = 'قائمة الجداول المستوردة لا تطابق وصف النسخة.'
    COLUMN_SET_MISMATCH = 'أعمدة الجداول المستوردة لا تطابق وصف النسخة.'
    ROW_COUNT_MISMATCH = 'أعداد السجلات المستوردة لا تطابق النسخة المعتمدة.'
    ORPHAN_REFERENCE = 'فحص العلاقات بين السجلات لم ينجح. لم يتم اعتماد ملف استعادة.'
    ADMIN_MISSING = 'لا يوجد حساب مدير صالح في البيانات المستوردة.'
    NO_USABLE_ADMIN = 'حسابات المدير غير مفعّلة أو غير مؤكدة البريد. تحتاج مراجعة قبل اعتماد النقل.'
    RESTORE_VERIFICATION_FAILED = 'لم تنجح تجربة استعادة النسخة الناتجة. لا تستخدم الملف الناتج.'
    DATABASE_SHUTDOWN_FAILED = 'لم يتأكد توقف قاعدة البيانات المؤقتة. لم تبدأ عملية النسخ.'
    MEDIA_RELINK_FAILED = 'تعذر تهيئة روابط الصور والمرفقات للعمل محلياً.'
}
$script:WarningMessages = @{
    ATTACHMENTS_NOT_PROVIDED = 'الصور والمرفقات الخارجية غير مضمّنة لأن مجلدها لم يُحدد.'
    STORAGE_NOT_PROVIDED = 'الصور والمرفقات الخارجية غير مضمّنة لأن مجلدها لم يُحدد.'
    NO_STORAGE = 'الصور والمرفقات الخارجية غير مضمّنة لأن مجلدها لم يُحدد.'
    MISSING_ATTACHMENTS = 'بعض المرفقات المشار إليها في البيانات لم تكن موجودة في الملفات المرفقة.'
    ONLINE_SERVICES_DISABLED = 'الخدمات الخارجية تحتاج إعداداً منفصلاً عند استخدام الإنترنت.'
    TEMP_CLEANUP_INCOMPLETE = 'اكتمل الملف، لكن بعض ملفات العمل المؤقتة لم تُحذف.'
    STAGE_CLEANUP_PENDING = 'اكتمل الملف، لكن بعض ملفات العمل المؤقتة لم تُحذف.'
}

function Set-InputState {
    param([bool]$Enabled)
    foreach ($control in $script:InputControls) { $control.Enabled = $Enabled }
    $script:StartButton.Enabled = $Enabled
}

function Clear-SecretFields {
    $script:PasswordBox.Clear()
    $script:ConfirmBox.Clear()
    $script:SourceKeyBox.Clear()
}

function Get-SafeCode {
    param($Code)
    $candidate = [string]$Code
    if ($candidate -match '^[A-Z0-9_]{1,80}$') { return $candidate }
    return 'TRANSFER_FAILED'
}

function Handle-OutputLine {
    param([string]$Line)
    if ([string]::IsNullOrWhiteSpace($Line)) { return }
    try { $eventData = $Line | ConvertFrom-Json -ErrorAction Stop } catch {
        $script:UnexpectedOutput = $true
        return
    }
    $eventType = [string](Get-PropertyValue $eventData 'type' '')
    if ($eventType -eq 'progress') {
        $phase = [string](Get-PropertyValue $eventData 'phase' '')
        if ($script:PhaseNames.ContainsKey($phase)) {
            $message = $script:PhaseNames[$phase]
            [double]$percent = 0
            if ([double]::TryParse([string](Get-PropertyValue $eventData 'percent' '0'), [ref]$percent)) {
                $percent = [Math]::Max(0, [Math]::Min(100, $percent))
                $script:ProgressBar.Value = [int]$percent
                $message += ' — ' + ([int]$percent).ToString() + '%'
            }
            $script:StatusLabel.Text = $message
        }
    } elseif ($eventType -eq 'result') {
        $script:FinalResult = $eventData
    }
}

function Finish-Transfer {
    $script:Timer.Stop()
    $exitCode = -1
    try { $exitCode = $script:Process.ExitCode } catch {}
    $result = $script:FinalResult
    $success = ($null -ne $result -and (Get-PropertyValue $result 'ok' $false) -eq $true -and $exitCode -eq 0)
    $script:Running = $false
    Set-InputState $true
    Clear-SecretFields
    $script:StartButton.Text = 'إنشاء ملف استعادة آخر'
    if ($success -and [IO.File]::Exists($script:SelectedOutput)) {
        $script:CompletedOutput = $script:SelectedOutput
        $script:OpenFolderButton.Enabled = $true
        $script:ProgressBar.Value = 100
        $script:StatusLabel.ForeColor = $script:Colors.Accent
        $script:StatusLabel.Text = 'اكتمل إنشاء ملف الاستعادة وفحصه.'
        $script:DetailsLabel.Text = 'افتح الدسكتوب: البرنامج ← إعداد النسخة والنسخ الاحتياطي.' + $script:NewLine +
            'اكتب كلمة سر الملف في خانة «كلمة سر النسخة الاحتياطية» أولاً.' + $script:NewLine +
            'اضغط «استعادة من ملف»، واختر الملف الجديد ثم أكّد الاستعادة.' + $script:NewLine +
            'بعد اكتمال الاستعادة اضغط «فتح البرنامج».'
        $warnings = New-Object System.Collections.Generic.List[string]
        foreach ($warning in @(Get-PropertyValue $result 'warnings' @())) {
            $code = Get-SafeCode $warning
            if ($script:WarningMessages.ContainsKey($code)) { $warnings.Add($script:WarningMessages[$code]) }
            elseif ($code -ne 'TRANSFER_FAILED') { $warnings.Add('ملاحظة من أداة النقل: ' + $code) }
        }
        if ($warnings.Count -gt 0) {
            Show-Notice (('اكتمل إنشاء ملف الاستعادة.' + $script:NewLine + $script:NewLine) + ($warnings -join $script:NewLine))
        }
    } else {
        $script:OpenFolderButton.Enabled = $false
        $script:StatusLabel.ForeColor = $script:Colors.Error
        $script:StatusLabel.Text = 'لم تكتمل عملية النقل.'
        $errorObject = Get-PropertyValue $result 'error'
        $code = Get-SafeCode (Get-PropertyValue $errorObject 'code' 'TRANSFER_FAILED')
        $message = 'تعذر إكمال النقل. راجع المدخلات وأعد المحاولة.'
        if ($script:ErrorMessages.ContainsKey($code)) { $message = $script:ErrorMessages[$code] }
        $script:DetailsLabel.Text = $message + $script:NewLine + 'رمز الخطأ: ' + $code
        if ((Get-PropertyValue $result 'cleanupComplete' $true) -eq $false) {
            $script:DetailsLabel.Text += $script:NewLine + 'لم يكتمل تنظيف ملفات العمل المؤقتة؛ احتفظ برمز الخطأ عند طلب المساعدة.'
        }
    }
    if ($null -ne $script:Process) { $script:Process.Dispose() }
    $script:Process = $null
    $script:ReadTask = $null
    $script:ErrorReadTask = $null
}

$script:Timer = New-Object System.Windows.Forms.Timer
$script:Timer.Interval = 100
$script:Timer.Add_Tick({
    if (-not $script:Running -or $null -eq $script:Process) { return }
    [int]$readCount = 0
    while (-not $script:StdoutEnded -and $null -ne $script:ReadTask -and $script:ReadTask.IsCompleted -and $readCount -lt 100) {
        try {
            $line = $script:ReadTask.GetAwaiter().GetResult()
            if ($null -eq $line) { $script:StdoutEnded = $true; break }
            Handle-OutputLine $line
            $script:ReadTask = $script:Process.StandardOutput.ReadLineAsync()
        } catch {
            $script:StdoutEnded = $true
            $script:UnexpectedOutput = $true
        }
        $readCount++
    }
    [int]$errorCount = 0
    while (-not $script:StderrEnded -and $null -ne $script:ErrorReadTask -and $script:ErrorReadTask.IsCompleted -and $errorCount -lt 100) {
        try {
            $discardedLine = $script:ErrorReadTask.GetAwaiter().GetResult()
            if ($null -eq $discardedLine) { $script:StderrEnded = $true; break }
            $discardedLine = $null
            $script:ErrorReadTask = $script:Process.StandardError.ReadLineAsync()
        } catch { $script:StderrEnded = $true }
        $errorCount++
    }
    try {
        if ($script:Process.HasExited -and $script:StdoutEnded -and $script:StderrEnded) { Finish-Transfer }
    } catch {
        $script:StatusLabel.Text = 'جارٍ انتظار انتهاء خدمات النقل المؤقتة…'
    }
})

$script:StartButton.Add_Click({
    if ($script:Running) { return }
    $config = $null
    $json = $null
    $jsonBytes = $null
    $installationDir = $script:InstallationBox.Text.Trim()
    $storagePath = $script:StorageBox.Text.Trim()
    $outputPath = $script:OutputBox.Text.Trim()
    $sourceKey = $script:SourceKeyBox.Text.Trim()
    try {
        if ([IO.File]::Exists($installationDir) -and [IO.Path]::GetFileName($installationDir) -eq 'Digital Lab Desktop.exe') {
            $installationDir = [IO.Path]::GetDirectoryName($installationDir)
        }
        if (-not $installationDir -or -not [IO.Directory]::Exists($installationDir)) {
            Show-Notice 'اختر مجلد برنامج Digital Lab Desktop المثبت أولاً.'
            return
        }
        $installationDir = [IO.Path]::GetFullPath($installationDir)
        $executable = Join-Path $installationDir 'Digital Lab Desktop.exe'
        if (-not [IO.File]::Exists($executable) -or -not [IO.File]::Exists((Join-Path $installationDir 'resources\bundle\build.json'))) {
            Show-Notice 'هذا المسار لا يحتوي نسخة Digital Lab Desktop المطلوبة. اختر البرنامج المثبت من زر استعراض.'
            return
        }
        if ($storagePath -and -not [IO.Directory]::Exists($storagePath)) {
            Show-Notice 'مجلد المرفقات غير موجود. اختر مجلد storage/app الصحيح.'
            return
        }
        if (-not $storagePath -and -not $script:NoStorageAcknowledgement.Checked) {
            Show-Notice 'حدد مجلد المرفقات، أو فعّل الإقرار بأن النسخة ستكون بيانات فقط.'
            return
        }
        if (-not $outputPath -or [IO.Path]::GetExtension($outputPath) -ne '.dlbackup') {
            Show-Notice 'اختر اسم ملف ينتهي بالامتداد .dlbackup لحفظ النسخة.'
            return
        }
        $outputPath = [IO.Path]::GetFullPath($outputPath)
        if (-not [IO.Directory]::Exists([IO.Path]::GetDirectoryName($outputPath))) {
            Show-Notice 'مجلد حفظ النسخة غير موجود. اختر مجلداً موجوداً.'
            return
        }
        if ([IO.File]::Exists($outputPath) -or [IO.Directory]::Exists($outputPath)) {
            Show-Notice 'يوجد ملف أو مجلد بالاسم المختار. اختر اسماً جديداً؛ الأداة لا تستبدل النسخ المحفوظة.'
            return
        }
        if ([string]::IsNullOrWhiteSpace($script:PasswordBox.Text) -or $script:PasswordBox.Text.Length -lt 12) {
            Show-Notice 'اكتب كلمة سر لملف الاستعادة تتكون من 12 حرفاً على الأقل.'
            return
        }
        if (-not [string]::Equals($script:PasswordBox.Text, $script:ConfirmBox.Text, [StringComparison]::Ordinal)) {
            Show-Notice 'كلمتا السر غير متطابقتين. أعد كتابة التأكيد.'
            return
        }
        if ($script:EncryptedCount -gt 0 -and [string]::IsNullOrWhiteSpace($sourceKey)) {
            Show-Notice 'توجد إعدادات مشفّرة في البيانات. أدخل قيمة APP_KEY للموقع في الحقل المخصص.'
            return
        }
        $config = [ordered]@{
            installationDir = $installationDir
            sqlPath = $script:SqlPath
            manifestPath = $script:ManifestPath
            storageAppPath = $null
            outputPath = $outputPath
            password = $script:PasswordBox.Text
        }
        if ($storagePath) { $config.storageAppPath = [IO.Path]::GetFullPath($storagePath) }
        if ($script:EncryptedCount -gt 0) { $config.sourceAppKey = $sourceKey }
        $json = $config | ConvertTo-Json -Compress -Depth 5
        $startInfo = New-Object Diagnostics.ProcessStartInfo
        $startInfo.FileName = $executable
        $startInfo.Arguments = '"' + $script:CliPath + '" --config-stdin'
        $startInfo.WorkingDirectory = $script:ToolRoot
        $startInfo.UseShellExecute = $false
        $startInfo.CreateNoWindow = $true
        $startInfo.RedirectStandardInput = $true
        $startInfo.RedirectStandardOutput = $true
        $startInfo.RedirectStandardError = $true
        $startInfo.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
        $startInfo.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
        $startInfo.EnvironmentVariables['ELECTRON_RUN_AS_NODE'] = '1'
        $script:Process = New-Object Diagnostics.Process
        $script:Process.StartInfo = $startInfo
        $script:FinalResult = $null
        $script:CompletedOutput = $null
        $script:SelectedOutput = $outputPath
        $script:StdoutEnded = $false
        $script:StderrEnded = $false
        $script:UnexpectedOutput = $false
        $script:OpenFolderButton.Enabled = $false
        $script:ProgressBar.Value = 0
        $script:StatusLabel.ForeColor = $script:Colors.Ink
        $script:StatusLabel.Text = 'جارٍ بدء النقل…'
        $script:DetailsLabel.Text = 'انتظر حتى تنتهي العملية. النافذة تتابع تقدم النقل وتنتظر إغلاق قاعدة البيانات المؤقتة قبل السماح بالإغلاق.'
        Set-InputState $false
        [void]$script:Process.Start()
        $script:Running = $true
        $script:ReadTask = $script:Process.StandardOutput.ReadLineAsync()
        $script:ErrorReadTask = $script:Process.StandardError.ReadLineAsync()
        try {
            # Windows PowerShell 5.1 may use a legacy console code page for
            # StandardInput. Write UTF-8 bytes explicitly for Arabic paths,
            # passwords and keys. The original StreamWriter remains unused.
            $jsonBytes = [Text.UTF8Encoding]::new($false).GetBytes($json + $script:NewLine)
            $script:Process.StandardInput.BaseStream.Write($jsonBytes, 0, $jsonBytes.Length)
            $script:Process.StandardInput.BaseStream.Flush()
            $script:Process.StandardInput.Close()
        } finally {
            if ($null -ne $jsonBytes) { [Array]::Clear($jsonBytes, 0, $jsonBytes.Length) }
            $jsonBytes = $null
            $json = $null
            $config.password = $null
            if ($config.Contains('sourceAppKey')) { $config.sourceAppKey = $null }
            $sourceKey = $null
            Clear-SecretFields
            $script:Timer.Start()
        }
    } catch {
        Clear-SecretFields
        if ($null -ne $jsonBytes) { [Array]::Clear($jsonBytes, 0, $jsonBytes.Length) }
        $jsonBytes = $null
        $json = $null
        if ($null -ne $config) {
            $config.password = $null
            if ($config.Contains('sourceAppKey')) { $config.sourceAppKey = $null }
        }
        $sourceKey = $null
        if ($script:Running) {
            try { $script:Process.StandardInput.Close() } catch {}
            $script:Timer.Start()
            $script:StatusLabel.Text = 'تعذر إرسال إعدادات النقل. جارٍ انتظار إغلاق العملية…'
        } else {
            Set-InputState $true
            $script:StatusLabel.ForeColor = $script:Colors.Error
            $script:StatusLabel.Text = 'تعذر بدء أداة النقل.'
            $script:DetailsLabel.Text = 'راجع مسار البرنامج وصلاحية الوصول إلى مجلد حفظ النسخة، ثم أعد المحاولة.'
            if ($null -ne $script:Process) { $script:Process.Dispose(); $script:Process = $null }
        }
    }
})

$script:OpenFolderButton.Add_Click({
    if ($script:CompletedOutput -and [IO.File]::Exists($script:CompletedOutput)) {
        try {
            $explorerArgs = '/select,"' + $script:CompletedOutput + '"'
            [void][Diagnostics.Process]::Start('explorer.exe', $explorerArgs)
        } catch { Show-Notice ('الملف محفوظ في:' + $script:NewLine + $script:CompletedOutput) }
    }
})
$script:Form.Add_FormClosing({
    param($sender, $eventArgs)
    if ($script:Running) {
        $eventArgs.Cancel = $true
        $script:StatusLabel.Text = 'النقل مستمر. انتظر اكتماله وإغلاق خدماته المؤقتة قبل إغلاق النافذة.'
    }
})
$script:Form.Add_FormClosed({
    Clear-SecretFields
    $script:Timer.Stop()
    $script:Timer.Dispose()
})
$script:Form.Add_Shown({
    Update-Layout
    if ($UiSmokeTest) {
        if ($env:DLM_TEST_INSTALLATION) { $script:InstallationBox.Text = $env:DLM_TEST_INSTALLATION }
        $script:NoStorageAcknowledgement.Checked = $true
        $script:PasswordBox.Text = 'Synthetic-UI-password-2026'
        $script:ConfirmBox.Text = 'Synthetic-UI-password-2026'
        $script:SourceKeyBox.Text = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
        $script:UiSmokeTimer = New-Object System.Windows.Forms.Timer
        $script:UiSmokeTimer.Interval = 1000
        $script:UiSmokeTimer.Add_Tick({
            $script:UiSmokeTimer.Stop()
            try {
                if (-not $script:Form.Visible -or $script:Form.ClientSize.Width -lt 600) { throw 'FORM_NOT_INITIALIZED' }
                if ($script:EncryptedCount -gt 0 -and -not $keyRow.Visible) { throw 'ENCRYPTION_FIELD_NOT_VISIBLE' }
                $target = [IO.Path]::GetFullPath($SmokeOutputPath)
                [void][IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($target))
                $bitmap = [Drawing.Bitmap]::new($script:Form.Width, $script:Form.Height)
                try {
                    $script:Form.DrawToBitmap($bitmap, [Drawing.Rectangle]::new(0, 0, $script:Form.Width, $script:Form.Height))
                    $bitmap.Save($target, [Drawing.Imaging.ImageFormat]::Png)
                } finally { $bitmap.Dispose() }
                $scroll.AutoScrollPosition = [Drawing.Point]::new(0, $script:Content.Height)
                $script:Form.Refresh()
                [System.Windows.Forms.Application]::DoEvents()
                $bottomTarget = [IO.Path]::Combine([IO.Path]::GetDirectoryName($target), [IO.Path]::GetFileNameWithoutExtension($target) + '-bottom.png')
                $bottomBitmap = [Drawing.Bitmap]::new($script:Form.Width, $script:Form.Height)
                try {
                    $script:Form.DrawToBitmap($bottomBitmap, [Drawing.Rectangle]::new(0, 0, $script:Form.Width, $script:Form.Height))
                    $bottomBitmap.Save($bottomTarget, [Drawing.Imaging.ImageFormat]::Png)
                } finally { $bottomBitmap.Dispose() }
                $script:UiSmokePassed = $true
            } catch {
                $script:UiSmokePassed = $false
                [Console]::Error.WriteLine('WinForms smoke initialization failed.')
            } finally {
                $script:UiSmokeTimer.Dispose()
                $script:Form.Close()
            }
        })
        $script:UiSmokeTimer.Start()
    }
})
Update-Layout
[void]$script:Form.ShowDialog()
$script:Form.Dispose()
if ($UiSmokeTest -and -not $script:UiSmokePassed) { exit 1 }
