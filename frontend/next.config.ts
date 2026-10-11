import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'http', hostname: 'localhost' },
      { protocol: 'http', hostname: '192.168.0.210' },
    ],
  },
  allowedDevOrigins: ['192.168.0.210'],
  // الصور الثابتة تُحفظ في المتصفح أسبوعاً (كانت تُعاد طلبها للتحقق في كل زيارة)
  async headers() {
    return [
      {
        source: '/:all*(png|jpg|jpeg|svg|webp|ico)',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=604800, stale-while-revalidate=86400' }],
      },
    ]
  },
}

export default nextConfig
