# Buat varian WebP (320/640/960/1280 px, tidak lebih lebar dari asli) untuk semua JPG di assets/.
# Jalankan dari root proyek: python tools/make-webp.py
import os, sys
from PIL import Image, ImageOps
SRC = 'assets'; OUT = os.path.join(SRC, 'webp'); WIDTHS = [320, 640, 960, 1280]
os.makedirs(OUT, exist_ok=True)
for name in sorted(os.listdir(SRC)):
    if not name.lower().endswith('.jpg'): continue
    base = os.path.splitext(name)[0]
    im = ImageOps.exif_transpose(Image.open(os.path.join(SRC, name))).convert('RGB')
    for w in WIDTHS:
        if w > im.width: w = im.width
        path = os.path.join(OUT, f'{base}-{w}.webp')
        h = round(im.height * w / im.width)
        im.resize((w, h), Image.LANCZOS).save(path, 'WEBP', quality=74, method=6)
        if w == im.width: break
    print(base, im.width)
