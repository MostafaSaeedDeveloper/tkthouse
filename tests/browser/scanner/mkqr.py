import sys, qrcode, subprocess
from PIL import Image
# usage: mkqr.py payload out.mjpeg
payload, out = sys.argv[1], sys.argv[2]
img = qrcode.make(payload, box_size=12, border=4).convert('RGB')
canvas = Image.new('RGB', (640, 480), 'white')
img.thumbnail((440, 440)); canvas.paste(img, ((640 - img.width)//2, (480 - img.height)//2))
canvas.save(out + '.png')
subprocess.run(['ffmpeg','-y','-loglevel','error','-loop','1','-i',out+'.png','-t','4','-r','10','-pix_fmt','yuvj420p',out], check=True)
