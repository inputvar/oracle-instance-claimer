FROM php:8.3-cli

# Install required extensions and tools
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    unzip \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for better caching
COPY app/composer.json app/composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer update --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the app
COPY app/ ./

# Re-run autoload dump after copying all source files
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev

# Copy PEM key file
COPY varunkargathara67@gmail.com-2026-02-23T20_29_16.921Z.pem /app/oci-key.pem

# Create log file
RUN touch /var/log/oci-loop.log

# Make start script executable
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]
