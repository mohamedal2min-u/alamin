const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const SVG_PATH = 'C:/Users/moham/Desktop/alamin/icon.svg';
const ASSETS_DIR = path.join(__dirname, 'assets');

if (!fs.existsSync(ASSETS_DIR)) {
  fs.mkdirSync(ASSETS_DIR);
}

async function generateIcons() {
  console.log('Rendering high-res icons from SVG...');

  let svgContent = fs.readFileSync(SVG_PATH, 'utf8');

  // Remove the static background rectangles to make the foreground transparent
  // We look for the rect tags that cover the whole area
  svgContent = svgContent.replace(/<rect [^>]*fill="#ffffff"[^>]*\/>/g, '');
  svgContent = svgContent.replace(/<rect [^>]*fill="#26292d"[^>]*\/>/g, '');

  const logoBuffer = Buffer.from(svgContent);

  // 1. Create a high-res (1024x1024) foreground with transparency
  const foregroundSize = 1024;
  const contentSize = Math.floor(foregroundSize * 0.66); // Safe zone size (72/108)

  // First render the stripped SVG to the correct content size
  const renderedLogo = await sharp(logoBuffer)
    .resize(contentSize, contentSize)
    .toBuffer();

  // Composite the rendered logo onto a transparent 1024x1024 canvas
  await sharp({
    create: {
      width: foregroundSize,
      height: foregroundSize,
      channels: 4,
      background: { r: 0, g: 0, b: 0, alpha: 0 }
    }
  })
    .composite([{ input: renderedLogo, gravity: 'center' }])
    .png()
    .toFile(path.join(ASSETS_DIR, 'icon-foreground.png'));

  console.log('Created assets/icon-foreground.png (Transparent)');

  // 2. Create a high-res solid background PNG
  await sharp({
    create: {
      width: foregroundSize,
      height: foregroundSize,
      channels: 3,
      background: '#26292d'
    }
  })
    .png()
    .toFile(path.join(ASSETS_DIR, 'icon-background.png'));

  console.log('Created assets/icon-background.png');

  // 3. Create a legacy unified icon.png (logo on colored background)
  await sharp({
    create: {
      width: foregroundSize,
      height: foregroundSize,
      channels: 3,
      background: '#26292d'
    }
  })
    .composite([{ input: renderedLogo, gravity: 'center' }])
    .png()
    .toFile(path.join(ASSETS_DIR, 'icon.png'));

  console.log('Created assets/icon.png');

  console.log('Icon generation complete!');
}

generateIcons().catch(err => {
  console.error('Error generating icons:', err);
  process.exit(1);
});
