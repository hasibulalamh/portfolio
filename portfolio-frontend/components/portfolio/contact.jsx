'use client'

import { useState } from 'react'
import { Mail, MapPin, Send, CircleCheck, MessageCircle, Phone } from 'lucide-react'
import { Reveal, SectionHeading } from './reveal'
import { SocialIcon } from './social-icons'
import { usableSocialLinks } from '@/lib/social-platforms'
import { trackEvent } from '@/lib/track'

const API_BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
).replace(/\/$/, '')

/**
 * Tracked event type for a social link click — GitHub, LinkedIn and email
 * only, matching the backend's allow-list. Other platforms resolve to null
 * and trackEvent ignores them. Keep in sync with hero.jsx's copy of the
 * mapping (both files need the same three names, and neither is a shared
 * module today).
 */
function socialEventFor(platform) {
  switch (platform) {
    case 'github':
      return 'github_click'
    case 'linkedin':
      return 'linkedin_click'
    case 'email':
      return 'email_click'
    default:
      return null
  }
}

/**
 * Contact and meeting-request forms.
 *
 * Both submit to the Laravel backend. The previous version only simulated a
 * send with setTimeout and reported success unconditionally, so nothing the
 * visitor typed was ever delivered.
 */
export function Contact({ hero = {}, contactInfo = {} }) {
  const [meetingFormActive, setMeetingFormActive] = useState(false)
  // 'idle' | 'sending' | 'sent' | 'error'
  const [status, setStatus] = useState('idle')
  const [message, setMessage] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const email = contactInfo.email || hero.email
  const { phone, location, whatsapp_number: whatsappNumber } = contactInfo

  // Same helper the Hero and Footer use, so all three render the same set.
  const socialLinks = usableSocialLinks(hero.social_links)

  const resetFeedback = () => {
    setStatus('idle')
    setMessage('')
    setFieldErrors({})
  }

  /**
   * POST a payload and translate the response into form feedback.
   * Returns true when the submission succeeded.
   */
  async function submit(path, payload, form) {
    setStatus('sending')
    setMessage('')
    setFieldErrors({})

    try {
      const response = await fetch(`${API_BASE_URL}${path}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      })

      const body = await response.json().catch(() => null)

      if (!response.ok) {
        // 422 carries per-field messages; anything else gets a generic notice.
        if (response.status === 422 && body?.errors) {
          setFieldErrors(body.errors)
          setStatus('error')
          setMessage('Please check the highlighted fields and try again.')
        } else {
          setStatus('error')
          setMessage(
            body?.message || 'Something went wrong. Please try again shortly.',
          )
        }
        return false
      }

      form.reset()
      setStatus('sent')
      setMessage(body?.message || 'Thanks — your message has been sent.')
      // Clear the confirmation after a while so the form looks reusable.
      setTimeout(resetFeedback, 6000)
      return true
    } catch {
      setStatus('error')
      setMessage(
        "Couldn't reach the server. Please check your connection and try again.",
      )
      return false
    }
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const form = e.currentTarget
    const data = new FormData(form)

    // Count the attempt, not the server's verdict: the visitor's intent — the
    // thing being measured — happened the moment they hit Send.
    trackEvent('contact_form_submit')

    await submit(
      '/contact-messages',
      {
        name: data.get('name'),
        email: data.get('email'),
        subject: data.get('subject'),
        message: data.get('message'),
      },
      form,
    )
  }

  async function handleMeetingSubmit(e) {
    e.preventDefault()
    // currentTarget is nulled once the handler returns, so capture the form
    // before the first await.
    const form = e.currentTarget
    const data = new FormData(form)

    const sent = await submit(
      '/meeting-requests',
      {
        name: data.get('name'),
        email: data.get('email'),
        preferred_date: data.get('preferred_date') || null,
        preferred_time: data.get('preferred_time') || null,
        message: data.get('message'),
      },
      form,
    )

    if (sent) {
      setMeetingFormActive(false)
    }
  }

  const isSending = status === 'sending'

  return (
    <section id="contact" className="relative py-12 md:py-16">
      <div
        aria-hidden
        className="absolute right-0 top-1/4 h-80 w-80 rounded-full bg-accent/10 blur-2xl sm:blur-3xl"
      />
      <div className="relative mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading eyebrow="Get In Touch" title="Let's Work Together" />

        <div className="grid grid-cols-1 gap-10 lg:grid-cols-2">
          {/* Left */}
          <Reveal>
            <div className="flex h-full flex-col">
              <h3 className="text-balance font-heading text-2xl font-bold text-foreground">
                Got a project in mind? Let&apos;s talk.
              </h3>
              <p className="mt-3 leading-relaxed text-muted-foreground">
                I&apos;m always open to discussing new projects, creative ideas, or
                opportunities to be part of your vision.
              </p>

              <div className="mt-8 space-y-4">
                {email && (
                  <a
                    href={`mailto:${email}`}
                    onClick={() => trackEvent('email_click')}
                    className="group flex items-center gap-4 rounded-xl border border-border bg-secondary p-4 transition-colors hover:border-accent"
                  >
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/15 text-accent">
                      <Mail className="h-5 w-5" />
                    </span>
                    <span>
                      <span className="block text-xs text-muted-foreground">Email</span>
                      <span className="font-medium text-foreground">{email}</span>
                    </span>
                  </a>
                )}

                {phone && (
                  <a
                    href={`tel:${phone}`}
                    className="group flex items-center gap-4 rounded-xl border border-border bg-secondary p-4 transition-colors hover:border-accent"
                  >
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/15 text-accent">
                      <Phone className="h-5 w-5" />
                    </span>
                    <span>
                      <span className="block text-xs text-muted-foreground">Phone</span>
                      <span className="font-medium text-foreground">{phone}</span>
                    </span>
                  </a>
                )}

                {/* Only rendered with a real number — wa.me rejects placeholders. */}
                {whatsappNumber && (
                  <a
                    href={`https://wa.me/${whatsappNumber}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={() => trackEvent('whatsapp_click')}
                    className="group flex items-center gap-4 rounded-xl border border-border bg-secondary p-4 transition-colors hover:border-accent"
                  >
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400">
                      <MessageCircle className="h-5 w-5" />
                    </span>
                    <span>
                      <span className="block text-xs text-muted-foreground">WhatsApp</span>
                      <span className="font-medium text-foreground">
                        Message me instantly
                      </span>
                    </span>
                  </a>
                )}

                {location && (
                  <div className="flex items-center gap-4 rounded-xl border border-border bg-secondary p-4">
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/15 text-accent">
                      <MapPin className="h-5 w-5" />
                    </span>
                    <span>
                      <span className="block text-xs text-muted-foreground">Location</span>
                      <span className="font-medium text-foreground">{location}</span>
                    </span>
                  </div>
                )}
              </div>

              {socialLinks.length > 0 && (
                <div className="mt-6 flex items-center gap-3">
                  {socialLinks.map(({ platform, label, href }) => (
                    <a
                      key={platform}
                      href={href}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label={label}
                      onClick={() => trackEvent(socialEventFor(platform))}
                      className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-secondary text-muted-foreground transition-all duration-300 hover:-translate-y-1 hover:border-accent hover:text-accent"
                    >
                      <SocialIcon platform={platform} className="h-5 w-5" />
                    </a>
                  ))}
                </div>
              )}

              <div className="mt-6 inline-flex w-fit items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-400">
                <span className="relative flex h-2 w-2">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                  <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                </span>
                Available for freelance
              </div>

              <div className="mt-8">
                <button
                  type="button"
                  onClick={() => {
                    setMeetingFormActive(!meetingFormActive)
                    resetFeedback()
                  }}
                  className="text-sm font-semibold text-accent hover:text-primary transition-colors"
                >
                  {meetingFormActive ? '← Back to contact' : 'Schedule a meeting →'}
                </button>
              </div>
            </div>
          </Reveal>

          {/* Right — forms */}
          <Reveal delay={0.15}>
            {!meetingFormActive ? (
              // method="post": if the visitor submits before React hydrates the
              // browser falls back to a native submission. Without a method
              // that defaults to GET and puts everything they typed — name,
              // email, message — into the URL query string.
              <form
                method="post"
                onSubmit={handleSubmit}
                className="glass space-y-4 rounded-2xl p-6 sm:p-8"
              >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Field label="Name" name="name" placeholder="John Doe" error={fieldErrors.name} />
                  <Field
                    label="Email"
                    name="email"
                    type="email"
                    placeholder="john@example.com"
                    error={fieldErrors.email}
                  />
                </div>
                <Field
                  label="Subject"
                  name="subject"
                  placeholder="Project inquiry"
                  required={false}
                  error={fieldErrors.subject}
                />
                <div>
                  <label
                    htmlFor="message"
                    className="mb-1.5 block text-sm font-medium text-foreground"
                  >
                    Message
                  </label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    rows={5}
                    placeholder="Tell me about your project..."
                    aria-invalid={Boolean(fieldErrors.message)}
                    className="w-full resize-none rounded-lg border border-input bg-background/40 px-4 py-2.5 text-foreground placeholder:text-muted-foreground/60 transition-all duration-200 focus:border-accent focus:outline-none focus:ring-2 focus:ring-ring/40"
                  />
                  <FieldError error={fieldErrors.message} />
                </div>

                <FormFeedback status={status} message={message} />

                <button
                  type="submit"
                  disabled={isSending}
                  className="group inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 font-semibold text-primary-foreground transition-all duration-300 hover:glow-primary hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {status === 'sent' ? (
                    <>
                      <CircleCheck className="h-4 w-4" />
                      Message Sent
                    </>
                  ) : isSending ? (
                    <>
                      <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                      Sending...
                    </>
                  ) : (
                    <>
                      Send Message
                      <Send className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
                    </>
                  )}
                </button>
              </form>
            ) : (
              // method="post" for the same reason as the contact form above.
              <form
                method="post"
                onSubmit={handleMeetingSubmit}
                className="glass space-y-4 rounded-2xl p-6 sm:p-8"
              >
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Field label="Name" name="name" placeholder="John Doe" error={fieldErrors.name} />
                  <Field
                    label="Email"
                    name="email"
                    type="email"
                    placeholder="john@example.com"
                    error={fieldErrors.email}
                  />
                </div>
                {/* Field names match the API payload keys exactly. */}
                {/* Date is optional in the API (nullable) and in the UI — Field
                    defaults to required, so opt out or an empty date silently
                    blocks native form submission before onSubmit ever runs. */}
                <Field
                  label="Preferred Date"
                  name="preferred_date"
                  type="date"
                  required={false}
                  error={fieldErrors.preferred_date}
                />
                <div>
                  <label
                    htmlFor="preferred_time"
                    className="mb-1.5 block text-sm font-medium text-foreground"
                  >
                    Preferred Time
                  </label>
                  <select
                    id="preferred_time"
                    name="preferred_time"
                    required
                    className="w-full rounded-lg border border-input bg-background/40 px-4 py-2.5 text-foreground transition-all duration-200 focus:border-accent focus:outline-none focus:ring-2 focus:ring-ring/40"
                  >
                    <option value="">Select a time</option>
                    <option value="09:00">9:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="16:00">4:00 PM</option>
                  </select>
                  <FieldError error={fieldErrors.preferred_time} />
                </div>
                <div>
                  <label
                    htmlFor="meeting-message"
                    className="mb-1.5 block text-sm font-medium text-foreground"
                  >
                    Meeting Purpose
                  </label>
                  <textarea
                    id="meeting-message"
                    name="message"
                    required
                    rows={4}
                    placeholder="What would you like to discuss?"
                    className="w-full resize-none rounded-lg border border-input bg-background/40 px-4 py-2.5 text-foreground placeholder:text-muted-foreground/60 transition-all duration-200 focus:border-accent focus:outline-none focus:ring-2 focus:ring-ring/40"
                  />
                  <FieldError error={fieldErrors.message} />
                </div>

                <FormFeedback status={status} message={message} />

                <button
                  type="submit"
                  disabled={isSending}
                  className="group inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 font-semibold text-background transition-all duration-300 hover:glow-primary hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {isSending ? (
                    <>
                      <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                      Scheduling...
                    </>
                  ) : (
                    'Schedule Meeting'
                  )}
                </button>
              </form>
            )}
          </Reveal>
        </div>

        {/* The confirmation lives outside the form too, since a successful
            meeting request closes the form it was submitted from. */}
        {status === 'sent' && !meetingFormActive && (
          <p
            role="status"
            className="mt-6 text-center text-sm font-medium text-emerald-400"
          >
            {message}
          </p>
        )}
      </div>
    </section>
  )
}

function FormFeedback({ status, message }) {
  if (!message || status === 'sending') return null

  return (
    <p
      role={status === 'error' ? 'alert' : 'status'}
      className={
        status === 'error'
          ? 'text-sm font-medium text-destructive'
          : 'text-sm font-medium text-emerald-400'
      }
    >
      {message}
    </p>
  )
}

/** Laravel returns an array of messages per field; only the first is shown. */
function FieldError({ error }) {
  if (!error) return null

  const text = Array.isArray(error) ? error[0] : error

  return (
    <p role="alert" className="mt-1 text-sm text-destructive">
      {text}
    </p>
  )
}

function Field({ label, name, type = 'text', placeholder, required = true, error }) {
  return (
    <div>
      <label
        htmlFor={name}
        className="mb-1.5 block text-sm font-medium text-foreground"
      >
        {label}
      </label>
      <input
        id={name}
        name={name}
        type={type}
        required={required}
        placeholder={placeholder}
        aria-invalid={Boolean(error)}
        className="w-full rounded-lg border border-input bg-background/40 px-4 py-2.5 text-foreground placeholder:text-muted-foreground/60 transition-all duration-200 focus:border-accent focus:outline-none focus:ring-2 focus:ring-ring/40"
      />
      <FieldError error={error} />
    </div>
  )
}
