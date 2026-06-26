/**
 * Secure password generation.
 * All printable ASCII (0x21-0x7E) except: ' " $ ` \ & # + = %
 * 84 characters — safe in shell scripts (single/double quotes) and raw URL query strings.
 */

const EXCLUDED = new Set([
  0x22, // "
  0x23, // #
  0x24, // $
  0x25, // %
  0x26, // &
  0x27, // '
  0x2B, // +
  0x3D, // =
  0x5C, // \
  0x60, // `
])

const CHARSET = Array.from({ length: 94 }, (_, i) => String.fromCharCode(i + 0x21))
  .filter(c => !EXCLUDED.has(c.charCodeAt(0)))
  .join('')

export function generateSecurePassword(length = 20) {
  const array = new Uint32Array(length)
  crypto.getRandomValues(array)
  return Array.from(array, n => CHARSET[n % CHARSET.length]).join('')
}
