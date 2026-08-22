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
 * box() - render 1 karakter per 1 kotak, terpusat di tengah kotaknya.
 * startX = left edge kotak pertama (%)
 * startY = top (%)
 * stepX  = lebar 1 kotak (%) — default 2.483% untuk kotak baris data utama
 * fs     = font-size
 * max    = jumlah kotak maksimum untuk field tersebut
 */
function box(text, startX, startY, stepX, fs, max) {
  if (!text) return '';
  const str  = String(text).toUpperCase();
  const sX   = stepX || 2.483;
  const size = fs    || '9.5pt';
  const limit = max  || 50;
  let html = '';
  let col = 0; // kolom kotak (spasi tetap melompati kotak)
  for (let i = 0; i < str.length && col < limit; i++) {
    const ch = str[i];
    if (ch === ' ') { col++; continue; } // kotak spasi dikosongi, tapi tetap dihitung
    const bLeft = (startX + col * sX).toFixed(3);
    const bW    = sX.toFixed(3);
    const bTop  = startY.toFixed(2);
    html += `<div class='fld' style='top:${bTop}%;left:${bLeft}%;width:${bW}%;font-size:${size};text-align:center;'>${ch}</div>\n`;
    col++;
  }
  return html;
}

/**
 * fld() - render teks biasa (tidak per-kotak) di posisi tertentu
 */
function fld(text, top, left, fs, extra) {
  if (!text && text !== 0) return '';
  fs = fs || '11pt';
  extra = extra || '';
  return `<div class='fld' style='top:${top}%;left:${left}%;font-size:${fs};${extra}'>${text}</div>`;
}

/**
 * Generator HTML Formulir Pendaftaran Layanan CBN (100% Identik dengan asli.pdf & contoh.jpeg)
 */
function generateCbnDocumentHtml(data) {
  const nama        = (data.nama_pelanggan || '').toUpperCase().trim();
  const salesCode   = (data.sales_code || 'SEP001').toUpperCase().replace(/[^A-Z0-9]/g, '');
  const salesName   = (data.sales_name || 'PUJA PANGESTU').toUpperCase().trim();
  const ttl         = (data.ttl || '').toUpperCase().trim();
  const ktp         = (data.nomor_ktp || '').replace(/[^0-9]/g, '');
  const gender      = (data.jenis_kelamin || 'PRIA').toUpperCase().trim();
  const telpSelular = (data.telp || '').replace(/[^0-9]/g, '');
  const telpRumah   = (data.telp_rumah || '').replace(/[^0-9]/g, '');
  
  const alamat      = (data.alamat || '').toUpperCase().trim();
  const kepemilikan = (data.status_kepemilikan || 'PEMILIK').toUpperCase().trim();
  const email       = (data.email_pelanggan || '').toUpperCase().trim();
  
  const service     = (data.service || 'Fiber 50').trim();
  const addonTv     = data.addon_tv || '';
  const usernameCbn = (data.username_cbn || (nama.split(' ')[0] || 'user')).toUpperCase().trim();
  
  const totalBiaya  = data.biaya_total || 'Rp 331.890';
  const biayaPasang = data.biaya_pasang || 'Rp 0';
  const biayaPaket  = data.biaya_paket || 'Rp 299.000';
  const tglTtd      = data.so_date || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy');
  const signatureImg= data.signature_data || '';

  const isPria    = (gender === 'PRIA' || gender === 'MALE');
  const isWanita  = (gender === 'WANITA' || gender === 'FEMALE');
  const isPemilik = (kepemilikan === 'PEMILIK' || kepemilikan === 'OWNER');
  const isPenyewa = (kepemilikan === 'PENYEWA' || kepemilikan === 'RENTER');

  // Pisahkan TTL
  let ttlKota = '', ttlDay = '', ttlMonth = '', ttlYear = '';
  if (ttl) {
    const ttlParts = ttl.split(',');
    ttlKota = (ttlParts[0] || '').trim();
    if (ttlParts[1]) {
      const dParts = ttlParts[1].trim().split(/[\/\-\s]+/);
      if (dParts.length >= 3) {
        ttlDay   = ('0' + dParts[0]).slice(-2);
        ttlMonth = ('0' + dParts[1]).slice(-2);
        ttlYear  = dParts[2];
      }
    }
  }

  // Pisahkan Alamat jadi 2 baris (maks 29 karakter per baris kotak)
  let alamat1 = alamat, alamat2 = '';
  if (alamat.length > 29) {
    const pos = alamat.substring(0, 29).lastIndexOf(' ');
    if (pos !== -1) {
      alamat1 = alamat.substring(0, pos);
      alamat2 = alamat.substring(pos).trim();
    }
  }

  // Ambil Template Gambar Resmi Asli CBN dari Server Railway / Drive Cache
  let bgTemplate = '';
  const urls = [
    'https://sales-sales.up.railway.app/assets/img/asli_bg.jpg',
    'https://sales-sales.up.railway.app/asli_bg.jpg'
  ];
  for (let i = 0; i < urls.length; i++) {
    try {
      const resp = UrlFetchApp.fetch(urls[i], { muteHttpExceptions: true });
      if (resp.getResponseCode() === 200) {
        bgTemplate = Utilities.base64Encode(resp.getBlob().getBytes());
        break;
      }
    } catch (e) {
      Logger.log('Fetch error: ' + e.message);
    }
  }

  return `<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  @page { size: A4 portrait; margin: 0; }
  body {
    width: 210mm;
    height: 297mm;
    margin: 0;
    padding: 0;
    position: relative;
    background: #fff;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    -webkit-print-color-adjust: exact;
  }
  .bg-img {
    position: absolute;
    top: 0; left: 0;
    width: 210mm;
    height: 297mm;
    z-index: 1;
  }
  .layer {
    position: absolute;
    top: 0; left: 0;
    width: 210mm;
    height: 297mm;
    z-index: 2;
  }
  .fld {
    position: absolute;
    font-weight: bold;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    white-space: nowrap;
    line-height: 1.15;
  }
</style>
</head>
<body>
  ${bgTemplate ? `<img class='bg-img' src='data:image/jpeg;base64,${bgTemplate}'>` : `<img class='bg-img' src='https://sales-sales.up.railway.app/assets/img/asli_bg.jpg'>`}
  <div class='layer'>

    <!-- 0. SALES CODE kanan atas (6 kotak, step=2.15%) -->
    ${box(salesCode, 74.0, 3.3, 2.15, '9pt', 6)}

    <!-- 1. NAMA PELANGGAN (29 kotak, step=2.483%) -->
    ${box(nama, 21.15, 11.25, 2.483, '10pt', 29)}

    <!-- TEMPAT LAHIR (15 kotak) -->
    ${box(ttlKota, 21.15, 13.8, 2.483, '10pt', 15)}

    <!-- TANGGAL LAHIR: DD (2), / (1 skip), MM (2), / (1 skip), YYYY (4) -->
    ${box(ttlDay,   59.0,  13.8, 2.483, '10pt', 2)}
    ${box(ttlMonth, 64.45, 13.8, 2.483, '10pt', 2)}
    ${box(ttlYear,  69.9,  13.8, 2.483, '10pt', 4)}

    <!-- NOMOR IDENTITAS KTP (16 kotak) -->
    ${box(ktp, 21.15, 16.45, 2.483, '10pt', 16)}

    <!-- JENIS KELAMIN -->
    ${isPria   ? fld('X', 16.35, 75.6, '12pt', 'width:2.483%;text-align:center;') : ''}
    ${isWanita ? fld('X', 16.35, 84.4, '12pt', 'width:2.483%;text-align:center;') : ''}

    <!-- TELEPON RUMAH -->
    ${telpRumah ? box(telpRumah, 21.15, 18.95, 2.483, '10pt', 12) : ''}

    <!-- TELEPON SELULAR / WA (2 baris sama) -->
    ${box(telpSelular, 68.8, 18.95, 2.483, '10pt', 12)}
    ${box(telpSelular, 68.8, 20.5,  2.483, '10pt', 12)}

    <!-- 2. ALAMAT PEMASANGAN (29 kotak × 2 baris) -->
    ${box(alamat1, 21.15, 26.8, 2.483, '9.5pt', 29)}
    ${alamat2 ? box(alamat2, 21.15, 28.7, 2.483, '9.5pt', 29) : ''}

    <!-- STATUS KEPEMILIKAN -->
    ${isPemilik ? fld('\u2714', 33.15, 21.2, '13pt') : ''}
    ${isPenyewa ? fld('\u2714', 33.15, 34.8, '13pt') : ''}

    <!-- ALAMAT EMAIL (29 kotak) -->
    ${box(email.toLowerCase(), 21.15, 35.2, 2.483, '9pt', 29)}

    <!-- 3. PILIHAN PAKET LAYANAN -->
    ${fld('\u2714', 42.25, 2.9, '13pt')}
    ${fld(service, 42.25, 11.4, '11.5pt')}

    <!-- ADD-ON TV -->
    ${addonTv ? fld('\u2714 ' + addonTv, 49.25, 74.4, '9pt') : ''}

    <!-- RINCIAN BIAYA -->
    ${fld(biayaPaket,                 60.0, 69.5, '11pt')}
    ${fld(biayaPasang,                61.5, 69.5, '11pt')}
    ${fld(data.biaya_ppn || 'Rp19.140', 67.4, 69.5, '11pt')}
    ${fld(totalBiaya,                 69.3, 69.5, '13pt')}

    <!-- 4. USERNAME (11 kotak) -->
    ${box(usernameCbn.toLowerCase(), 2.8, 84.5, 2.483, '10pt', 11)}

    <!-- NOTES -->
    ${fld(data.catatan || 'REGULAR PROMO CBN - PT. SEP', 88.8, 51.5, '9.5pt')}

    <!-- 5. TANGGAL & TANDA TANGAN -->
    ${fld(tglTtd, 92.85, 9.5, '10.5pt')}

    ${signatureImg ? `<img src='${signatureImg}' style='position:absolute;top:89.5%;left:4%;max-height:45px;max-width:150px;z-index:3;'>` : ''}

    ${fld(salesName, 94.85, 38.5, '10.5pt')}
    ${fld(salesCode + '-' + salesName.split(' ')[0], 94.85, 74.0, '10.5pt')}
  </div>
</body>
</html>`;
}


/**
 * function appendToSheet(params, pdfUrl, ktpUrl, sheetId) {
  const ssId  = sheetId || CONFIG.SPREADSHEET_ID;
  const ss    = SpreadsheetApp.openById(ssId);
  const sheet = ss.getSheetByName(CONFIG.SHEET_NAME) || ss.getSheets()[0];

  const headers = [
    'Timestamp', 'Sales Code', 'Vendor', 'Nama Pelanggan', 'Nomor KTP',
    'TTL', 'Jenis Kelamin', 'Telepon Rumah', 'No. WhatsApp / HP', 'Alamat Pemasangan',
    'RT', 'RW', 'Kode Pos', 'Status Rumah', 'Email Pelanggan',
    'Paket CBN (Service)', 'Add-On TV', 'Perangkat Tambahan', 'Akun Email CBN',
    'Jadwal Pasang', 'Waktu Pasang', 'Catatan / Notes', 'Total Biaya',
    'Link PDF Surat CBN', 'Link Foto KTP'
  ];

  // Inisialisasi Header Kolom CBN jika sheet masih baru/kosong
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
    setupSheetHeaderStyles(sheet);
  }

  const now = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy HH:mm:ss');
  
  // Format Hyperlink yang rapi agar tidak berupa text URL panjang
  const pdfFormula = pdfUrl ? `=HYPERLINK("${pdfUrl}", "📄 Buka PDF Surat CBN")` : '-';
  const ktpFormula = ktpUrl ? `=HYPERLINK("${ktpUrl}", "🪪 Lihat Foto KTP")` : '-';

  sheet.appendRow([
    now,
    params.sales_code        || 'SEP-001',
    params.vendor            || 'PT. SINERGI EMAS PERDANA',
    params.nama_pelanggan    || '',
    "'" + (params.nomor_ktp  || ''),
    params.ttl               || '',
    params.jenis_kelamin     || '',
    params.telp_rumah        || '',
    "'" + (params.telp       || ''),
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
    pdfFormula,
    ktpFormula
  ]);

  const lastRow = sheet.getLastRow();
  const rowRange = sheet.getRange(lastRow, 1, 1, headers.length);
  
  // Zebra Striping & Row Styling
  rowRange.setVerticalAlignment('middle');
  rowRange.setFontFamily('Arial');
  rowRange.setFontSize(9.5);
  rowRange.setBorder(true, true, true, true, true, true, '#dde3ea', SpreadsheetApp.BorderStyle.SOLID);
  
  if (lastRow % 2 === 0) {
    rowRange.setBackground('#f0f7ff');
  } else {
    rowRange.setBackground('#ffffff');
  }

  // Alignment per kolom
  sheet.getRange(lastRow, 1, 1, 2).setHorizontalAlignment('center'); // Timestamp, Sales Code
  sheet.getRange(lastRow, 5, 1, 4).setHorizontalAlignment('center'); // KTP, TTL, Gender, Telp Rumah
  sheet.getRange(lastRow, 9, 1, 1).setHorizontalAlignment('center'); // WA
  sheet.getRange(lastRow, 11, 1, 4).setHorizontalAlignment('center'); // RT, RW, Pos, Status
  sheet.getRange(lastRow, 16, 1, 1).setHorizontalAlignment('center'); // Paket
  sheet.getRange(lastRow, 20, 1, 2).setHorizontalAlignment('center'); // Jadwal, Waktu
  sheet.getRange(lastRow, 23, 1, 3).setHorizontalAlignment('center'); // Total, PDF, KTP
}

/**
 * Styling Header & Pengaturan Lebar Kolom Spreadsheet
 */
function setupSheetHeaderStyles(sheet) {
  const headersCount = 25;
  const headerRange = sheet.getRange(1, 1, 1, headersCount);
  
  headerRange.setBackground('#003366');
  headerRange.setFontColor('#ffffff');
  headerRange.setFontWeight('bold');
  headerRange.setFontFamily('Arial');
  headerRange.setFontSize(10);
  headerRange.setHorizontalAlignment('center');
  headerRange.setVerticalAlignment('middle');
  headerRange.setWrap(true);
  sheet.setRowHeight(1, 38);
  sheet.setFrozenRows(1);

  // Atur lebar kolom yang pas dan nyaman dibaca
  const colWidths = [
    140, // Timestamp
    95,  // Sales Code
    180, // Vendor
    180, // Nama Pelanggan
    150, // Nomor KTP
    160, // TTL
    100, // Jenis Kelamin
    120, // Telp Rumah
    140, // WhatsApp / HP
    250, // Alamat Pemasangan
    55,  // RT
    55,  // RW
    80,  // Kode Pos
    120, // Status Rumah
    200, // Email Pelanggan
    130, // Paket CBN
    140, // Add-On TV
    150, // Perangkat Tambahan
    160, // Akun Email CBN
    110, // Jadwal Pasang
    110, // Waktu Pasang
    200, // Catatan
    120, // Total Biaya
    160, // Link PDF
    150  // Link KTP
  ];

  for (let i = 0; i < colWidths.length; i++) {
    sheet.setColumnWidth(i + 1, colWidths[i]);
  }
}

/**
 * Fungsi Format Spreadsheet Baru / Reset Desain Tabel Secara Instan
 */
function formatCurrentSheet() {
  const ss    = SpreadsheetApp.openById(CONFIG.SPREADSHEET_ID);
  const sheet = ss.getSheetByName(CONFIG.SHEET_NAME) || ss.getSheets()[0];
  setupSheetHeaderStyles(sheet);
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
