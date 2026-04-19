from PIL import Image
import os

files = [
    r"c:\Users\moham\Desktop\alamin\YMD.png",
    r"c:\Users\moham\Desktop\alamin\frontend\android\app\src\main\res\mipmap-xxxhdpi\ic_launcher_foreground.png",
    r"c:\Users\moham\Desktop\alamin\frontend\android\app\src\main\res\mipmap-xxxhdpi\ic_launcher.png"
]

for f in files:
    if os.path.exists(f):
        with Image.open(f) as img:
            print(f"{f}: {img.size} {img.format}")
    else:
        print(f"{f} not found")
