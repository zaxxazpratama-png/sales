import pypdfium2 as pdfium
from PIL import Image, ImageDraw, ImageFont

pdf_doc = pdfium.PdfDocument('asli.pdf')
page = pdf_doc[0]
img = page.render(scale=3).to_pil().convert('RGB')
W, H = img.size # 1786 x 2580

# Clean watermark
draw = ImageDraw.Draw(img)
draw.rectangle([(0, 0), (W, int(H * 0.026))], fill=(255, 255, 255))

font = ImageFont.truetype('arialbd.ttf', 20)
font_ktp = ImageFont.truetype('arialbd.ttf', 22)

# Measure Box Grids:
# 1. NAMA PELANGGAN Box Grid:
# Left start: X = ~21.0% of W. Right end: ~93.0% of W.
# Total 29 boxes!
# Box width = (93.0 - 21.0) / 29 = 2.4827% per box!

def draw_boxed_chars(text, start_x_pct, y_pct, step_pct, font=font, color=(0,0,0)):
    clean_text = str(text).upper()
    for i, ch in enumerate(clean_text):
        if ch == ' ': continue
        x_pct = start_x_pct + (i * step_pct) + (step_pct * 0.15)
        x = int(W * (x_pct / 100.0))
        y = int(H * (y_pct / 100.0))
        draw.text((x, y), ch, fill=color, font=font)

# Test drawing into exact boxes:
# Nama (29 boxes): start = 21.15%, step = 2.483%, y = 11.4%
draw_boxed_chars('PUJA PANGESTU', 21.15, 11.4, 2.483, font=font)

# Tempat Lahir (15 boxes): start = 21.15%, step = 2.483%, y = 14.0%
draw_boxed_chars('MEDAN', 21.15, 14.0, 2.483, font=font)

# Tanggal Lahir: DD (2 boxes at ~59.0%), MM (2 boxes at ~64.0%), YYYY (4 boxes at ~69.8%)
draw_boxed_chars('02', 59.0, 14.0, 2.483, font=font)
draw_boxed_chars('11', 64.0, 14.0, 2.483, font=font)
draw_boxed_chars('2000', 69.8, 14.0, 2.483, font=font)

# KTP (16 boxes): start = 21.15%, step = 2.483%, y = 16.6%
draw_boxed_chars('1271184887725666', 21.15, 16.6, 2.483, font=font_ktp)

# Telepon Selular (10-14 boxes at ~68.8%): start = 68.8%, step = 2.483%, y = 19.2%
draw_boxed_chars('081265753141', 68.8, 19.2, 2.483, font=font)

# Alamat Baris 1 (29 boxes): start = 21.15%, step = 2.483%, y = 27.0%
draw_boxed_chars('JL. KL. YOS SUDARSO NO. 12', 21.15, 27.0, 2.483, font=font)

# Alamat Email (29 boxes): start = 21.15%, step = 2.483%, y = 35.4%
draw_boxed_chars('PUJAPANGESTU02@GMAIL.COM', 21.15, 35.4, 2.483, font=font)

# Username (11 boxes): start = 2.8%, step = 2.483%, y = 84.6%
draw_boxed_chars('PUJAPANGESTU', 2.8, 84.6, 2.483, font=font)

# Sales code kanan atas (6 boxes at 84.8%): start = 84.8%, step = 2.15%, y = 3.5%
draw_boxed_chars('SEP001', 84.8, 3.5, 2.15, font=font)

img.save('grid_calibrated_test.png')
print('Rendered grid_calibrated_test.png!')
