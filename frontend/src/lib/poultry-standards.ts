/**
 * Final Precision Poultry Standards based on Ross 308 - 2023 Official Manuals.
 */

interface GuidelineTargets {
  targetTemp: number
  minTemp: number
  maxTemp: number
  feedGoalKilos: number
  feedBags: number
  dimmingHours: number
  dimmingDist: string
}

function interpolate(x: number, x0: number, x1: number, y0: number, y1: number): number {
  if (x <= x0) return y0
  if (x >= x1) return y1
  return y0 + (y1 - y0) * ((x - x0) / (x1 - x0))
}

/**
 * Ross 308 (2023) Performance Objectives: Daily Feed Intake (grams/bird/day)
 */
export function getTargetFeedPerBird(ageDays: number): number {
  const points = [
    { age: 0, grams: 13 },
    { age: 1, grams: 13 },
    { age: 2, grams: 17 },
    { age: 3, grams: 20 },
    { age: 4, grams: 24 },
    { age: 5, grams: 28 },
    { age: 6, grams: 31 },
    { age: 7, grams: 34 },
    { age: 14, grams: 75 },
    { age: 21, grams: 121 },
    { age: 28, grams: 168 },
    { age: 35, grams: 205 },
    { age: 42, grams: 242 },
  ]
  for (let i = 0; i < points.length - 1; i++) {
    if (ageDays >= points[i].age && ageDays <= points[i + 1].age) {
      return interpolate(ageDays, points[i].age, points[i + 1].age, points[i].grams, points[i + 1].grams)
    }
  }
  return ageDays > 42 ? 245 : 13
}

/**
 * Ross 308 (2023) Management: Temperature Recommendations
 */
export function getTargetTemperature(ageDays: number): number {
  const points = [
    { age: 0, temp: 33 },
    { age: 7, temp: 30.5 },
    { age: 14, temp: 27.5 },
    { age: 21, temp: 24.5 },
    { age: 28, temp: 21.5 },
    { age: 35, temp: 20 },
  ]
  for (let i = 0; i < points.length - 1; i++) {
    if (ageDays >= points[i].age && ageDays <= points[i + 1].age) {
      return interpolate(ageDays, points[i].age, points[i + 1].age, points[i].temp, points[i + 1].temp)
    }
  }
  return ageDays > 35 ? 20 : 33
}

/**
 * Aviagen Lighting Recommendations (Modified for health split)
 */
export function getDimmingProgram(ageDays: number): { hours: number, dist: string } {
  if (ageDays <= 0) return { hours: 0, dist: 'فترة واحدة' }
  if (ageDays <= 7) return { hours: 1, dist: 'فترة واحدة' }
  if (ageDays <= 14) return { hours: 4, dist: 'فترة واحدة' }
  return { hours: 6, dist: 'على فترتين 3 + 3' }
}

/**
 * Calculate all precision targets
 */
export function getFlockDailyGuidelines(ageDays: number, birdCount: number, bagWeightKg = 50): GuidelineTargets {
  const gramsPerBird = getTargetFeedPerBird(ageDays)
  const totalKilos = (gramsPerBird * birdCount) / 1000
  const totalBags = totalKilos / bagWeightKg
  const targetTemp = getTargetTemperature(ageDays)
  const { hours, dist } = getDimmingProgram(ageDays)

  return {
    targetTemp: Math.round(targetTemp * 10) / 10,
    minTemp: Math.round((targetTemp - 1) * 10) / 10, // Standard ±1°C range
    maxTemp: Math.round((targetTemp + 1) * 10) / 10,
    feedGoalKilos: Math.round(totalKilos),
    feedBags: Math.round(totalBags * 10) / 10,
    dimmingHours: hours,
    dimmingDist: dist
  }
}
