<?php
/**
 * RadiusManager — Comprehensive User & Administrator Operational Guide Generator
 * Generates an exhaustive, printable, standalone HTML & PDF Manual covering every single feature.
 */

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RadiusManager — Modul Panduan Lengkap & Operasional Sistem (Comprehensive User & Administrator Guide)</title>
<style>
  /* ── CSS Reset & Base Print Styles ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  
  @page {
    size: A4 portrait;
    margin: 15mm 13mm 15mm 13mm;
  }
  
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 9.5pt;
    line-height: 1.52;
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

  h1 { font-size: 19pt; margin-bottom: 10px; color: #0f172a; }
  h2 { font-size: 13.5pt; margin-top: 16px; margin-bottom: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; color: #1e3a8a; }
  h3 { font-size: 11pt; margin-top: 12px; margin-bottom: 6px; color: #1e40af; }
  h4 { font-size: 10pt; margin-top: 10px; margin-bottom: 4px; color: #334155; }
  
  p { margin-bottom: 8px; }
  p:last-child { margin-bottom: 0; }
  
  strong { color: #0f172a; font-weight: 600; }
  code, pre { font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 8.5pt; }
  
  code {
    background-color: #f1f5f9;
    color: #0369a1;
    padding: 1.5px 5px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
  }
  
  pre {
    background-color: #0f172a;
    color: #f8fafc;
    padding: 9px 12px;
    border-radius: 6px;
    margin: 8px 0 10px 0;
    overflow-x: auto;
    line-height: 1.38;
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
    padding-bottom: 4px;
    margin-bottom: 14px;
    font-size: 8pt;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .doc-footer {
    border-top: 1px solid #e2e8f0;
    margin-top: 20px;
    padding-top: 5px;
    font-size: 7.5pt;
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
    padding: 18mm 10mm 15mm 10mm;
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
    margin-bottom: 18mm;
  }

  .cover-logo {
    width: 68px;
    height: 68px;
  }

  .cover-brand {
    font-size: 24pt;
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
    margin-bottom: 20mm;
  }

  .cover-pill {
    display: inline-block;
    background-color: #dbeafe;
    color: #1e40af;
    font-size: 8pt;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 14px;
    border: 1px solid #bfdbfe;
  }

  .cover-title {
    font-size: 25pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    margin-bottom: 12px;
    letter-spacing: -0.5px;
  }

  .cover-subtitle {
    font-size: 11.5pt;
    color: #475569;
    line-height: 1.45;
    font-weight: 400;
    max-width: 92%;
    margin-bottom: 20px;
  }

  .cover-specs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    max-width: 90%;
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
    padding-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 8pt;
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
    padding: 14px 18px;
    margin: 10px 0 16px 0;
  }

  .toc-title {
    font-size: 12.5pt;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 10px;
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
    gap: 6px 20px;
  }

  .toc-item {
    font-size: 8.5pt;
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
    font-size: 7.5pt;
  }

  /* ── UI Elements, Cards, Tables, Badges ── */
  .card-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 9px 12px;
    margin: 8px 0;
  }

  .table-custom {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0 12px 0;
    font-size: 8pt;
  }

  .table-custom th, .table-custom td {
    padding: 5px 8px;
    border: 1px solid #cbd5e1;
    text-align: left;
    vertical-align: top;
  }

  .table-custom th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 7pt;
    letter-spacing: 0.5px;
  }

  .table-custom tr:nth-child(even) td {
    background-color: #f8fafc;
  }

  /* Badges */
  .badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 7pt;
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
    padding: 9px 12px;
    border-radius: 6px;
    margin: 8px 0;
    font-size: 8.5pt;
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
    margin-bottom: 2px;
  }

  /* Step by Step List */
  .step-list {
    list-style: none;
    counter-reset: step-counter;
    margin: 8px 0;
  }

  .step-list li {
    counter-increment: step-counter;
    position: relative;
    padding-left: 28px;
    margin-bottom: 8px;
    font-size: 8.5pt;
  }

  .step-list li::before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    background-color: #2563eb;
    color: #ffffff;
    font-size: 7.5pt;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Grids */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 8px 0;
  }

  .grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin: 8px 0;
  }

  .kpi-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 10px;
    border-top: 3px solid #2563eb;
  }

  .kpi-mini-card.success { border-top-color: #16a34a; }
  .kpi-mini-card.warning { border-top-color: #d97706; }
  .kpi-mini-card.danger  { border-top-color: #dc2626; }

  .kpi-mini-title {
    font-size: 7pt;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }

  .kpi-mini-val {
    font-size: 12pt;
    font-weight: 800;
    color: #0f172a;
    margin-top: 1px;
  }

  .kpi-mini-desc {
    font-size: 7pt;
    color: #64748b;
  }

  .diagram-wrapper {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 10px;
    margin: 10px 0;
    text-align: center;
  }

  .diagram-caption {
    font-size: 7.5pt;
    color: #64748b;
    font-style: italic;
    margin-top: 4px;
  }
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 1: COVER PAGE
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page cover-page">
  <div class="cover-header">
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
    <div class="cover-pill">Dokumentasi Resmi & Panduan Seluruh Fitur</div>
    <div class="cover-title">MODUL PANDUAN PENGGUNAAN SISTEM LENGKAP</div>
    <div class="cover-subtitle">
      Buku Petunjuk Operasional Komprehensif: Menjelaskan Setiap Fitur, Form, Tombol, Alur Kerja, Pemecahan Masalah, dan Integrasi Portal untuk Seluruh Pengguna.
    </div>

    <div class="cover-specs">
      <div class="spec-item">
        <div class="spec-label">Versi Sistem</div>
        <div class="spec-value">RadiusManager v1.8.0 Enterprise</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Basis Data & RADIUS</div>
        <div class="spec-value">FreeRADIUS 3.x + MariaDB / MySQL</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Perangkat Jaringan</div>
        <div class="spec-value">Ruijie Networks, MikroTik, Cisco, Ubiquiti</div>
      </div>
      <div class="spec-item">
        <div class="spec-label">Cakupan Pengguna</div>
        <div class="spec-value">Superadmin, Operator, Readonly, Siswa/Pegawai</div>
      </div>
    </div>
  </div>

  <div class="cover-footer">
    <div class="cover-meta">
      <strong>RadiusManager Software Engineering & Network Team</strong>
      <span>Infrastruktur Jaringan & Server Autentikasi Kampus</span>
    </div>
    <div style="text-align:right;">
      <strong>Edisi Pembaruan: September 2026</strong>
      <span>Status: Standar Operasional Prosedur (SOP) Resmi</span>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 2: DAFTAR ISI & MATRIKS FITUR LENGKAP
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Daftar Isi & Matriks Fitur</span>
  </div>

  <h1>Daftar Isi & Matriks Fitur Sistem</h1>
  <p>
    Buku panduan ini menguraikan <strong>setiap fitur</strong> yang terdapat di dalam aplikasi RadiusManager v1.8.0, lengkap dengan petunjuk langkah-demi-langkah (<em>How-to</em>), tujuan teknis, dan contoh tindakan praktis.
  </p>

  <div class="toc-container">
    <div class="toc-title">
      <span>Daftar 20 Modul Panduan Fitur</span>
      <span style="font-size: 7.5pt; font-weight: normal; color: #64748b;">Rujukan Cepat Halaman</span>
    </div>
    <ul class="toc-list">
      <li class="toc-item"><a class="toc-link" href="#modul-1">1. Arsitektur & FreeRADIUS Database Core</a> <span class="toc-badge">Hal. 3</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-2">2. Akses Masuk, Keamanan & Dual-Source Auth</a> <span class="toc-badge">Hal. 4</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-3">3. Dashboard & Analisis Jaringan Tingkat Lanjut</a> <span class="toc-badge">Hal. 5</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-4">4. Manajemen Pengguna RADIUS (Users)</a> <span class="toc-badge">Hal. 6</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-4b">5. Operasi Massal Pengguna (Batch Toolbar)</a> <span class="toc-badge">Hal. 7</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-5">6. Grup Kebijakan & Atribut (Check & Reply)</a> <span class="toc-badge">Hal. 8</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-6">7. Paket Bandwidth Terpusat (Rate Plans)</a> <span class="toc-badge">Hal. 9</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-7">8. Perangkat NAS & Access Point (Ruijie/MikroTik)</a> <span class="toc-badge">Hal. 10</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-8">9. Manajemen Alokasi IP Pool (IP Pools)</a> <span class="toc-badge">Hal. 11</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-9">10. Hotspot & Generator Voucher Prabayar</a> <span class="toc-badge">Hal. 12</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-10">11. Peringatan Masa Aktif Akun (Expiry Warnings)</a> <span class="toc-badge">Hal. 13</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-11">12. Pemantauan Sesi Aktif & CoA Disconnect</a> <span class="toc-badge">Hal. 14</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-12">13. Riwayat Akuntansi & Analisis Pemutusan</a> <span class="toc-badge">Hal. 15</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-13">14. Laporan Eksekutif Jaringan & Cetak A4</a> <span class="toc-badge">Hal. 16</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-14">15. Audit Log Autentikasi (Post-Auth Logs)</a> <span class="toc-badge">Hal. 17</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-15">16. Jejak Audit Aktivitas Admin (Audit Trail)</a> <span class="toc-badge">Hal. 18</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-16">17. Mesin Ekspor Data Universal (Export Engine)</a> <span class="toc-badge">Hal. 19</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-17">18. Hak Akses Operator Berjenjang (RBAC)</a> <span class="toc-badge">Hal. 20</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-18">19. Portal Mandiri Pengguna (Subscriber Portal)</a> <span class="toc-badge">Hal. 21</span></li>
      <li class="toc-item"><a class="toc-link" href="#modul-19">20. Pengaturan Sistem, Profil & Diagnostik</a> <span class="toc-badge">Hal. 22</span></li>
    </ul>
  </div>

  <h2>Matriks Matriks Hak Akses Fitur (Permission Matrix)</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:30%;">Nama Modul / Fitur</th>
        <th style="width:22%; text-align:center;">Superadmin</th>
        <th style="width:22%; text-align:center;">Operator</th>
        <th style="width:26%; text-align:center;">Readonly (Auditor)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Dashboard & Grafik Analisis</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-primary">Lihat Saja</span></td>
      </tr>
      <tr>
        <td><strong>Manajemen Pengguna (CRUD & Batch)</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-slate">Lihat Saja</span></td>
      </tr>
      <tr>
        <td><strong>Grup & Rate Plans (Bandwidth)</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-slate">Lihat Saja</span></td>
      </tr>
      <tr>
        <td><strong>Perangkat NAS / Controller AP</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-danger">Terkunci</span></td>
        <td style="text-align:center;"><span class="badge badge-slate">Lihat Saja</span></td>
      </tr>
      <tr>
        <td><strong>Vouchers & Generator Hotspot</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-slate">Lihat Saja</span></td>
      </tr>
      <tr>
        <td><strong>Pemutusan Sesi (CoA Disconnect Kick)</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Bisa</span></td>
        <td style="text-align:center;"><span class="badge badge-success">Bisa</span></td>
        <td style="text-align:center;"><span class="badge badge-danger">Dilarang</span></td>
      </tr>
      <tr>
        <td><strong>Operators & RBAC Management</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-danger">Terkunci</span></td>
        <td style="text-align:center;"><span class="badge badge-danger">Terkunci</span></td>
      </tr>
      <tr>
        <td><strong>Jejak Audit Keamanan (Audit Log)</strong></td>
        <td style="text-align:center;"><span class="badge badge-success">Penuh</span></td>
        <td style="text-align:center;"><span class="badge badge-danger">Terkunci</span></td>
        <td style="text-align:center;"><span class="badge badge-primary">Lihat Saja</span></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 3: MODUL 1 — ARSITEKTUR & FREERADIUS CORE
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-1">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 1: Arsitektur & Database Core</span>
  </div>

  <h1>Modul 1: Arsitektur Sistem & FreeRADIUS Core</h1>
  
  <h2>1.1 Mengapa RadiusManager?</h2>
  <p>
    Sistem manajemen lawas seperti daloRADIUS seringkali mengalami <em>crash</em> atau kelambatan parah ketika menangani tabel akuntansi jutaan baris. <strong>RadiusManager</strong> dirancang khusus dengan arsitektur query <strong>SARGable</strong> yang ramah indeks, menjamin respons antarmuka di bawah <strong>15 milidetik</strong> bahkan saat menangani lebih dari 940.000 riwayat sesi.
  </p>

  <h2>1.2 Diagram Alir Interaksi 4 Lapis</h2>
  <div class="diagram-wrapper">
    <svg viewBox="0 0 700 180" width="100%" height="135" xmlns="http://www.w3.org/2000/svg">
      <!-- Layer 1 -->
      <rect x="10" y="25" width="120" height="60" rx="6" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.2"/>
      <text x="70" y="47" font-size="10" font-weight="bold" fill="#0f172a" text-anchor="middle">Klien / Gadget</text>
      <text x="70" y="62" font-size="7.5" fill="#64748b" text-anchor="middle">Mahasiswa / Pegawai</text>
      <text x="70" y="74" font-size="7" fill="#2563eb" text-anchor="middle">WPA2-Ent / Hotspot</text>

      <!-- Layer 2 -->
      <rect x="180" y="25" width="140" height="60" rx="6" fill="#eff6ff" stroke="#3b82f6" stroke-width="1.2"/>
      <text x="250" y="47" font-size="10" font-weight="bold" fill="#1e40af" text-anchor="middle">Ruijie / MikroTik NAS</text>
      <text x="250" y="62" font-size="7.5" fill="#475569" text-anchor="middle">Gateway 172.16.0.70</text>
      <text x="250" y="74" font-size="7" fill="#2563eb" text-anchor="middle">Radio Dot11radio</text>

      <!-- Layer 3 -->
      <rect x="370" y="25" width="130" height="60" rx="6" fill="#ecfdf5" stroke="#10b981" stroke-width="1.2"/>
      <text x="435" y="47" font-size="10" font-weight="bold" fill="#065f46" text-anchor="middle">FreeRADIUS Core</text>
      <text x="435" y="62" font-size="7.5" fill="#475569" text-anchor="middle">Port 1812 / 1813 UDP</text>
      <text x="435" y="74" font-size="7" fill="#16a34a" text-anchor="middle">Modul rlm_sql</text>

      <!-- Layer 4 -->
      <rect x="550" y="25" width="140" height="60" rx="6" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.2"/>
      <text x="620" y="47" font-size="10" font-weight="bold" fill="#92400e" text-anchor="middle">MariaDB Storage</text>
      <text x="620" y="62" font-size="7.5" fill="#475569" text-anchor="middle">Tabel rad* & rm_*</text>
      <text x="620" y="74" font-size="7" fill="#d97706" text-anchor="middle">Composite Indexes</text>

      <!-- Web Layer -->
      <rect x="250" y="115" width="440" height="50" rx="6" fill="#0f172a" stroke="#334155" stroke-width="1.2"/>
      <text x="470" y="137" font-size="10.5" font-weight="bold" fill="#38bdf8" text-anchor="middle">RadiusManager Web Administration & Self-Service Portal</text>
      <text x="470" y="152" font-size="7.5" fill="#94a3b8" text-anchor="middle">Users, Groups, Rate Plans, NAS Devices, Vouchers, Expiry Warnings, CoA Kick, REST API</text>

      <!-- Connections -->
      <line x1="130" y1="55" x2="180" y2="55" stroke="#2563eb" stroke-width="1.5"/>
      <line x1="320" y1="55" x2="370" y2="55" stroke="#2563eb" stroke-width="1.5"/>
      <line x1="500" y1="55" x2="550" y2="55" stroke="#16a34a" stroke-width="1.5"/>
      <line x1="470" y1="115" x2="470" y2="85" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="3"/>
    </svg>
    <div class="diagram-caption">Gambar 1.1 — Topologi Hubungan Antara Klien, Access Point Ruijie, FreeRADIUS Daemon, Database, dan RadiusManager</div>
  </div>

  <h2>1.3 Relasi Tabel Standar FreeRADIUS vs Tabel RadiusManager</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:22%;">Nama Tabel</th>
        <th style="width:28%;">Kunci / Kolom Utama</th>
        <th style="width:35%;">Tujuan & Peran Operasional</th>
        <th style="width:15%;">Tipe</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>radcheck</code></td>
        <td>username, attribute, op, value</td>
        <td>Menyimpan kata sandi (Cleartext-Password), masa aktif (Expiration), dan batas perangkat (Simultaneous-Use).</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>radreply</code></td>
        <td>username, attribute, op, value</td>
        <td>Atribut balasan individual, seperti penugasan IP Statis (<code>Framed-IP-Address</code>).</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>radusergroup</code></td>
        <td>username, groupname, priority</td>
        <td>Menghubungkan user dengan profil grup (misal: <code>Mhs2024</code>, <code>Pegawai</code>).</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>radgroupreply</code></td>
        <td>groupname, attribute, op, value</td>
        <td>Menyimpan limitasi bandwidth MikroTik dan WISPr yang otomatis diisi oleh Rate Plans.</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>nas</code></td>
        <td>nasname, shortname, type, secret</td>
        <td>Daftar gateway yang diizinkan (misal: Ruijie AC <code>172.16.0.70</code>).</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>radacct</code></td>
        <td>radacctid, username, acctstarttime, etc.</td>
        <td>Audit riwayat sesi, kuota upload/download (octets), IP, dan MAC address.</td>
        <td><span class="badge badge-primary">Core</span></td>
      </tr>
      <tr>
        <td><code>userinfo</code></td>
        <td>username, firstname, department, email</td>
        <td>Data profil mahasiswa/karyawan yang otomatis terintegrasi di seluruh tabel.</td>
        <td><span class="badge badge-success">Profil</span></td>
      </tr>
      <tr>
        <td><code>radippool</code></td>
        <td>pool_name, framedipaddress, expiry_time</td>
        <td>Daftar alokasi subnet IP Pool dinamis untuk klien WiFi.</td>
        <td><span class="badge badge-success">IP Pool</span></td>
      </tr>
      <tr>
        <td><code>rm_plans</code></td>
        <td>name, groupname, dl_kbps, ul_kbps</td>
        <td>Definisi paket kecepatan yang otomatis disinkronkan ke <code>radgroupreply</code>.</td>
        <td><span class="badge badge-purple">Ekstensi</span></td>
      </tr>
      <tr>
        <td><code>rm_vouchers</code></td>
        <td>batch_name, username, password, status</td>
        <td>Inventaris voucher hotspot (unused, active, expired) untuk tamu.</td>
        <td><span class="badge badge-purple">Ekstensi</span></td>
      </tr>
      <tr>
        <td><code>rm_audit_log</code></td>
        <td>operator, action, target, ip_address</td>
        <td>Catatan jejak aktivitas admin yang tidak dapat diubah (append-only).</td>
        <td><span class="badge badge-purple">Ekstensi</span></td>
      </tr>
      <tr>
        <td><code>operators</code></td>
        <td>username, password, role, lastlogin</td>
        <td>Akun staf admin lengkap dengan peran RBAC (superadmin, operator, readonly).</td>
        <td><span class="badge badge-purple">Ekstensi</span></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 4: MODUL 2 — AKSES SISTEM, KEAMANAN & LOGIN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-2">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 2: Akses Sistem & Keamanan</span>
  </div>

  <h1>Modul 2: Akses Sistem, Keamanan & Dual-Source Auth</h1>

  <h2>2.1 Alamat URL Akses</h2>
  <p>Panel web dapat dibuka melalui peramban web modern di alamat berikut:</p>
  <div class="card-box">
    <strong>Akses Panel Administrator:</strong><br>
    <code>http://&lt;IP-SERVER&gt;/radiusmanager/radius-manager/login.php</code><br>
    <span style="font-size:7.5pt; color:#64748b;">(Pada server lokal uji: <code>http://localhost/radiusmanager/radius-manager/login.php</code>)</span>
  </div>

  <h2>2.2 Skema Dual-Source Authentication (Cara Kerja)</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th>Sumber Akun</th>
        <th>Username Bawaan</th>
        <th>Password Bawaan</th>
        <th>Tingkat Peran</th>
        <th>Kapan Digunakan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>1. Database Operators</strong><br>(Tabel <code>operators</code>)</td>
        <td><code>administrator</code></td>
        <td><code>4dm1nNamloP</code></td>
        <td><span class="badge badge-danger">superadmin</span></td>
        <td>Digunakan untuk pekerjaan operasional harian. Mendukung bcrypt hash dan mencatat waktu login terakhir (<code>lastlogin</code>).</td>
      </tr>
      <tr>
        <td><strong>2. Config Emergency Fallback</strong><br>(Berkas <code>config.php</code>)</td>
        <td><code>admin</code></td>
        <td><code>admin123</code></td>
        <td><span class="badge badge-primary">superadmin</span></td>
        <td>Akun darurat jika database sedang dalam perbaikan atau pemulihan berkas.</td>
      </tr>
    </tbody>
  </table>

  <h2>2.3 Fitur Keamanan Bawaan</h2>
  <div class="grid-3">
    <div class="kpi-mini-card success">
      <div class="kpi-mini-title">CSRF Token Guard</div>
      <div class="kpi-mini-val" style="font-size:9pt; color:#16a34a;">Validasi Otomatis</div>
      <div class="kpi-mini-desc">Token kriptografi 32-byte pada setiap form POST mencegah serangan Cross-Site Request Forgery.</div>
    </div>
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Session Hijack Shield</div>
      <div class="kpi-mini-val" style="font-size:9pt; color:#2563eb;">Browser Fingerprint</div>
      <div class="kpi-mini-desc">Mengikat sesi operator ke User-Agent unik. Sesi otomatis ditolak jika cookie dipindah komputer.</div>
    </div>
    <div class="kpi-mini-card warning">
      <div class="kpi-mini-title">Inactivity Timeout</div>
      <div class="kpi-mini-val" style="font-size:9pt; color:#d97706;">60 Menit (3600s)</div>
      <div class="kpi-mini-desc">Sesi otomatis keluar jika layar ditinggalkan tanpa aktivitas untuk melindungi akses server.</div>
    </div>
  </div>

  <h2>2.4 Petunjuk Mengubah Kata Sandi Admin (How-to)</h2>
  <ol class="step-list">
    <li>
      <strong>Mengubah Password Akun Database (Disarankan):</strong> Buka menu <strong>Settings</strong> (`settings.php`) &rarr; masukkan Password Lama &rarr; ketikkan Password Baru (minimal 6 karakter) &rarr; klik <strong>Update Password</strong>.
    </li>
    <li>
      <strong>Mengubah Password Fallback di config.php:</strong> Buka terminal shell server dan jalankan perintah:
      <pre><code>php -r "echo password_hash('PasswordBaruKuat123', PASSWORD_DEFAULT);"</code></pre>
      Salin hash yang dihasilkan ke konstanta <code>APP_PASS</code> pada berkas <code>config.php</code>.
    </li>
  </ol>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 5: MODUL 3 — DASHBOARD & ANALISIS JARINGAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-3">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 3: Dashboard & Analisis</span>
  </div>

  <h1>Modul 3: Dashboard & Analisis Jaringan Tingkat Lanjut</h1>

  <h2>3.1 Kartu Metrik Utama (Live KPIs)</h2>
  <div class="grid-2">
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Total Users (Total Pengguna)</div>
      <div class="kpi-mini-val">2.845 <span style="font-size:8pt; font-weight:normal; color:#64748b;">Akun</span></div>
      <div class="kpi-mini-desc">Jumlah akun di <code>radcheck</code> (siswa, dosen, staf, dan voucher hotspot).</div>
    </div>
    <div class="kpi-mini-card success">
      <div class="kpi-mini-title">Active Connections (Sesi Live)</div>
      <div class="kpi-mini-val" style="color: #16a34a;">142 <span style="font-size:8pt; font-weight:normal; color:#64748b;">Perangkat</span></div>
      <div class="kpi-mini-desc">Pengguna yang sedang online detik ini di Access Point (<code>acctstoptime IS NULL</code>).</div>
    </div>
    <div class="kpi-mini-card warning">
      <div class="kpi-mini-title">Registered NAS (Gerbang Gateway)</div>
      <div class="kpi-mini-val" style="color: #d97706;">3 <span style="font-size:8pt; font-weight:normal; color:#64748b;">Unit Gateway</span></div>
      <div class="kpi-mini-desc">Access Controller terdaftar (Ruijie Core 172.16.0.70, Eduroam, MikroTik).</div>
    </div>
    <div class="kpi-mini-card">
      <div class="kpi-mini-title">Authentications Today (Hari Ini)</div>
      <div class="kpi-mini-val">8.412 <span style="font-size:8pt; font-weight:normal; color:#64748b;">Requests</span></div>
      <div class="kpi-mini-desc">Permintaan autentikasi yang masuk ke server sejak pukul 00:00 tengah malam.</div>
    </div>
  </div>

  <h2>3.2 Tiga Grafik Analisis Visual (Chart.js)</h2>
  <ol class="step-list">
    <li>
      <strong>Grafik Profil Sesi 24 Jam (Hourly Trajectory):</strong>
      <p>
        Menampilkan kurva fluktuasi koneksi per jam antara <strong>Hari Ini (biru pekat)</strong> dan <strong>Kemarin (garis putus-putus)</strong>. Membantu administrator memprediksi puncak kepadatan bandwidth pada jam kuliah (pukul 09:00 dan 14:00).
      </p>
    </li>
    <li>
      <strong>Grafik Tren Bandwidth 14 Hari (Bandwidth Volume Trends):</strong>
      <p>
        Membandingkan volume trafik <strong>Download (hijau)</strong> dan <strong>Upload (biru)</strong> dalam MegaByte/GigaByte selama dua minggu terakhir untuk evaluasi langganan ISP kampus.
      </p>
    </li>
    <li>
      <strong>Diagram Distribusi Access Point / NAS:</strong>
      <p>
        Diagram batang horizontal yang menunjukkan gateway mana yang menanggung trafik paling berat (misal: <code>Ruijie-AP-Core</code> memproses 85% lalu lintas).
      </p>
    </li>
  </ol>

  <h2>3.3 Live Active Sessions Preview</h2>
  <p>
    Bagian bawah dashboard menampilkan pratinjau 5 koneksi terbaru beserta alamat IP klien, MAC address kartu jaringan, dan tombol pintas untuk langsung membuka halaman pemantauan sesi lengkap.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 6: MODUL 4 — MANAJEMEN PENGGUNA (USERS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-4">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 4: Manajemen Pengguna (Users)</span>
  </div>

  <h1>Modul 4: Manajemen Pengguna RADIUS (Users)</h1>

  <h2>4.1 Menjelajahi Antarmuka Pengguna (`users.php`)</h2>
  <p>Halaman ini menampilkan seluruh akun dengan fitur pencarian dan indikator status visual:</p>
  <table class="table-custom">
    <thead>
      <tr>
        <th>Elemen / Kolom</th>
        <th>Keterangan</th>
        <th>Contoh Nilai</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Status Badge</strong></td>
        <td>Kondisi konektivitas dan keaktifan akun.</td>
        <td>
          <span class="badge badge-success">Online</span> (Klien aktif)<br>
          <span class="badge badge-slate">Offline</span> (Tidak tersambung)<br>
          <span class="badge badge-danger">Disabled</span> (Dinonaktifkan)
        </td>
      </tr>
      <tr>
        <td><strong>Username & Password</strong></td>
        <td>NIM mahasiswa atau NIP staf, dilengkapi tombol mata untuk melihat teks asli sandi.</td>
        <td><code>206412005</code> &bull; <code>••••••••</code> <span class="badge badge-slate">Lihat</span></td>
      </tr>
      <tr>
        <td><strong>Group Badge</strong></td>
        <td>Kelompok paket dan kebijakan kecepatan.</td>
        <td><span class="badge badge-primary">Pegawai</span>, <span class="badge badge-warning">Mhs2024</span></td>
      </tr>
      <tr>
        <td><strong>User Profile</strong></td>
        <td>Nama lengkap, jurusan/unit kerja, dan email dari tabel <code>userinfo</code>.</td>
        <td><strong>Rheza Raditya</strong><br><small>Teknik Mesin &bull; rheza@polman.ac.id</small></td>
      </tr>
      <tr>
        <td><strong>IP Address</strong></td>
        <td>IP Statis (jika ada) atau IP dinamis yang sedang digunakan.</td>
        <td><code>172.16.10.45</code></td>
      </tr>
    </tbody>
  </table>

  <h2>4.2 Petunjuk Menambah Pengguna Baru (`user-add.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Users</strong> &rarr; klik tombol <strong>+ Add User</strong> di sudut kanan atas.</li>
    <li>
      <strong>Data Kredensial:</strong> Masukkan <strong>Username</strong> (contoh: NIM <code>224312005</code>). Masukkan <strong>Password</strong> atau klik tombol <strong>Generate</strong> untuk membuat sandi acak otomatis yang aman.
    </li>
    <li>
      <strong>Data Profil (userinfo):</strong> Isi <strong>Full Name</strong>, <strong>Department</strong> (misal: <em>Teknik Otomasi Manufaktur</em>), dan <strong>Email</strong>.
    </li>
    <li>
      <strong>Batasan RADIUS:</strong>
      <ul style="margin-left: 15px; margin-top: 3px;">
        <li><strong>Group:</strong> Pilih kelompok layanan (misal: <code>Mhs2024</code>).</li>
        <li><strong>Simultaneous-Use:</strong> Jumlah maksimal perangkat per akun (standar: <code>1</code>).</li>
        <li><strong>Expiration Date:</strong> Tanggal berakhir masa aktif akun (kosongkan jika tanpa batas).</li>
        <li><strong>Static IP (Framed-IP-Address):</strong> Isi IP statis jika akun dialokasikan untuk server lab atau printer.</li>
      </ul>
    </li>
    <li>Klik <strong>Save User</strong>. Sistem menyimpan akun secara transaksional dan mencatatnya ke Log Audit.</li>
  </ol>

  <h2>4.3 Fitur Soft-Disable (Nonaktifkan Sementara Tanpa Hapus)</h2>
  <div class="card-box">
    <strong>Mekanisme Kerja Soft-Disable:</strong><br>
    Saat Anda mengklik tombol <strong>Disable</strong> (ikon saklar/power), sistem menyisipkan atribut <code>Auth-Type := Reject</code> ke tabel <code>radcheck</code>.<br>
    <span style="font-size:7.5pt; color:#475569;">
      &bull; <strong>Dampak:</strong> Pengguna langsung ditolak saat mencoba login ke WiFi.<br>
      &bull; <strong>Keutuhan Data:</strong> Password asli, profil mahasiswa, dan riwayat sesi <strong>tidak hilang</strong>.<br>
      &bull; <strong>Mengaktifkan Kembali:</strong> Cukup klik tombol <strong>Enable</strong> satu kali, atribut Reject akan otomatis dihapus.
    </span>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 7: MODUL 5 — OPERASI MASSAL PENGGUNA (BATCH ACTIONS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-4b">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 5: Operasi Massal Pengguna</span>
  </div>

  <h1>Modul 5: Operasi Massal Pengguna (Batch Actions Toolbar)</h1>

  <h2>5.1 Bilah Alat Melayang (Sticky Batch Toolbar)</h2>
  <p>
    Ketika mengelola ratusan mahasiswa baru atau kenaikan semester, melakukan perubahan akun satu per satu memakan waktu lama. RadiusManager menyediakan fitur <strong>Batch Actions Toolbar</strong> interaktif yang muncul otomatis saat Anda mencentang satu atau lebih kotak centang di tabel pengguna:
  </p>

  <div class="card-box" style="background:#0f172a; color:#f8fafc; border-color:#334155; margin:8px 0;">
    <span style="color: #38bdf8; font-weight: bold;">[24 users selected]</span> &mdash; Choose Batch Action: 
    <span class="badge badge-success">Enable Accounts</span> 
    <span class="badge badge-warning">Disable Accounts</span> 
    <span class="badge badge-primary">Change Group</span> 
    <span class="badge badge-slate">Export CSV</span> 
    <span class="badge badge-danger">Delete Permanently</span>
  </div>

  <h2>5.2 Panduan Langkah Operasi Massal (How-to)</h2>
  <ol class="step-list">
    <li>
      <strong>Memilih Pengguna:</strong> Buka halaman <code>users.php</code>. Centang kotak di sebelah kiri nama pengguna yang diinginkan, atau centang kotak di baris judul (header) untuk memilih seluruh pengguna di halaman tersebut sekaligus.
    </li>
    <li>
      <strong>Operasi Bulk Enable / Disable:</strong>
      Pilih opsi <em>Enable</em> atau <em>Disable</em> pada bilah alat &rarr; klik tombol <strong>Apply Action</strong>. Sistem akan menyisipkan atau menghapus atribut <code>Auth-Type := Reject</code> pada seluruh akun terpilih dalam 1 query transaksi SQL berkecepatan tinggi.
    </li>
    <li>
      <strong>Operasi Bulk Change Group (Pindah Kelas/Grup Massal):</strong>
      Pilih opsi <em>Change Group</em> &rarr; pilih nama grup target (misal: memindahkan 50 mahasiswa dari <code>Mhs2023</code> ke <code>Mhs2024</code>) &rarr; klik <strong>Apply Action</strong>. Atribut <code>radusergroup</code> akan diperbarui secara serentak tanpa mengubah password mereka.
    </li>
    <li>
      <strong>Operasi Bulk Export CSV:</strong>
      Pilih opsi <em>Export CSV</em> untuk langsung mengunduh berkas spreadsheet berisi kredensial dan profil lengkap akun-akun terpilih.
    </li>
    <li>
      <strong>Operasi Bulk Delete (Penghapusan Massal):</strong>
      Pilih opsi <em>Delete Permanently</em> &rarr; konfirmasikan dialog keamanan. Sistem akan mengeksekusi penghapusan berantai (*cascade cleanup*) pada tabel <code>radcheck</code>, <code>radreply</code>, <code>radusergroup</code>, dan <code>userinfo</code>.
    </li>
  </ol>

  <h2>5.3 Mengedit Pengguna Individual (`user-edit.php`)</h2>
  <p>
    Klik ikon <strong>Pencil (Edit)</strong> pada baris manapun untuk membuka formulir edit lengkap. Di halaman ini administrator dapat:
  </p>
  <ul style="margin-left: 20px; font-size: 8.5pt; line-height: 1.55;">
    <li>Mengganti password baru (kosongkan kolom jika tidak ingin mengubah sandi lama).</li>
    <li>Mengganti grup paket bandwidth.</li>
    <li>Menambah tanggal kadaluarsa (Expiration Date).</li>
    <li>Memperbarui data jurusan/email di <code>userinfo</code>.</li>
    <li><strong>Melihat Riwayat Sesi Pengguna:</strong> Di bagian bawah form edit, sistem menampilkan tabel riwayat 10 sesi terakhir khusus untuk pengguna tersebut (waktu mulai, durasi, dan total data yang dihabiskan).</li>
  </ul>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 8: MODUL 6 — GRUP KEBIJAKAN & ATRIBUT (GROUPS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-5">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 6: Grup Kebijakan & Atribut</span>
  </div>

  <h1>Modul 6: Grup Kebijakan & Editor Atribut RADIUS</h1>

  <h2>6.1 Konsep Check Attributes vs Reply Attributes</h2>
  <p>
    Setiap kebijakan jaringan diatur melalui dua kategori atribut pada grup FreeRADIUS:
  </p>
  <div class="grid-2">
    <div class="card-box" style="border-left: 4px solid #2563eb;">
      <strong style="color: #1e40af;">Check Attributes (radgroupcheck)</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 3px;">
        Syarat yang <strong>harus dipenuhi</strong> klien sebelum diizinkan masuk. Contoh: <code>Auth-Type := Local</code> (jenis otentikasi) atau <code>Simultaneous-Use := 1</code> (hanya boleh login di 1 gadget).
      </p>
    </div>
    <div class="card-box" style="border-left: 4px solid #16a34a;">
      <strong style="color: #166534;">Reply Attributes (radgroupreply)</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 3px;">
        Konfigurasi yang <strong>dikirimkan ke router NAS</strong> setelah login berhasil. Contoh: batas kecepatan <code>Mikrotik-Rate-Limit</code>, <code>WISPr-Bandwidth</code>, VLAN ID, atau alokasi pool IP.
      </p>
    </div>
  </div>

  <h2>6.2 Panduan Menambah Grup Baru (`groups.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Groups</strong> &rarr; pada kartu <em>Create New Group</em>, masukkan <strong>Group Name</strong> (contoh: <code>Dosen-Khusus</code> tanpa spasi).</li>
    <li>Klik tombol <strong>Create Group</strong>. Sistem akan membuat grup baru dengan inisialisasi <code>Auth-Type := Local</code>.</li>
  </ol>

  <h2>6.3 Panduan Mengelola Atribut Grup (How-to)</h2>
  <ol class="step-list">
    <li>Pada halaman <code>groups.php</code>, klik salah satu nama grup pada daftar kiri (contoh: <code>Pegawai</code>).</li>
    <li>
      <strong>Menambah Atribut Check Baru:</strong> Klik <strong>+ Add Check Attribute</strong> &rarr; pilih nama atribut (misal: <code>Simultaneous-Use</code>) &rarr; pilih operator <code>:=</code> &rarr; masukkan nilai <code>1</code> &rarr; simpan.
    </li>
    <li>
      <strong>Menambah Atribut Reply Baru:</strong> Klik <strong>+ Add Reply Attribute</strong> &rarr; pilih nama atribut (misal: <code>Filter-Id</code> atau <code>Framed-Pool</code>) &rarr; masukkan nilai &rarr; simpan.
    </li>
    <li>
      <strong>Menghapus Atribut:</strong> Klik ikon tempat sampah kecil di sebelah kanan baris atribut yang tidak diperlukan lagi.
    </li>
  </ol>

  <h2>6.4 Melihat Anggota Grup (Group Members Listing)</h2>
  <p>
    Di sisi kanan bawah halaman grup, terdapat daftar seluruh pengguna yang tergabung dalam grup tersebut lengkap dengan nama asli mahasiswa/staf dan tautan cepat untuk membuka profil mereka.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 9: MODUL 7 — PAKET KECEPATAN (RATE PLANS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-6">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 7: Paket Bandwidth (Rate Plans)</span>
  </div>

  <h1>Modul 7: Paket Bandwidth Terpusat (Rate Plans)</h1>

  <h2>7.1 Fungsi Sinkronisasi Otomatis Rate Plans</h2>
  <p>
    RadiusManager menyediakan modul <strong>Rate Plans</strong> (`plans.php`) agar administrator tidak perlu menghafal sintaks atribut router yang rumit. Cukup tentukan kecepatan dalam Kbps/Mbps, dan sistem secara otomatis menyelaraskan seluruh standar industri ke tabel `radgroupreply`:
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Atribut Terbitan Otomatis</th>
        <th>Contoh Nilai</th>
        <th>Target Perangkat & Penjelasan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>Mikrotik-Rate-Limit</code></td>
        <td><code>5120k/10240k</code></td>
        <td><strong>MikroTik RouterOS:</strong> Mengatur Simple Queue (Upload 5M / Download 10M).</td>
      </tr>
      <tr>
        <td><code>WISPr-Bandwidth-Max-Down</code></td>
        <td><code>10485760</code></td>
        <td><strong>Ruijie, Cisco, Ubiquiti, Ruckus:</strong> Standar WiFi Alliance dalam satuan bit/s (10 Mbps).</td>
      </tr>
      <tr>
        <td><code>WISPr-Bandwidth-Max-Up</code></td>
        <td><code>5242880</code></td>
        <td><strong>Ruijie, Cisco, Ubiquiti, Ruckus:</strong> Standar WiFi Alliance dalam satuan bit/s (5 Mbps).</td>
      </tr>
      <tr>
        <td><code>Session-Timeout</code></td>
        <td><code>86400</code></td>
        <td><strong>Semua Router:</strong> Batas durasi maksimal per sesi dalam detik (contoh: 24 jam).</td>
      </tr>
      <tr>
        <td><code>ChilliSpot-Max-Total-Octets</code></td>
        <td><code>5368709120</code></td>
        <td><strong>Captive Portal:</strong> Batas kuota total data transfer bulanan (contoh: 5 GB).</td>
      </tr>
    </tbody>
  </table>

  <h2>7.2 Petunjuk Membuat Paket Baru (`plan-add.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Rate Plans</strong> &rarr; klik <strong>+ Add Rate Plan</strong>.</li>
    <li>
      Isi formulir spesifikasi paket:
      <ul style="margin-left: 15px; margin-top: 3px;">
        <li><strong>Plan Name:</strong> Nama paket (contoh: <code>Mahasiswa-10Mbps</code>).</li>
        <li><strong>Target Group:</strong> Pilih grup asosiasi di database (contoh: <code>Mhs2024</code>).</li>
        <li><strong>Download Speed (Kbps):</strong> Kecepatan unduh maksimal (contoh: <code>10240</code> untuk 10 Mbps).</li>
        <li><strong>Upload Speed (Kbps):</strong> Kecepatan unggah maksimal (contoh: <code>5120</code> untuk 5 Mbps).</li>
        <li><strong>Data Quota (MB):</strong> Kuota data bulanan (isi <code>0</code> jika tanpa batas / unlimited).</li>
        <li><strong>Session Validity (Hours):</strong> Durasi sesi (isi <code>0</code> jika tanpa batas).</li>
      </ul>
    </li>
    <li>Klik <strong>Create Plan</strong>. Sistem akan menyimpan paket dan menerbitkan atribut ke `radgroupreply`.</li>
  </ol>

  <h2>7.3 Meninjau Pelanggan Aktif per Paket (Subscribers)</h2>
  <p>
    Tabel utama `plans.php` menampilkan kolom <strong>Subscribers</strong> yang menghitung jumlah pengguna aktif secara live. Mengklik angka pelanggan akan langsung membuka daftar pengguna terkait di halaman `users.php`.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 10: MODUL 8 — PERANGKAT NAS & ACCESS POINT
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-7">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 8: Perangkat NAS & AP</span>
  </div>

  <h1>Modul 8: Perangkat NAS & Access Point (Ruijie / MikroTik)</h1>

  <h2>8.1 Integrasi Ruijie Networks Wireless AC (`172.16.0.70`)</h2>
  <p>
    Pada jaringan kampus, pusat kendali seluruh radio Access Point adalah <strong>Ruijie Wireless Access Controller</strong> dengan gateway IP <code>172.16.0.70</code>. RadiusManager v1.8.0 mendukung Ruijie sebagai tipe perangkat kelas satu dengan lencana biru berikon router:
  </p>

  <div class="card-box">
    <strong>Profil Konfigurasi Ruijie Core di RadiusManager:</strong><br>
    &bull; <strong>IP Address:</strong> <code>172.16.0.70</code><br>
    &bull; <strong>Short Name:</strong> <code>Ruijie-AP-Core</code><br>
    &bull; <strong>Device Type:</strong> <span class="badge badge-primary">Ruijie</span><br>
    &bull; <strong>Port Autentikasi:</strong> <code>1812</code> &bull; <strong>Port Akuntansi:</strong> <code>1813</code><br>
    &bull; <strong>Shared Secret:</strong> <code>4dm1nNamloP</code><br>
    &bull; <strong>Identifikasi Radio:</strong> Port dilaporkan sebagai <code>Dot11radio x/0.x</code> pada log akuntansi.
  </div>

  <h2>8.2 Petunjuk Mendaftarkan NAS Baru (`nas-add.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>NAS Devices</strong> pada bilah navigasi kiri &rarr; klik tombol <strong>+ Add NAS</strong>.</li>
    <li>
      Lengkapi kolom konfigurasi:
      <ul style="margin-left: 15px; margin-top: 3px;">
        <li><strong>IP Address / CIDR / Hostname:</strong> Masukkan IP statis router (misal: <code>172.16.0.70</code>) atau subnet (misal: <code>192.168.1.0/24</code>).</li>
        <li><strong>Short Name:</strong> Nama panggilan ringkas tanpa spasi (misal: <code>Ruijie-AP-Core</code>).</li>
        <li><strong>Device Type:</strong> Pilih dari dropdown: <code>Ruijie</code>, <code>MikroTik</code>, <code>Cisco</code>, <code>Ubiquiti</code>, <code>Ruckus</code>, <code>Huawei</code>, <code>ZTE</code>, atau <code>Other</code>.</li>
        <li><strong>Ports:</strong> Port autentikasi RADIUS (standar: <code>1812</code>).</li>
        <li><strong>Shared Secret:</strong> Kata sandi rahasia bersama yang harus sama persis dengan pengaturan di controller.</li>
        <li><strong>Description:</strong> Catatan lokasi perangkat (misal: <em>Core Wireless Controller - Gedung Rektorat Lt. 2</em>).</li>
      </ul>
    </li>
    <li>Klik <strong>Add NAS</strong>.</li>
  </ol>

  <h2>8.3 Pemantauan Status & Latensi Ping (How-to)</h2>
  <p>
    Tabel `nas.php` melakukan uji soket ICMP ping otomatis ke setiap gateway:
  </p>
  <table class="table-custom">
    <tr>
      <th style="width:20%;">Lencana Status</th>
      <th style="width:25%;">Kondisi Perangkat</th>
      <th style="width:55%;">Tindakan Administrator</th>
    </tr>
    <tr>
      <td><span class="badge badge-success">Online (ms)</span></td>
      <td>Perangkat merespons cepat (contoh: <code>1.4ms</code>).</td>
      <td>Normal. Gateway siap melayani otentikasi.</td>
    </tr>
    <tr>
      <td><span class="badge badge-slate">Subnet</span></td>
      <td>Definisi wildcard (contoh: <code>0.0.0.0/0</code>).</td>
      <td>Normal untuk penangkap jaringan luas.</td>
    </tr>
    <tr>
      <td><span class="badge badge-danger">Offline</span></td>
      <td>Perangkat tidak merespons dalam batas waktu probe.</td>
      <td>Periksa kabel uplink, daya router, atau firewall.</td>
    </tr>
  </table>
  <p style="font-size:7.5pt; color:#64748b;">
    *Tips: Klik tombol <strong>Check Status</strong> di sudut kanan atas untuk memperbarui latensi seluruh perangkat secara live.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 11: MODUL 9 — ALOKASI IP POOL (IP POOLS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-8">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 9: Manajemen IP Pool</span>
  </div>

  <h1>Modul 9: Manajemen Alokasi IP Pool (IP Pools)</h1>

  <h2>9.1 Fungsi Tabel `radippool` di FreeRADIUS</h2>
  <p>
    Ketika FreeRADIUS difungsikan untuk mendistribusikan alamat IP secara terpusat ke klien (menggantikan DHCP router lokal), tabel `radippool` digunakan untuk mengelola sewa IP (*IP Leases*), masa kedaluwarsa sewa (*expiry time*), dan pemetaan ke NAS tertentu.
  </p>

  <h2>9.2 Menambah Rentang IP Baru ke Pool (`ippool.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>IP Pools</strong> pada sidebar navigasi.</li>
    <li>
      Pada formulir <em>Add IP Addresses to Pool</em>:
      <ul style="margin-left: 15px; margin-top: 3px;">
        <li><strong>Pool Name:</strong> Pilih pool yang sudah ada atau ketikkan nama pool baru (contoh: <code>pool-mahasiswa</code>).</li>
        <li><strong>Input Mode:</strong> Pilih <code>Single IP</code> (1 alamat) atau <code>IP Range</code> (rentang IP sekaligus, misal: <code>10.10.1.1</code> hingga <code>10.10.1.254</code>).</li>
        <li><strong>NAS IP Address:</strong> Isi IP gateway yang melayani pool ini (contoh: <code>172.16.0.70</code>) atau kosongkan jika berlaku untuk semua NAS.</li>
      </ul>
    </li>
    <li>Klik <strong>Add IP Addresses</strong>. Sistem akan membuat entri IP pada basis data.</li>
  </ol>

  <h2>9.3 Melepas Sewa IP (Release IP Lease) & Menghapus IP</h2>
  <p>
    Jika terjadi konflik IP atau alamat IP tersangkut (*stuck lease*) pada perangkat klien yang sudah tidak aktif:
  </p>
  <div class="card-box">
    <strong>Langkah Melepas IP Kembali ke Pool:</strong><br>
    1. Temukan baris IP pada tabel <code>ippool.php</code> yang bertanda status <span class="badge badge-warning">Allocated</span>.<br>
    2. Klik tombol <strong>Release</strong> (ikon gembok terbuka).<br>
    3. Sistem seketika mengosongkan username penyewa dan memundurkan waktu <code>expiry_time</code>, sehingga IP tersebut langsung berstatus <span class="badge badge-success">Free</span> dan dapat digunakan klien lain.
  </div>

  <h2>9.4 Pemantauan Utilisasi Pool (Usage Progress Bar)</h2>
  <p>
    Di bagian atas halaman `ippool.php`, terdapat kartu ringkasan utilisasi yang menampilkan: Total Alamat IP, Jumlah Terpakai (Allocated), Jumlah Bebas (Free), dan bilah persentase pemakaian. Jika utilisasi mencapai &gt;85%, segera tambahkan rentang IP baru agar klien tidak mengalami gagal dapat IP.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 12: MODUL 10 — HOTSPOT & VOUCHER PRABAYAR
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-9">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 10: Hotspot & Voucher</span>
  </div>

  <h1>Modul 10: Hotspot & Generator Voucher Prabayar</h1>

  <h2>10.1 Kasus Penggunaan Voucher Hotspot</h2>
  <p>
    Untuk tamu seminar kampus, peserta ujian seleksi, atau pengunjung perpustakaan, membuat akun permanen tidak efisien. Modul <strong>Vouchers & Hotspot</strong> (`vouchers.php`) memungkinkan pembuatan ratusan voucher internet sementara dengan masa berlaku fleksibel.
  </p>

  <h2>10.2 Petunjuk Membuat Batch Voucher Baru (`voucher-generate.php`)</h2>
  <ol class="step-list">
    <li>Buka menu <strong>Vouchers & Hotspot</strong> &rarr; klik tombol <strong>+ Generate Vouchers</strong>.</li>
    <li>
      Isi formulir parameter batch:
      <ul style="margin-left: 15px; margin-top: 3px;">
        <li><strong>Batch Name:</strong> Nama penanda kegiatan (contoh: <code>Seminar-AI-2026</code> atau <code>Guest-Okt</code>).</li>
        <li><strong>Number of Vouchers:</strong> Jumlah kartu yang ingin dicetak (antara 1 hingga 200 lembar per batch).</li>
        <li><strong>Username Prefix:</strong> Awalan nama pengguna (contoh: <code>tamu-</code> atau <code>wifi-</code>).</li>
        <li><strong>Password Format:</strong> Pilih <code>Numeric (PIN 6-Digit)</code> agar ramah smartphone, atau <code>Alphanumeric (6 Karakter)</code> untuk keamanan lebih tinggi.</li>
        <li><strong>Rate Plan:</strong> Pilih paket kecepatan bandwidth yang telah dibuat di Modul 7 (contoh: <code>Tamu-5Mbps</code>).</li>
        <li><strong>Validity (Days):</strong> Masa berlaku voucher dalam hitungan hari (contoh: <code>1</code> hari atau <code>7</code> hari).</li>
      </ul>
    </li>
    <li>Klik <strong>Generate Vouchers</strong>. Akun seketika diterbitkan ke <code>rm_vouchers</code>, <code>radcheck</code>, dan <code>radusergroup</code>.</li>
  </ol>

  <h2>10.3 Mencetak Kartu Voucher Siap Gunting (`voucher-print.php`)</h2>
  <p>
    Setelah batch berhasil dibuat, klik tombol <strong>Print Cards</strong>. Sistem menyajikan tata letak kartu A4 standar (grid 4&times;5) siap dipotong dengan gunting kertas:
  </p>

  <div class="card-box" style="border: 1px dashed #3b82f6; max-width: 300px; margin: 8px auto; background: #ffffff;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:3px; margin-bottom:5px;">
      <span style="font-weight:bold; font-size:8.5pt; color:#1e40af;">WiFi Hotspot Kampus</span>
      <span class="badge badge-primary">1 HARI</span>
    </div>
    <div style="font-size:7.5pt; color:#475569;">SSID: <strong>@WiFi-Kampus-Guest</strong></div>
    <div style="background:#f1f5f9; padding:5px; border-radius:4px; margin:5px 0; font-family:monospace; font-size:8.5pt;">
      User: <strong>tamu-84291</strong><br>
      Pass: <strong>749210</strong>
    </div>
    <div style="font-size:6.5pt; color:#64748b; line-height:1.2;">
      Kecepatan: Up to 5 Mbps &bull; Buka browser &amp; login pada portal captive.
    </div>
  </div>

  <h2>10.4 Siklus Status Voucher</h2>
  <table class="table-custom">
    <tr>
      <th style="width:20%;">Status</th>
      <th style="width:40%;">Kondisi di Lapangan</th>
      <th style="width:40%;">Perilaku Sistem RADIUS</th>
    </tr>
    <tr>
      <td><span class="badge badge-success">unused</span></td>
      <td>Voucher telah dicetak namun belum pernah digunakan untuk login.</td>
      <td>Siap diautentikasi pertama kali di Access Point.</td>
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
     HALAMAN 13: MODUL 11 — PERINGATAN KADALUARSA (EXPIRY)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-10">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 11: Peringatan Kadaluarsa</span>
  </div>

  <h1>Modul 11: Sistem Peringatan Masa Aktif (Expiry Warnings)</h1>

  <h2>11.1 Mengapa Pemantauan Masa Aktif Krusial?</h2>
  <p>
    Setiap mahasiswa memiliki batas masa studi per semester. Modul <strong>Expiry Warnings</strong> (`expiry-check.php`) secara proaktif memantau tanggal <code>Expiration</code> pada `radcheck` dan memberikan peringatan <strong>7 hari</strong> sebelum akun mati mendadak.
  </p>

  <h2>11.2 Tiga Tab Kategori Dasbor Expiry</h2>
  <div class="grid-3">
    <div class="kpi-mini-card danger">
      <div class="kpi-mini-title">Expired (Sudah Lewat)</div>
      <div class="kpi-mini-val" style="color: #dc2626;">Akun Nonaktif</div>
      <div class="kpi-mini-desc">Akun yang masa aktifnya telah terlampaui. User sudah ditolak server.</div>
    </div>
    <div class="kpi-mini-card warning">
      <div class="kpi-mini-title">Expiring Soon (Akan Habis)</div>
      <div class="kpi-mini-val" style="color: #d97706;">&le; 7 Hari Lagi</div>
      <div class="kpi-mini-desc">Akun yang membutuhkan perpanjangan semester dalam waktu dekat.</div>
    </div>
    <div class="kpi-mini-card success">
      <div class="kpi-mini-title">Active Monitored (Aktif)</div>
      <div class="kpi-mini-val" style="color: #16a34a;">&gt; 7 Hari</div>
      <div class="kpi-mini-desc">Akun normal dengan masa berlaku yang masih panjang dan aman.</div>
    </div>
  </div>

  <h2>11.3 Tombol Perpanjangan Cepat 1-Klik (Quick Extension)</h2>
  <p>
    Untuk mempercepat layanan helpdesk saat registrasi semester, operator dapat memperpanjang masa aktif akun secara instan langsung di tabel:
  </p>
  <div class="card-box">
    <strong>Tombol Aksi Cepat per Baris Akun:</strong><br>
    &bull; <span class="badge badge-primary">+7 Days</span> : Menambahkan 7 hari ke tanggal kadaluarsa saat ini.<br>
    &bull; <span class="badge badge-success">+30 Days</span> : Menambahkan 30 hari (1 bulan kalender).<br>
    &bull; <span class="badge badge-purple">+90 Days</span> : Menambahkan 90 hari (1 triwulan / semester).<br>
    &bull; <span class="badge badge-danger">Disable Now</span> : Segera mematikan akun jika mahasiswa terbukti drop out / lulus.
  </div>

  <h2>11.4 Otomatisasi Terjadwal via CLI / Cron Job</h2>
  <p>
    Skrip dapat dijalankan tanpa peramban (*headless*) oleh server setiap tengah malam:
  </p>
  <pre><code># Perintah eksekusi CLI manual atau via cron:
php /var/www/html/radiusmanager/radius-manager/expiry-check.php --cli</code></pre>
  <p><strong>Contoh Konfigurasi Crontab Linux (Pukul 00:05 WIB setiap malam):</strong></p>
  <pre><code>5 0 * * * /usr/bin/php /var/www/html/radiusmanager/radius-manager/expiry-check.php --cli >> /var/log/radius_expiry.log 2>&1</code></pre>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 14: MODUL 12 — SESI AKTIF & PEMUTUSAN COA
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-11">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 12: Sesi Aktif & CoA Disconnect</span>
  </div>

  <h1>Modul 12: Pemantauan Sesi Aktif & CoA Disconnect Helper</h1>

  <h2>12.1 Pemantauan Koneksi Real-Time (`sessions.php`)</h2>
  <p>
    Halaman <strong>Active Sessions</strong> menampilkan seluruh klien yang sedang terhubung detik ini. Antarmuka melakukan <strong>Auto-Refresh setiap 30 detik</strong> secara otomatis.
  </p>

  <table class="table-custom">
    <thead>
      <tr>
        <th>Kolom Sesi</th>
        <th>Sumber Data</th>
        <th>Informasi yang Disajikan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>User & Profile</strong></td>
        <td><code>username</code> + <code>userinfo</code></td>
        <td>NIM/NIP, Nama Lengkap Siswa/Staf, dan Jurusan/Departemen.</td>
      </tr>
      <tr>
        <td><strong>Framed IP Address</strong></td>
        <td><code>framedipaddress</code></td>
        <td>Alamat IP lokal yang sedang dipinjam perangkat klien.</td>
      </tr>
      <tr>
        <td><strong>MAC Address</strong></td>
        <td><code>callingstationid</code></td>
        <td>Identitas fisik kartu jaringan perangkat klien (HP / Laptop).</td>
      </tr>
      <tr>
        <td><strong>Access Point / NAS</strong></td>
        <td><code>nasipaddress</code> / shortname</td>
        <td>Gerbang gateway tempat klien tersambung (contoh: <code>Ruijie-AP-Core</code>).</td>
      </tr>
      <tr>
        <td><strong>Start Time & Duration</strong></td>
        <td><code>acctstarttime</code></td>
        <td>Waktu awal klien terhubung dan durasi aktif berjalan (Jam:Menit:Detik).</td>
      </tr>
      <tr>
        <td><strong>Data Transferred</strong></td>
        <td><code>acctinput/outputoctets</code></td>
        <td>Akumulasi volume upload dan download selama sesi berlangsung.</td>
      </tr>
    </tbody>
  </table>

  <h2>12.2 Prosedur Memutus Paksa Sesi Klien (Kick / CoA Disconnect)</h2>
  <ol class="step-list">
    <li>Pada halaman <code>sessions.php</code>, temukan pengguna yang melanggar aturan atau menyedot bandwidth abnormal.</li>
    <li>Klik tombol merah <strong>Disconnect</strong> di sisi kanan tabel.</li>
    <li>
      Jendela konfirmasi modal akan muncul menampilkan helper perintah <code>radclient</code>:
      <pre><code>echo "User-Name=206412005,Acct-Session-Id=sess_001a" | radclient -x 172.16.0.70:3799 disconnect '4dm1nNamloP'</code></pre>
    </li>
    <li>
      Klik tombol <strong>Execute Disconnect</strong>. Sistem web akan menembakkan paket UDP socket ke port <strong>3799</strong> controller router target untuk memerintahkan pemutusan koneksi radio secara instan.
    </li>
  </ol>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 15: MODUL 13 — RIWAYAT AKUNTANSI (ACCOUNTING)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-12">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 13: Riwayat Akuntansi</span>
  </div>

  <h1>Modul 13: Riwayat Akuntansi & Analisis Pemutusan (Accounting)</h1>

  <h2>13.1 Audit Sesi Lampau (`accounting.php`)</h2>
  <p>
    Tabel `radacct` menyimpan seluruh sesi historis yang pernah terjadi di kampus. Pada instalasi besar dengan lebih dari <strong>940.000 baris rekaman</strong>, RadiusManager menggunakan query rentang tanggal SARGable berbasis indeks (`acctstarttime &gt;= ? AND acctstoptime &lt;= ?`) sehingga pencarian selesai dalam &lt;50 milidetik.
  </p>

  <h2>13.2 Analisis Alasan Pemutusan (Terminate Cause)</h2>
  <p>
    Kolom <code>acctterminatecause</code> menjelaskan alasan fisik terputusnya koneksi klien:
  </p>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:25%;">Nilai Terminate Cause</th>
        <th style="width:35%;">Arti Kondisi di Lapangan</th>
        <th style="width:40%;">Tindakan Administrator</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>User-Request</code></td>
        <td>Pengguna secara sadar mematikan WiFi atau menekan Disconnect di HP-nya.</td>
        <td>Normal. Tidak ada kendala jaringan.</td>
      </tr>
      <tr>
        <td><code>Lost-Carrier</code></td>
        <td>Klien keluar dari jangkauan sinyal AP atau baterai laptop mati mendadak.</td>
        <td>Evaluasi *blind spot* sinyal jika terjadi berulang di gedung tertentu.</td>
      </tr>
      <tr>
        <td><code>Session-Timeout</code></td>
        <td>Batas waktu sesi paket (misal 24 jam) telah tercapai.</td>
        <td>Normal sesuai kebijakan paket bandwidth.</td>
      </tr>
      <tr>
        <td><code>Idle-Timeout</code></td>
        <td>Klien tidak mengirim/menerima data sama sekali selama periode tertentu.</td>
        <td>Normal untuk membebaskan alokasi IP agar tidak terbuang.</td>
      </tr>
      <tr>
        <td><code>Admin-Reset</code></td>
        <td>Koneksi diputus secara paksa oleh administrator melalui fitur CoA Disconnect.</td>
        <td>Tercatat pada log audit keamanan.</td>
      </tr>
    </tbody>
  </table>

  <h2>13.3 Filter Pencarian Akuntansi</h2>
  <p>
    Gunakan kolom filter di bagian atas halaman <code>accounting.php</code> untuk menyaring sesi berdasarkan: Rentang Tanggal Mulai/Selesai, Username Mahasiswa tertentu, atau Alamat IP Gateway NAS tertentu.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 16: MODUL 14 — LAPORAN EKSEKUTIF (REPORTS)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-13">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 14: Laporan Eksekutif Jaringan</span>
  </div>

  <h1>Modul 14: Laporan Eksekutif Jaringan & Cetak A4 (Reports)</h1>

  <h2>14.1 Modul Laporan Tingkat Pimpinan (`reports.php`)</h2>
  <p>
    Modul <strong>Reports</strong> merangkum jutaan data transaksi jaringan menjadi ringkasan analitik yang siap dipresentasikan pada rapat evaluasi bulanan pimpinan kampus.
  </p>

  <h2>14.2 Komponen Laporan yang Disajikan</h2>
  <ol class="step-list">
    <li>
      <strong>Preset Rentang Waktu Instan:</strong> Tombol pilihan cepat: <em>This Month (Bulan Ini)</em>, <em>Last Month (Bulan Lalu)</em>, <em>Last 30 Days</em>, <em>This Year</em>, atau rentang kustom.
    </li>
    <li>
      <strong>4 Kartu KPI Eksekutif:</strong> Total Sesi Terlayani, Pengguna Unik (Distinct Users), Akumulasi Total Bandwidth (Upload & Download dalam GB/TB), dan Rata-rata Durasi Sesi.
    </li>
    <li>
      <strong>Tabel Top 10 Konsumen Terboros:</strong> Menampilkan 10 mahasiswa/staf dengan konsumsi data terbesar, lengkap dengan NIM/NIP, Nama Lengkap dari `userinfo`, dan total gigabyte yang dihabiskan.
    </li>
    <li>
      <strong>Distribusi Bandwidth per Grup:</strong> Menilai porsi penggunaan bandwidth antara grup <code>Mhs2024</code>, <code>Pegawai</code>, dan <code>Tamu</code>.
    </li>
    <li>
      <strong>Rekapitulasi Trafik per Access Point:</strong> Mengetahui gedung atau controller yang menampung beban trafik tertinggi.
    </li>
  </ol>

  <h2>14.3 Panduan Mencetak & Ekspor Laporan (How-to)</h2>
  <div class="grid-2">
    <div class="card-box">
      <strong>Mode Cetak Bersih (Print View A4):</strong><br>
      Klik tombol <strong>Print Report</strong>. CSS cetak khusus (`@media print`) akan menyembunyikan sidebar, topbar, dan tombol aksi, menyisakan tata letak dokumen resmi berlogo kampus siap cetak ke kertas atau simpan ke PDF via browser.
    </div>
    <div class="card-box">
      <strong>Ekspor Spreadsheet CSV:</strong><br>
      Klik tombol <strong>Export CSV</strong> (`reports.php?export=csv`). Berkas spreadsheet akan terunduh secara instan berisi seluruh data ringkasan untuk diolah lebih lanjut di Microsoft Excel.
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 17: MODUL 15 — AUDIT LOG AUTENTIKASI (POST-AUTH)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-14">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 15: Log Autentikasi Post-Auth</span>
  </div>

  <h1>Modul 15: Audit Log Autentikasi (`postauth.php`)</h1>

  <h2>15.1 Memeriksa Catatan Percobaan Login (`radpostauth`)</h2>
  <p>
    Tabel `radpostauth` mencatat setiap paket Access-Request yang masuk ke FreeRADIUS server, baik yang berhasil diverifikasi maupun yang ditolak:
  </p>

  <div class="grid-2">
    <div class="card-box" style="border-left: 4px solid #16a34a;">
      <strong style="color: #166534;">Access-Accept (Berhasil Masuk)</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 3px;">
        Kredensial username dan password cocok dengan data di database. Klien diizinkan terhubung ke jaringan internet.
      </p>
    </div>
    <div class="card-box" style="border-left: 4px solid #dc2626;">
      <strong style="color: #991b1b;">Access-Reject (Ditolak Server)</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 3px;">
        Permintaan ditolak. Penyebab umum: salah ketik password, akun disabled, masa aktif habis, atau melebihi Simultaneous-Use.
      </p>
    </div>
  </div>

  <h2>15.2 Kalkulator Persentase Keberhasilan (Success Rate %)</h2>
  <p>
    Di bagian atas halaman, terdapat indikator <strong>Success Rate</strong> yang menghitung rasio login berhasil terhadap total percobaan:
  </p>
  <ul style="margin-left: 20px; font-size: 8.5pt; line-height: 1.55;">
    <li><strong>Kondisi Normal (&gt;85%):</strong> Jaringan dalam kondisi prima dan mayoritas pengguna memasukkan sandi dengan benar.</li>
    <li><strong>Kondisi Waspada (&lt;70%):</strong> Menunjukkan kemungkinan adanya serangan tebak sandi massal (<em>Brute-Force Attack</em>) pada SSID tertentu atau terdapat banyak mahasiswa yang lupa memperbarui sandi setelah pergantian semester.</li>
  </ul>

  <h2>15.3 Menyaring Log Berdasarkan Status</h2>
  <p>
    Gunakan tab filter: <strong>All Requests</strong> (semua percobaan), <strong>Accepted Only</strong> (hanya yang berhasil), atau <strong>Rejected Only</strong> (hanya yang gagal) untuk mempercepat diagnosis keluhan pengguna yang tidak bisa login.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 18: MODUL 16 — JEJAK AUDIT OPERATOR (AUDIT TRAIL)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-15">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 16: Jejak Audit Operator</span>
  </div>

  <h1>Modul 16: Jejak Audit Aktivitas Operator (`audit.php`)</h1>

  <h2>16.1 Kepatuhan Tata Kelola IT (Governance & Compliance)</h2>
  <p>
    Untuk mencegah penyalahgunaan wewenang dan melacak setiap perubahan data pengguna atau jaringan, RadiusManager mencatat seluruh tindakan administratif ke dalam tabel `rm_audit_log`.
  </p>

  <h2>16.2 Kolom Rekaman Jejak Audit</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th>Waktu (Timestamp)</th>
        <th>Operator</th>
        <th>Tindakan (Action)</th>
        <th>Target & Rincian Perubahan</th>
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

  <h2>16.3 Karakteristik Log Append-Only & Filter Pencarian</h2>
  <div class="callout callout-info">
    <strong>Integritas Rekaman Audit:</strong>
    Tabel jejak audit bersifat <em>Append-Only</em> (hanya dapat menambah data). Tidak ada tombol edit atau hapus di antarmuka web untuk menjamin rekaman forensik tidak dapat dimanipulasi oleh operator manapun.
  </div>
  <p>
    Gunakan bilah filter di atas tabel untuk menyaring log berdasarkan: Nama Operator, Kategori Tindakan (create, update, delete, toggle, disconnect, login), atau Kata Kunci NIM target.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 19: MODUL 17 — MESIN EKSPOR UNIVERSAL (EXPORT)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-16">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 17: Mesin Ekspor Data Universal</span>
  </div>

  <h1>Modul 17: Mesin Ekspor Data Universal (`export.php`)</h1>

  <h2>17.1 Arsitektur Streaming CSV Berperforma Tinggi</h2>
  <p>
    RadiusManager dilengkapi mesin ekspor universal berbasis *streaming output buffer* (`export.php`). Alih-alih memuat ratusan ribu data ke memori RAM yang berisiko menyebabkan *memory exhaustion error*, sistem mengalirkan data baris-per-baris langsung ke klien peramban web dengan penambahan header <strong>UTF-8 BOM</strong> agar karakter aksen dan format nomor terbaca sempurna di Microsoft Excel.
  </p>

  <h2>17.2 Empat Tipe Ekspor Data Utama</h2>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:25%;">Tipe Ekspor (URL Parameter)</th>
        <th style="width:40%;">Kolom Data yang Dihasilkan</th>
        <th style="width:35%;">Penggunaan & Manfaat</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><code>export.php?type=users</code></td>
        <td>Username, Password, Groups, First Name, Last Name, Department, Email, Online Status.</td>
        <td>Cadangan data pengguna atau sinkronisasi dengan Sistem Akademik kampus.</td>
      </tr>
      <tr>
        <td><code>export.php?type=accounting</code></td>
        <td>Username, Start Time, Stop Time, Duration, Upload Bytes, Download Bytes, NAS IP, Terminate Cause.</td>
        <td>Laporan pemakaian data bulanan untuk audit billing atau analisis kapasitas jaringan.</td>
      </tr>
      <tr>
        <td><code>export.php?type=audit</code></td>
        <td>Timestamp, Operator, Action, Target, Detail, IP Address.</td>
        <td>Laporan kepatuhan forensik IT untuk auditor independen.</td>
      </tr>
      <tr>
        <td><code>export.php?type=vouchers</code></td>
        <td>Batch Name, Username, Password, Plan Name, Status (unused/active/expired), Created At, Used At.</td>
        <td>Rekapitulasi inventaris kartu voucher hotspot tamu.</td>
      </tr>
    </tbody>
  </table>

  <h2>17.3 Cara Melakukan Ekspor Data (How-to)</h2>
  <ol class="step-list">
    <li>Buka halaman yang sesuai (misal: <strong>Users</strong> atau <strong>Audit Log</strong>).</li>
    <li>Jika ingin mengekspor seluruh data, langsung klik tombol <strong>Export CSV</strong> di bilah aksi atas.</li>
    <li>Jika ingin mengekspor data tertentu saja, lakukan pencarian terlebih dahulu (misal: cari departemen <em>Teknik Mesin</em>), lalu klik <strong>Export CSV</strong>. Sistem akan mengekspor data yang sesuai dengan kriteria filter tersebut.</li>
  </ol>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 20: MODUL 18 — HAK AKSES OPERATOR (RBAC)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-17">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 18: Hak Akses Operator (RBAC)</span>
  </div>

  <h1>Modul 18: Hak Akses Operator Berjenjang (RBAC)</h1>

  <h2>18.1 Tiga Tingkatan Peran Operator (3-Tier RBAC)</h2>
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
        <td><strong>Administrator Penuh:</strong> Wewenang mutlak ke seluruh modul, konfigurasi database, manajemen NAS, dan manajemen akun operator lain.</td>
        <td>Tidak ada batasan. Akses penuh ke seluruh fitur.</td>
      </tr>
      <tr>
        <td><span class="badge badge-primary">operator</span></td>
        <td><strong>Teknisi / Helpdesk Tingkat 2:</strong> Bertugas mengelola akun harian: menambah user, mereset password, membuat voucher, perpanjang masa aktif, kick sesi, dan cetak laporan.</td>
        <td>
          &bull; <strong>Dilarang</strong> menambah atau menghapus perangkat NAS.<br>
          &bull; <strong>Dilarang</strong> memodifikasi akun operator lain.<br>
          &bull; <strong>Dilarang</strong> mengakses halaman pengaturan sensitif.
        </td>
      </tr>
      <tr>
        <td><span class="badge badge-slate">readonly</span></td>
        <td><strong>Auditor / Helpdesk Tingkat 1:</strong> Khusus untuk melihat dasbor, memeriksa status koneksi siswa, log postauth, dan laporan bulanan.</td>
        <td>
          &bull; <strong>Dilarang melakukan perubahan data apapun.</strong><br>
          &bull; Seluruh tombol Tambah, Edit, Disable, dan Delete disembunyikan.<br>
          &bull; Jika mencoba memotong URL form, sistem menolak dengan kode HTTP 403 Forbidden.
        </td>
      </tr>
    </tbody>
  </table>

  <h2>18.2 Petunjuk Mengelola Operator (`operators.php`)</h2>
  <ol class="step-list">
    <li>Masuk menggunakan akun <strong>superadmin</strong> &rarr; buka menu <strong>Operators & RBAC</strong> di sidebar.</li>
    <li>
      <strong>Menambah Operator Baru:</strong> Klik <strong>+ Add Operator</strong> &rarr; masukkan Username, Nama Lengkap, Email, Kata Sandi, dan pilih <strong>Role</strong> yang sesuai (`superadmin`, `operator`, atau `readonly`).
    </li>
    <li>
      <strong>Mengubah Peran / Reset Sandi:</strong> Klik tombol <strong>Edit</strong> pada akun operator target &rarr; ubah peran atau masukkan kata sandi baru &rarr; simpan.
    </li>
  </ol>

  <div class="callout callout-warning">
    <strong>Proteksi Akun Sendiri (Self-Preservation Guard):</strong>
    Seorang superadmin tidak dapat menghapus atau menurunkan peran akunnya sendiri untuk mencegah hilangnya akses superadmin secara tidak disengaja.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 21: MODUL 19 — PORTAL MANDIRI PENGGUNA (PORTAL)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-18">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 19: Portal Mandiri Pengguna</span>
  </div>

  <h1>Modul 19: Portal Mandiri Pengguna (Subscriber Portal)</h1>

  <h2>19.1 Tujuan & Keuntungan Portal Mandiri</h2>
  <p>
    Untuk mengurangi antrean di meja bantuan IT terkait keluhan lupa password atau pertanyaan sisa kuota internet, RadiusManager menyediakan <strong>Portal Pengguna Mandiri</strong> di subdirektori <code>/portal/</code> yang terpisah dari panel administrasi.
  </p>

  <div class="card-box">
    <strong>Tautan Akses Portal Siswa / Karyawan:</strong><br>
    <code style="font-size:10pt;">http://&lt;IP-SERVER&gt;/radiusmanager/radius-manager/portal/</code><br>
    <span style="font-size:7.5pt; color:#64748b;">(Klien yang belum masuk otomatis diarahkan ke <code>portal/login.php</code>)</span>
  </div>

  <h2>19.2 Cara Masuk Pengguna (`portal/login.php`)</h2>
  <p>
    Siswa atau pegawai login menggunakan akun WiFi mereka masing-masing:
  </p>
  <ul style="margin-left: 20px; font-size: 8.5pt; line-height: 1.55;">
    <li><strong>Username:</strong> Nomor Induk Mahasiswa (NIM), NIP staf, atau kode voucher tamu.</li>
    <li><strong>Password:</strong> Kata sandi akun WiFi RADIUS mereka.</li>
    <li>Sistem secara otomatis memverifikasi password di <code>radcheck</code>, memastikan akun tidak berstatus <code>Disabled</code>, dan tanggal akun belum kadaluarsa.</li>
  </ul>

  <h2>19.3 Fitur di Dasbor Pengguna (`portal/dashboard.php`)</h2>
  <div class="grid-2">
    <div class="card-box">
      <strong>1. Status Akun &amp; Paket Layanan:</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 2px;">
        Menampilkan status aktif, nama paket (misal: <em>Mahasiswa-10Mbps</em>), batas kecepatan, dan tanggal kadaluarsa akun.
      </p>
    </div>
    <div class="card-box">
      <strong>2. Grafik Batang Pemakaian Kuota:</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 2px;">
        Bilah kemajuan visual yang menunjukkan persentase pemakaian kuota data bulanan terhadap total kuota yang dialokasikan.
      </p>
    </div>
    <div class="card-box">
      <strong>3. Status Koneksi Terkini (Live Device):</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 2px;">
        Menampilkan apakah akun sedang online saat ini, beserta IP address dan nama Access Point tempat perangkat tersambung.
      </p>
    </div>
    <div class="card-box">
      <strong>4. Riwayat 10 Sesi Koneksi Terakhir:</strong>
      <p style="font-size: 8pt; color: #475569; margin-top: 2px;">
        Tabel ringkas yang memuat riwayat koneksi sebelumnya beserta durasi dan volume data yang dihabiskan.
      </p>
    </div>
  </div>

  <h2>19.4 Fitur Ubah Kata Sandi Mandiri (Self-Service Password Change)</h2>
  <p>
    Pengguna dapat mengubah kata sandi WiFi mereka sendiri secara langsung di portal. Pengguna diwajibkan memasukkan <strong>Password Lama</strong> untuk validasi keamanan sebelum memasukkan <strong>Password Baru</strong> dan konfirmasi. Pembaruan sandi langsung disinkronkan ke basis data `radcheck`.
  </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     HALAMAN 22: MODUL 20 — PENGATURAN SISTEM & DIAGNOSTIK
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="page page-break" id="modul-19">
  <div class="doc-header">
    <span>RadiusManager v1.8.0 — Modul Pelatihan Lengkap</span>
    <span>Modul 20: Pengaturan & Diagnostik</span>
  </div>

  <h1>Modul 20: Pengaturan Sistem & Diagnostik (`settings.php`)</h1>

  <h2>20.1 Halaman Pengaturan Akun Operator</h2>
  <p>
    Halaman <strong>Settings</strong> (`settings.php`) menyediakan dua modul utama untuk mengelola preferensi operator yang sedang masuk:
  </p>
  <ol class="step-list">
    <li>
      <strong>Ubah Kata Sandi Operator:</strong> Masukkan Password Saat Ini &rarr; masukkan Password Baru (minimal 6 karakter) &rarr; konfirmasi kata sandi &rarr; simpan. Sistem akan mengenkripsi sandi dengan standar bcrypt terbaru.
    </li>
    <li>
      <strong>Perbarui Profil Diri:</strong> Memperbarui Nama Depan, Nama Belakang, Unit Kerja/Departemen, Alamat Email, dan Nomor Telepon langsung di database.
    </li>
  </ol>

  <h2>20.2 Panel Diagnostik Server & FreeRADIUS Service Info</h2>
  <p>
    Di sisi kanan halaman pengaturan, terdapat kartu diagnostik kesehatan server secara transparan:
  </p>
  <table class="table-custom">
    <thead>
      <tr>
        <th style="width:35%;">Indikator Sistem</th>
        <th style="width:65%;">Nilai & Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Status Koneksi Database</strong></td>
        <td><span class="badge badge-success">Connected</span> (MariaDB / MySQL localhost:3306)</td>
      </tr>
      <tr>
        <td><strong>Versi Mesin PHP</strong></td>
        <td>PHP 8.2+ (Ekstensi aktif: <code>pdo_mysql</code>, <code>mbstring</code>, <code>openssl</code>)</td>
      </tr>
      <tr>
        <td><strong>Kapasitas Memori & Eksekusi</strong></td>
        <td>Memory Limit: <code>512M</code> &bull; Max Execution Time: <code>300s</code></td>
      </tr>
      <tr>
        <td><strong>Jumlah Baris Tabel Akuntansi</strong></td>
        <td><code>941.600+</code> baris rekaman tersimpan di <code>radacct</code></td>
      </tr>
      <tr>
        <td><strong>Status Indeks SARGable</strong></td>
        <td><span class="badge badge-success">Optimized</span> (Indeks komposit aktif dari install.sql)</td>
      </tr>
    </tbody>
  </table>

  <h2>20.3 Prosedur Cadangan Rutin (Backup & Restore)</h2>
  <pre><code># 1. Perintah Cadangan Database (Single-Transaction tanpa mengunci tabel):
mysqldump -u root -p --single-transaction --routines --triggers radius > /backup/radius_$(date +%F).sql

# 2. Perintah Pemulihan (Restore) Database:
mysql -u root -p radius < /backup/radius_2026-09-28.sql</code></pre>

  <div style="margin-top: 20px; padding: 10px; background: #f1f5f9; border-radius: 6px; text-align: center; font-size: 8pt; color: #64748b;">
    <strong>RadiusManager v1.8.0 Enterprise Documentation</strong> &bull; Seluruh Fitur Telah Terdokumentasi Lengkap.<br>
    &copy; 2026 RadiusManager Project. Hak Cipta Dilindungi Undang-Undang.
  </div>
</div>

</body>
</html>
HTML;

$outputPath = __DIR__ . '/RadiusManager_User_Guide.html';
file_put_contents($outputPath, $html);
echo "Exhaustive HTML Guide successfully generated at: $outputPath\n";
echo "File size: " . strlen($html) . " bytes\n";
