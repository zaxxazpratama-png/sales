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
 * Generator HTML Formulir Pendaftaran Layanan CBN (100% Identik dengan asli.pdf)
 */
function generateCbnDocumentHtml(data) {
  const nama        = (data.nama_pelanggan || '').toUpperCase();
  const salesCode   = (data.sales_code || 'SEP-001').toUpperCase();
  const salesName   = (data.sales_name || 'PUJA PANGESTU').toUpperCase();
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
  const biayaPasang = data.biaya_pasang || 'Rp 0 (Promo Gratis Pasang)';
  const biayaPaket  = data.biaya_paket || 'Rp 299.000';
  const tglTtd      = data.so_date || Utilities.formatDate(new Date(), 'Asia/Jakarta', 'dd/MM/yyyy');
  const signatureImg= data.signature_data || '';

  const isPria    = (gender === 'PRIA' || gender === 'MALE');
  const isWanita  = (gender === 'WANITA' || gender === 'FEMALE');
  const isPemilik = (kepemilikan === 'PEMILIK' || kepemilikan === 'OWNER');
  const isPenyewa = (kepemilikan === 'PENYEWA' || kepemilikan === 'RENTER');

  const isFiberSafe = (service.indexOf('Safe') !== -1);
  const isFiberPro  = (service.indexOf('Pro') !== -1);
  const isFiberStd  = (!isFiberSafe && !isFiberPro);

  const hasDensTv = addonTv.indexOf('Dens') !== -1;
  const hasVision = addonTv.indexOf('Vision') !== -1;
  const hasRouter = addonDevice.indexOf('Router') !== -1 || routerQty !== '0';
  const hasSmartbox = addonDevice.indexOf('Smartbox') !== -1 || smartboxQty !== '0';

  // Pisahkan TTL
  let ttlKota = '', ttlDay = '', ttlMonth = '', ttlYear = '';
  if (ttl) {
    const ttlParts = ttl.split(',');
    ttlKota = (ttlParts[0] || '').trim();
    if (ttlParts[1]) {
      const dParts = ttlParts[1].trim().split(/[\/\-\s]+/);
      if (dParts.length >= 3) {
        ttlDay = ('0' + dParts[0]).slice(-2);
        ttlMonth = ('0' + dParts[1]).slice(-2);
        ttlYear = dParts[2];
      }
    }
  }

  // Pisahkan Alamat
  const alamatRow1 = alamat.substring(0, 30);
  const alamatRow2 = alamat.substring(30, 60);
  const alamatRow3 = alamat.substring(60, 73);

  // Jadwal
  let jadwalDay = '', jadwalMonth = '', jadwalYear = '';
  if (tglPasang) {
    const jParts = tglPasang.trim().split(/[\/\-\s]+/);
    if (jParts.length >= 3) {
      jadwalDay = ('0' + jParts[0]).slice(-2);
      jadwalMonth = ('0' + jParts[1]).slice(-2);
      jadwalYear = jParts[2];
    }
  }

  return `
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 7.2pt; color: #111; background: #fff; line-height: 1.15; padding: 6mm 8mm; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; }
  .brand { font-size: 24pt; font-weight: 900; color: #005696; font-family: 'Arial Black', sans-serif; line-height: 1; }
  .brand span { color: #00a0df; }
  .title-h1 { font-size: 9.5pt; color: #0066a1; font-weight: 800; text-transform: uppercase; margin-top: 3px; }
  .title-sub { font-size: 7.5pt; font-style: italic; color: #0066a1; }
  .bar { background: #0066a1; color: #fff; font-weight: 800; font-size: 7.5pt; padding: 2px 6px; margin: 3px 0 2px; text-transform: uppercase; }
  .row { display: flex; margin-bottom: 1.5px; align-items: center; }
  .lbl { width: 135px; font-size: 6.8pt; font-weight: 800; color: #111; flex-shrink: 0; }
  .lbl small { display: block; font-size: 5.5pt; font-weight: normal; font-style: italic; color: #555; }
  .fld { flex: 1; display: flex; align-items: center; }
  .sq { width: 10px; height: 10px; border: 1.2px solid #222; display: inline-block; text-align: center; line-height: 8px; font-size: 7pt; font-weight: bold; margin-right: 3px; }
  .chk { display: inline-flex; align-items: center; margin-right: 10px; font-size: 6.8pt; }
  .cols { display: grid; grid-template-columns: 49.5% 49.5%; gap: 1%; margin-top: 2px; }
  .tbl { width: 100%; border-collapse: collapse; font-size: 6.5pt; margin-top: 2px; }
  .tbl td { border: 1px solid #888; padding: 1.5px 4px; }
  .disc { font-size: 5.2pt; color: #444; line-height: 1.15; margin: 1.5px 0; font-style: italic; }
  .signs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 5px; text-align: center; }
  .sig-col { position: relative; padding-top: 26px; }
  .sig-img { max-height: 30px; max-width: 100px; position: absolute; top: 0; left: 0; right: 0; margin: auto; }
  .sig-line { border-top: 1px solid #333; padding-top: 1.5px; font-size: 6.5pt; font-weight: 700; color: #222; }
  .sig-sub { font-size: 5.5pt; font-style: italic; color: #555; }
</style>
</head>
<body>

  <!-- HEADER -->
  <div class="head">
    <div>
      <div class="brand"><span>&bull;</span>CBN</div>
      <div class="title-h1">FORMULIR PENDAFTARAN LAYANAN CBN</div>
      <div class="title-sub">CBN service application form</div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:7.2pt;font-weight:bold;margin-bottom:3px;">
        Sales code: ${renderPdfBoxes(salesCode, 8)}
      </div>
      <div style="font-size:6.5pt;color:#333;">
        <span>🌐 www.cbn.id &bull; di_CBN &bull; </span>
        <span style="background:#e30613;color:#fff;padding:1px 6px;border-radius:8px;font-weight:bold;">1500 780</span>
      </div>
    </div>
  </div>

  <!-- 1. DATA PELANGGAN -->
  <div class="bar">DATA PELANGGAN / CUSTOMER DATA</div>
  <div class="row">
    <div class="lbl">NAMA PELANGGAN<small>Full Name</small></div>
    <div class="fld">${renderPdfBoxes(nama, 28)}</div>
  </div>
  <div class="row">
    <div class="lbl">TEMPAT/TANGGAL LAHIR<small>Place/Date of birth</small></div>
    <div class="fld" style="gap:3px;">
      ${renderPdfBoxes(ttlKota, 14)}
      <span style="font-size:5.8pt;font-style:italic;color:#444;">dd/mm/yyyy</span>
      ${renderPdfBoxes(ttlDay, 2)} / ${renderPdfBoxes(ttlMonth, 2)} / ${renderPdfBoxes(ttlYear, 4)}
    </div>
  </div>
  <div class="row">
    <div class="lbl">NOMOR IDENTITAS<small>ID Card No.</small></div>
    <div class="fld" style="justify-content:space-between;">
      ${renderPdfBoxes(ktp, 16)}
      <div style="display:flex;align-items:center;">
        <span style="font-size:6.5pt;font-weight:bold;margin-right:4px;">JENIS KELAMIN</span>
        <div class="chk"><span class="sq">${isPria ? '&#10003;' : ''}</span> Pria</div>
        <div class="chk"><span class="sq">${isWanita ? '&#10003;' : ''}</span> Wanita</div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="lbl">TELEPON RUMAH<small>Home Phone</small></div>
    <div class="fld" style="justify-content:space-between;">
      ${renderPdfBoxes(telpRumah, 10)}
      <div style="display:flex;align-items:center;">
        <span style="font-size:6.5pt;font-weight:bold;margin-right:4px;">TELEPON SELULAR</span>
        ${renderPdfBoxes(telpSelular, 13)}
      </div>
    </div>
  </div>
  <div class="disc">
    Data yang tercantum harus sesuai dengan identitas pelanggan yang berlaku. Semua pelanggan baru CBN diwajibkan untuk menyertakan kopi identitas yang berlaku.
  </div>

  <!-- 2. ALAMAT PEMASANGAN -->
  <div class="bar">ALAMAT PEMASANGAN / INSTALLATION ADDRESS</div>
  <div class="row"><div class="lbl">ALAMAT PEMASANGAN<small>Installation Address</small></div><div class="fld">${renderPdfBoxes(alamatRow1, 30)}</div></div>
  <div class="row"><div class="lbl"></div><div class="fld">${renderPdfBoxes(alamatRow2, 30)}</div></div>
  <div class="row">
    <div class="lbl"></div>
    <div class="fld" style="gap:3px;">
      ${renderPdfBoxes(alamatRow3, 13)}
      <span style="font-size:6.2pt;font-weight:bold;">RT</span> ${renderPdfBoxes(rt, 2)}
      <span style="font-size:6.2pt;font-weight:bold;">RW</span> ${renderPdfBoxes(rw, 2)}
      <span style="font-size:6.2pt;font-weight:bold;margin-left:2px;">KODE POS</span> ${renderPdfBoxes(kodePos, 5)}
    </div>
  </div>
  <div class="row">
    <div class="lbl">STATUS KEPEMILIKAN<small>Ownership Status</small></div>
    <div class="fld">
      <div class="chk"><span class="sq">${isPemilik ? '&#10003;' : ''}</span> Pemilik - Owner</div>
      <div class="chk"><span class="sq">${isPenyewa ? '&#10003;' : ''}</span> Penyewa - Renter</div>
    </div>
  </div>
  <div class="row"><div class="lbl">ALAMAT EMAIL<small>Email Address</small></div><div class="fld">${renderPdfBoxes(email, 30)}</div></div>
  <div class="disc">
    Alamat pemasangan di atas akan berlaku sebagai alamat penagihan biaya berlangganan Anda. Tagihan dikirimkan via e-billing ke alamat email tercantum.
  </div>

  <!-- 3. MIDDLE TWO COLUMNS -->
  <div class="cols">
    <div>
      <div class="bar">PILIHAN PAKET LAYANAN / SERVICE OPTIONS</div>
      <div style="padding:1px 0;">
        <div class="chk"><span class="sq">${isFiberStd ? '&#10003;' : ''}</span> CBN Fiber <strong>${service}</strong></div><br>
        <div class="chk"><span class="sq">${isFiberSafe ? '&#10003;' : ''}</span> CBN Fiber Safe (Free Cyber Insurance)</div><br>
        <div class="chk"><span class="sq">${isFiberPro ? '&#10003;' : ''}</span> CBN Fiber Pro</div>
      </div>
      <div class="disc">Minimal kontrak berlangganan 12 bulan. Termasuk paket Dens.TV.</div>

      <div class="bar" style="margin-top:3px;">PEMBAYARAN VIA KARTU KREDIT / CC METHOD</div>
      <div style="font-size:6pt;line-height:1.2;">
        Nama pada kartu : ................................................................<br>
        Nomor kartu : ................................................................<br>
        Masa berlaku : ................................ (MM/YYYY)<br>
        Bank : ................................. (Visa/Mastercard/BCA/JCB)
      </div>
    </div>

    <div>
      <div class="bar">PILIHAN LAYANAN TAMBAHAN / ADD-ON</div>
      <div style="font-size:6.2pt;">
        <strong>PERANGKAT TAMBAHAN:</strong><br>
        <div class="chk"><span class="sq">${hasRouter ? '&#10003;' : ''}</span> Wireless Router [ ${hasRouter ? routerQty : '0'} Unit ]</div>
        <div class="chk"><span class="sq">${hasSmartbox ? '&#10003;' : ''}</span> Smartbox [ ${hasSmartbox ? smartboxQty : '0'} Unit ]</div><br>
        <strong style="display:inline-block;margin-top:2px;">ADD-ON TV:</strong><br>
        <div class="chk"><span class="sq">${hasDensTv ? '&#10003;' : ''}</span> Dens.TV+ Apps</div>
        <div class="chk"><span class="sq">${hasVision ? '&#10003;' : ''}</span> Vision+ Premium</div>
      </div>

      <div class="bar" style="margin-top:3px;">PERINCIAN BIAYA / PAYMENT DETAILS</div>
      <table class="tbl">
        <tr><td>Biaya Pasang</td><td style="text-align:right;">${biayaPasang}</td></tr>
        <tr><td>Biaya Paket</td><td style="text-align:right;">${biayaPaket}</td></tr>
        <tr><td>PPN 11%</td><td style="text-align:right;">Termasuk</td></tr>
        <tr style="font-weight:bold;background:#f0f4f8;"><td>TOTAL</td><td style="text-align:right;color:#005696;">${totalBiaya}</td></tr>
      </table>
    </div>
  </div>

  <!-- 4. LOWER TWO COLUMNS -->
  <div class="cols" style="margin-top:3px;">
    <div>
      <div class="bar">AKTIVASI LAYANAN / SERVICE ACTIVATION</div>
      <div class="row" style="margin:2px 0;">
        <div class="lbl" style="width:65px;">USERNAME</div>
        <div class="fld">
          ${renderPdfBoxes(usernameCbn, 14)} <strong style="font-size:6.5pt;margin-left:2px;">@ cbn.net.id</strong>
        </div>
      </div>
      <div style="border:1px solid #999;padding:2px 3px;font-size:5pt;color:#333;line-height:1.1;">
        <strong>Syarat dan ketentuan:</strong> Saya dengan ini menyatakan bahwa semua keterangan yang diisi adalah benar, serta menerima dan bersedia untuk terikat pada seluruh ketentuan berlangganan CBN di www.cbn.id/terms-of-service.html.
      </div>
    </div>

    <div>
      <div class="bar">JADWAL PEMASANGAN / INSTALLATION SCHEDULE</div>
      <div style="font-size:6.2pt;">
        <div><strong>Tanggal:</strong> ${renderPdfBoxes(jadwalDay, 2)} / ${renderPdfBoxes(jadwalMonth, 2)} / ${renderPdfBoxes(jadwalYear, 4)}</div>
        <div style="margin-top:2px;">
          <strong>Waktu:</strong>
          <div class="chk"><span class="sq">${waktuPasang === '09.00-11.00' ? '&#10003;' : ''}</span> 09.00-11.00</div>
          <div class="chk"><span class="sq">${waktuPasang === '11.00-13.00' ? '&#10003;' : ''}</span> 11.00-13.00</div>
          <div class="chk"><span class="sq">${waktuPasang === '13.00-15.00' ? '&#10003;' : ''}</span> 13.00-15.00</div>
        </div>
        <div style="margin-top:2px;font-size:5.5pt;"><strong>Notes:</strong> ${catatan || '-'}</div>
      </div>
    </div>
  </div>

  <!-- 5. SIGNATURES & FOOTER -->
  <div style="font-size:6.5pt;font-weight:bold;margin-top:4px;">Tanggal : ${tglTtd}</div>
  <div class="signs">
    <div class="sig-col">
      ${signatureImg ? `<img class="sig-img" src="${signatureImg}" alt="TTD">` : ''}
      <div class="sig-line">Tanda tangan pelanggan</div>
      <div class="sig-sub">customer signature</div>
    </div>
    <div class="sig-col">
      <div style="position:absolute;top:2px;left:0;right:0;font-size:6.5pt;font-weight:bold;color:#005696;">
        ${salesCode} - ${salesName}
      </div>
      <div class="sig-line">Tanda tangan sales</div>
      <div class="sig-sub">sales signature</div>
    </div>
    <div class="sig-col">
      <div style="position:absolute;top:2px;left:0;right:0;font-size:6.5pt;font-weight:bold;color:#005696;">
        PT. SINERGI EMAS PERDANA
      </div>
      <div class="sig-line">Tanda tangan sales SPV</div>
      <div class="sig-sub">sales SPV signature</div>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;font-size:5.5pt;color:#666;margin-top:3px;border-top:1px solid #ddd;padding-top:2px;">
    <span>Dokumen Resmi Pendaftaran Layanan CBN &bull; PT. Sinergi Emas Perdana</span>
    <span>F /CA-COMM/CBD-BDSA/IX/2025/</span>
  </div>

</body>
</html>
  `;
}


/**
 * Tambahkan baris data lengkap ke Google Sheets dengan Auto Styling Profesional
 */
function appendToSheet(params, pdfUrl, ktpUrl, sheetId) {
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
