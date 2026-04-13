import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'http', hostname: 'localhost' },
      { protocol: 'http', hostname: '192.168.0.210' },
    ],
  },
  allowedDevOrigins: ['192.168.0.210'],
}

export default nextConfig
