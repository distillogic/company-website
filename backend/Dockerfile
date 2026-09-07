FROM node:22-alpine AS dependencies
WORKDIR /app/backend
ENV NPM_CONFIG_UPDATE_NOTIFIER=false
COPY backend/package.json backend/package-lock.json ./
RUN npm ci --omit=dev

FROM node:22-alpine AS runner
WORKDIR /app/backend
ENV NODE_ENV=production
ENV PORT=3000
COPY --from=dependencies /app/backend/node_modules ./node_modules
COPY . /app
USER node
EXPOSE 3000
CMD ["node", "src/server.js"]

