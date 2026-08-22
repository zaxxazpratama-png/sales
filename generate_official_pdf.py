import os
import pypdfium2 as pdfium
from PIL import Image, ImageDraw, ImageFont
import io
import base64

def generate_filled_cbn_pdf(data, output_pdf_path='output_cbn_test.pdf'):
    # 1. Render template asli.pdf into high-res 300 DPI PIL Image
    pdf_doc = pdfium.PdfDocument('asli.pdf')
    page = pdf_doc[0]
    # scale 4.1666 gives exact 2480 x 3508 px (A4 @ 300 DPI)
    # scale 3 gives 1786 x 2580 px
    pil_img = page.render(scale=3).to_pil().convert('RGB')
    
    draw = ImageDraw.Draw(pil_img)
    W, H = pil_img.size # 1786, 2580
    
    # Load fonts
    try:
        font_bold = ImageFont.truetype('arialbd.ttf', 24)
        font_regular = ImageFont.truetype('arial.ttf', 22)
        font_small = ImageFont.truetype('arial.ttf', 18)
        font_large = ImageFont.truetype('arialbd.ttf', 28)
    except:
        font_bold = font_regular = font_small = font_large = ImageFont.load_default()

    def draw_text(text, x_pct, y_pct, font=font_bold, color=(0, 0, 0)):
        if not text: return
        x = int(W * (x_pct / 100.0))
        y = int(H * (y_pct / 100.0))
        draw.text((x, y), str(text), fill=color, font=font)

    # 0. Bersihkan watermark atas 'Made with Xodo PDF Reader and Editor'
    draw.rectangle([(0, 0), (W, int(H * 0.025))], fill=(255, 255, 255))

    # Sales Code di kotak kanan atas (Contoh: S E P - 0 0 1)
    sales_code_clean = data.get('sales_code', 'SEP-001').upper()
    draw_text('  '.join(list(sales_code_clean)), 84.8, 3.2, font=font_bold)

    # 1. DATA PELANGGAN
    nama = data.get('nama_pelanggan', '').upper()
    draw_text(nama, 21.5, 10.4, font=font_bold)

    # TTL
    ttl = data.get('ttl', '')
    if ttl:
        ttl_parts = ttl.split(',')
        kota = ttl_parts[0].strip().upper()
        draw_text(kota, 21.5, 13.0, font=font_bold)
        if len(ttl_parts) > 1:
            import re
            d_parts = re.split(r'[\/\-\s]+', ttl_parts[1].strip())
            if len(d_parts) >= 3:
                draw_text(d_parts[0].zfill(2), 59.2, 13.0, font=font_bold)
                draw_text(d_parts[1].zfill(2), 64.0, 13.0, font=font_bold)
                draw_text(d_parts[2], 69.8, 13.0, font=font_bold)

    # KTP (16 Digit)
    ktp = data.get('nomor_ktp', '')
    if ktp:
        draw_text('  '.join(list(ktp)), 21.6, 15.6, font=font_bold)

    # Jenis Kelamin
    gender = data.get('jenis_kelamin', 'PRIA').upper()
    if gender in ['WANITA', 'FEMALE']:
        draw_text('X', 84.8, 15.6, font=font_bold)
    else:
        draw_text('X', 75.8, 15.6, font=font_bold)

    # Telepon Selular
    telp = data.get('telp', '')
    draw_text(telp, 69.0, 18.0, font=font_bold)
    draw_text(data.get('telp_rumah') or telp, 69.0, 19.8, font=font_bold)

    # 2. ALAMAT PEMASANGAN
    alamat = data.get('alamat', '').upper()
    if len(alamat) > 40:
        pos = alamat[:40].rfind(' ')
        if pos != -1:
            draw_text(alamat[:pos], 21.8, 25.8, font=font_bold)
            draw_text(alamat[pos:].strip(), 21.8, 27.6, font=font_bold)
        else:
            draw_text(alamat, 21.8, 25.8, font=font_bold)
    else:
        draw_text(alamat, 21.8, 25.8, font=font_bold)

    # Status Kepemilikan
    kepemilikan = data.get('status_kepemilikan', 'PEMILIK').upper()
    if kepemilikan in ['PENYEWA', 'RENTER']:
        draw_text('✔', 35.2, 32.7, font=font_bold)
    else:
        draw_text('✔', 21.6, 32.7, font=font_bold)

    # Email
    email = data.get('email_pelanggan', '').lower()
    draw_text(email, 21.8, 34.6, font=font_bold)

    # 3. PILIHAN PAKET & ADD-ON
    service = data.get('service', 'Fiber 50')
    draw_text('✔', 3.0, 42.4, font=font_bold)
    draw_text(f'{service} ....................................................', 11.8, 42.4, font=font_bold)

    addon_tv = data.get('addon_tv', '')
    if addon_tv:
        draw_text(f'✔ {addon_tv}', 74.4, 49.3, font=font_small)

    # Rincian Biaya
    draw_text(data.get('biaya_paket', 'Rp 299.000'), 69.5, 60.1, font=font_bold)
    draw_text(data.get('biaya_pasang', 'Rp 0'), 69.5, 61.6, font=font_bold)
    draw_text(data.get('biaya_ppn', 'Rp 32.890'), 69.5, 67.5, font=font_bold)
    draw_text(data.get('biaya_total', 'Rp 331.890'), 69.5, 69.4, font=font_large)

    # 4. USERNAME & NOTES
    username = data.get('username_cbn', '') or (nama.split(' ')[0] if nama else 'user').lower()
    draw_text(username, 2.8, 84.8, font=font_bold)

    catatan = data.get('catatan', '') or 'REGULAR PROMO CBN - PT. SEP'
    draw_text(catatan, 53.0, 89.2, font=font_small)

    # 5. TANGGAL & TANDA TANGAN
    tgl_ttd = data.get('so_date', '22/08/2026')
    draw_text(tgl_ttd, 10.5, 93.3, font=font_regular)

    sales_name = data.get('sales_name', 'PUJA PANGESTU').upper()
    sales_code = data.get('sales_code', 'SEP-001').upper()
    draw_text(sales_name, 42.0, 95.0, font=font_bold)
    draw_text(f'{sales_code} - PT. SEP', 77.5, 95.0, font=font_bold)

    # Overlay Tanda Tangan Pelanggan jika ada
    sig_data = data.get('signature_data', '')
    if sig_data and 'base64,' in sig_data:
        try:
            sig_b64 = sig_data.split('base64,')[1]
            sig_bytes = base64.b64decode(sig_b64)
            sig_img = Image.open(io.BytesIO(sig_bytes)).convert('RGBA')
            sig_img.thumbnail((300, 100))
            sig_x = int(W * 0.05)
            sig_y = int(H * 0.90)
            pil_img.paste(sig_img, (sig_x, sig_y), sig_img)
        except Exception as e:
            print('Error pasting signature:', e)

    # Save to PDF
    pil_img.save(output_pdf_path, 'PDF', resolution=300.0)
    print(f'Successfully generated: {output_pdf_path}')

if __name__ == '__main__':
    sample_data = {
        'nama_pelanggan': 'PUJA PANGESTU',
        'ttl': 'JAKARTA, 02/05/2000',
        'nomor_ktp': '1271184887725666',
        'jenis_kelamin': 'PRIA',
        'telp': '081265753141',
        'telp_rumah': '081265753141',
        'alamat': 'JL. KL. YOS SUDARSO NO. 12 MEDAN',
        'status_kepemilikan': 'PEMILIK',
        'email_pelanggan': 'pujapangestu02@gmail.com',
        'service': 'Fiber 50',
        'addon_tv': 'CBN Fiber July 2026 Package 1',
        'biaya_paket': 'Rp 299.000',
        'biaya_pasang': 'Rp 0',
        'biaya_ppn': 'Rp 32.890',
        'biaya_total': 'Rp 331.890',
        'username_cbn': 'pujapangestu',
        'catatan': 'REGULAR PROMO JULI 2026 - NAB',
        'sales_name': 'PUJA PANGESTU',
        'sales_code': 'SEP-001',
        'so_date': '22/08/2026'
    }
    generate_filled_cbn_pdf(sample_data)
