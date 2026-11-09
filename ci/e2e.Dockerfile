FROM mcr.microsoft.com/playwright:v1.56.1-noble
WORKDIR /e2e
COPY tests/e2e/package.json ./
RUN npm install --no-audit --no-fund --loglevel=error
COPY tests/e2e/ ./
