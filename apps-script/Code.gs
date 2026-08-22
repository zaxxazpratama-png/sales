// ================================================
// GOOGLE APPS SCRIPT - FORMULIR PENDAFTARAN LAYANAN CBN
// Otomatis Simpan ke Sheets, Generate PDF Surat CBN ke Drive, & Kirim Email ke Admin + Pelanggan
// PT. Sinergi Emas Perdana
//
// CATATAN:
// Konfigurasi SPREADSHEET_ID, DRIVE_FOLDER_ID, dan NOTIF_EMAIL sekarang otomatis
// dibaca secara dinamis dari Dashboard Admin. Anda TIDAK PERLU lagi mengedit script ini
// jika mengganti spreadsheet, folder drive, atau email admin. Cukup ubah di Dashboard Admin!
// ================================================

// ===== KONFIGURASI CADANGAN (FALLBACK JIKA TIDAK DIKIRIM DARI DASHBOARD) =====
const CONFIG = {
  SPREADSHEET_ID:  '1cXeq5CkL4QqhsOnAg7bvV7JQvz5gxXnXE1H1JwF9PmQ',
  SHEET_NAME:      'Sheet1',
  DRIVE_FOLDER_ID: '12q5pLGP9og9rcfVs_CKwKhTxfufvsN1A',
  NOTIF_EMAIL:     'pujapangestu02@gmail.com',
  NOTIF_NAME:      'Admin Sales CBN - PT. SEP',
};
// ===========================================================================


/**
 * Handler untuk HTTP POST dari Form Web PHP
 */
function doPost(e) {
  try {
    let params = {};

    // Parse JSON body atau Form parameters
    if (e.postData && e.postData.contents) {
      try {
        params = JSON.parse(e.postData.contents);
      } catch (jsonErr) {
        params = e.parameter || {};
      }
    } else if (e.parameter) {
      params = e.parameter;
    }

    // Ambil konfigurasi dinamis yang dikirim dari Dashboard Admin
    const targetFolderId = params.drive_folder_id || CONFIG.DRIVE_FOLDER_ID;
    const targetSheetId  = params.spreadsheet_id  || CONFIG.SPREADSHEET_ID;
    const targetEmail    = params.notif_email     || CONFIG.NOTIF_EMAIL;

    let ktpDriveUrl = '';
    let pdfDriveUrl = '';
    let pdfBlob     = null;

    // ---- 1. Simpan Foto KTP ke Google Drive (jika ada) ----
    if (params.file_data && params.file_name) {
      try {
        ktpDriveUrl = saveBase64ToDrive(
          params.file_data,
          params.file_name,
          params.file_mime || 'image/jpeg',
          targetFolderId
        );
      } catch (eKtp) {
        logError('KTP Drive Error: ' + eKtp.toString(), targetSheetId);
      }
    }

    // ---- 2. Generate PDF Surat Formulir CBN Resmi ----
    try {
      const cbnHtmlContent = generateCbnDocumentHtml(params);
      const cleanName = (params.nama_pelanggan || 'Pelanggan').replace(/[^a-zA-Z0-9_\-]/g, '_');
      const pdfFileName = 'Formulir_CBN_' + cleanName + '_' + Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyyMMdd_HHmm') + '.pdf';
      
      const htmlOutput = HtmlService.createHtmlOutput(cbnHtmlContent);
      pdfBlob = htmlOutput.getAs('application/pdf').setName(pdfFileName);

      let folder;
      try {
        folder = DriveApp.getFolderById(targetFolderId);
      } catch (fErr) {
        folder = DriveApp.getRootFolder();
      }

      const pdfFile = folder.createFile(pdfBlob);
      pdfFile.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      pdfDriveUrl = pdfFile.getUrl();
    } catch (driveErr) {
      logError('PDF Drive Error: ' + driveErr.toString(), targetSheetId);
    }

    // ---- 3. Simpan Baris Data ke Google Sheets ----
    try {
      appendToSheet(params, pdfDriveUrl, ktpDriveUrl, targetSheetId);
    } catch (sheetErr) {
      logError('Sheet Error: ' + sheetErr.toString(), targetSheetId);
    }

    // ---- 4. Kirim Email Notifikasi ke Master / Admin (+ Attachment PDF Surat CBN) ----
    try {
      sendAdminEmail(params, pdfDriveUrl, ktpDriveUrl, pdfBlob, targetEmail);
    } catch (mailErr) {
      logError('Mail Admin Error: ' + mailErr.toString(), targetSheetId);
    }

    // ---- 5. Kirim Email "Terima Kasih Atas Pendaftaran Anda" ke Email Pelanggan (+ Attachment PDF Surat CBN) ----
    if (params.email_pelanggan && params.email_pelanggan.indexOf('@') !== -1) {
      try {
        sendCustomerEmail(params, pdfDriveUrl, pdfBlob);
      } catch (custMailErr) {
        logError('Mail Customer Error: ' + custMailErr.toString(), targetSheetId);
      }
    }

    // ---- 6. Response Sukses ke PHP Form ----
    return ContentService
      .createTextOutput(JSON.stringify({
        status:  'success',
        message: 'Data dan Surat Formulir CBN berhasil disimpan',
        pdf_url: pdfDriveUrl,
        drive:   pdfDriveUrl,
        ktp_url: ktpDriveUrl
      }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    logError('General Error: ' + err.toString());

    return ContentService
      .createTextOutput(JSON.stringify({
        status:  'error',
        message: err.toString()
      }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}


/**
 * GET handler untuk cek status endpoint
 */
function doGet(e) {
  return ContentService
    .createTextOutput(JSON.stringify({
      status:  'ok',
      message: 'CBN Google Apps Script Web App Aktif & Siap Menerima Data Formulir!',
      time:    new Date().toISOString()
    }))
    .setMimeType(ContentService.MimeType.JSON);
}


/**
 * Simpan file base64 ke Google Drive
 */
function saveBase64ToDrive(base64Data, fileName, mimeType, folderId) {
  let folder;
  try {
    folder = DriveApp.getFolderById(folderId || CONFIG.DRIVE_FOLDER_ID);
  } catch (e) {
    folder = DriveApp.getRootFolder();
  }
  const decoded = Utilities.base64Decode(base64Data);
  const blob    = Utilities.newBlob(decoded, mimeType, fileName);
  const file    = folder.createFile(blob);

  file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  return file.getUrl();
}


/**
 * Helper render char boxes untuk template PDF
 */
function renderPdfBoxes(text, count) {
  text = (text || '').toUpperCase();
  let html = '<div style="display:inline-flex;gap:1px;">';
  for (let i = 0; i < count; i++) {
    const ch = i < text.length ? text[i] : '&nbsp;';
    html += '<div style="width:13px;height:15px;border:1px solid #777;text-align:center;font-size:8pt;font-weight:bold;line-height:14px;background:#fff;">' + ch + '</div>';
  }
  html += '</div>';
  return html;
}


/**
 * Generator HTML Formulir Pendaftaran Layanan CBN (PDF Ready)
 */
function generateCbnDocumentHtml(data) {
  const nama        = (data.nama_pelanggan || '').toUpperCase();
  const salesCode   = (data.sales_code || 'SEP-001').toUpperCase();
  const ttl         = (data.ttl || '').toUpperCase();
  const ktp         = data.nomor_ktp || '';
  const gender      = (data.jenis_kelamin || 'PRIA').toUpperCase();
  const telpRumah   = data.telp_rumah || '';
  const telpSelular = data.telp || '';
  
  const alamat      = (data.alamat || '').toUpperCase();
  const rt          = data.rt || '';
  const rw          = data.rw || '';
  const kodePos     = data.kode_pos || '';
  const kepemilikan = (data.status_kepemilikan || 'PEMILIK').toUpperCase();
  const email       = (data.email_pelanggan || '').toLowerCase();
  
  const service     = data.service || 'Fiber 50';
  const addonTv     = data.addon_tv || '';
  const addonDevice = data.addon_device || '';
  const routerQty   = data.router_qty || '1';
  const smartboxQty = data.smartbox_qty || '0';
  const usernameCbn = data.username_cbn || (nama.split(' ')[0] || 'user').toLowerCase();
  
  const tglPasang   = data.jadwal_tanggal || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy');
  const waktuPasang = data.jadwal_waktu || '09.00-11.00';
  const catatan     = data.catatan || '';
  const totalBiaya  = data.biaya_total || 'Rp 331.890';
  const tglTtd      = data.so_date || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy');
  const signatureImg= data.signature_data || '';

  const isPria    = (gender === 'PRIA' || gender === 'MALE');
  const isWanita  = (gender === 'WANITA' || gender === 'FEMALE');
  const isPemilik = (kepemilikan === 'PEMILIK' || kepemilikan === 'OWNER');
  const isPenyewa = (kepemilikan === 'PENYEWA' || kepemilikan === 'RENTER');

  const isF50     = (service === 'Fiber 50');
  const isF100    = (service === 'Fiber 100');
  const isF200    = (service === 'Fiber 200');
  const isF250    = (service === 'Fiber 250');
  const isF1G     = (service === 'Fiber 1Gbps');
  const isPro100  = (service === 'Fiber PRO 100');
  const isPro200  = (service === 'Fiber PRO 200');

  const hasDensTv = addonTv.indexOf('Dens') !== -1;
  const hasVision = addonTv.indexOf('Vision') !== -1;
  const hasRouter = addonDevice.indexOf('Router') !== -1 || routerQty !== '0';
  const hasSmartbox = addonDevice.indexOf('Smartbox') !== -1 || smartboxQty !== '0';

  return `
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #111; background: #fff; line-height: 1.2; padding: 8mm 10mm; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #005696; padding-bottom: 5px; margin-bottom: 5px; }
  .logo { font-size: 24pt; font-weight: 900; color: #005696; font-family: 'Arial Black', sans-serif; }
  .logo span { color: #00a0df; }
  .sec-bar { background: #005696; color: #fff; font-weight: bold; font-size: 8pt; padding: 3px 6px; margin: 5px 0 3px; text-transform: uppercase; }
  .row { display: flex; margin-bottom: 2px; align-items: center; }
  .lbl { width: 140px; font-size: 7.5pt; font-weight: bold; color: #222; flex-shrink: 0; }
  .lbl small { display: block; font-size: 6pt; font-weight: normal; font-style: italic; color: #555; }
  .val { flex: 1; display: flex; align-items: center; }
  .sq { width: 11px; height: 11px; border: 1.5px solid #222; display: inline-block; text-align: center; line-height: 9px; font-size: 8pt; font-weight: bold; margin-right: 3px; }
  .chk { display: inline-flex; align-items: center; margin-right: 12px; font-size: 7.5pt; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 4px; }
  .card { border: 1px solid #ccc; padding: 4px 6px; font-size: 7.5pt; }
  .card h4 { font-size: 7.5pt; font-weight: bold; color: #005696; border-bottom: 1px dotted #ccc; padding-bottom: 2px; margin-bottom: 3px; }
  .sign-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 6px; text-align: center; }
  .sign-box { border: 1px solid #aaa; height: 65px; position: relative; display: flex; flex-direction: column; justify-content: flex-end; padding: 2px; }
  .sign-img { max-height: 40px; max-width: 120px; margin: 0 auto; position: absolute; top: 2px; left: 0; right: 0; }
  .sign-title { font-size: 6.5pt; font-weight: bold; border-top: 1px solid #333; padding-top: 2px; }
</style>
</head>
<body>

  <div class="head">
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="logo">cbn<span>.</span></div>
      <div>
        <h1 style="font-size:10pt;color:#005696;font-weight:bold;margin:0;">FORMULIR PENDAFTARAN LAYANAN CBN</h1>
        <p style="font-size:7.5pt;font-style:italic;color:#444;margin:0;">CBN service application form</p>
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:7.5pt;font-weight:bold;margin-bottom:2px;">Sales code: ${renderPdfBoxes(salesCode, 8)}</div>
      <div style="font-size:7pt;color:#555;">@www.cbn.id &bull; Call Center: 1500 780</div>
    </div>
  </div>

  <div class="sec-bar">DATA PELANGGAN / CUSTOMER DATA</div>
  <div class="row"><div class="lbl">NAMA PELANGGAN<small>Full Name</small></div><div class="val">${renderPdfBoxes(nama, 28)}</div></div>
  <div class="row">
    <div class="lbl">TEMPAT/TGL LAHIR<small>Place/Date of birth</small></div>
    <div class="val" style="gap:10px;">
      ${renderPdfBoxes(ttl, 16)}
      <span class="chk"><span class="sq">${isPria ? 'V' : ''}</span> Pria</span>
      <span class="chk"><span class="sq">${isWanita ? 'V' : ''}</span> Wanita</span>
    </div>
  </div>
  <div class="row"><div class="lbl">NOMOR IDENTITAS<small>No. KTP 16 Digit</small></div><div class="val">${renderPdfBoxes(ktp, 20)}</div></div>
  <div class="row">
    <div class="lbl">TELEPON<small>Rumah / Selular</small></div>
    <div class="val" style="gap:12px;">
      <span>Rumah: ${renderPdfBoxes(telpRumah, 10)}</span>
      <span>Selular: ${renderPdfBoxes(telpSelular, 13)}</span>
    </div>
  </div>

  <div class="sec-bar">ALAMAT PEMASANGAN / INSTALLATION ADDRESS</div>
  <div class="row"><div class="lbl">ALAMAT PEMASANGAN<small>Installation Address</small></div><div class="val">${renderPdfBoxes(alamat, 28)}</div></div>
  <div class="row">
    <div class="lbl">RT/RW & KODE POS</div>
    <div class="val" style="gap:6px;">
      <span>RT ${renderPdfBoxes(rt, 3)}</span>
      <span>RW ${renderPdfBoxes(rw, 3)}</span>
      <span>KODE POS ${renderPdfBoxes(kodePos, 5)}</span>
    </div>
  </div>
  <div class="row">
    <div class="lbl">STATUS KEPEMILIKAN</div>
    <div class="val">
      <span class="chk"><span class="sq">${isPemilik ? 'V' : ''}</span> Pemilik - Owner</span>
      <span class="chk"><span class="sq">${isPenyewa ? 'V' : ''}</span> Penyewa - Renter</span>
    </div>
  </div>
  <div class="row"><div class="lbl">ALAMAT EMAIL</div><div class="val">${renderPdfBoxes(email, 28)}</div></div>

  <div class="grid2">
    <div class="card">
      <h4>PILIHAN PAKET LAYANAN CBN</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px;">
        <div>
          <strong>FIBER</strong><br>
          <span class="chk"><span class="sq">${isF50 ? 'V' : ''}</span> Fiber 50</span><br>
          <span class="chk"><span class="sq">${isF100 ? 'V' : ''}</span> Fiber 100</span><br>
          <span class="chk"><span class="sq">${isF200 ? 'V' : ''}</span> Fiber 200</span><br>
          <span class="chk"><span class="sq">${isF250 ? 'V' : ''}</span> Fiber 250</span><br>
          <span class="chk"><span class="sq">${isF1G ? 'V' : ''}</span> Fiber 1Gbps</span>
        </div>
        <div>
          <strong>FIBER PRO</strong><br>
          <span class="chk"><span class="sq">${isPro100 ? 'V' : ''}</span> Fiber PRO 100</span><br>
          <span class="chk"><span class="sq">${isPro200 ? 'V' : ''}</span> Fiber PRO 200</span>
        </div>
      </div>
    </div>
    <div class="card">
      <h4>LAYANAN TAMBAHAN & PERANGKAT</h4>
      <div style="margin-bottom:3px;">
        <span class="chk"><span class="sq">${hasRouter ? 'V' : ''}</span> Wireless Router (${routerQty} Unit)</span><br>
        <span class="chk"><span class="sq">${hasSmartbox ? 'V' : ''}</span> Smartbox (${smartboxQty} Unit)</span>
      </div>
      <div>
        <strong>ADD-ON TV:</strong><br>
        <span class="chk"><span class="sq">${hasDensTv ? 'V' : ''}</span> Dens TV+</span>
        <span class="chk"><span class="sq">${hasVision ? 'V' : ''}</span> Vision Sports</span>
      </div>
    </div>
  </div>

  <div class="grid2">
    <div class="card">
      <h4>AKTIVASI & PEMBAYARAN</h4>
      <p style="margin:2px 0;">Username: <strong>${usernameCbn}@cbn.net.id</strong></p>
      <p style="margin:2px 0;">Total Biaya: <strong>${totalBiaya}</strong></p>
    </div>
    <div class="card">
      <h4>JADWAL PEMASANGAN</h4>
      <p style="margin:2px 0;">Tanggal: <strong>${tglPasang}</strong></p>
      <p style="margin:2px 0;">Waktu: <strong>${waktuPasang} WIB</strong></p>
      ${catatan ? '<p style="margin:2px 0;font-size:6.5pt;color:#666;">Catatan: ' + catatan + '</p>' : ''}
    </div>
  </div>

  <div class="sign-row">
    <div class="sign-box">
      ${signatureImg ? '<img class="sign-img" src="' + signatureImg + '" alt="TTD">' : ''}
      <div class="sign-title">Tanda tangan pelanggan<br><span style="font-weight:normal;font-size:5.5pt;">Tanggal: ${tglTtd}</span></div>
    </div>
    <div class="sign-box">
      <div class="sign-title">Tanda tangan sales<br><span style="font-weight:normal;font-size:5.5pt;">Sales: ${salesCode}</span></div>
    </div>
    <div class="sign-box">
      <div class="sign-title">Tanda tangan SPV<br><span style="font-weight:normal;font-size:5.5pt;">PT. SEP</span></div>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;font-size:6pt;color:#888;margin-top:4px;border-top:1px solid #ddd;padding-top:2px;">
    <span>Dokumen Resmi Pendaftaran Layanan CBN</span>
    <span>CA-JKT-REL-FRM-00002023-1.0</span>
  </div>

</body>
</html>
  `;
}


/**
 * Tambahkan baris data lengkap ke Google Sheets
 */
function appendToSheet(params, pdfUrl, ktpUrl, sheetId) {
  const ssId  = sheetId || CONFIG.SPREADSHEET_ID;
  const ss    = SpreadsheetApp.openById(ssId);
  const sheet = ss.getSheetByName(CONFIG.SHEET_NAME) || ss.getSheets()[0];

  // Inisialisasi Header Kolom CBN jika sheet masih baru/kosong
  if (sheet.getLastRow() === 0) {
    sheet.appendRow([
      'Timestamp', 'Sales Code', 'Vendor', 'Nama Pelanggan', 'Nomor KTP',
      'TTL', 'Jenis Kelamin', 'Telepon Rumah', 'Telepon Seluler / WA', 'Alamat Pemasangan',
      'RT', 'RW', 'Kode Pos', 'Status Kepemilikan', 'Email Pelanggan',
      'Paket CBN (Service)', 'Add-On TV', 'Perangkat Tambahan', 'Username @cbn.net.id',
      'Jadwal Pasang', 'Waktu Pasang', 'Catatan / Notes', 'Total Biaya',
      'Link PDF Surat CBN', 'Link Foto KTP'
    ]);

    const headerRange = sheet.getRange(1, 1, 1, 25);
    headerRange.setBackground('#005696');
    headerRange.setFontColor('#ffffff');
    headerRange.setFontWeight('bold');
    sheet.setFrozenRows(1);
  }

  const now = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy HH:mm:ss');
  sheet.appendRow([
    now,
    params.sales_code        || 'SEP-001',
    params.vendor            || 'PT. SINERGI EMAS PERDANA',
    params.nama_pelanggan    || '',
    params.nomor_ktp         || '',
    params.ttl               || '',
    params.jenis_kelamin     || '',
    params.telp_rumah        || '',
    params.telp              || '',
    params.alamat            || '',
    params.rt                || '',
    params.rw                || '',
    params.kode_pos          || '',
    params.status_kepemilikan|| '',
    params.email_pelanggan   || '',
    params.service           || '',
    params.addon_tv          || '',
    params.addon_device      || '',
    (params.username_cbn ? params.username_cbn + '@cbn.net.id' : ''),
    params.jadwal_tanggal    || '',
    params.jadwal_waktu      || '',
    params.catatan           || '',
    params.biaya_total       || '',
    pdfUrl                   || '',
    ktpUrl                   || ''
  ]);

  const lastRow = sheet.getLastRow();
  if (lastRow % 2 === 0) {
    sheet.getRange(lastRow, 1, 1, 25).setBackground('#f0f7ff');
  }
}


/**
 * Kirim email notifikasi ke Master / Admin dengan Lampiran PDF Formulir CBN
 */
function sendAdminEmail(params, pdfUrl, ktpUrl, pdfBlob, targetEmail) {
  const adminRecipient = targetEmail || CONFIG.NOTIF_EMAIL;
  const subject = `[SALES ORDER CBN] ${params.sales_code || 'SEP'} - ${params.nama_pelanggan || ''} (${params.service || 'Fiber'})`;

  const pdfLink = pdfUrl ? `<a href="${pdfUrl}" style="color:#005696;font-weight:bold;">[Buka Dokumen PDF Surat CBN di Google Drive]</a>` : '-';
  const ktpLink = ktpUrl ? `<a href="${ktpUrl}" style="color:#0088cc;font-weight:bold;">[Lihat Foto KTP di Google Drive]</a>` : 'Tidak dilampirkan';

  const htmlBody = `
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; background:#f4f6f8; margin:0; padding:20px; color:#222; }
  .wrap { max-width:680px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); border:1px solid #e2e8f0; }
  .head { background:#005696; color:#ffffff; padding:24px; text-align:center; }
  .head h1 { margin:0; font-size:20px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase; }
  .head p { margin:6px 0 0; opacity:.9; font-size:13px; }
  .body { padding:24px 28px; font-size:13px; color:#333; }
  .sec { margin-bottom:20px; }
  .sec h3 { color:#005696; border-bottom:2px solid #00a0df; padding-bottom:5px; margin:0 0 10px; font-size:13px; text-transform:uppercase; font-weight:bold; }
  table { width:100%; border-collapse:collapse; }
  td { padding:7px 10px; font-size:13px; vertical-align:top; }
  td:first-child { color:#555; width:38%; font-weight:600; }
  td:last-child { color:#111; }
  tr:nth-child(even) td { background:#f8fafc; }
  .badge { background:#005696; color:white; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold; display:inline-block; }
  .notice-box { background:#e6f4ff; border-left:4px solid #0088cc; padding:12px 14px; margin-bottom:20px; border-radius:4px; font-size:13px; color:#003366; }
  .foot { background:#f8fafc; padding:16px; text-align:center; color:#777; font-size:12px; border-top:1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>FORMULIR PENDAFTARAN LAYANAN CBN</h1>
    <p>Sales Code: <strong>${params.sales_code || 'SEP-001'}</strong> &bull; Mitra Resmi: PT. Sinergi Emas Perdana</p>
  </div>
  <div class="body">
    
    <div class="notice-box">
      <strong>Surat Formulir Resmi CBN (PDF)</strong> telah dibuat otomatis dan terlampir pada email ini.
    </div>

    <div class="sec">
      <h3>1. DATA PELANGGAN</h3>
      <table>
        <tr><td>Nama Lengkap</td><td><strong>${params.nama_pelanggan || '-'}</strong></td></tr>
        <tr><td>Nomor KTP</td><td>${params.nomor_ktp || '-'}</td></tr>
        <tr><td>Tempat / Tgl Lahir</td><td>${params.ttl || '-'}</td></tr>
        <tr><td>Jenis Kelamin</td><td>${params.jenis_kelamin || '-'}</td></tr>
        <tr><td>No. Telepon / WA</td><td>${params.telp || '-'}</td></tr>
        <tr><td>No. Telepon Rumah</td><td>${params.telp_rumah || '-'}</td></tr>
        <tr><td>Email Pelanggan</td><td>${params.email_pelanggan || '-'}</td></tr>
      </table>
    </div>

    <div class="sec">
      <h3>2. ALAMAT PEMASANGAN</h3>
      <table>
        <tr><td>Alamat Lengkap</td><td>${params.alamat || '-'}</td></tr>
        <tr><td>RT / RW</td><td>RT ${params.rt || '-'} / RW ${params.rw || '-'}</td></tr>
        <tr><td>Kelurahan / Kecamatan</td><td>${params.kelurahan || '-'} / ${params.kecamatan || '-'}</td></tr>
        <tr><td>Kode Pos</td><td>${params.kode_pos || '-'}</td></tr>
        <tr><td>Status Kepemilikan</td><td><strong>${params.status_kepemilikan || 'PEMILIK'}</strong></td></tr>
      </table>
    </div>

    <div class="sec">
      <h3>3. PAKET LAYANAN & JADWAL</h3>
      <table>
        <tr><td>Paket Layanan</td><td><span class="badge">${params.service || '-'}</span></td></tr>
        <tr><td>Add-On TV</td><td>${params.addon_tv || 'Tidak ada'}</td></tr>
        <tr><td>Perangkat Tambahan</td><td>${params.addon_device || 'Router Standard'}</td></tr>
        <tr><td>Username Email CBN</td><td>${params.username_cbn ? params.username_cbn + '@cbn.net.id' : '-'}</td></tr>
        <tr><td>Jadwal Pemasangan</td><td><strong>${params.jadwal_tanggal || '-'}</strong> (${params.jadwal_waktu || '-'})</td></tr>
        <tr><td>Catatan Lokasi</td><td>${params.catatan || '-'}</td></tr>
        <tr><td>Estimasi Total Biaya</td><td><strong>${params.biaya_total || '-'}</strong></td></tr>
      </table>
    </div>

    <div class="sec">
      <h3>4. BERKAS & LAMPIRAN DOKUMEN</h3>
      <p style="margin:6px 0;">${pdfLink}</p>
      <p style="margin:6px 0;">${ktpLink}</p>
    </div>

  </div>
  <div class="foot">
    Email otomatis dari Sistem Formulir Layanan CBN &bull; PT. Sinergi Emas Perdana
  </div>
</div>
</body>
</html>`;

  const emailOptions = {
    htmlBody: htmlBody,
    name:     'CBN Application Form System'
  };

  if (pdfBlob) {
    emailOptions.attachments = [pdfBlob];
  }

  GmailApp.sendEmail(adminRecipient, subject, '', emailOptions);
}


/**
 * Kirim email "Terima Kasih Atas Pendaftaran Anda" ke Pelanggan dengan Lampiran PDF Formulir CBN
 */
function sendCustomerEmail(params, pdfUrl, pdfBlob) {
  const subject = `Terima Kasih Atas Pendaftaran Anda - Layanan Internet Fiber CBN`;

  const htmlBody = `
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; background:#f4f6f8; margin:0; padding:20px; color:#222; }
  .wrap { max-width:620px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); border:1px solid #e2e8f0; }
  .head { background:#005696; color:white; padding:24px 20px; text-align:center; }
  .head h2 { margin:0; font-size:20px; font-weight:bold; letter-spacing:0.5px; }
  .head p { margin:6px 0 0; opacity:.9; font-size:13.5px; }
  .body { padding:26px 28px; font-size:13.5px; line-height:1.6; color:#333; }
  .summary-card { background:#f0f8ff; border:1px solid #b9e3fc; padding:16px 18px; border-radius:6px; margin:18px 0; }
  .summary-card table { width:100%; border-collapse:collapse; font-size:13px; }
  .summary-card td { padding:5px 6px; vertical-align:top; }
  .summary-card td:first-child { color:#555; width:40%; font-weight:600; }
  .summary-card td:last-child { color:#111; }
  .badge { background:#005696; color:white; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold; display:inline-block; }
  .foot { background:#f8fafc; padding:18px; text-align:center; font-size:12px; color:#777; border-top:1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h2>Terima Kasih Atas Pendaftaran Anda</h2>
    <p>Layanan Internet Fiber CBN &bull; Mitra Resmi: PT. Sinergi Emas Perdana</p>
  </div>
  <div class="body">
    <p>Halo <strong>${params.nama_pelanggan}</strong>,</p>
    <p>Pendaftaran layanan internet fiber CBN Anda telah berhasil kami terima. Terlampir pada email ini adalah <strong>Salinan Resmi Formulir Pendaftaran Layanan CBN (PDF)</strong> yang telah Anda isi dan tandatangani.</p>
    
    <div class="summary-card">
      <div style="font-weight:bold;color:#005696;margin-bottom:8px;font-size:13.5px;border-bottom:1px solid #b9e3fc;padding-bottom:4px;">
        RINGKASAN PENDAFTARAN ANDA:
      </div>
      <table>
        <tr><td>Nama Lengkap</td><td><strong>${params.nama_pelanggan || '-'}</strong></td></tr>
        <tr><td>Nomor Identitas (KTP)</td><td>${params.nomor_ktp || '-'}</td></tr>
        <tr><td>Paket Layanan</td><td><span class="badge">${params.service || 'CBN Fiber'}</span></td></tr>
        <tr><td>Alamat Pemasangan</td><td>${params.alamat || '-'} (RT ${params.rt || '-'}/RW ${params.rw || '-'}, Kode Pos: ${params.kode_pos || '-'})</td></tr>
        <tr><td>Rencana Jadwal Pemasangan</td><td><strong>${params.jadwal_tanggal || '-'}</strong> (Slot: ${params.jadwal_waktu || '-'})</td></tr>
        <tr><td>Estimasi Biaya</td><td><strong>${params.biaya_total || '-'}</strong></td></tr>
        <tr><td>Sales Code</td><td>${params.sales_code || 'SEP-001'}</td></tr>
      </table>
    </div>

    <p style="margin-top:14px;">
      Tim teknisi kami akan menghubungi Anda sebelum jadwal instalasi untuk konfirmasi kedatangan dan kesiapan lokasi.
    </p>
    <p style="margin-top:10px;">
      Jika ada pertanyaan atau perubahan jadwal, Anda dapat menghubungi Customer Service CBN di Call Center <strong>1500 780</strong> atau email ke <strong>customercare@cbn.net.id</strong>.
    </p>
  </div>
  <div class="foot">
    PT. Sinergi Emas Perdana &bull; Mitra Resmi CBN &bull; www.cbn.id
  </div>
</div>
</body>
</html>`;

  const emailOptions = {
    htmlBody: htmlBody,
    name:     'CBN Customer Care'
  };

  if (pdfBlob) {
    emailOptions.attachments = [pdfBlob];
  }

  GmailApp.sendEmail(params.email_pelanggan, subject, '', emailOptions);
}


/**
 * Log error jika ada kendala
 */
function logError(msg, sheetId) {
  try {
    const ssId  = sheetId || CONFIG.SPREADSHEET_ID;
    const ss    = SpreadsheetApp.openById(ssId);
    let sheet = ss.getSheetByName('ERROR_LOG');
    if (!sheet) {
      sheet = ss.insertSheet('ERROR_LOG');
      sheet.appendRow(['Timestamp', 'Error Message']);
    }
    const now = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy HH:mm:ss');
    sheet.appendRow([now, msg]);
  } catch (e) {
    // silent
  }
}
