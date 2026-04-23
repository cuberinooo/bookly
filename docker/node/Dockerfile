FROM node:20-slim

WORKDIR /app

# Install Nx globally
RUN npm install -g nx

# Entrypoint script to handle npm install
COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
# Run the Nx serve command for the frontend
CMD ["nx", "serve", "frontend", "--host", "0.0.0.0"]
