import { readFileSync } from 'fs'
import { join } from 'path'

export function getCurrentBuildId(): string {
  try {
    return readFileSync(join(process.cwd(), '.next', 'BUILD_ID'), 'utf8').trim()
  } catch {
    return ''
  }
}
