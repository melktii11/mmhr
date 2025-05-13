<?php
include 'config.php';

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


$files_query = "SELECT id, file_name FROM uploaded_files ORDER BY upload_date DESC";
$files_result = $conn->query($files_query);
$files = [];
while ($row = $files_result->fetch_assoc()) {
    $files[] = $row;
}
$selected_file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;

$sheets = [];
if ($selected_file_id) {
    $sheets_query = "SELECT DISTINCT sheet_name FROM patient_records WHERE file_id = $selected_file_id";
    $sheets_result = $conn->query($sheets_query);
    while ($row = $sheets_result->fetch_assoc()) {
        $sheets[] = $row['sheet_name'];
    }

    $sheets_query_2 = "SELECT DISTINCT sheet_name_2 FROM patient_records_2 WHERE file_id = $selected_file_id";
    $sheets_result_2 = $conn->query($sheets_query_2);
    $sheets_2 = [];
    while ($row = $sheets_result_2->fetch_assoc()) {
        $sheets_2[] = $row['sheet_name_2'];
    }

    $sheets_query_3 = "SELECT DISTINCT sheet_name_3 FROM patient_records_3 WHERE file_id = $selected_file_id";
    $sheets_result_3 = $conn->query($sheets_query_3);
    $sheets_3 = [];
    while ($row = $sheets_result_3->fetch_assoc()) {
        $sheets_3[] = $row['sheet_name_3'];
    }
}

$selected_sheet_1 = isset($_GET['sheet_1']) ? $_GET['sheet_1'] : '';
$selected_sheet_2 = isset($_GET['sheet_2']) ? $_GET['sheet_2'] : '';
$selected_sheet_3 = isset($_GET['sheet_3']) ? $_GET['sheet_3'] : '';

$all_patient_data = [];

$all_sheets_query = "SELECT admission_date, discharge_date, member_category, sheet_name 
                     FROM patient_records 
                     WHERE file_id = $selected_file_id";
$all_sheets_result = $conn->query($all_sheets_query);

$summary = array_fill(1, 31, [
    'govt' => 0, 'private' => 0, 'self_employed' => 0, 'ofw' => 0,
    'owwa' => 0, 'sc' => 0, 'pwd' => 0, 'indigent' => 0, 'pensioners' => 0,
    'nhip' => 0, 'non_nhip' => 0, 'total_admissions' => 0, 'total_discharges_nhip' => 0,
    'total_discharges_non_nhip' => 0,'lohs_nhip' => 0, 'lohs_non_nhip' => 0
]);

    #column 1-5
    while ($row = $all_sheets_result->fetch_assoc()) {
        $admit = DateTime::createFromFormat('Y-m-d', trim($row['admission_date']))->setTime(0, 0, 0);
        $discharge = DateTime::createFromFormat('Y-m-d', trim($row['discharge_date']))->setTime(0, 0, 0);
        $category = trim(strtolower($row['member_category']));
    
        $selected_year = 2025;
        $month_numbers = [
            'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4, 'MAY' => 5, 'JUNE' => 6,
            'JULY' => 7, 'AUGUST' => 8, 'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12
        ];
    
        $selected_month_name = strtoupper($selected_sheet_1);
        if (!isset($month_numbers[$selected_month_name])) {
            continue;
        }
        $selected_month = $month_numbers[$selected_month_name];
    
        $first_day_of_month = new DateTime("$selected_year-$selected_month-01");
        $last_day_of_month = new DateTime("$selected_year-$selected_month-" . cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year));
    
        if ($admit == $discharge) {
            continue;
        }
    
        // If the patient has days in this selected month
        if ($discharge >= $first_day_of_month && $admit <= $last_day_of_month) {
            $startDay = max($first_day_of_month, $admit)->format('d');
            $endDay = min($last_day_of_month, (clone $discharge)->modify('-1 day'))->format('d');
    
            for ($day = (int)$startDay; $day <= (int)$endDay; $day++) {
                if (!isset($summary[$day])) {
                    $summary[$day] = [
                        'govt' => 0, 'private' => 0, 'self_employed' => 0, 'ofw' => 0,
                        'owwa' => 0, 'sc' => 0, 'pwd' => 0, 'indigent' => 0, 'pensioners' => 0
                    ];
                }
    
                // Categorizing patients
                if (stripos($category, 'formal-government') !== false || stripos($category, 'sponsored- local govt unit') !== false) {
                    $summary[$day]['govt'] += 1;
                } elseif (stripos($category, 'formal-private') !== false) {
                    $summary[$day]['private'] += 1;
                } elseif (stripos($category, 'self earning individual') !== false || stripos($category, 'indirect contributor') !== false
                    || stripos($category, 'informal economy- informal sector') !== false) {
                    $summary[$day]['self_employed'] += 1;
                } elseif (stripos($category, 'migrant worker') !== false) {
                    $summary[$day]['ofw'] += 1;
                } elseif (stripos($category, 'direct contributor') !== false) {
                    $summary[$day]['owwa'] += 1;
                } elseif (stripos($category, 'senior citizen') !== false) {
                    $summary[$day]['sc'] += 1;
                } elseif (stripos($category, 'pwd') !== false) {
                    $summary[$day]['pwd'] += 1;
                } elseif (stripos($category, 'indigent') !== false || stripos($category, 'sponsored- pos financially incapable') !== false
                    || stripos($category, '4ps/mcct') !== false) {
                    $summary[$day]['indigent'] += 1;
                } elseif (stripos($category, 'lifetime member') !== false) {
                    $summary[$day]['pensioners'] += 1;
                }
            }
        }
    }

    #nhip column
    foreach ($summary as $day => $row) {
        $summary[$day]['nhip'] = 
            $row['govt'] + $row['private'] + $row['self_employed'] + 
            $row['ofw'] + $row['owwa'] + $row['sc'] + 
            $row['pwd'] + $row['indigent'] + $row['pensioners'];
    }    

    #column 9 non-nhip
    foreach ($summary as $day => $row) {
        $summary[$day]['lohs_nhip'] = 
            $row['govt'] + $row['private'] + $row['self_employed'] + 
            $row['ofw'] + $row['owwa'] + $row['sc'] + 
            $row['pwd'] + $row['indigent'] + $row['pensioners'];
    }  

    #non-nhip column
    $non_nhip_query = "SELECT date_admitted, date_discharge, category, sheet_name_3 
                   FROM patient_records_3 
                   WHERE sheet_name_3 = '$selected_sheet_3' AND file_id = $selected_file_id";
    $non_nhip_result = $conn->query($non_nhip_query);

    while ($row = $non_nhip_result->fetch_assoc()) {
        $admit = new DateTime($row['date_admitted']);
        $discharge = new DateTime($row['date_discharge']);
        $category = strtolower($row['category']);

        if (!(stripos($category, 'n/a') !== false)) {
            continue;
        }

        if ($admit->format('Y-m-d') === $discharge->format('Y-m-d')) {
            continue;
        }

        if ((int) $discharge->format('d') === 1) {
            continue;
        }

        $selected_month_name = date('F', mktime(0, 0, 0, $selected_month, 1, $selected_year));

        $monthStart = new DateTime("first day of $selected_month_name $selected_year");
        $monthEnd = new DateTime("last day of $selected_month_name $selected_year");

        $startDay = max(1, (int) $admit->format('d'));
        if ($admit < $monthStart) {
            $startDay = 1;
        }

        $endDay = min((int) $discharge->format('d') - 1, (int) $monthEnd->format('d'));

        if ($startDay <= $endDay) {
            for ($day = $startDay; $day <= $endDay; $day++) {
                $summary[$day]['non_nhip'] += 1;
            }
        }
    }

    #total admission column
    $admission_query = "SELECT admission_date_2 FROM patient_records_2 
                    WHERE sheet_name_2 = '$selected_sheet_2' AND file_id = $selected_file_id";
    $admission_result = $conn->query($admission_query);

    while ($row = $admission_result->fetch_assoc()) {
        $admit_day = (int)date('d', strtotime($row['admission_date_2']));

        if ($admit_day >= 1 && $admit_day <= 31) {
            $summary[$admit_day]['total_admissions'] += 1;
        }
    }

    $discharge_query = "SELECT date_discharge, category FROM patient_records_3 
                    WHERE sheet_name_3 = '$selected_sheet_3' AND file_id = $selected_file_id";
    $discharge_result = $conn->query($discharge_query);

    while ($row = $discharge_result->fetch_assoc()) {
        $discharge_day = (int)date('d', strtotime($row['date_discharge'])); 
        $category = strtolower($row['category']);

        if ($discharge_day >= 1 && $discharge_day <= 31) {
            if (!isset($summary[$discharge_day])) {
                $summary[$discharge_day] = [
                    'total_discharges_non_nhip' => 0,
                    'total_discharges_nhip' => 0
                ];
            }
            if (strpos($category, 'n/a') !== false || strpos($category, 'non phic') !== false || strpos($category, '#n/a') !== false) {
                $summary[$discharge_day]['total_discharges_non_nhip'] += 1;
            } else {
                $summary[$discharge_day]['total_discharges_nhip'] += 1;
            }
        }
    }
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMHR Census</title>
    <link rel="stylesheet" href="css/census.css">
</head>
<body class="container">
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
            <a href="user-manual.php" class="btrdy">User Manual</a>
            <a href="#" class="logout-link" onclick="confirmLogout(event)">
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

<aside>
    <div class="sidebar" id="sidebar">
        <h3>Menu</h3>
        <button class="btn btn-success no-print" onclick="window.print()">Print Table</button>
        <form action="display_summary.php" method="GET">
            <button type="submit" class="btn btn-primary btn-2">View MMHR Table</button>
        </form>
        <form action="leading_causes.php" method="GET">
            <button type="submit" class="btn btn-primary btn-3">View Leading Causes</button>
        </form>
    </div>
</aside>

<div class="main-content" id="main-content">
    <div class="print-area">
        <div class="header-text">
            <div class="container">
                <p>REPUBLIC OF THE PHILIPPINES</p>
                <p>PHILIPPINE HEALTH INSURANCE CORPORATION</p>
                <p>MANDATORY MONTHLY HOSPITAL REPORT</p>
                <p>12/F City State Centre, 709 Shaw Blvd., Brgy. Oranbo, Pasig City</p>
                <p>For the Month of JANUARY 2025</p>
            </div>
        </div>

    <form class="form1">
        <div class="row">
            <!-- LEFT SIDE -->
            <div class="col-md-6 text-start">
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Accreditation No. :</label>
                    <div class="col-sm-7">
                        <input type="text" name="accreditation_no">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Name of Hospital :</label>
                    <div class="col-sm-7">
                        <input type="text" name="hospital_name">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Address No./Street :</label>
                    <div class="col-sm-7">
                        <input type="text" name="address">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Municipality :</label>
                    <div class="col-sm-7">
                        <input class="blank" type="text" name="municipality">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Province :</label>
                    <div class="col-sm-7">
                        <input type="text" name="province">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Zip Code :</label>
                    <div class="col-sm-7">
                        <input type="text" name="zip_code">
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-6">
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Region :</label>
                    <div class="col-sm-7">
                        <input type="text" name="region">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">Category :</label>
                    <div class="col-sm-7">
                        <input type="text" name="category">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">PHIC Accredited Beds :</label>
                    <div class="col-sm-7">
                        <input type="text" name="phic_beds">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-5 col-form-label">DOH Authorized Beds :</label>
                    <div class="col-sm-7">
                        <input type="text" name="doh_beds">
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form method="GET" id="filterForm">
        <div class="sige">
            <label for="file_id">Select File:</label>
            <select class="pass" name="file_id" id="file_id" onchange="document.getElementById('filterForm').submit()">
                <option value="">-- Choose File --</option>
                <?php foreach ($files as $file): ?>
                    <option value="<?= $file['id'] ?>" <?= $selected_file_id == $file['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($file['file_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($selected_file_id): ?>
                <select class="col" name="sheet_1" onchange="document.getElementById('filterForm').submit()">
                    <option value="" disabled selected>Select Month</option>
                    <?php foreach ($sheets as $sheet) { ?>
                        <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selected_sheet_1 ? 'selected' : ''; ?>>
                            <?php echo $sheet; ?>
                        </option>
                    <?php } ?>
                </select>

                <select class="col" name="sheet_2" onchange="document.getElementById('filterForm').submit()">
                    <option value="" disabled selected>Select Admission Sheet</option>
                    <?php foreach ($sheets_2 as $sheet) { ?>
                        <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selected_sheet_2 ? 'selected' : ''; ?>>
                            <?php echo $sheet; ?>
                        </option>
                    <?php } ?>
                </select>

                <select class="col" name="sheet_3" onchange="document.getElementById('filterForm').submit()">
                    <option value="" disabled selected>Select Discharge Sheet</option>
                    <?php foreach ($sheets_3 as $sheet): ?>
                        <option value="<?= $sheet ?>" <?= $sheet == $selected_sheet_3 ? 'selected' : '' ?>>
                            <?= $sheet ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-container row">
          <!-- First Table -->
          <div class="col-md-6">
            <p class="table-title">A. DAILY CENSUS OF NHIP PATIENTS</p>
            <p class="subtitle">(EVERY 12:00MN.)</p>

            <center><table class="custom-table">
              <thead>
                <tr>
                  <th rowspan="2">DATE</th>
                  <th colspan="3">CENSUS</th>
                </tr>
                <tr>
                  <th>NHIP</th>
                  <th>NON-NHIP</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  $totals = ['nhip' => 0, 'non_nhip' => 0, 'total' => 0];
                  for ($i = 1; $i <= 31; $i++) { 
                      $nhip = $summary[$i]['nhip'] ?? 0;
                      $non_nhip = $summary[$i]['non_nhip'] ?? 0;
                      $total = $nhip + $non_nhip;
                      $totals['nhip']  += $nhip;
                      $totals['non_nhip'] += $non_nhip;
                      $totals['total'] += $total;
                  ?>
                      <tr>
                          <td><?php echo $i; ?></td>
                          <td><?php echo $nhip; ?></td>
                          <td><?php echo $non_nhip; ?></td>
                          <td><?php echo $total; ?></td>
                      </tr>
                  <?php } ?>
                  <tr class="footer-row">
                      <td colspan="4">*** NOTHING FOLLOWS ***</td>
                  </tr>
                  <tr class="footer-total">
                      <td>Total</td>
                      <td><?php echo $totals['nhip']; ?></td>
                      <td><?php echo $totals['non_nhip']; ?></td>
                      <td><?php echo $totals['total']; ?></td>
                  </tr>
              </tbody>
            </table></center>
          </div>
                
          <!-- Second Table -->
          <div class="col-md-6">
            <p class="table-title">CENSUS FOR THE DAY = PREVIOUS + ADMISSION - DISCHARGES</p>
                <br>
            <center><table class="custom-table">
              <thead>
                <tr>
                  <th rowspan="2">DATE</th>
                  <th colspan="3">DISCHARGES</th>
                </tr>
                <tr>
                  <th>NHIP</th>
                  <th>NON-NHIP</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $totals_discharge = ['nhip' => 0, 'non_nhip' => 0, 'total' => 0];
                for ($i = 1; $i <= 31; $i++) { 
                    $nhip = $summary[$i]['total_discharges_nhip'] ?? 0;
                    $non_nhip = $summary[$i]['total_discharges_non_nhip'] ?? 0;
                    $total = $nhip + $non_nhip;
                    $totals_discharge['nhip'] += $nhip;
                    $totals_discharge['non_nhip'] += $non_nhip;
                    $totals_discharge['total'] += $total;
                ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td><?php echo $nhip; ?></td>
                        <td><?php echo $non_nhip; ?></td>
                        <td><?php echo $total; ?></td>
                    </tr>
                <?php } ?>
                <tr class="footer-row">
                    <td colspan="4">*** NOTHING FOLLOWS ***</td>
                </tr>
                <tr class="footer-total">
                    <td>Total</td>
                    <td><?php echo $totals_discharge['nhip']; ?></td>
                    <td><?php echo $totals_discharge['non_nhip']; ?></td>
                    <td><?php echo $totals_discharge['total']; ?></td>
                </tr>
              </tbody>
            </table></center>
          </div>
    </div>

    <div class="col-12 mt-5">
        <div class="indicator-section">
          <h4><strong>B. QUALITY ASSURANCE INDICATOR</strong></h4>
        <?php 
            $days_in_month_map = [
                'JANUARY' => 31, 'FEBRUARY' => 28, 'MARCH' => 31,
                'APRIL' => 30, 'MAY' => 31, 'JUNE' => 30,
                'JULY' => 31, 'AUGUST' => 31, 'SEPTEMBER' => 30,
                'OCTOBER' => 31, 'NOVEMBER' => 30, 'DECEMBER' => 31
            ];
            $month_upper = strtoupper($selected_sheet_1 ?? '');
            $days_in_month = $days_in_month_map[$month_upper] ?? 30; 
            $days_in_thousand = $days_in_month * 100;
            $total_all = $totals['total'];
            $total_nhip = $totals['nhip'];
            $mbor = $days_in_thousand > 0 ? round(($total_all / $days_in_thousand) * 100, 2) : 0;
            $mnhibor = $days_in_thousand > 0 ? round(($total_nhip / $days_in_thousand) * 100, 2) : 0;
        ?>
    <p><b>1. Monthly Bed Occupancy Rate (MBOR) = <u><?= number_format($mbor, 2); ?>%</u></b></p>
      <div class="formula">
        <div>Total of NHIP + NON-NHIP: <?= $totals['total']; ?></div>
        <div>MBOR = (Total / (Days x 100)) x 100</div>
        <div><?= $days_in_month * 100; ?></div>
        <div>Number of days per Month indicated X Number of DOH Authorized Beds</div><br><br>
      </div>
        
      <p><b>2. Monthly NHIP Beneficiary Occupancy Rate (MNHIBOR) = <u><?= number_format($mnhibor, 2); ?>%</u></b></p>
      <div class="formula">
        <div>NHIP Total: <?= $totals['nhip']; ?></div>
        <div>MNHIBOR = (NHIP / (Days x 100)) x 100</div>
        <div><?= $days_in_month * 100; ?></div>
        <div>Number of days per Month indicated X Number of PHIC Accredited Beds</div>
      </div>
        
<?php
  if ($totals_discharge['nhip'] > 0) {
    $aslp = $totals['nhip'] / $totals_discharge['nhip'];
  } else {
    $aslp = 0;
  }
?>
      <p><b>3. Average Length of Stay per NHIP Patient (ASLP) = <u><?= $totals_discharge['nhip'] > 0 ? number_format($aslp, 2) : 'N/A'; ?>%</u></b></p>
      <div class="formula">
        <div>NHIP Total: <?= $totals['nhip']; ?></div>
        <div>ASLP = (NHIP Total / NHIP Discharges)</div>
        <div><?= $totals_discharge['nhip']; ?></div>
        <div>Total No. of NHIP Discharges</div>
      </div>
    </div>
  </div>     
</div>

</body>

<div class="fixed-footer">
    <small>
        <span class="copyright-symbol">©</span>
        <span class="full-text"> Bicutan Medical Center Inc. All rights reserved.</span>
    </small>
</div>

<script>
function confirmLogout(event) {
    event.preventDefault(); // Prevent default link behavior
    if (confirm("⚠️ Are you sure you want to logout?")) {
        window.location.href = "logout.php"; // Proceed with logout
    }
}

function toggleDropdown(event) {
    event.preventDefault();
    var dropdown = event.currentTarget.nextElementSibling;
    dropdown.classList.toggle("show");

    var otherDropdowns = document.querySelectorAll(".dropdown-content");
    otherDropdowns.forEach(function (otherDropdown) {
        if (otherDropdown !== dropdown) {
            otherDropdown.classList.remove("show");
        }
    });

    var submenu = dropdown.querySelector(".submenu-content");
    if (submenu) {
        submenu.classList.remove("show");
    }
}

function toggleSubmenu(event) {
    event.preventDefault();
    event.stopPropagation(); // Prevents the main dropdown from closing

    var submenu = event.currentTarget.nextElementSibling;
    submenu.classList.toggle("show");

    // Optionally close other submenus
    var otherSubmenus = document.querySelectorAll(".submenu-content");
    otherSubmenus.forEach(function (other) {
        if (other !== submenu) {
            other.classList.remove("show");
        }
    });
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(function(dropdown) {
            dropdown.classList.remove('show');
        });
        document.querySelectorAll('.submenu-content').forEach(function(submenu) {
            submenu.classList.remove('show');
        });
    }
});

</script>

<script>
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
