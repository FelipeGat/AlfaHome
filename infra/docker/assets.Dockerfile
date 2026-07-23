# Assets do vite prontos pro staging extrair (public/build vira /build).
# Buildado pelo CI (ci.yml) — nunca no alfa-server.
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM alpine:3.20
COPY --from=build /app/public/build /build
