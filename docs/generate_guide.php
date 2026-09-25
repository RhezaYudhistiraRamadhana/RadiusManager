<?php
/**
 * RadiusManager — User & Administrator Operational Guide Generator
 * Generates an executive-grade, printable, standalone HTML & PDF Manual.
 */

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RadiusManager — Modul Panduan & Operasional Sistem (User & Administrator Guide)</title>
<style>
  /* ── CSS Reset & Base Print Styles ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  
  @page {
    size: A4 portrait;
    margin: 16mm 14mm 16mm 14mm;
  }
  
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.55;
    color: #1e293b;
    background-color: #ffffff;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .page {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
  }

  .page-break {
    page-break-before: always;
    break-before: page;
  }

  .no-break {
    page-break-inside: avoid;
    break-inside: avoid;
  }

  /* ── Typography & Headings ── */
  h1, h2, h3, h4, h5 {
    color: #0f172a;
    font-weight: 700;
    line-height: 1.25;
  }

  h1 { font-size: 20pt; margin-bottom: 12px; }
  h2 { font-size: 14pt; margin-top: 18px; margin-bottom: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
  h3 { font-size: 11.5pt; margin-top: 14px; margin-bottom: 6px; color: #1e40af; }
  h4 { font-size: 10.5pt; margin-top: 10px; margin-bottom: 4px; color: #334155; }
  
  p { margin-bottom: 8px; }
  p:last-child { margin-bottom: 0; }
  
  strong { color: #0f172a; font-weight: 600; }
  code, pre { font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 8.5pt; }
  
  code {
    background-color: #f1f5f9;
    color: #0369a1;
    padding: 2px 5px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
  }
  
  pre {
    background-color: #0f172a;
    color: #f8fafc;
    padding: 10px 14px;
    border-radius: 6px;
    margin: 8px 0 12px 0;
    overflow-x: auto;
    line-height: 1.4;
  }
  
  pre code {
    background: transparent;
    color: inherit;
    padding: 0;
    border: none;
  }

  /* ── Document Header & Footer on Pages ── */
  .doc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 5px;
    margin-bottom: 16px;
    font-size: 8pt;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .doc-footer {
    border-top: 1px solid #e2e8f0;
    margin-top: 24px;
    padding-top: 6px;
    font-size: 8pt;
    color: #94a3b8;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  /* ── Cover Page Design ── */
  .cover-page {
    min-height: 250mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20mm 10mm 15mm 10mm;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 50%, #eff6ff 100%);
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
  }

  .cover-page::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #38bdf8, #2563eb, #1e40af);
  }

  .cover-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 25mm;
  }

  .cover-logo {
    width: 68px;
    height: 68px;
  }

  .cover-brand {
    font-size: 22pt;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
  }

  .cover-brand span {
    color: #2563eb;
  }

  .cover-tagline {
    font-size: 9.5pt;
    color: #64748b;
    font-weight: 500;
  }

  .cover-body {
    margin-bottom: 30mm;
  }

  .cover-pill {
    display: inline-block;
    background-color: #dbeafe;
    color: #1e40af;
    font-size: 8.5pt;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 16px;
    border: 1px solid #bfdbfe;
  }

  .cover-title {
    font-size: 26pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    margin-bottom: 14px;
    letter-spacing: -0.5px;
  }

  .cover-subtitle {
    font-size: 13pt;
    color: #475569;
    line-height: 1.45;
    font-weight: 400;
    max-width: 90%;
    margin-bottom: 24px;
  }

  .cover-specs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    max-width: 85%;
    margin-top: 10px;
  }

  .spec-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }

  .spec-label {
    font-size: 7.5pt;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 600;
    letter-spacing: 0.5px;
  }

  .spec-value {
    font-size: 9.5pt;
    font-weight: 700;
    color: #0f172a;
    margin-top: 2px;
  }

  .cover-footer {
    border-top: 1px solid #cbd5e1;
    padding-top: 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 8.5pt;
    color: #64748b;
  }

  .cover-meta strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
  }

  /* ── Table of Contents ── */
  .toc-container {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    margin: 14px 0 20px 0;
  }

  .toc-title {
    font-size: 13pt;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    border-bottom: 2px solid #cbd5e1;
    padding-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .toc-list {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 24px;
  }

  .toc-item {
    font-size: 9pt;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px dotted #cbd5e1;
    padding-bottom: 2px;
  }

  .toc-link {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
  }

  .toc-badge {
    color: #64748b;
    font-weight: 600;
    font-size: 8pt;
  }

  /* ── UI Elements, Cards, Tables, Badges ── */
  .card-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 14px;
    margin: 10px 0;
  }

  .table-custom {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0 14px 0;
    font-size: 8.5pt;
  }

  .table-custom th, .table-custom td {
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    text-align: left;
    vertical-align: top;
  }

  .table-custom th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 7.5pt;
    letter-spacing: 0.5px;
  }

  .table-custom tr:nth-child(even) td {
    background-color: #f8fafc;
  }

  /* Badges */
  .badge {
    display: inline-block;
    padding: 2px 7px;
    font-size: 7.5pt;
    font-weight: 600;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
  }

  .badge-primary { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
  .badge-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
  .badge-warning { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
  .badge-danger  { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
  .badge-purple  { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }
  .badge-slate   { background-color: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }

  /* Callout boxes */
  .callout {
    padding: 10px 14px;
    border-radius: 6px;
    margin: 10px 0;
    font-size: 9pt;
    line-height: 1.45;
  }

  .callout-info {
    background-color: #eff6ff;
    border-left: 4px solid #2563eb;
    color: #1e40af;
  }

  .callout-success {
    background-color: #f0fdf4;
    border-left: 4px solid #16a34a;
    color: #166534;
  }

  .callout-warning {
    background-color: #fffbeb;
    border-left: 4px solid #d97706;
    color: #92400e;
  }

  .callout-danger {
    background-color: #fef2f2;
    border-left: 4px solid #dc2626;
    color: #991b1b;
  }

  .callout strong {
    display: block;
    margin-bottom: 3px;
  }

  /* Step by Step List */
  .step-list {
    list-style: none;
    counter-reset: step-counter;
    margin: 10px 0;
  }

  .step-list li {
    counter-increment: step-counter;
    position: relative;
    padding-left: 32px;
    margin-bottom: 10px;
    font-size: 9pt;
  }

  .step-list li::before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 22px;
    height: 22px;
    background-color: #2563eb;
    color: #ffffff;
    font-size: 8pt;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Grid 2 Column */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 10px 0;
  }

  .grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 10px 0;
  }

  .kpi-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    border-top: 3px solid #2563eb;
  }

  .kpi-mini-card.success { border-top-color: #16a34a; }
  .kpi-mini-card.warning { border-top-color: #d97706; }
  .kpi-mini-card.danger  { border-top-color: #dc2626; }

  .kpi-mini-title {
    font-size: 7.5pt;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }

  .kpi-mini-val {
    font-size: 13pt;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
  }

  .kpi-mini-desc {
    font-size: 7.5pt;
    color: #64748b;
  }

  /* SVG Diagrams */
  .diagram-wrapper {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 12px;
    margin: 12px 0;
    text-align: center;
  }

  .diagram-caption {
    font-size: 8pt;
    color: #64748b;
    font-style: italic;
    margin-top: 6px;
  }
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 1: COVER PAGE
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page cover-page">
  <div class="cover-header">
    <!-- SVG Vector Brand Logo -->
    <svg class="cover-logo" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="64" height="64" rx="16" fill="#0f172a"/>
      <rect x="1" y="1" width="62" height="62" rx="15" stroke="rgba(255,255,255,0.2)" stroke-width="1.5"/>
      <path d="M 14 20 A 22 22 0 0 1 50 20" stroke="#38bdf8" stroke-width="3" stroke-linecap="round" fill="none"/>
      <path d="M 20 25 A 15 15 0 0 1 44 25" stroke="#ffffff" stroke-width="2.6" stroke-linecap="round" fill="none"/>
      <circle cx="32" cy="32" r="3" fill="#38bdf8"/>
      <rect x="14" y="37" width="36" height="13" rx="4" fill="#ffffff"/>
      <rect x="16.5" y="39.5" width="31" height="8" rx="2" fill="#0f172a"/>
      <circle cx="21" cy="43.5" r="1.5" fill="#10b981"/>
      <circle cx="26" cy="43.5" r="1.5" fill="#38bdf8"/>
      <circle cx="31" cy="43.5" r="1.5" fill="#38bdf8"/>
      <rect x="38" y="42" width="4" height="3" rx="0.5" fill="#64748b"/>
      <rect x="43" y="42" width="4" height="3" rx="0.5" fill="#64748b"/>
    </svg>
    <div>
      <div class="cover-brand">Radius<span>Manager</span></div>
      <div class="cover-tagline">High-Performance FreeRADIUS 3.x Web Management Suite</div>
    </div>
  </div>

  <div class="cover-body">
    <div class="cover-pill">Dokumentasi Resmi & Modul Operasional</div>
    <div class="cover-title">MODUL PANDUAN PENGGUNAAN SISTEM</div>
    <div class="cover-subtitle">
      Panduan Lengkap Pengoperasian Panel Web RadiusManager untuk Administrator Jaringan, Staf Helpdesk IT, dan Pengguna Hotspot Kampus.
    </div>

    <div class="cover-specs">
      <div class="spec-item">
        <div class="spec-label">Versi Aplikasi</div>
        <div class="spec-value">RadiusManager v1.8.0 Enterprise</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Mesin RADIUS & Basis Data</div>
        <div class="spec-value">FreeRADIUS 3.x + MariaDB / MySQL</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Dukungan Perangkat Keras</div>
        <div class="spec-value">Ruijie Networks, MikroTik, Cisco, Ubiquiti</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Target Pengguna</div>
        <div class="spec-value">Admin Jaringan, Operator, Siswa/Pegawai</div>
      </div>
    </div>
  </div>

  <div class="cover-footer">
    <div class="cover-meta">
      <strong>RadiusManager Software Engineering Team</strong>
      <span>Infrastruktur Jaringan & Server Autentikasi Kampus</span>
    </div>
    <div style="text-align:right;">
      <strong>Edisi Revisi: September 2026</strong>
      <span>Status: Dokumen Operasional Standar (SOP)</span>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 2: DAFTAR ISI & STRUKTUR MODUL
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Operasional</span>
    <span>Daftar Isi & Ringkasan Modul</span>
  </div>

  <h1>Daftar Isi Modul Pelatihan</h1>
  <p>
    Modul ini disusun secara terstruktur agar siapapun—mulai dari administrator jaringan senior, teknisi IT lapangan, operator voucher, hingga staf helpdesk—dapat memahami dan mengoperasikan setiap fungsi RadiusManager secara mandiri.
  </p>

  <div class="toc-container">
    <div class="toc-title">
      <span>Struktur Modul Pelatihan (15 Modul)</span>
      <span style="font-size: 8pt; font-weight: normal; color: #64748b;">Klik atau tuju nomor modul</span>
    </div>
    <ul class="toc-list">
      <li class="toc-item"><a class="toc-link" href="#modul-1">Modul 1: Pengenalan & Arsitektur Sistem</a> <span class="toc-badge">Hal. 3</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-2">Modul 2: Akses Sistem & Autentikasi</a> <span class="toc-badge">Hal. 4</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-3">Modul 3: Navigasi Dashboard & Analisis</a> <span class="toc-badge">Hal. 5</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-4">Modul 4: Manajemen Pengguna RADIUS</a> <span class="toc-badge">Hal. 6</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-5">Modul 5: Grup & Paket Kecepatan Bandwidth</a> <span class="toc-badge">Hal. 8</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-6">Modul 6: Perangkat NAS & Access Point</a> <span class="toc-badge">Hal. 9</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-7">Modul 7: Hotspot & Generator Voucher</a> <span class="toc-badge">Hal. 10</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-8">Modul 8: Peringatan Masa Aktif & Kadaluarsa</a> <span class="toc-badge">Hal. 11</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-9">Modul 9: Sesi Aktif & Pemutusan Koneksi (CoA)</a> <span class="toc-badge">Hal. 12</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-10">Modul 10: Riwayat Akuntansi & Laporan</a> <span class="toc-badge">Hal. 13</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-11">Modul 11: Audit Keamanan & Log Autentikasi</a> <span class="toc-badge">Hal. 14</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-12">Modul 12: Hak Akses Operator Berjenjang (RBAC)</a> <span class="toc-badge">Hal. 15</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-13">Modul 13: Portal Mandiri Pengguna (Subscriber)</a> <span class="toc-badge">Hal. 16</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-14">Modul 14: Panduan Integrasi RESTful API</a> <span class="toc-badge">Hal. 17</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-15">Modul 15: Pemeliharaan, Troubleshooting & FAQ</a> <span class="toc-badge">Hal. 18</span></li>
    </ul>
  </div>

  <h2>Konvensi Visual dalam Modul</h2>
  <div class="grid-2">
    <div class="callout callout-info">
      <strong>Catatan Penting (Note / Info)</strong>
      Menjelaskan latar belakang teknis, keterkaitan protokol RADIUS, dan tips efisiensi kerja.
    </div>
    <div class="callout callout-warning">
      <strong>Perhatian (Warning / Caution)</strong>
      Menyoroti tindakan yang berdampak besar terhadap koneksi pengguna aktif atau keamanan kredensial.
    </div>
  </div>

  <div class="card-box" style="margin-top: 14px;">
    <strong>Spesifikasi Lingkungan Operasional (Prerequisites):</strong>
    <table class="table-custom" style="margin-top: 8px;">
      <tr>
        <th style="width:25%;">Komponen</th>
        <th style="width:35%;">Spesifikasi Rekomendasi</th>
        <th style="width:40%;">Fungsi Utama</th>
      </tr>
      <tr>
        <td><strong>Web Server & PHP</strong></td>
        <td>Apache 2.4+ / Nginx & PHP 8.1 / 8.2 (ext: pdo_mysql, mbstring)</td>
        <td>Menjalankan antarmuka grafis RadiusManager, Portal, dan REST API.</td>
      </tr>
      <tr>
        <td><strong>Database Engine</strong></td>
        <td>MariaDB 10.4+ / MySQL 8.0+ (InnoDB, Collation utf8mb4)</td>
        <td>Menyimpan tabel autentikasi, akuntansi jutaan baris, dan jejak audit.</td>
      </tr>
      <tr>
        <td><strong>RADIUS Daemon</strong></td>
        <td>FreeRADIUS 3.0.x / 3.2.x dengan modul `rlm_sql` aktif</td>
        <td>Memproses paket Access-Request, Access-Accept, dan Accounting UDP.</td>
      </tr>
      <tr>
        <td><strong>Access Controller</strong></td>
        <td>Ruijie AC (172.16.0.70), MikroTik RouterOS, atau Cisco WLC</td>
        <td>Mengalihkan autentikasi captive portal/WPA2-Enterprise ke server RADIUS.</td>
      </tr>
    </table>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 3: MODUL 1 — PENGENALAN & ARSITEKTUR SISTEM
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-1">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 1: Pengenalan & Arsitektur</span>
  </div>

  <h1>Modul 1: Pengenalan & Arsitektur Sistem</h1>
  
  <h2>1.1 Latar Belakang & Tujuan RadiusManager</h2>
  <p>
    <strong>RadiusManager</strong> dirancang sebagai platform administrasi modern berbasis web yang menggantikan perangkat lunak lama (seperti daloRADIUS). Aplikasi lama kerap mengalami penurunan performa drastis (lambat atau *timeout*) ketika menangani basis data produksi kampus yang memiliki jutaan baris log akuntansi (`radacct`) dan autentikasi (`radpostauth`).
  </p>
  <p>
    RadiusManager dibangun dengan arsitektur <em>Zero-Bloat</em> tanpa pustaka pihak ketiga yang berat, menggunakan query SQL yang <strong>SARGable</strong> (memanfaatkan indeks komposit secara optimal), serta mendukung penuh integrasi perangkat keras modern seperti <strong>Ruijie Networks Wireless Controller</strong> dan <strong>MikroTik RouterOS</strong>.
  </p>

  <h2>1.2 Diagram Alir & Interaksi Sistem</h2>
  <div class="diagram-wrapper">
    <!-- Inline SVG Architecture Diagram -->
    <svg viewBox="0 0 720 220" width="100%" height="160" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <marker id="arrow" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
          <path d="M 0 1 L 10 5 L 0 9 z" fill="#2563eb" />
        </marker>
        <marker id="arrow-green" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
          <path d="M 0 1 L 10 5 L 0 9 z" fill="#16a34a" />
        </marker>
      </defs>

      <!-- Client Layer -->
      <rect x="10" y="30" width="130" height="70" rx="8" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.5"/>
      <text x="75" y="55" font-size="11" font-weight="bold" fill="#0f172a" text-anchor="middle">Pengguna / Klien</text>
      <text x="75" y="72" font-size="8.5" fill="#64748b" text-anchor="middle">Laptop / HP / Siswa</text>
      <text x="75" y="85" font-size="8" fill="#2563eb" text-anchor="middle">WPA2-Ent / Captive Portal</text>

      <!-- NAS Layer -->
      <rect x="200" y="30" width="140" height="70" rx="8" fill="#eff6ff" stroke="#3b82f6" stroke-width="1.5"/>
      <text x="270" y="55" font-size="11" font-weight="bold" fill="#1e40af" text-anchor="middle">Perangkat NAS / AP</text>
      <text x="270" y="72" font-size="8.5" fill="#475569" text-anchor="middle">Ruijie AC (172.16.0.70)</text>
      <text x="270" y="85" font-size="8" fill="#2563eb" text-anchor="middle">MikroTik / Cisco WLC</text>

      <!-- RADIUS Core -->
      <rect x="400" y="30" width="130" height="70" rx="8" fill="#ecfdf5" stroke="#10b981" stroke-width="1.5"/>
      <text x="465" y="55" font-size="11" font-weight="bold" fill="#065f46" text-anchor="middle">FreeRADIUS Engine</text>
      <text x="465" y="72" font-size="8.5" fill="#475569" text-anchor="middle">Port 1812 (Auth)</text>
      <text x="465" y="85" font-size="8" fill="#16a34a" text-anchor="middle">Port 1813 (Acct / CoA)</text>

      <!-- Database -->
      <rect x="585" y="30" width="125" height="70" rx="8" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.5"/>
      <text x="647" y="55" font-size="11" font-weight="bold" fill="#92400e" text-anchor="middle">MariaDB Database</text>
      <text x="647" y="72" font-size="8.5" fill="#475569" text-anchor="middle">Tabel rad* & rm_*</text>
      <text x="647" y="85" font-size="8" fill="#d97706" text-anchor="middle">940k+ Sesi Tersimpan</text>

      <!-- Web Panel (RadiusManager) -->
      <rect x="400" y="140" width="310" height="65" rx="8" fill="#0f172a" stroke="#334155" stroke-width="1.5"/>
      <text x="555" y="165" font-size="12" font-weight="bold" fill="#38bdf8" text-anchor="middle">RadiusManager Web Administration & Portal</text>
      <text x="555" y="182" font-size="8.5" fill="#94a3b8" text-anchor="middle">Dashboard, Users, Rate Plans, NAS, Vouchers, CoA Kick, REST API</text>
      <text x="555" y="195" font-size="8" fill="#10b981" text-anchor="middle">Multi-Role RBAC: Superadmin • Operator • Readonly</text>

      <!-- Arrows -->
      <line x1="140" y1="65" x2="195" y2="65" stroke="#2563eb" stroke-width="1.8" marker-end="url(#arrow)"/>
      <line x1="340" y1="65" x2="395" y2="65" stroke="#2563eb" stroke-width="1.8" marker-end="url(#arrow)"/>
      <line x1="530" y1="65" x2="580" y2="65" stroke="#16a34a" stroke-width="1.8" marker-end="url(#arrow-green)"/>
      <line x1="555" y1="140" x2="555" y2="105" stroke="#38bdf8" stroke-width="1.8" stroke-dasharray="4" marker-end="url(#arrow)"/>
      <line x1="465" y1="140" x2="300" y2="105" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3" marker-end="url(#arrow)"/>
      <text x="365" y="125" font-size="7.5" fill="#dc2626" font-weight="bold">CoA Kick (UDP 3799)</text>
    </svg>
    <div class="diagram-caption">Gambar 1.1 — Topologi Komunikasi Antara Pengguna, NAS Ruijie, FreeRADIUS, Database, dan RadiusManager</div>
  </div>

  <h2>1.3 Struktur Basis Data (Schema Mapping)</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:20%;">Nama Tabel</th>
        <th style="width:28%;">Kolom Utama</th>
        <th style="width:32%;">Deskripsi & Peran dalam Sistem</th>
        <th style="width:20%;">Kategori</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>radcheck</code></td>
        <td>username, attribute, op, value</td>
        <td>Menyimpan password (Cleartext), status aktif, masa berlaku (Expiration), dan Simultaneous-Use.</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>radreply</code></td>
        <td>username, attribute, op, value</td>
        <td>Menyimpan atribut khusus individual, seperti alokasi <code>Framed-IP-Address</code> (Static IP).</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>radusergroup</code></td>
        <td>username, groupname, priority</td>
        <td>Memetakan setiap pengguna ke dalam kelompok kebijakan (misal: <code>Pegawai</code>, <code>Mhs2024</code>).</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>radgroupreply</code></td>
        <td>groupname, attribute, op, value</td>
        <td>Menyimpan parameter bandwidth (MikroTik Rate-Limit, WISPr), batas kuota, dan session timeout.</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>nas</code></td>
        <td>nasname, shortname, type, secret</td>
        <td>Daftar router/controller yang diizinkan mengirim paket autentikasi (contoh: <code>172.16.0.70</code>).</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>radacct</code></td>
        <td>radacctid, username, acctstarttime, acctstoptime, acctinputoctets, acctoutputoctets, nasipaddress</td>
        <td>Log rekaman sesi koneksi, kuota terpakai (upload/download), IP klien, dan MAC address.</td>
        <td><span class="badge badge-primary">FreeRADIUS Core</span></td>
      </tr>
      <tr>
        <td><code>userinfo</code></td>
        <td>username, firstname, lastname, department, email, mobilephone</td>
        <td>Data profil identitas pengguna asli (Siswa, Dosen, Karyawan) yang terintegrasi di semua halaman.</td>
        <td><span class="badge badge-success">Ekstensi Profil</span></td>
      </tr>
      <tr>
        <td><code>rm_plans</code></td>
        <td>name, groupname, dl_kbps, ul_kbps, data_mb, time_hours</td>
        <td>Katalog paket bandwidth yang otomatis menyinkronkan atribut ke <code>radgroupreply</code>.</td>
        <td><span class="badge badge-purple">RadiusManager</span></td>
      </tr>
      <tr>
        <td><code>rm_vouchers</code></td>
        <td>batch_name, username, password, plan_id, status, used_at</td>
        <td>Inventaris voucher hotspot sekali pakai (unused, active, expired) untuk tamu/event.</td>
        <td><span class="badge badge-purple">RadiusManager</span></td>
      </tr>
      <tr>
        <td><code>rm_audit_log</code></td>
        <td>operator, action, target, detail, ip_address, created_at</td>
        <td>Rekaman jejak audit keamanan dari setiap tindakan yang dilakukan oleh administrator.</td>
        <td><span class="badge badge-purple">RadiusManager</span></td>
      </tr>
      <tr>
        <td><code>operators</code></td>
        <td>username, password, firstname, role, lastlogin</td>
        <td>Akun staf pengelola sistem lengkap dengan tingkatan peran (superadmin, operator, readonly).</td>
        <td><span class="badge badge-purple">RadiusManager</span></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 4: MODUL 2 — AKSES SISTEM & AUTENTIKASI OPERATOR
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-2">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 2: Akses Sistem & Keamanan</span>
  </div>

  <h1>Modul 2: Akses Sistem & Autentikasi Operator</h1>

  <h2>2.1 Alamat Akses & Kredensial Masuk</h2>
  <p>
    Panel administrasi RadiusManager dapat diakses melalui peramban web modern (Google Chrome, Microsoft Edge, Mozilla Firefox, Safari) pada jaringan lokal kampus maupun melalui VPN:
  </p>

  <div class="card-box">
    <strong>Tautan Akses Panel Admin:</strong><br>
    <code style="font-size: 11pt;">http://&lt;IP-SERVER-ANDA&gt;/radiusmanager/radius-manager/login.php</code><br>
    <span style="font-size: 8pt; color: #64748b;">(Pada server lokal XAMPP pengujian: <code>http://localhost/radiusmanager/radius-manager/</code>)</span>
  </div>

  <h2>2.2 Skema Dual-Source Authentication</h2>
  <p>
    RadiusManager dilengkapi sistem autentikasi ganda yang tangguh untuk memastikan administrator tidak akan terkunci meskipun database mengalami kendala:
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Metode Autentikasi</th>
        <th>Username Bawaan</th>
        <th>Password Bawaan</th>
        <th>Peran (Role)</th>
        <th>Penjelasan & Rekomendasi</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>1. Database Operators</strong><br>(Tabel <code>operators</code>)</td>
        <td><code>administrator</code></td>
        <td><code>4dm1nNamloP</code></td>
        <td><span class="badge badge-danger">superadmin</span></td>
        <td>
          Akun utama operasional harian. Tersimpan di database dengan pelacakan waktu login terakhir (<code>lastlogin</code>). Mendukung enkripsi password modern (Bcrypt).
        </td>
      </tr>
      <tr>
        <td><strong>2. Configuration Fallback</strong><br>(Berkas <code>config.php</code>)</td>
        <td><code>admin</code></td>
        <td><code>admin123</code></td>
        <td><span class="badge badge-primary">superadmin</span></td>
        <td>
          Akun darurat (*emergency fallback*) yang didefinisikan secara statis. Digunakan jika terjadi perbaikan tabel database atau migrasi darurat.
        </td>
      </tr>
    </tbody>
  </table>

  <h2>2.3 Fitur Keamanan Bawaan (Security Features)</h2>
  <div class="grid-3">
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Proteksi CSRF Token</div>
      <div class="kpi-mini-val" style="font-size: 10pt; color: #16a34a;">Aktif di Setiap Form</div>
      <div class="kpi-mini-desc">Token acak 32-byte <code>csrfField()</code> mencegah serangan pemalsuan permintaan antar-situs.</div>
    </div>
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Session Hijack Guard</div>
      <div class="kpi-mini-val" style="font-size: 10pt; color: #2563eb;">Validasi User-Agent</div>
      <div class="kpi-mini-desc">Sesi terkunci pada sidik jari browser operator untuk mencegah pencurian cookie sesi.</div>
    </div>
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Auto Timeout</div>
      <div class="kpi-mini-val" style="font-size: 10pt; color: #d97706;">60 Menit Inaktif</div>
      <div class="kpi-mini-desc">Sesi otomatis ditutup jika tidak ada aktivitas dalam 3600 detik untuk melindungi layar operator.</div>
    </div>
  </div>

  <h2>2.4 Langkah Mengubah Kata Sandi Administrator</h2>
  <ol class="step-list">
    <li>
      <strong>Mengubah Password Operator Database:</strong> Masuk sebagai <code>administrator</code> &rarr; buka menu <strong>Operators & RBAC</strong> (`operators.php`) &rarr; klik tombol <strong>Edit</strong> pada akun Anda &rarr; masukkan password baru pada kolom sandi &rarr; klik <strong>Save Operator</strong>.
    </li>
    <li>
      <strong>Mengubah Password Fallback di config.php:</strong> Buka terminal server, jalankan perintah PHP CLI berikut untuk menghasilkan hash kata sandi baru:
      <pre><code>php -r "echo password_hash('KataSandiBaruAnda', PASSWORD_DEFAULT);"</code></pre>
      Salin string hash yang dihasilkan (dimulai dengan <code>$2y$10$...</code>), kemudian buka berkas <code>config.php</code> dan perbarui konstanta <code>APP_PASS</code>.
    </li>
  </ol>

  <div class="callout callout-warning">
    <strong>Peringatan Keamanan Produksi:</strong>
    Setelah instalasi di server produksi selesai, segera ubah kedua kata sandi bawaan di atas untuk mencegah akses tidak sah ke seluruh infrastruktur jaringan kampus.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 5: MODUL 3 — DASHBOARD & ANALISIS JARINGAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-3">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 3: Navigasi Dashboard</span>
  </div>

  <h1>Modul 3: Navigasi Dashboard & Analisis Jaringan</h1>

  <h2>3.1 Struktur Navigasi Panel (Sidebar Menu)</h2>
  <p>
    Sisi kiri antarmuka menyajikan bilah navigasi dengan kelompok menu yang jelas:
  </p>
  <ul style="margin-left: 20px; font-size: 9pt; line-height: 1.6;">
    <li><strong>Main Navigation:</strong> <code>Dashboard</code> (Pusat pemantauan metrik dan grafik).</li>
    <li><strong>RADIUS Core:</strong> <code>Users</code> (Akun), <code>Groups</code> (Profil), <code>Rate Plans</code> (Paket Bandwidth), <code>NAS Devices</code> (Router/AP), <code>Vouchers & Hotspot</code> (Voucher), <code>Expiry Warnings</code> (Masa Aktif).</li>
    <li><strong>Monitoring:</strong> <code>Active Sessions</code> (Koneksi live), <code>Accounting</code> (Riwayat koneksi lampau), <code>Reports</code> (Laporan eksekutif).</li>
    <li><strong>Administration:</strong> <code>Auth Logs</code> (Catatan login), <code>Operators & RBAC</code> (Manajemen staf), <code>Audit Trail</code> (Catatan aksi admin).</li>
  </ul>

  <h2>3.2 Kartu Indikator Utama (KPI Metrics)</h2>
  <div class="grid-2">
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Total Users (Total Pengguna)</div>
      <div class="kpi-mini-val">2.845 <span style="font-size:9pt; font-weight:normal; color:#64748b;">Akun</span></div>
      <div class="kpi-mini-desc">Total kredensial terdaftar di <code>radcheck</code>, mencakup akun aktif dan nonaktif.</div>
    </div>
    <div class="kpi-mini-card success">
      <div class="kpi-mini-title">Active Connections (Sesi Aktif)</div>
      <div class="kpi-mini-val" style="color: #16a34a;">142 <span style="font-size:9pt; font-weight:normal; color:#64748b;">Perangkat Online</span></div>
      <div class="kpi-mini-desc">Jumlah perangkat yang saat ini terhubung langsung ke Access Point (<code>acctstoptime IS NULL</code>).</div>
    </div>
    <div class="kpi-mini-card warning">
      <div class="kpi-mini-title">Registered NAS (Gerbang Jaringan)</div>
      <div class="kpi-mini-val" style="color: #d97706;">3 <span style="font-size:9pt; font-weight:normal; color:#64748b;">Unit NAS / Gateway</span></div>
      <div class="kpi-mini-desc">Perangkat Access Controller (Ruijie Core, Eduroam Gateway, MikroTik).</div>
    </div>
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Authentications Today (Autentikasi Hari Ini)</div>
      <div class="kpi-mini-val">8.412 <span style="font-size:9pt; font-weight:normal; color:#64748b;">Permintaan</span></div>
      <div class="kpi-mini-desc">Total Access-Request yang diproses server sejak pukul 00:00 hari ini.</div>
    </div>
  </div>

  <h2>3.3 Tiga Grafik Analisis Tingkat Lanjut</h2>
  <ol class="step-list">
    <li>
      <strong>Grafik Profil Sesi 24 Jam (Hourly Session Trajectory):</strong>
      <p>
        Menampilkan kurva perbandingan jumlah sesi pengguna setiap jam antara <strong>Hari Ini (biru pekat)</strong> dan <strong>Kemarin (garis putus-putus abu-abu)</strong>. Grafik ini sangat berguna untuk memprediksi jam sibuk perkuliahan (biasanya pukul 08:00–11:00 dan 13:00–15:00) serta mengantisipasi kepadatan spektrum WiFi.
      </p>
    </li>
    <li>
      <strong>Grafik Tren Bandwidth 14 Hari (Bandwidth Volume Trends):</strong>
      <p>
        Menampilkan pergerakan volume konsumsi data harian dalam satuan MegaByte/GigaByte. Membedakan secara visual antara trafik <strong>Download (hijau)</strong> dan <strong>Upload (biru)</strong>, memudahkan evaluasi kapasitas uplink bandwidth ISP kampus.
      </p>
    </li>
    <li>
      <strong>Diagram Distribusi Trafik NAS (Access Point Bandwidth Distribution):</strong>
      <p>
        Diagram batang horizontal yang menunjukkan beban kerja antar-gateway. Di lingkungan kampus, <code>Ruijie-AP-Core</code> memproses mayoritas sesi lokal, sedangkan <code>eduroam-itb</code> memproses roaming edukasi.
      </p>
    </li>
  </ol>

  <div class="callout callout-info">
    <strong>Fitur Caching Otomatis untuk Performa Maksimal:</strong>
    Data kalkulasi analitik pada dashboard disimpan dalam cache sesi selama 120 detik. Hal ini memastikan halaman dashboard dapat terbuka instan (&lt;15 milidetik) tanpa membebani database, bahkan saat ratusan klien melakukan autentikasi secara bersamaan.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 6: MODUL 4 — MANAJEMEN PENGGUNA RADIUS (USERS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-4">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 4: Manajemen Pengguna (Users)</span>
  </div>

  <h1>Modul 4: Manajemen Pengguna RADIUS (Users)</h1>

  <h2>4.1 Menjelajahi Daftar Pengguna (`users.php`)</h2>
  <p>
    Halaman <strong>Users</strong> merupakan pusat pengelolaan seluruh akun jaringan. Sistem menggabungkan data kredensial autentikasi (`radcheck`), penugasan grup (`radusergroup`), serta data identitas siswa/pegawai (`userinfo`):
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Kolom</th>
        <th>Isi & Keterangan</th>
        <th>Contoh Nilai</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Status</strong></td>
        <td>Indikator koneksi live dan status keaktifan akun.</td>
        <td>
          <span class="badge badge-success">Online</span> (Sedang terhubung)<br>
          <span class="badge badge-slate">Offline</span> (Sedang tidak terhubung)<br>
          <span class="badge badge-danger">Disabled</span> (Diblokir sementara)
        </td>
      </tr>
      <tr>
        <td><strong>Username</strong></td>
        <td>Nomor Induk Mahasiswa (NIM), NIP Pegawai, atau ID Tamu.</td>
        <td><code>206412005</code>, <code>201403003</code></td>
      </tr>
      <tr>
        <td><strong>Password</strong></td>
        <td>Kata sandi tersembunyi dengan tombol mata untuk melihat teks asli.</td>
        <td><code>••••••••</code> &rarr; <code>22051981</code></td>
      </tr>
      <tr>
        <td><strong>Group</strong></td>
        <td>Kelompok kebijakan paket bandwidth dan pembatasan.</td>
        <td><span class="badge badge-primary">Pegawai</span>, <span class="badge badge-warning">Mhs2024</span></td>
      </tr>
      <tr>
        <td><strong>User Profile</strong></td>
        <td>Nama lengkap, jurusan/departemen, dan email resmi.</td>
        <td><strong>Rheza Raditya</strong><br><small>Teknik Mesin • rheza@polman.ac.id</small></td>
      </tr>
      <tr>
        <td><strong>IP Address</strong></td>
        <td>Alokasi IP Statis (jika ada) atau IP dinamis terkini.</td>
        <td><code>172.16.10.45</code></td>
      </tr>
      <tr>
        <td><strong>Actions</strong></td>
        <td>Tombol aksi cepat: Edit, Toggle Enable/Disable, dan Hapus.</td>
        <td>Pencil (Edit), Power (Toggle), Trash (Hapus)</td>
      </tr>
    </tbody>
  </table>

  <h2>4.2 Prosedur Menambah Pengguna Baru (`user-add.php`)</h2>
  <ol class="step-list">
    <li>Klik menu <strong>Users</strong> pada navigasi kiri, kemudian klik tombol biru <strong>+ Add User</strong> di sudut kanan atas.</li>
    <li>
      <strong>Data Kredensial Akun (Wajib):</strong>
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Username:</strong> Masukkan NIM mahasiswa atau NIP staf (contoh: <code>224312005</code>). Karakter harus unik.</li>
        <li><strong>Password:</strong> Masukkan kata sandi atau klik tombol <strong>Generate</strong> untuk menghasilkan kata sandi acak yang kuat secara otomatis.</li>
      </ul>
    </li>
    <li>
      <strong>Profil Pengguna (Disimpan ke `userinfo`):</strong>
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Full Name:</strong> Nama lengkap siswa atau pegawai.</li>
        <li><strong>Department:</strong> Program studi atau unit kerja (contoh: <code>Teknik Otomasi Manufaktur</code>).</li>
        <li><strong>Email:</strong> Alamat email resmi untuk notifikasi dan pemulihan mandiri.</li>
      </ul>
    </li>
    <li>
      <strong>Kebijakan & Batasan RADIUS (Constraints):</strong>
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Group:</strong> Pilih kelompok layanan (misal: <code>Mhs2024</code> untuk kuota mahasiswa).</li>
        <li><strong>Simultaneous-Use:</strong> Jumlah maksimal perangkat yang dapat login bersamaan dengan satu akun (default: <code>1</code>).</li>
        <li><strong>Expiration Date:</strong> Tanggal berakhirnya akun (misal akhir semester atau masa studi). Kosongkan jika tanpa batas waktu.</li>
        <li><strong>Static IP (Framed-IP-Address):</strong> Isi alamat IP statis jika pengguna adalah server internal atau perangkat lab khusus.</li>
      </ul>
    </li>
    <li>Klik tombol <strong>Save User</strong>. Sistem akan menyimpan data secara transaksional ke database dan mencatatnya di Log Audit.</li>
  </ol>

  <h2>4.3 Fitur Soft-Disable (Nonaktifkan Sementara)</h2>
  <p>
    Terkadang administrator perlu menonaktifkan akun sementara waktu (misalnya: mahasiswa cuti akademik, sanksi pelanggaran tata tertib jaringan, atau tunggakan administrasi) tanpa harus menghapus data akun tersebut.
  </p>
  <div class="card-box">
    <strong>Cara Kerja Soft-Disable di RadiusManager:</strong><br>
    Saat Anda mengklik tombol <strong>Disable</strong> (ikon saklar/power), sistem secara otomatis menyisipkan atribut standar FreeRADIUS:
    <div style="margin: 6px 0;"><code>Auth-Type := Reject</code> pada tabel <code>radcheck</code>.</div>
    <span style="font-size: 8.5pt; color: #475569;">
      &bull; <strong>Keuntungan:</strong> Setiap kali pengguna mencoba login, server RADIUS langsung menolaknya (Access-Reject) secara instan.<br>
      &bull; <strong>Keamanan Data:</strong> Password asli, profil mahasiswa, riwayat sesi lampau, dan grup <strong>tetap utuh 100%</strong>.<br>
      &bull; <strong>Re-enable:</strong> Untuk mengaktifkan kembali, cukup klik tombol <strong>Enable</strong> satu kali. Atribut Reject akan otomatis dihapus.
    </span>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 7: MODUL 4 (LANJUTAN) — OPERASI MASSAL (BATCH ACTIONS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 4: Operasi Massal Pengguna</span>
  </div>

  <h2>4.4 Operasi Massal Pengguna (User Batch Operations)</h2>
  <p>
    Ketika mengelola ribuan mahasiswa di awal semester baru, melakukan perubahan akun satu per satu sangat tidak efisien. RadiusManager menyediakan fitur <strong>Batch Actions Toolbar</strong> yang interaktif:
  </p>

  <ol class="step-list">
    <li>Buka halaman <strong>Users</strong> (`users.php`).</li>
    <li>Gunakan kotak centang (checkbox) pada baris tabel pengguna yang ingin Anda kelola, atau centang kotak di baris judul (header) untuk memilih seluruh pengguna pada halaman tersebut.</li>
    <li>
      Saat setidaknya satu pengguna dipilih, bilah alat melayang (<em>Sticky Batch Action Toolbar</em>) akan muncul otomatis di bagian bawah layar:
      <div class="card-box" style="background:#0f172a; color:#f8fafc; margin: 6px 0;">
        <span style="color: #38bdf8; font-weight: bold;">[24 users selected]</span> — Choose Action: 
        <span class="badge badge-success">Enable Accounts</span> 
        <span class="badge badge-warning">Disable Accounts</span> 
        <span class="badge badge-primary">Change Group</span> 
        <span class="badge badge-slate">Export CSV</span> 
        <span class="badge badge-danger">Delete Permanently</span>
      </div>
    </li>
    <li>
      Pilih aksi yang diinginkan:
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Bulk Enable:</strong> Mengaktifkan kembali seluruh akun yang dipilih dalam 1 transaksi SQL.</li>
        <li><strong>Bulk Disable:</strong> Memblokir seluruh akun terpilih secara serentak.</li>
        <li><strong>Bulk Change Group:</strong> Memindahkan seluruh akun terpilih ke kelompok kebijakan baru (misal: naik kelas dari <code>Mhs2023</code> ke <code>Mhs2024</code>) tanpa mengubah password.</li>
        <li><strong>Bulk Export CSV:</strong> Mengunduh data lengkap akun terpilih ke format file Excel / Spreadsheet.</li>
        <li><strong>Bulk Delete:</strong> Menghapus akun-akun terpilih secara permanen setelah konfirmasi keamanan.</li>
      </ul>
    </li>
  </ol>

  <h2>4.5 Prosedur Mengedit Pengguna (`user-edit.php`)</h2>
  <p>
    Klik ikon <strong>Pencil (Edit)</strong> pada baris pengguna manapun untuk membuka formulir pengeditan lengkap. Pada halaman ini, administrator dapat:
  </p>
  <ul style="margin-left: 20px; font-size: 9pt; line-height: 1.6;">
    <li>Memperbarui kata sandi baru (kosongkan kolom jika tidak ingin mengubah kata sandi lama).</li>
    <li>Mengubah grup penugasan kebijakan bandwidth.</li>
    <li>Memperpanjang tanggal kadaluarsa (Expiration Date).</li>
    <li>Memperbarui email, nomor telepon, atau data departemen di `userinfo`.</li>
    <li><strong>Melihat Riwayat Sesi Pengguna Tersebut:</strong> Di bagian bawah formulir edit, sistem menampilkan tabel riwayat koneksi khusus untuk pengguna tersebut (waktu mulai, waktu selesai, IP, durasi, dan total data yang dihabiskan).</li>
  </ul>

  <h2>4.6 Prosedur Menghapus Pengguna (`user-delete.php`)</h2>
  <div class="callout callout-danger">
    <strong>Perhatian Penghapusan Permanen:</strong>
    Penghapusan pengguna bersifat permanen dan tidak dapat dibatalkan (irreversible). Gunakan fitur ini hanya jika akun sudah tidak diperlukan lagi (misal: mahasiswa telah diwisuda atau keluar).
  </div>
  <p>
    RadiusManager menerapkan penghapusan aman berantai (<em>Transactional Cascade Cleanup</em>). Ketika seorang pengguna dihapus, sistem mengeksekusi transaksi basis data untuk menghapus:
  </p>
  <table class="table-custom">
    <tr>
      <td style="width:30%;"><strong>1. Tabel <code>radcheck</code></strong></td>
      <td>Kredensial password, batas concurrent sessions, dan atribut cek pengguna.</td>
    </tr>
    <tr>
      <td><strong>2. Tabel <code>radreply</code></strong></td>
      <td>Atribut reply khusus seperti Static IP (`Framed-IP-Address`).</td>
    </tr>
    <tr>
      <td><strong>3. Tabel <code>radusergroup</code></strong></td>
      <td>Asosiasi keanggotaan grup pengguna.</td>
    </tr>
    <tr>
      <td><strong>4. Tabel <code>userinfo</code></strong></td>
      <td>Biodata lengkap (nama, email, departemen) pengguna.</td>
    </tr>
  </table>
  <p style="font-size:8.5pt; color:#64748b;">
    *Catatan: Riwayat akuntansi masa lalu di <code>radacct</code> tetap dipertahankan untuk kebutuhan audit forensik dan laporan historis kampus.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 8: MODUL 5 — GRUP KEBIJAKAN & PAKET BANDWIDTH (PLANS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-5">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 5: Grup & Paket Kecepatan</span>
  </div>

  <h1>Modul 5: Manajemen Grup & Paket Kecepatan (Rate Plans)</h1>

  <h2>5.1 Konsep Kebijakan Grup di FreeRADIUS</h2>
  <p>
    Daripada mengatur batas kecepatan pada setiap akun satu per satu, FreeRADIUS menggunakan konsep <strong>Grup Kebijakan</strong>. Setiap pengguna dikelompokkan ke dalam satu grup (melalui tabel `radusergroup`). Parameter teknis yang diterapkan pada grup tersebut (melalui tabel `radgroupreply`) akan otomatis berlaku bagi seluruh anggota grup.
  </p>

  <h2>5.2 Manajemen Paket Bandwidth (`plans.php`)</h2>
  <p>
    RadiusManager v1.8.0 memperkenalkan fitur <strong>Rate Plans Engine</strong>. Administrator tidak perlu lagi mengetikkan nama atribut RADIUS yang rumit secara manual. Cukup masukkan angka kecepatan dalam satuan Kbps/Mbps, dan sistem akan otomatis menyinkronkan seluruh atribut ke tabel `radgroupreply`:
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Atribut yang Dihasilkan</th>
        <th>Contoh Nilai</th>
        <th>Target Perangkat & Penjelasan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>Mikrotik-Rate-Limit</code></td>
        <td><code>5120k/10240k</code></td>
        <td><strong>MikroTik RouterOS:</strong> Mengatur batas antrian Simple Queue / Queue Tree (Upload 5M / Download 10M).</td>
      </tr>
      <tr>
        <td><code>WISPr-Bandwidth-Max-Down</code></td>
        <td><code>10485760</code></td>
        <td><strong>Ruijie, Cisco, Ruckus, Ubiquiti:</strong> Standar WiFi Alliance dalam satuan bit per detik (bps) untuk pembatasan unduh.</td>
      </tr>
      <tr>
        <td><code>WISPr-Bandwidth-Max-Up</code></td>
        <td><code>5242880</code></td>
        <td><strong>Ruijie, Cisco, Ruckus, Ubiquiti:</strong> Standar WiFi Alliance dalam satuan bps untuk pembatasan unggah.</td>
      </tr>
      <tr>
        <td><code>Session-Timeout</code></td>
        <td><code>86400</code></td>
        <td><strong>Semua NAS:</strong> Batas maksimal satu sesi koneksi dalam hitungan detik (contoh: 86400 detik = 24 jam).</td>
      </tr>
      <tr>
        <td><code>ChilliSpot-Max-Total-Octets</code></td>
        <td><code>5368709120</code></td>
        <td><strong>Captive Portal / CoovaChilli:</strong> Kuota volume data kumulatif (contoh: 5368709120 byte = 5 GB).</td>
      </tr>
    </tbody>
  </table>

  <h2>5.3 Langkah Membuat Paket Kecepatan Baru (`plan-add.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Rate Plans</strong> di bilah navigasi kiri, lalu klik <strong>+ Add Rate Plan</strong>.</li>
    <li>
      Isi kolom parameter paket:
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Plan Name:</strong> Nama paket yang mudah dibaca (contoh: <code>Mahasiswa-10Mbps</code> atau <code>Dosen-20Mbps</code>).</li>
        <li><strong>Group Name:</strong> Nama grup di database (contoh: <code>Mhs2024</code> atau <code>Pegawai</code>).</li>
        <li><strong>Download Speed (Kbps):</strong> Kecepatan unduh maksimal (contoh: <code>10240</code> untuk 10 Mbps).</li>
        <li><strong>Upload Speed (Kbps):</strong> Kecepatan unggah maksimal (contoh: <code>5120</code> untuk 5 Mbps).</li>
        <li><strong>Data Quota (MB):</strong> Batas kuota bulanan dalam MegaByte (isi <code>0</code> jika tanpa batas kuota / unlimited).</li>
        <li><strong>Validity (Hours):</strong> Durasi masa aktif sesi dalam hitungan jam (isi <code>0</code> jika tanpa batas sesi).</li>
      </ul>
    </li>
    <li>Klik <strong>Create Plan</strong>. Sistem akan menyimpan definisi paket ke `rm_plans` dan secara otomatis menerbitkan atribut `radgroupreply` yang sesuai.</li>
  </ol>

  <h2>5.4 Meninjau Pelanggan per Paket (Subscriber Count)</h2>
  <p>
    Pada tabel utama `plans.php`, terdapat kolom <strong>Subscribers</strong> yang menampilkan jumlah akun yang sedang terhubung ke paket tersebut secara live. Anda dapat mengklik angka pelanggan untuk langsung menyaring daftar pengguna di halaman `users.php`.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 9: MODUL 6 — PERANGKAT NAS & ACCESS POINT
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-6">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 6: Perangkat NAS & AP</span>
  </div>

  <h1>Modul 6: Manajemen Perangkat NAS & Access Point</h1>

  <h2>6.1 Apa itu NAS (Network Access Server)?</h2>
  <p>
    <strong>NAS (Network Access Server)</strong> adalah perangkat gerbang jaringan fisik—seperti Wireless LAN Controller (WLC), Access Point (AP), atau Router—yang mencegat permintaan koneksi klien dan meneruskannya ke server FreeRADIUS untuk diverifikasi.
  </p>
  <p>
    Server FreeRADIUS <strong>hanya akan merespons</strong> permintaan yang berasal dari IP perangkat yang telah terdaftar di tabel `nas` dengan kata sandi rahasia bersama (<em>Shared Secret</em>) yang sesuai.
  </p>

  <h2>6.2 Integrasi Khusus Ruijie Networks Wireless AC (`172.16.0.70`)</h2>
  <p>
    Pada sistem jaringan kampus, controller nirkabel utama adalah <strong>Ruijie Wireless AC</strong> yang mengelola ratusan Access Point di seluruh gedung. RadiusManager v1.8.0 telah mendukung penuh perangkat Ruijie sebagai tipe perangkat kelas satu:
  </p>

  <div class="card-box">
    <strong>Konfigurasi Ruijie Core di RadiusManager:</strong><br>
    &bull; <strong>IP Address:</strong> <code>172.16.0.70</code><br>
    &bull; <strong>Short Name:</strong> <code>Ruijie-AP-Core</code><br>
    &bull; <strong>Device Type:</strong> <span class="badge badge-primary">Ruijie</span><br>
    &bull; <strong>RADIUS Port:</strong> <code>1812</code> (Autentikasi) / <code>1813</code> (Akuntansi)<br>
    &bull; <strong>Shared Secret:</strong> <code>4dm1nNamloP</code><br>
    &bull; <strong>Karakteristik Port:</strong> Port radio dilaporkan sebagai <code>Dot11radio x/0.x</code> pada log akuntansi.
  </div>

  <h2>6.3 Menambah Perangkat NAS Baru (`nas-add.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>NAS Devices</strong> pada bilah navigasi kiri &rarr; klik tombol <strong>+ Add NAS</strong>.</li>
    <li>
      Lengkapi kolom konfigurasi:
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>IP Address / CIDR / Hostname:</strong> Masukkan IP statis router (contoh: <code>172.16.0.70</code>) atau subnet jaringan (contoh: <code>192.168.10.0/24</code>).</li>
        <li><strong>Short Name:</strong> Nama ringkas tanpa spasi untuk identifikasi visual di grafik dan laporan (contoh: <code>Ruijie-AP-Core</code>, <code>Mikrotik-Gedung-A</code>).</li>
        <li><strong>Device Type:</strong> Pilih tipe perangkat dari menu tarik-turun (<code>Ruijie</code>, <code>MikroTik</code>, <code>Cisco</code>, <code>Ubiquiti</code>, <code>Ruckus</code>, <code>Huawei</code>, <code>ZTE</code>, atau <code>Other</code>).</li>
        <li><strong>Ports:</strong> Port UDP autentikasi (standar: <code>1812</code>).</li>
        <li><strong>Shared Secret:</strong> Kunci rahasia bersama yang harus sama persis dengan yang dikonfigurasikan pada router/controller.</li>
        <li><strong>Description:</strong> Catatan lokasi fisik atau penanggung jawab (contoh: <em>Core Wireless Controller - Gedung Rektorat Lt. 2</em>).</li>
      </ul>
    </li>
    <li>Klik <strong>Add NAS</strong> untuk menyimpan.</li>
  </ol>

  <h2>6.4 Pemantauan Status Konektivitas & Latensi Ping</h2>
  <p>
    Halaman `nas.php` secara otomatis melakukan uji jangkauan jaringan (ICMP Ping / Socket Probe) ke setiap perangkat NAS terdaftar:
  </p>
  <table class="table-custom">
    <tr>
      <th style="width:20%;">Lencana Status</th>
      <th style="width:25%;">Kondisi Jaringan</th>
      <th style="width:55%;">Tindakan Administrator jika Bermasalah</th>
    </tr>
    <tr>
      <td><span class="badge badge-success">Online (ms)</span></td>
      <td>Perangkat merespons cepat (contoh: <code>1.4ms</code>).</td>
      <td>Normal. Perangkat siap melayani autentikasi.</td>
    </tr>
    <tr>
      <td><span class="badge badge-slate">Subnet</span></td>
      <td>Definisi wildcard (contoh: <code>0.0.0.0/0</code>).</td>
      <td>Normal untuk aturan tangkapan umum (*catch-all*).</td>
    </tr>
    <tr>
      <td><span class="badge badge-danger">Offline</span></td>
      <td>Perangkat tidak merespons dalam batas waktu probe.</td>
      <td>Periksa kabel uplink, daya router, atau aturan firewall server.</td>
    </tr>
  </table>
  <p style="font-size:8.5pt; color:#64748b;">
    *Tips: Klik tombol <strong>Check Status</strong> di sudut kanan atas untuk memicu pengecekan latensi ulang secara langsung.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 10: MODUL 7 — HOTSPOT & GENERATOR VOUCHER
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-7">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 7: Hotspot & Generator Voucher</span>
  </div>

  <h1>Modul 7: Hotspot & Generator Voucher Prabayar</h1>

  <h2>7.1 Fungsi & Kasus Penggunaan Voucher</h2>
  <p>
    Untuk tamu seminar, peserta ujian masuk kampus, kunjungan dinas, atau area hotspot publik kafe kampus, membuat akun pengguna permanen tidaklah praktis. RadiusManager menyediakan modul <strong>Hotspot Vouchers</strong> yang memungkinkan pembuatan ratusan voucher internet sementara hanya dalam hitungan detik.
  </p>

  <h2>7.2 Langkah Pembuatan Batch Voucher (`voucher-generate.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Vouchers & Hotspot</strong> pada sidebar, lalu klik <strong>+ Generate Vouchers</strong>.</li>
    <li>
      Isi formulir pembuatan batch:
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Batch Name:</strong> Nama unik pengenal kegiatan (contoh: <code>Seminar-Nasional-2026</code> atau <code>Guest-Maret</code>).</li>
        <li><strong>Number of Vouchers:</strong> Jumlah kartu yang ingin dibuat sekaligus (antara 1 hingga 200 lembar per batch).</li>
        <li><strong>Username Prefix:</strong> Awalan nama pengguna agar mudah dikenali (contoh: <code>guest-</code> atau <code>wifi-</code>).</li>
        <li><strong>Password Format:</strong>
          Pilih <code>Numeric (PIN 6-digit)</code> agar mudah diketikkan pengguna smartphone, atau <code>Alphanumeric (6 karakter)</code> untuk keamanan lebih tinggi.
        </li>
        <li><strong>Rate Plan / Package:</strong> Pilih paket kecepatan dan kuota yang telah dibuat di Modul 5 (contoh: <code>Tamu-1Hari-5Mbps</code>).</li>
        <li><strong>Validity (Days):</strong> Masa berlaku akun sejak voucher dibuat (contoh: <code>1</code> hari atau <code>7</code> hari).</li>
      </ul>
    </li>
    <li>Klik tombol <strong>Generate Vouchers</strong>. Sistem akan menyisipkan akun ke tabel `rm_vouchers`, `radcheck`, dan `radusergroup` secara otomatis.</li>
  </ol>

  <h2>7.3 Mencetak Kartu Voucher Siap Gunting (`voucher-print.php`)</h2>
  <p>
    Setelah batch berhasil dibuat, sistem akan menyediakan tombol <strong>Print Cards</strong>. Halaman cetak menampilkan tata letak kartu A4 standar (grid 4 kolom &times; 5 baris) yang siap dipotong dengan gunting kertas:
  </p>

  <div class="card-box" style="border: 1px dashed #3b82f6; max-width: 320px; margin: 10px auto; background: #ffffff;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:4px; margin-bottom:6px;">
      <span style="font-weight:bold; font-size:9pt; color:#1e40af;">WiFi Hotspot Kampus</span>
      <span class="badge badge-primary">1 HARI</span>
    </div>
    <div style="font-size:8pt; color:#475569;">SSID: <strong>@WiFi-Kampus-Guest</strong></div>
    <div style="background:#f1f5f9; padding:6px; border-radius:4px; margin:6px 0; font-family:monospace; font-size:9pt;">
      User: <strong>guest-84291</strong><br>
      Pass: <strong>749210</strong>
    </div>
    <div style="font-size:7pt; color:#64748b; line-height:1.2;">
      Kecepatan: Up to 5 Mbps<br>
      Buka browser &amp; login pada halaman captive portal.
    </div>
  </div>

  <h2>7.4 Siklus Hidup & Status Voucher (Lifecycle)</h2>
  <table class="table-custom">
    <tr>
      <th style="width:20%;">Status</th>
      <th style="width:40%;">Deskripsi Kondisi</th>
      <th style="width:40%;">Dampak pada Server</th>
    </tr>
    <tr>
      <td><span class="badge badge-success">unused</span></td>
      <td>Voucher telah dicetak namun belum pernah digunakan untuk login.</td>
      <td>Akun siap diautentikasi di Access Point.</td>
    </tr>
    <tr>
      <td><span class="badge badge-primary">active</span></td>
      <td>Voucher telah berhasil login pertama kali di Access Point.</td>
      <td>Waktu mulai pemakaian tercatat di kolom <code>used_at</code>.</td>
    </tr>
    <tr>
      <td><span class="badge badge-danger">expired</span></td>
      <td>Masa berlaku hari atau kuota data voucher telah habis.</td>
      <td>Server otomatis menolak login berikutnya.</td>
    </tr>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 11: MODUL 8 — PERINGATAN KADALUARSA (EXPIRY WARNINGS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-8">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 8: Peringatan Kadaluarsa</span>
  </div>

  <h1>Modul 8: Sistem Peringatan Kadaluarsa (Expiry Warnings)</h1>

  <h2>8.1 Urgensi Pemantauan Masa Aktif Akun</h2>
  <p>
    Setiap akun mahasiswa memiliki masa aktif berdasarkan semester atau kalender akademik. Jika akun kadaluarsa tanpa pemberitahuan, mahasiswa akan mendadak tidak dapat mengakses ujian daring atau materi perkuliahan.
  </p>
  <p>
    RadiusManager v1.8.0 menyediakan modul <strong>Expiry Warnings</strong> (`expiry-check.php`) dengan jendela peringatan yang dapat diatur (default: <strong>7 hari</strong> sebelum tanggal kadaluarsa).
  </p>

  <h2>8.2 Antarmuka Dasbor Kadaluarsa</h2>
  <p>
    Halaman menyaring akun-akun yang memiliki atribut <code>Expiration</code> pada tabel `radcheck` dan mengelompokkannya ke dalam tiga tab status:
  </p>
  <div class="grid-3">
    <div class="kpi-mini-card danger">
      <div class="kpi-mini-title">Expired (Sudah Lewat)</div>
      <div class="kpi-mini-val" style="color: #dc2626;">Akun Nonaktif</div>
      <div class="kpi-mini-desc">Akun yang tanggal berlakunya telah terlampaui. User sudah tidak bisa login.</div>
    </div>
    <div class="kpi-mini-card warning">
      <div class="kpi-mini-title">Expiring Soon (Akan Habis)</div>
      <div class="kpi-mini-val" style="color: #d97706;">&le; 7 Hari Lagi</div>
      <div class="kpi-mini-desc">Akun yang akan habis dalam waktu dekat dan membutuhkan perpanjangan masa aktif.</div>
    </div>
    <div class="kpi-mini-card success">
      <div class="kpi-mini-title">Active Monitored (Aktif)</div>
      <div class="kpi-mini-val" style="color: #16a34a;">&gt; 7 Hari</div>
      <div class="kpi-mini-desc">Akun dengan masa aktif panjang yang aman dalam pemantauan sistem.</div>
    </div>
  </div>

  <h2>8.3 Tombol Perpanjangan Cepat 1-Klik (Quick Extension)</h2>
  <p>
    Untuk mempercepat tugas staf helpdesk ketika mahasiswa datang melakukan registrasi ulang semester, RadiusManager menyediakan tombol perpanjangan instan langsung di tabel tanpa perlu membuka form edit:
  </p>
  <div class="card-box">
    <strong>Tombol Aksi Cepat pada Baris Akun:</strong><br>
    &bull; <span class="badge badge-primary">+7 Days</span> : Menambahkan 7 hari ke tanggal kadaluarsa saat ini.<br>
    &bull; <span class="badge badge-success">+30 Days</span> : Menambahkan 30 hari (1 bulan) secara otomatis.<br>
    &bull; <span class="badge badge-purple">+90 Days</span> : Menambahkan 90 hari (1 triwulan / semester).<br>
    &bull; <span class="badge badge-danger">Disable Now</span> : Segera memblokir akun jika mahasiswa terbukti berhenti studi.
  </div>

  <h2>8.4 Otomatisasi via Command-Line Interface (CLI / Cron Job)</h2>
  <p>
    Skrip `expiry-check.php` dapat dijalankan secara berkala pada latar belakang (*headless cron job*) tanpa perlu membuka browser. Skrip ini akan mengaudit akun-akun kadaluarsa dan mencatat laporannya ke tabel jejak audit:
  </p>

  <pre><code># Perintah eksekusi CLI manual atau via Crontab:
php /var/www/html/radiusmanager/radius-manager/expiry-check.php --cli</code></pre>

  <p><strong>Contoh Konfigurasi Crontab Linux (Dijalankan setiap hari pukul 00:05 WIB):</strong></p>
  <pre><code>5 0 * * * /usr/bin/php /var/www/html/radiusmanager/radius-manager/expiry-check.php --cli >> /var/log/radiusmanager_expiry.log 2>&1</code></pre>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 12: MODUL 9 — SESI AKTIF & PEMUTUSAN KONEKSI (COA)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-9">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 9: Sesi Aktif & CoA Disconnect</span>
  </div>

  <h1>Modul 9: Pemantauan Sesi Aktif & Pemutusan Koneksi (CoA)</h1>

  <h2>9.1 Pemantauan Sesi Real-Time (`sessions.php`)</h2>
  <p>
    Halaman <strong>Active Sessions</strong> menampilkan seluruh pengguna yang saat ini sedang terhubung ke jaringan kampus secara *live*. Halaman ini secara otomatis melakukan penyegaran data (<em>Auto-Refresh</em>) setiap <strong>30 detik</strong>.
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Informasi Sesi</th>
        <th>Sumber Kolom</th>
        <th>Fungsi Analisis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>User & Profile</strong></td>
        <td><code>username</code> + <code>userinfo</code></td>
        <td>Mengetahui identitas pemilik perangkat (NIM/NIP, Nama Lengkap, dan Departemen).</td>
      </tr>
      <tr>
        <td><strong>Framed IP Address</strong></td>
        <td><code>framedipaddress</code></td>
        <td>Alamat IP lokal yang sedang dipinjam perangkat klien dari DHCP pool server.</td>
      </tr>
      <tr>
        <td><strong>MAC Address</strong></td>
        <td><code>callingstationid</code></td>
        <td>Identitas fisik kartu jaringan perangkat klien (HP, laptop, tablet).</td>
      </tr>
      <tr>
        <td><strong>Access Point / NAS</strong></td>
        <td><code>nasipaddress</code> / <code>nas.shortname</code></td>
        <td>Lokasi gateway tempat klien tersambung (contoh: <code>Ruijie-AP-Core</code>).</td>
      </tr>
      <tr>
        <td><strong>Start Time & Duration</strong></td>
        <td><code>acctstarttime</code>, <code>acctsessiontime</code></td>
        <td>Waktu awal klien terhubung dan durasi aktif berjalan dalam jam:menit:detik.</td>
      </tr>
      <tr>
        <td><strong>Data Transferred</strong></td>
        <td><code>acctinputoctets</code>, <code>acctoutputoctets</code></td>
        <td>Akumulasi volume upload dan download selama sesi berlangsung.</td>
      </tr>
    </tbody>
  </table>

  <h2>9.2 Memutus Paksa Sesi Pengguna (Disconnect / CoA Kick Helper)</h2>
  <p>
    Terkadang administrator harus memutus koneksi pengguna yang sedang aktif (misalnya: akun terindikasi menyebarkan virus, pengguna mengunduh file terlarang yang menghabiskan bandwidth, atau akun baru saja diubah kata sandinya):
  </p>

  <div class="card-box">
    <strong>Mekanisme CoA / PoD (RFC 3576 &amp; RFC 5176):</strong><br>
    RadiusManager menggunakan paket standar <em>Change of Authorization / Packet of Disconnect</em> yang dikirimkan ke port <strong>UDP 3799</strong> pada router atau Access Controller NAS.
  </div>

  <ol class="step-list">
    <li>Pada halaman <code>sessions.php</code>, temukan sesi pengguna yang ingin diputus &rarr; klik tombol merah <strong>Disconnect</strong> di sisi kanan tabel.</li>
    <li>
      Jendela dialog (*modal dialog*) akan muncul menampilkan rincian sesi beserta helper perintah `radclient`:
      <pre><code>echo "User-Name=206412005,Acct-Session-Id=sess_001a" | radclient -x 172.16.0.70:3799 disconnect '4dm1nNamloP'</code></pre>
    </li>
    <li>
      Klik tombol konfirmasi <strong>Execute Disconnect</strong>.
      <p style="font-size:8.5pt; color:#475569; margin-top:3px;">
        Sistem web akan mencoba mengirimkan paket UDP socket ke port 3799 router NAS target untuk memerintahkan radio Access Point memutus koneksi klien secara instan.
      </p>
    </li>
  </ol>

  <div class="callout callout-info">
    <strong>Prasyarat Fitur Disconnect di Router NAS:</strong><br>
    Pastikan fitur <em>Incoming CoA / RADIUS Disconnect</em> telah diaktifkan pada controller router Anda (di MikroTik: <code>/radius incoming set accept=yes port=3799</code>; di Ruijie: pastikan service CoA diaktifkan pada menu RADIUS Server Profile).
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 13: MODUL 10 — RIWAYAT AKUNTANSI & LAPORAN EKSEKUTIF
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-10">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 10: Riwayat Akuntansi & Laporan</span>
  </div>

  <h1>Modul 10: Riwayat Akuntansi & Laporan Eksekutif</h1>

  <h2>10.1 Audit Sesi Lampau (`accounting.php`)</h2>
  <p>
    Tabel `radacct` menyimpan riwayat seluruh sesi yang pernah terjadi di jaringan kampus. Pada instalasi berskala besar, tabel ini menampung <strong>lebih dari 940.000 baris rekaman</strong>.
  </p>
  <p>
    RadiusManager menggunakan klausa SQL berbasis rentang tanggal SARGable (`acctstarttime &gt;= ? AND acctstoptime &lt;= ?`) sehingga pencarian riwayat ribuan sesi dapat selesai dalam waktu <strong>kurang dari 50 milidetik</strong>.
  </p>

  <h2>10.2 Analisis Alasan Penghentian Sesi (Terminate Cause)</h2>
  <p>
    Kolom <code>acctterminatecause</code> pada tabel akuntansi menjelaskan secara ilmiah mengapa sebuah koneksi klien berakhir:
  </p>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:25%;">Nilai Terminate Cause</th>
        <th style="width:35%;">Arti & Kondisi Lapangan</th>
        <th style="width:40%;">Tindakan Administrator</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>User-Request</code></td>
        <td>Pengguna secara sadar mematikan WiFi atau menekan tombol Disconnect di gadget-nya.</td>
        <td>Normal. Tidak ada masalah jaringan.</td>
      </tr>
      <tr>
        <td><code>Lost-Carrier</code></td>
        <td>Klien keluar dari jangkauan sinyal Access Point atau baterai perangkat habis mendadak.</td>
        <td>Evaluasi *blind spot* (area tanpa sinyal) jika sering terjadi di lokasi yang sama.</td>
      </tr>
      <tr>
        <td><code>Session-Timeout</code></td>
        <td>Batas maksimal durasi paket (misal 24 jam) telah tercapai.</td>
        <td>Normal sesuai kebijakan paket bandwidth.</td>
      </tr>
      <tr>
        <td><code>Idle-Timeout</code></td>
        <td>Pengguna tidak mengirim/menerima data sama sekali selama periode tertentu.</td>
        <td>Membebaskan alokasi IP agar tidak terbuang sia-sia.</td>
      </tr>
      <tr>
        <td><code>Admin-Reset</code></td>
        <td>Koneksi diputus secara paksa oleh administrator melalui perintah CoA Disconnect.</td>
        <td>Tercatat pada log audit keamanan.</td>
      </tr>
    </tbody>
  </table>

  <h2>10.3 Modul Laporan Eksekutif (`reports.php`)</h2>
  <p>
    Modul <strong>Reports</strong> menyajikan data intelijen jaringan yang siap dicetak untuk laporan bulanan pimpinan kampus / rektorat:
  </p>
  <ol class="step-list">
    <li><strong>Pemilihan Rentang Waktu Dinamis:</strong> Pilih opsi cepat seperti <em>This Month (Bulan Ini)</em>, <em>Last Month (Bulan Lalu)</em>, <em>Last 30 Days</em>, <em>This Year</em>, atau tentukan rentang tanggal kustom.</li>
    <li>
      <strong>4 Kartu KPI Eksekutif:</strong> Total Sesi Terlayani, Pengguna Unik (Distinct Users), Akumulasi Total Bandwidth (GB/TB), dan Rata-rata Durasi Penggunaan.
    </li>
    <li>
      <strong>Tabel Top 10 Konsumen Terboros (Top Consumers):</strong> Menampilkan 10 pengguna yang menghabiskan bandwidth terbesar, lengkap dengan nama mahasiswa, jurusan, jumlah sesi, dan total kuota gigabyte.
    </li>
    <li>
      <strong>Distribusi Grup Kebijakan:</strong> Mengetahui kelompok mana yang paling dominan menggunakan internet (contoh: <code>Mhs2024</code> menghabiskan 65% trafik, <code>Pegawai</code> menghabiskan 35%).
    </li>
    <li>
      <strong>Rekapitulasi Trafik Access Point:</strong> Menilai kepadatan Access Point di berbagai gedung kampus.
    </li>
    <li>
      <strong>Opsi Ekspor:</strong>
      <ul style="margin-left: 15px; margin-top: 4px;">
        <li><strong>Print View (Cetak A4):</strong> Klik tombol <strong>Print Report</strong>. CSS khusus cetak akan membersihkan navigasi dan menyusun laporan dalam format kertas resmi.</li>
        <li><strong>CSV Export:</strong> Klik tombol <strong>Export CSV</strong> (`reports.php?export=csv`) untuk mengunduh data mentah ke spreadsheet Excel.</li>
      </ul>
    </li>
  </ol>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 14: MODUL 11 — AUDIT KEAMANAN & LOG AUTENTIKASI
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-11">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 11: Audit Keamanan & Log</span>
  </div>

  <h1>Modul 11: Audit Keamanan & Log Autentikasi</h1>

  <h2>11.1 Log Autentikasi (`postauth.php`)</h2>
  <p>
    Tabel `radpostauth` mencatat setiap percobaan otentikasi yang masuk ke server FreeRADIUS, baik yang berhasil maupun yang ditolak:
  </p>

  <div class="grid-2">
    <div class="card-box" style="border-left: 4px solid #16a34a;">
      <strong style="color: #166534;">Access-Accept (Diterima)</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Kredensial username dan password cocok dengan data di database. Klien diizinkan terhubung ke jaringan internet.
      </p>
    </div>
    <div class="card-box" style="border-left: 4px solid #dc2626;">
      <strong style="color: #991b1b;">Access-Reject (Ditolak)</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Permintaan ditolak server. Penyebab umum: salah ketik password, akun berstatus disabled, kuota/masa aktif habis, atau melampaui Simultaneous-Use.
      </p>
    </div>
  </div>

  <p>
    Halaman `postauth.php` dilengkapi dengan <strong>Indikator Tingkat Keberhasilan (Success Rate %)</strong>. Jika tingkat keberhasilan turun di bawah 85%, administrator perlu waspada terhadap kemungkinan adanya serangan tebak sandi massal (<em>Brute-Force Attack</em>) atau kesalahan konfigurasi SSID WiFi.
  </p>

  <h2>11.2 Jejak Audit Aktivitas Operator (`audit.php`)</h2>
  <p>
    Untuk memenuhi standar kepatuhan tata kelola IT kampus (<em>IT Governance &amp; Compliance</em>), RadiusManager merekam seluruh aktivitas administratif ke dalam tabel `rm_audit_log`:
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Waktu (Timestamp)</th>
        <th>Operator</th>
        <th>Tindakan (Action)</th>
        <th>Target & Rincian (Detail)</th>
        <th>Alamat IP</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>2026-09-24 14:10:22</code></td>
        <td><code>administrator</code></td>
        <td><span class="badge badge-success">create_user</span></td>
        <td>User: <code>224312005</code> (Grup: Mhs2024, Limit: 1)</td>
        <td><code>192.168.1.15</code></td>
      </tr>
      <tr>
        <td><code>2026-09-24 14:15:05</code></td>
        <td><code>operator_it</code></td>
        <td><span class="badge badge-warning">toggle_user</span></td>
        <td>User: <code>206412005</code> status changed to DISABLED</td>
        <td><code>192.168.1.22</code></td>
      </tr>
      <tr>
        <td><code>2026-09-24 14:22:40</code></td>
        <td><code>administrator</code></td>
        <td><span class="badge badge-primary">create_nas</span></td>
        <td>NAS: <code>172.16.0.70</code> (Ruijie-AP-Core)</td>
        <td><code>192.168.1.15</code></td>
      </tr>
      <tr>
        <td><code>2026-09-24 15:01:18</code></td>
        <td><code>operator_it</code></td>
        <td><span class="badge badge-danger">disconnect_session</span></td>
        <td>Kicked user: <code>guest-01</code> on NAS 172.16.0.70</td>
        <td><code>192.168.1.22</code></td>
      </tr>
    </tbody>
  </table>

  <div class="callout callout-info">
    <strong>Integritas Jejak Audit:</strong>
    Tabel jejak audit bersifat <em>Append-Only</em> (hanya dapat bertambah). Tidak ada tombol hapus atau edit pada halaman audit untuk memastikan data log tidak dapat dimanipulasi oleh siapapun.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 15: MODUL 12 — HAK AKSES OPERATOR (RBAC)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-12">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 12: Hak Akses Operator (RBAC)</span>
  </div>

  <h1>Modul 12: Manajemen Hak Akses Operator Berjenjang (RBAC)</h1>

  <h2>12.1 Arsitektur Tiga Tingkatan Peran (Three-Tier RBAC)</h2>
  <p>
    Tidak semua staf IT memerlukan akses setara ke seluruh konfigurasi server. RadiusManager menerapkan sistem kendali akses berbasis peran (<em>Role-Based Access Control</em>) dengan 3 tingkatan:
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:18%;">Tingkat Peran</th>
        <th style="width:32%;">Deskripsi & Wewenang</th>
        <th style="width:50%;">Batasan Hak Akses</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="badge badge-danger">superadmin</span></td>
        <td><strong>Administrator Penuh:</strong> Memiliki wewenang mutlak ke seluruh modul, database, konfigurasi sistem, dan manajemen operator lain.</td>
        <td>Tidak ada batasan. Akses penuh ke seluruh fitur.</td>
      </tr>
      <tr>
        <td><span class="badge badge-primary">operator</span></td>
        <td><strong>Teknisi / Helpdesk Tingkat 2:</strong> Bertugas mengelola akun harian: menambah user, mereset password, membuat voucher, perpanjang masa aktif, kick sesi, dan mencetak laporan.</td>
        <td>
          &bull; <strong>Dilarang</strong> menambah atau menghapus perangkat NAS.<br>
          &bull; <strong>Dilarang</strong> melihat atau memodifikasi kredensial operator lain.<br>
          &bull; <strong>Dilarang</strong> mengakses halaman konfigurasi sensitif.
        </td>
      </tr>
      <tr>
        <td><span class="badge badge-slate">readonly</span></td>
        <td><strong>Helpdesk Tingkat 1 / Auditor:</strong> Hanya diperbolehkan memantau dashboard, melihat status koneksi user, memeriksa log postauth, dan melihat laporan.</td>
        <td>
          &bull; <strong>Dilarang melakukan mutasi data apapun.</strong><br>
          &bull; Tombol Tambah, Edit, Disable, dan Delete disembunyikan.<br>
          &bull; Jika mencoba memotong URL form, sistem langsung menolak dengan kode HTTP 403 Forbidden.
        </td>
      </tr>
    </tbody>
  </table>

  <h2>12.2 Mengelola Akun Operator (`operators.php`)</h2>
  <ol class="step-list">
    <li>Masuk menggunakan akun <strong>superadmin</strong> &rarr; buka menu <strong>Operators & RBAC</strong> di sidebar.</li>
    <li>
      <strong>Menambah Operator Baru:</strong> Klik <strong>+ Add Operator</strong> &rarr; masukkan username, nama lengkap, alamat email, kata sandi, dan pilih <strong>Role</strong> yang sesuai (`superadmin`, `operator`, atau `readonly`).
    </li>
    <li>
      <strong>Mengubah Peran / Reset Sandi:</strong> Klik tombol <strong>Edit</strong> pada akun operator target &rarr; ubah peran atau masukkan kata sandi baru &rarr; klik <strong>Save</strong>.
    </li>
  </ol>

  <div class="callout callout-warning">
    <strong>Proteksi Akun Sendiri (Self-Preservation):</strong>
    Seorang superadmin tidak dapat menghapus atau menurunkan peran akunnya sendiri untuk mencegah hilangnya akses superadmin secara tidak disengaja.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 16: MODUL 13 — PORTAL MANDIRI PENGGUNA (SUBSCRIBER PORTAL)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-13">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 13: Portal Mandiri Pengguna</span>
  </div>

  <h1>Modul 13: Portal Mandiri Pengguna (Self-Service Subscriber Portal)</h1>

  <h2>13.1 Tujuan & Keuntungan Portal Pengguna</h2>
  <p>
    Untuk mengurangi beban panggilan ke meja bantuan IT (*helpdesk*) terkait pertanyaan kuota, sisa masa aktif, atau permintaan reset password, RadiusManager dilengkapi dengan direktori khusus <strong>Portal Pengguna</strong> (`/portal/`) yang terpisah dari panel administrasi.
  </p>

  <div class="card-box">
    <strong>Tautan Akses Portal Siswa / Karyawan:</strong><br>
    <code style="font-size: 11pt;">http://&lt;IP-SERVER-ANDA&gt;/radiusmanager/radius-manager/portal/</code>
  </div>

  <h2>13.2 Alur Masuk Pengguna (`portal/login.php`)</h2>
  <p>
    Pengguna dapat login menggunakan kredensial akun WiFi masing-masing (NIM/NIP dan password yang sama dengan yang digunakan saat terhubung ke WiFi kampus). Sistem portal memverifikasi:
  </p>
  <ul style="margin-left: 20px; font-size: 8.5pt; line-height: 1.6;">
    <li>Kecocokan username dan sandi pada `radcheck`.</li>
    <li>Memastikan akun tidak sedang dalam kondisi <code>Auth-Type := Reject</code> (Disabled).</li>
    <li>Memastikan tanggal akun belum melewati batas <code>Expiration</code>.</li>
  </ul>

  <h2>13.3 Fitur Dasbor Pengguna (`portal/dashboard.php`)</h2>
  <div class="grid-2">
    <div class="card-box">
      <strong>1. Status Akun &amp; Paket Layanan:</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Menampilkan nama paket bandwidth aktif (contoh: <em>Mahasiswa-10Mbps</em>), batas kecepatan unduh/unggah, dan tanggal kadaluarsa akun.
      </p>
    </div>
    <div class="card-box">
      <strong>2. Grafik Batang Penggunaan Kuota:</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Bilah kemajuan (*progress bar*) visual yang menunjukkan persentase pemakaian kuota bulanan terhadap total kuota yang dialokasikan.
      </p>
    </div>
    <div class="card-box">
      <strong>3. Status Koneksi Terkini (Live Device):</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Menampilkan apakah akun sedang digunakan online saat ini, beserta IP address dan nama Access Point tempat perangkat tersambung.
      </p>
    </div>
    <div class="card-box">
      <strong>4. Riwayat 10 Sesi Koneksi Terakhir:</strong>
      <p style="font-size: 8.5pt; color: #475569; margin-top: 4px;">
        Tabel ringkas yang memuat riwayat penggunaan data pada sesi-sesi sebelumnya beserta durasi koneksi.
      </p>
    </div>
  </div>

  <h2>13.4 Penggantian Kata Sandi Mandiri</h2>
  <p>
    Pengguna dapat mengganti kata sandi WiFi mereka sendiri secara langsung di portal tanpa harus datang ke ruang server IT. Prosedur mewajibkan pengguna memasukkan <strong>Password Lama</strong> untuk verifikasi, diikuti <strong>Password Baru</strong> dan konfirmasi. Pembaruan sandi langsung disinkronkan ke database `radcheck`.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 17: MODUL 14 — INTEGRASI RESTFUL JSON API
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-14">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 14: Integrasi RESTful API</span>
  </div>

  <h1>Modul 14: Panduan Integrasi RESTful JSON API</h1>

  <h2>14.1 Arsitektur & Autentikasi API Engine</h2>
  <p>
    RadiusManager v1.8.0 menyediakan RESTful JSON API engine di bawah direktori <code>/api/</code> untuk integrasi dengan sistem eksternal kampus (Sistem Informasi Akademik / SIAKAD, Portal HR Kepegawaian, atau Aplikasi Billing Hotspot).
  </p>
  <p>
    Setiap permintaan API harus menyertakan kunci otorisasi rahasia pada header HTTP:
  </p>
  <pre><code>Authorization: Bearer &lt;API_KEY_ANDA&gt;
# atau menggunakan custom header:
X-API-Key: &lt;API_KEY_ANDA&gt;</code></pre>
  <p style="font-size:8.5pt; color:#64748b;">
    *Nilai <code>API_KEY</code> didefinisikan pada berkas <code>config.php</code>.
  </p>

  <h2>14.2 Daftar Endpoint Utama</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:12%;">Metode</th>
        <th style="width:28%;">Endpoint</th>
        <th style="width:60%;">Fungsi & Payload</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="badge badge-primary">GET</span></td>
        <td><code>/api/users.php</code></td>
        <td>Mengambil daftar user terpaginasi (dukungan parameter: <code>?page=1&amp;per_page=20&amp;q=keyword</code>).</td>
      </tr>
      <tr>
        <td><span class="badge badge-success">POST</span></td>
        <td><code>/api/users.php</code></td>
        <td>
          Membuat user baru secara terprogram.<br>
          Payload: <code>{"username":"22401","password":"secret","group":"Mhs2024","firstname":"Ali"}</code>
        </td>
      </tr>
      <tr>
        <td><span class="badge badge-warning">PUT</span></td>
        <td><code>/api/users.php</code></td>
        <td>
          Memperbarui password, status, atau grup pengguna.<br>
          Payload: <code>{"username":"22401","password":"newpass","disabled":false}</code>
        </td>
      </tr>
      <tr>
        <td><span class="badge badge-danger">DELETE</span></td>
        <td><code>/api/users.php?username=XYZ</code></td>
        <td>Menghapus akun pengguna beserta atribut terkait secara permanen.</td>
      </tr>
      <tr>
        <td><span class="badge badge-primary">GET</span></td>
        <td><code>/api/sessions.php</code></td>
        <td>Mendapatkan daftar seluruh sesi pengguna yang sedang aktif saat ini.</td>
      </tr>
      <tr>
        <td><span class="badge badge-danger">POST</span></td>
        <td><code>/api/sessions.php</code></td>
        <td>
          Memutus koneksi pengguna secara remote via CoA.<br>
          Payload: <code>{"username":"22401","nasip":"172.16.0.70"}</code>
        </td>
      </tr>
      <tr>
        <td><span class="badge badge-primary">GET</span></td>
        <td><code>/api/accounting.php</code></td>
        <td>Mengambil riwayat akuntansi pemakaian data (dukungan filter tanggal dan username).</td>
      </tr>
    </tbody>
  </table>

  <h2>14.3 Contoh Permintaan via cURL & Respon JSON</h2>
  <pre><code># Contoh: Mengambil data pengguna baru via cURL
curl -X GET "http://localhost/radiusmanager/radius-manager/api/users.php?username=206412005" \
     -H "Authorization: Bearer YOUR_SECRET_API_KEY"</code></pre>

  <p><strong>Contoh Respon JSON Terstandarisasi:</strong></p>
  <pre><code>{
  "success": true,
  "data": {
    "username": "206412005",
    "group": "Pegawai",
    "firstname": "Rheza",
    "lastname": "Raditya",
    "department": "Teknik Mesin",
    "disabled": false
  },
  "timestamp": 1790324800
}</code></pre>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 18: MODUL 15 — PEMELIHARAAN, TROUBLESHOOTING & FAQ
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-15">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan</span>
    <span>Modul 15: Pemeliharaan & Troubleshooting</span>
  </div>

  <h1>Modul 15: Pemeliharaan Sistem, Troubleshooting & FAQ</h1>

  <h2>15.1 Rutinitas Pencadangan Basis Data (Backup & Restore)</h2>
  <p>
    Seluruh konfigurasi pengguna dan jejak log tersimpan di database MariaDB/MySQL. Lakukan pencadangan berkala secara terjadwal:
  </p>
  <pre><code># 1. Perintah Backup Tanpa Mengunci Tabel (Single-Transaction):
mysqldump -u root -p --single-transaction --routines --triggers radius > /backup/radius_$(date +%F).sql

# 2. Perintah Pemulihan (Restore) Database:
mysql -u root -p radius < /backup/radius_2026-09-24.sql</code></pre>

  <h2>15.2 Panduan Pemecahan Masalah Umum (Troubleshooting)</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:25%;">Gejala Masalah</th>
        <th style="width:35%;">Kemungkinan Penyebab</th>
        <th style="width:40%;">Langkah Solusi Praktis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Pengguna Gagal Login (Access-Reject)</strong></td>
        <td>
          1. Salah kata sandi.<br>
          2. Akun dalam status Disabled.<br>
          3. Masa aktif habis.<br>
          4. Perangkat melebihi batas Simultaneous-Use.
        </td>
        <td>
          1. Buka halaman <code>postauth.php</code> untuk melihat alasan penolakan.<br>
          2. Cek status akun di <code>users.php</code> (pastikan tidak bertanda Disabled).<br>
          3. Perpanjang tanggal di <code>expiry-check.php</code> jika kadaluarsa.
        </td>
      </tr>
      <tr>
        <td><strong>Sesi Aktif Tidak Muncul di Web</strong></td>
        <td>
          1. Port UDP 1813 (Accounting) terblokir di firewall.<br>
          2. Router NAS belum mengaktifkan fitur Accounting.<br>
          3. Shared Secret NAS tidak sama.
        </td>
        <td>
          1. Buka port firewall di server: <code>sudo ufw allow 1812,1813/udp</code>.<br>
          2. Di MikroTik: pastikan centang opsi <code>accounting=yes</code> pada menu RADIUS.<br>
          3. Samakan secret di <code>nas.php</code> dan di router.
        </td>
      </tr>
      <tr>
        <td><strong>Tombol Disconnect Tidak Memutus Klien</strong></td>
        <td>
          Port UDP 3799 (CoA) di router NAS belum diizinkan atau tertutup firewall lokal.
        </td>
        <td>
          1. Di MikroTik: jalankan <code>/radius incoming set accept=yes port=3799</code>.<br>
          2. Di Ruijie AC: pastikan server RADIUS diizinkan mengirimkan paket CoA.
        </td>
      </tr>
      <tr>
        <td><strong>Indikator NAS Menunjukkan "Offline"</strong></td>
        <td>
          Server web tidak dapat melakukan ping ke IP gateway NAS (misal: beda subnet tanpa routing atau ICMP ping dinonaktifkan di router).
        </td>
        <td>
          1. Lakukan ping manual dari server ke IP router (contoh: <code>ping 172.16.0.70</code>).<br>
          2. Izinkan ICMP echo request pada firewall router NAS.
        </td>
      </tr>
    </tbody>
  </table>

  <h2>15.3 Tanya Jawab Umum (FAQ)</h2>
  <div class="card-box">
    <strong>T: Apakah RadiusManager aman menangani puluhan ribu mahasiswa?</strong><br>
    <span style="font-size: 8.5pt; color: #475569;">
      J: Ya, RadiusManager telah diuji dan berjalan mulus pada basis data berukuran lebih dari 2.3 GB dengan 940.000+ sesi akuntansi berkat skrip indeks komposit pada <code>install.sql</code>.
    </span>
  </div>

  <div class="card-box">
    <strong>T: Bagaimana cara memperbarui masa aktif seluruh mahasiswa seangkatan sekaligus?</strong><br>
    <span style="font-size: 8.5pt; color: #475569;">
      J: Buka menu <code>users.php</code> &rarr; cari nama grup (misal: <code>Mhs2024</code>) &rarr; centang Select All &rarr; gunakan bilah Batch Action untuk memperpanjang grup atau mengekspor data.
    </span>
  </div>

  <div style="margin-top: 25px; padding: 12px; background: #f1f5f9; border-radius: 6px; text-align: center; font-size: 8.5pt; color: #64748b;">
    <strong>RadiusManager v1.8.0 Enterprise Documentation</strong><br>
    Disusun untuk Keperluan Operasional Jaringan &amp; Panduan Pengguna Baru.<br>
    &copy; 2026 RadiusManager Project. Hak Cipta Dilindungi Undang-Undang.
  </div>
</div>

</body>
</html>
HTML;

$outputPath = __DIR__ . '/RadiusManager_User_Guide.html';
file_put_contents($outputPath, $html);
echo "HTML Guide successfully generated at: $outputPath\n";
echo "File size: " . strlen($html) . " bytes\n";
