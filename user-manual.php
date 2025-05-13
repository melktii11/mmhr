<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Manual</title>
  <link rel="stylesheet" href="css/manual.css">
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="navbar">
    <div class="nav-container">
      <div class="logo">BMCI User Manual</div>
      <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="display_summary.php">MMHR Table</a></li>
        <li><a href="census.php">MMHR Census</a></li>
        <li><a href="leading_causes.php">Leading Causes</a></li>
      </ul>
    </div>
  </header>

  <div class="wrapper">
    <!-- Sidebar TOC -->
    <nav class="sidebar">
      <h2>User Manual</h2>
      <ul class="toc">
        <li><a href="#login">Login</a></li>
        <li><a href="#upload">Excel Upload</a></li>
        <li><a href="#Excel-File">Uploaded Excel File</a></li>
        <li><a href="#census">MMHR Census</a></li>
        <li><a href="#Leading-Causes">Leading Causes</a></li>
      </ul>
    </nav>

    <!-- Main Content -->
     <main class="content">
  <section id="login">
    <h2>Login</h2>
    <img src="css/login.png" alt="Login Example" class="manual-img">
    <p>To log in, the user must enter their <b>Email and Password provided by the admin</b>. Click "Login" to continue.</p>
  </section>

  <section id="upload">
    <h2>Excel Upload / Dashboard</h2>
    <img src="css/dashboard.png" alt="Upload Example" class="manual-img">
    <p>After logging in, the user will be directed to the <b>Main Form or Dashboard</b>. Here, they can upload an Excel file, view the results, and see a summary of the data.</p>
  </section>

  <section id="Excel-File">
    <h2>Uploaded Excel File</h2>
    <img src="css/table1.png" alt="Table Example" class="manual-img">
    <p>Once an Excel file is uploaded and a month is selected, the system will automatically show the results in a table. 
      <b>Red-orange</b> columns (2–5) show data for the selected month, <b>blue</b> (column 7) shows admissions, and <b>yellow-orange</b> shows discharge billings.</p>
    <p>The sidebar has three options: <b>Print Table</b>, <b>View MMHR Census</b>, and <b>View Leading Causes</b>.</p>
  </section>

  <section id="census">
    <h2>MMHR Census</h2>
    <img src="css/table2.png" alt="Census Example" class="manual-img">
    <p>The user can choose to <b>fill in all the blanks</b> before printing. A new Excel file must be selected for the month's census. 
      <b>Yellow</b> highlights the daily census of NHIP patients (first table), and <b>red</b> shows monthly admissions and discharges.</p><br>

    <img src="css/table2.1.png" alt="Summary Example" class="manual-img">
    <p><b>Quality Assurance Indicator</b>: This is part of the MMHR Census. It shows the total <b>summary of admissions and discharges</b> for the month.
      The system automatically calculates the totals. The user just needs to select the month.</p>
  </section>

  <section id="Leading-Causes">
    <h2>Leading Causes</h2>
    <img src="css/table3.png" alt="Leading Causes Example" class="manual-img">
    <p>Just like the other sections, the user must select an Excel file and month to generate the <b>Leading Causes</b> report for that month.</p>
  </section>

  <br><br>
  <p><b>That's all for the User Manual! We hope this system really helps your department! (｡♡‿♡｡)</b></p>
</main>
  </div>

</body>
</html>
