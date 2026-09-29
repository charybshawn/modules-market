/**
 * Plain-English rendering of a schedule's structured recurrence fields
 * (weekdays / week_of_month / times), shared by the Show page and the
 * calendar so both describe a schedule the same way.
 */
export const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const WEEK_ORDINALS: Record<number, string> = { 1: '1st', 2: '2nd', 3: '3rd', 4: '4th', [-1]: 'Last' }

export interface Recurrence {
  frequency: string | null
  weekdays: number[] | null
  week_of_month: number | null
  start_time: string | null
  end_time: string | null
}

/** "08:30" -> "8:30 am", "17:00" -> "5 pm" */
export const formatTime = (time: string | null) => {
  if (!time) return ''
  const [h, m] = time.split(':').map(Number)
  const suffix = h < 12 ? 'am' : 'pm'
  const hour = h % 12 || 12
  return m ? `${hour}:${String(m).padStart(2, '0')} ${suffix}` : `${hour} ${suffix}`
}

export const formatHours = (start: string | null, end: string | null) =>
  start && end ? `${formatTime(start)} – ${formatTime(end)}` : start ? `From ${formatTime(start)}` : end ? `Until ${formatTime(end)}` : ''

const joinDays = (days: number[]) => {
  const names = days.map((d) => WEEKDAY_NAMES[d])
  return names.length <= 1 ? names.join('') : `${names.slice(0, -1).join(', ')} & ${names[names.length - 1]}`
}

export const recurrenceSummary = (r: Recurrence) => {
  const days = r.weekdays ?? []
  let when = ''
  if (days.length && r.frequency === 'monthly' && r.week_of_month != null) {
    when = `${WEEK_ORDINALS[r.week_of_month]} ${joinDays(days)} of the month`
  } else if (days.length && r.frequency === 'biweekly') {
    when = `Every other ${joinDays(days)}`
  } else if (days.length) {
    when = `Every ${joinDays(days)}`
  }
  return [when, formatHours(r.start_time, r.end_time)].filter(Boolean).join(' · ')
}
