<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php"); // Redirect to login if not authenticated
    exit;
}

include 'config.php'; // Include database connection

if (isset($_GET['action']) && $_GET['action'] === 'version') {
    $version = 'MMHR Census v1.0.0';
    $lastUpdated = 'April 29, 2025';

    echo "<script>
        if (confirm('🔖 Version Information:\\n\\nVersion: $version\\nLast Updated: $lastUpdated')) {
            window.location.href = 'display_summary.php';
        } else {
            window.location.href = 'display_summary.php';
        }
    </script>";
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'clear_data') {
    require_once 'config.php';

    $tablesToClear = [
        'patient_records',
        'patient_records_2',
        'patient_records_3',
        'leading_causes',
    ];

    foreach ($tablesToClear as $table) {
        $conn->query("TRUNCATE TABLE `$table`");
    }

    echo "<script>
        alert('✅ All data has been cleared successfully!\\n\\nTables cleared: " . implode(', ', $tablesToClear) . "');
        window.location.href = 'display_summary.php';
    </script>";
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'maintenance') {
    require_once 'config.php'; 

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $conn->query("OPTIMIZE TABLE `$table`");
    }

    $backupFolder = __DIR__; 
    $files = scandir($backupFolder);

    $deletedFiles = 0;
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip' && strpos($file, 'mmhr_census') !== false) {
            $filePath = $backupFolder . DIRECTORY_SEPARATOR . $file;
            $fileModifiedTime = filemtime($filePath);

            if (time() - $fileModifiedTime > 7 * 24 * 60 * 60) {
                unlink($filePath);
                $deletedFiles++;
            }
        }
    }

    header("Location: display_summary.php?maintenance=success&deleted=$deletedFiles");
    exit;
}

// Fetch uploaded files
$files = $conn->query("SELECT * FROM uploaded_files");

$totalFiles = $files->num_rows;
//$maxFilesAllowed = 10; // Set limit here

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/dashboard.css">
    <title>MMHR Census</title>
</head>
<body class="body-bg">
    <nav class="navbar">
        <div class="nav-container">
            <img src="css/download-removebg-preview.png" alt="icon" class="logo">
            <div class="nav-text">
                <h1>BicutanMed</h1>
                <p>Caring For Life</p>
            </div>
            <!--<div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="#">Maintenance</a>
                <a href="https://bicutanmed.com/about-us">About us</a>
                <a href="#">Settings</a>
            </div>-->
            
            <div class="nav-tools">
            <a href="dashboard.php" class="btrdy">Home</a>

                <div class="dropdown" id="toolsDropdown">
                <a href="#" class="dropbtn" onclick="toggleDropdown(event)">Tools</a>
                    <div class="dropdown-content">
                        <a href="#" onclick="exportToExcel()" style="color:blue;">📊 Export Data</a>
                        <a href="#" onclick="downloadBackup()" style="color:blue;">💾 Download Backup</a>
                        <a href="display_summary.php?action=maintenance" style="color:blue;">🛠️ Maintenance</a>
                        <a href="display_summary.php?action=clear_data" onclick="confirmClearData(event)" style="color: red;">❌ Clear All Data</a>
                    </div>
                </div>

                <div class="dropdown" id="settingsDropdown">
                <a href="#" class="dropbtn" onclick="toggleDropdown(event)">Settings</a>
                <div class="dropdown-content">
                    <a href="#" onclick="showVersionInfo()" style="color:blue;">🔖 Version</a>
                    <div class="dropdown-submenu">
                    <a href="#" class="submenu-link" onclick="toggleSubmenu(event)" style="color:blue;">⚙️ Options ▾</a>
                        <div class="submenu-content">
                            <a href="#" onclick="openOptionsPopup('upload_limits')">📂 File Upload Settings</a>
                            <!-- <a href="#" onclick="openOptionsPopup('⏰ Cutoff Time Settings', 
                             'Change the default cutoff time for patient stay counting (e.g., 11:59 PM, 1:00 AM).')">🕛 Cutoff Time Settings</a> -->
                            <a href="#" onclick="openOptionsPopup('🗑️ Retention Settings', 
                            'Set how long to keep uploaded files before auto-deletion. Toggle auto-cleanup of old data.')">🗑️ Retention Settings</a>
                            <!-- <a href="#" onclick="openOptionsPopup('🔀 Category Mapping Rules', 
                             'View and edit how member categories are grouped for NHIP/NON-NHIP.')">🔀 Category Mapping Rules</a> -->
                            <a href="#" onclick="openOptionsPopup('📤 Export Format Preferences', 'Choose preferred export formats: Excel, CSV, or PDF.')">📤 Export Format Preferences</a>
                            <a href="#" onclick="openOptionsPopup('🎨 Theme / Appearance', 'Toggle light/dark mode. Adjust font size or layout spacing.')">🎨 Theme / Appearance</a>
                            <a href="#" onclick="openOptionsPopup('📝 Admin Notes / Logs', 'Add system notes or view recent maintenance/backup actions.')">📝 Admin Notes / Logs</a>
                            <a href="#" onclick="openOptionsPopup('🔄 Reset Options', 'Reset filters to default or clear browser-stored preferences.')">🔄 Reset Options</a>
                            <!-- <a href="#" onclick="openOptionsPopup('🔖 Version Info', 'Current system version: v1.0.0.\nManual updates available if needed.')">🔖 Version Info</a> -->
                        </div>
                    </div>
                </div>
            </div>
            <a href="https://bicutanmed.com/about-us" class="btrdy">About Us</a>
            <a href="logout.php" class="logout-link">
                <img src="css/power-off.png" alt="logout" class="logout-icon">
            </a>
        </div>
    </nav>

    <div id="optionsPopup" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: white; border: 2px solid #007BFF; border-radius: 10px; padding: 20px; width: 400px; z-index: 1000; box-shadow: 0 0 15px rgba(0,0,0,0.3);">
        
        <h2 id="popupTitle" style="margin-top: 0; color: #007BFF;">Option Title</h2>
        <div id="popupContent" style="font-size: 14px;"></div>
        <div style="text-align: right; margin-top: 20px;">
            <button onclick="closeOptionsPopup()" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Close</button>
        </div>
    </div>

    <div class="container">
    <main class="main-content">
        <section class="upload-section">
            <h2>Upload Excel File</h2>
            <form action="upload.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="excelFile" accept=".xlsx, .xls" class="file-input">
                <button type="submit" class="btn-upload">Upload</button>
            </form>
        </section>

        <section class="select-section">
            <form action="display_summary.php" method="GET">
                <h2>Select File & Sheet</h2>
                <label for="file">Select File:</label>
                <select name="file_id" id="file" class="file-dropdown">
                    <?php while ($file = $files->fetch_assoc()): ?>
                        <option value="<?= $file['id'] ?>"><?= $file['file_name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-submit">Load Sheets</button>
            </form>
        </section>

    </main>
</body>

<script>

function toggleDropdown(event) {
    event.preventDefault();
    event.stopPropagation(); // Prevent it from closing immediately

    const dropdown = event.target.closest('.dropdown');
    const content = dropdown.querySelector('.dropdown-content');

    // Toggle the clicked dropdown
    content.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
        if (!dropdown.parentElement.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Also close submenus
    document.querySelectorAll('.submenu-content').forEach(function(submenu) {
        if (!submenu.parentElement.contains(event.target)) {
            submenu.classList.remove('show');
        }
    });
});

// Toggle submenu
function toggleSubmenu(event) {
    event.preventDefault();
    event.stopPropagation(); // Prevent outside click from closing it

    const submenu = event.target.nextElementSibling;
    submenu.classList.toggle('show');
}

        const totalFiles = <?= $totalFiles ?>;
    const maxFiles = <?= $maxFilesAllowed ?>;

    window.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form[action="upload.php"]');
        const fileInput = form.querySelector('input[type="file"]');

        form.addEventListener('submit', (e) => {
            const file = fileInput.files[0];
            if (!file) return; // allow empty

            if (totalFiles >= maxFiles) {
                alert(`❌ Upload limit reached. Only ${maxFiles} files allowed.`);
                e.preventDefault();
                return;
            }

            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                alert("❌ File is too large. Max size is 5MB.");
                e.preventDefault();
                return;
            }
        });
    });

    function openOptionsPopup(title, description) {
    document.getElementById('popupTitle').innerText = title;

    console.log("Opening popup with title:", title);

    let content = '';
    if (title === 'upload_limits') {
        document.getElementById('popupTitle').innerText = '📁 File Upload Limits';
        console.log('Matched upload_limits type');

        content = `
            <form id="uploadSettingsForm">
              <label>Maximum Files:</label>
              <input type="number" name="max_upload_files" min="1" required><br><br>

              <label>Max File Size (MB):</label>
              <input type="number" name="max_file_size_mb" min="1" required><br><br>

              <label>Allowed Extensions (comma-separated):</label>
              <input type="text" name="allowed_file_extensions" required><br><br>

            </form>
            <div style="text-align: right;">
                <button type="submit" onclick="submitUploadSettings()" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Save</button>
                </div>
            <div id="uploadSettingsFeedback" style="margin-top: 10px; font-size: 13px; color: green;"></div>
        `;
    } else if (title === '🗑️ Retention Settings') {
        document.getElementById('popupTitle').innerText = '🗑️ Retention Settings';
        content = `
            <form id="retentionSettingsForm">
                <label>Retention Period (in days):</label>
                <input type="number" name="retention_days" min="1" required><br><br>

                <label>Enable Auto-Delete:</label>
                <select name="enable_auto_delete" required>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select><br><br>
            </form>
            <div style="text-align: right;">
                <button type="submit" form="retentionSettingsForm"
                    style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Save
                </button>
            </div>
            <div id="retentionSettingsFeedback" style="margin-top: 10px; font-size: 13px; color: green;"></div>
        `;
        fetchSettingsForRetention();

    } else if (title === '📤 Export Format Preferences') {
        document.getElementById('popupTitle').innerText = '📤 Export Format Preferences';

        content = `
            <form id="exportFormatForm">
                <label><input type="checkbox" name="formats[]" value="excel"> Excel</label><br>
                <label><input type="checkbox" name="formats[]" value="csv"> CSV</label><br>
                <label><input type="checkbox" name="formats[]" value="pdf"> PDF</label><br><br>

                <div style="text-align: right;">
                    <button type="submit" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Save</button>
                </div>
            </form>

            <div style="text-align: right;">
                <button type="submit" form="exportFormatForm" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Save</button>
            </div>
            <div id="exportFormatFeedback" style="margin-top: 10px; font-size: 13px; color: green;"></div>
        `;

        fetch('get_export_format.php')
            .then(res => res.json())
            .then(data => {
                const form = document.getElementById('exportFormatForm');
                data.formats.forEach(fmt => {
                    const checkbox = form.querySelector(`input[value="${fmt}"]`);
                    if (checkbox) checkbox.checked = true;
                });

                form.onsubmit = (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    fetch('save_export_format.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.text())
                    .then(msg => {
                        document.getElementById('exportFormatFeedback').innerText = msg;
                    });
                };
            });

    } else if (title === '🎨 Theme / Appearance') {
    document.getElementById('popupTitle').innerText = '🎨 Theme / Appearance';

    content = `
        <form id="themeSettingsForm">
            <label>
                Mode:
                <select name="theme_mode">
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </label><br><br>

            <label>
                Font Size:
                <select name="font_size">
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                </select>
            </label><br><br>

            <label>
                Layout Spacing:
                <select name="layout_spacing">
                    <option value="compact">Compact</option>
                    <option value="comfortable">Comfortable</option>
                    <option value="spacious">Spacious</option>
                </select>
            </label><br><br>
        </form>
        <div style="text-align: right;">
                <button type="submit" form="themeSettingsForm" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Save</button>
            </div>

        <div id="themeSettingsFeedback" style="margin-top: 10px; font-size: 13px; color: green;"></div>
    `;
        fetch('get_theme_settings.php')
            .then(res => res.json())
            .then(data => {
                const form = document.getElementById('themeSettingsForm');
                form.theme_mode.value = data.theme_mode;
                form.font_size.value = data.font_size;
                form.layout_spacing.value = data.layout_spacing;

                applyThemeSettings(data); 

                form.onsubmit = (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    fetch('save_theme_settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.text())
                    .then(msg => {
                        document.getElementById('themeSettingsFeedback').innerText = msg;
                        fetch('get_theme_settings.php')
                        .then(res => res.json())
                        .then(data => applyThemeSettings(data));
                    });
                };
            });

    } else if (title === '📝 Admin Notes / Logs') {
    document.getElementById('popupTitle').innerText = '📝 Admin Notes / Logs';

    content = `
        <form id="adminNotesForm">
            <label for="admin_notes">System Notes:</label><br>
            <textarea name="admin_notes" rows="6" style="width: 100%;"></textarea><br><br>
        </form>

        <div style="text-align: right;">
                <button type="submit" id="adminNotesForm" style="padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">Save</button>
        </div>
        <div id="adminNotesFeedback" style="margin-top: 10px; font-size: 13px; color: green;"></div>
    `;

    fetch('get_admin_notes.php')
    .then(res => res.json())
    .then(data => {
        const form = document.getElementById('adminNotesForm');
        form.admin_notes.value = data.admin_notes || '';

        form.onsubmit = (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            fetch('save_admin_notes.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(msg => {
                document.getElementById('adminNotesFeedback').innerText = msg;
            });
        };
    });
    } else if (title === '🔄 Reset Options') {
    document.getElementById('popupTitle').innerText = '🔄 Reset Options';

    content = `
        <div style="padding: 10px;">
            <p>Choose an action:</p>
            <button onclick="resetFilters()" style="margin-bottom: 10px; padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 5px;">Reset Filters</button><br>
            <button onclick="clearPreferences()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 5px;">Clear Preferences</button>
            <div id="resetFeedback" style="margin-top: 15px; font-size: 13px; color: green;"></div>
        </div>
    `;
    } else {
        content = `<p>${description}</p>`;
    }

    document.getElementById('popupContent').innerHTML = content;
    document.getElementById('optionsPopup').style.display = 'block';

    if (title === 'upload_limits') {
    fetchSettingsForUploadLimits();
    }
}

function submitUploadSettings() {
    const form = document.getElementById('uploadSettingsForm');
    form.requestSubmit();
}

function closeOptionsPopup() {
    document.getElementById('optionsPopup').style.display = 'none';
    document.getElementById('popupBackground').style.display = 'none';
}

function fetchSettingsForUploadLimits() {
    fetch('get_upload_settings.php')
        .then(res => res.json())
        .then(data => {
            const form = document.getElementById('uploadSettingsForm');
            form.max_upload_files.value = data.max_upload_files;
            form.max_file_size_mb.value = data.max_file_size_mb;
            form.allowed_file_extensions.value = data.allowed_file_extensions;

            form.onsubmit = (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                fetch('save_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(msg => {
                    document.getElementById('uploadSettingsFeedback').innerText = msg;
                });
            };
        });
}

function fetchSettingsForRetention() {
    fetch('get_retention_settings.php')
        .then(res => res.json())
        .then(data => {
            const form = document.getElementById('retentionSettingsForm');
            form.retention_days.value = data.retention_days;
            form.enable_auto_delete.value = data.enable_auto_delete;

            form.onsubmit = (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                fetch('save_retention_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(msg => {
                    document.getElementById('retentionSettingsFeedback').innerText = msg;
                });
            };
        });
}

function applyThemeSettings(data) {
    document.body.classList.toggle('dark-mode', data.theme_mode === 'dark');

    document.body.style.fontSize = (
        data.font_size === 'small' ? '13px' :
        data.font_size === 'large' ? '17px' :
        '15px'
    );

    document.body.style.padding = (
        data.layout_spacing === 'compact' ? '8px' :
        data.layout_spacing === 'spacious' ? '24px' :
        '16px'
    );
}

function resetFilters() {
    document.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    document.getElementById('resetFeedback').innerText = 'Filters have been reset.';
}

function clearPreferences() {
    localStorage.clear();
    sessionStorage.clear();

    document.cookie.split(";").forEach(cookie => {
        const eqPos = cookie.indexOf("=");
        const name = eqPos > -1 ? cookie.substring(0, eqPos) : cookie;
        document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
    });

    document.getElementById('resetFeedback').innerText = 'Preferences and cookies cleared. Refreshing...';
    setTimeout(() => location.reload(), 1000);
}

function showVersionInfo() {
    alert("🔖 Version Information:\n\nVersion: MMHR Census v1.0.0\nLast Updated: April 29, 2025");
}

function showOptionsInfo() {
    alert("⚙️ Options:\n\nNo options are available yet. Future updates will add configurable settings.");
}

function confirmClearData(event) {
    event.preventDefault(); 

    if (confirm("⚠️ Are you sure you want to clear all data? This action cannot be undone.")) {
        window.location.href = 'display_summary.php?action=clear_data';
    }
}

window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('maintenance') === 'success') {
            const deleted = urlParams.get('deleted') || 0;
            alert(`✅ Maintenance Completed!\nAll tables optimized.\n🧹 ${deleted} old backup files deleted.`);
        }
    });

function printTable() {
    var printContents = document.getElementById("printable").innerHTML;
    var originalContents = document.body.innerHTML;

    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;

    reinitializeEventListeners();
}

function exportToExcel() {
    var spinner = document.getElementById('loadingSpinner');
    spinner.style.display = 'block'; 

    setTimeout(function () {
        var table = document.getElementById("summaryTable");

        if (!table) {
            console.log("Table not found!");
            spinner.style.display = 'none'; 
            return;
        }

        var ws = XLSX.utils.table_to_sheet(table);
        const range = XLSX.utils.decode_range(ws['!ref']);

        for (let R = range.s.r; R <= range.e.r; ++R) {
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cell_ref = XLSX.utils.encode_cell({ r: R, c: C });
                const cell = ws[cell_ref];
                if (!cell) continue;

                if (!cell.s) cell.s = {};
                cell.s.alignment = { horizontal: "center", vertical: "center" };

                if (R <= 2) {
                    cell.s.font = { bold: true };

                    if (R === 0) {
                        cell.s.fill = { fgColor: { rgb: "000000" } };
                        cell.s.font.color = { rgb: "FFFFFF" };
                    } else if (R === 1) {
                        if (C === 0 || (C >= 10 && C <= 11)) {
                            cell.s.fill = { fgColor: { rgb: "c7f9ff" } };
                        } else {
                            cell.s.fill = { fgColor: { rgb: "FFFF00" } };
                        }
                    } else if (R === 2) {
                        if (C >= 0 && C <= 6) {
                            cell.s.fill = { fgColor: { rgb: "008000" } };
                            cell.s.font.color = { rgb: "FFFFFF" };
                        } else if (C === 7) {
                            cell.s.fill = { fgColor: { rgb: "000000" } };
                            cell.s.font.color = { rgb: "FFFFFF" };
                        } else if (C === 8) {
                            cell.s.fill = { fgColor: { rgb: "c7f9ff" } };
                        } else if (C === 9 || C === 10) {
                            cell.s.fill = { fgColor: { rgb: "FFA500" } };
                        } else if (C === 11 || C === 12) {
                            cell.s.fill = { fgColor: { rgb: "0000FF" } };
                            cell.s.font.color = { rgb: "FFFFFF" };
                        }
                    }
                }
            }
        }

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "MMHR Summary");

        var wbout = XLSX.write(wb, {
            bookType: "xlsx",
            type: "binary",
            cellStyles: true
        });

        var blob = new Blob([s2ab(wbout)], { type: "application/octet-stream" });
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "MMHR_Summary.xlsx";
        link.click();

        spinner.style.display = 'none'; 
    }, 100); 
}

function s2ab(s) {
    var buf = new ArrayBuffer(s.length);
    var view = new Uint8Array(buf);
    for (var i = 0; i < s.length; i++) {
        view[i] = s.charCodeAt(i) & 0xff;
    }
    return buf;
}

function downloadBackup() {
    var spinner = document.getElementById('loadingSpinner');
    spinner.style.display = 'block';

    fetch('backup.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not OK');
            }
            return response.blob();
        })
        .then(blob => {
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = "MMHR_Backup.sql"; 
            link.click();
            spinner.style.display = 'none';
        })
        .catch(error => {
            console.error('There was a problem with the backup download:', error);
            spinner.style.display = 'none'; 
        });
}

</script>

</html>