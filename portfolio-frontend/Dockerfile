FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci

# Build-arg bridge for Render's Docker env-var translation.
#
# Render exposes a Docker service's env vars to the image build as build args
# (docs/render.com: "Environment variable translation"), but a build arg is
# invisible to RUN steps unless the Dockerfile re-declares it with ARG. Without
# these declarations, `next build` ran with NEXT_PUBLIC_API_URL unset and
# inlined the localhost fallback into the client bundle — the production
# incident where the site silently rendered fallback settings.
#
# NEXT_PUBLIC_* values are public by definition (they ship to the browser
# anyway), so passing them as build args is safe here. Never pass real secrets
# this way — Render's docs recommend secret files for those.
ARG NEXT_PUBLIC_API_URL
ARG NEXT_PUBLIC_BASE_URL

FROM node:20-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# ARG scope is per-stage, so re-declare before use, then export as ENV so
# `next build` — which reads process.env, not build args — sees the values.
# Unset args become empty strings, which are falsy in the `|| fallback`
# expressions in lib/api.js and contact.jsx, so a missing var degrades to the
# documented localhost default instead of producing a broken URL.
ARG NEXT_PUBLIC_API_URL
ARG NEXT_PUBLIC_BASE_URL
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL \
    NEXT_PUBLIC_BASE_URL=$NEXT_PUBLIC_BASE_URL

RUN npm run build

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
EXPOSE 3000
CMD ["node", "server.js"]
