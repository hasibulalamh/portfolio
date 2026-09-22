/**
 * Fire-and-forget CTA click tracking for the public site.
 *
 * Posts the event type to the backend's POST /api/track. The endpoint accepts
 * only a fixed allow-list, mirrored from the backend's
 * ClickTrackingService::EVENT_TYPES — the backend silently rejects anything
 * else with a 422, so a typo here fails quietly rather than polluting the
 * aggregates. Keep the two lists in sync.
 *
 * Every failure path is swallowed on purpose: this call must never block a
 * navigation, never throw into a click handler, and never surface an error to
 * a visitor. In development a console.warn names the failure so a broken
 * endpoint is still discoverable; in production it is silent.
 */

const EVENT_TYPES = [
  'hire_me_click',
  'email_click',
  'whatsapp_click',
  'cv_download',
  'github_click',
  'linkedin_click',
  'contact_form_submit',
]

const TRACK_URL = `${(
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
).replace(/\/$/, '')}/track`

/**
 * Record one conversion-intent click. Safe to call from any click handler.
 *
 * Uses keepalive so an in-flight request survives the page unloading — a CV
 * download or an external link can tear down the document before the POST
 * would otherwise be sent.
 *
 * @param {string} eventType one of the EVENT_TYPES above
 */
export function trackEvent(eventType) {
  if (!EVENT_TYPES.includes(eventType)) return

  try {
    const result = fetch(TRACK_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ event_type: eventType }),
      keepalive: true,
    })

    // Awaiting nothing: the response is 204 with no body, and the click
    // handler must not wait on the network. The catch below only covers
    // synchronous failures such as a serialized payload that cannot be sent.
    if (result && typeof result.catch === 'function') {
      result.catch((error) => {
        if (process.env.NODE_ENV === 'development') {
          console.warn(`[track] ${eventType} failed:`, error)
        }
      })
    }
  } catch (error) {
    if (process.env.NODE_ENV === 'development') {
      console.warn(`[track] ${eventType} failed:`, error)
    }
  }
}
