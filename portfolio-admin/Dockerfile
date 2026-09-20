FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci

# Build-arg bridge for Render's Docker env-var translation — see
# portfolio-frontend/Dockerfile for the full explanation. Without the ARG
# declaration the admin's `next build` never sees NEXT_PUBLIC_API_URL and
# bakes the localhost fallback into the bundle.
#
# NEXT_PUBLIC_* values are public by definition; never pass real secrets as
# build args.
ARG NEXT_PUBLIC_API_URL

FROM node:20-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# ARG scope is per-stage: re-declare, then export as ENV for `next build`.
ARG NEXT_PUBLIC_API_URL
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL

RUN npm run build

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
EXPOSE 3000
CMD ["node", "server.js"]
