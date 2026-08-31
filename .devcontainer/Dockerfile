FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# Install essential system dependencies, SQLite, zip, and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    sqlite3 \
    libsqlite3-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    ca-certificates \
    && docker-php-ext-install intl pdo pdo_sqlite zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install uv for Python diff-cover & tooling
COPY --from=ghcr.io/astral-sh/uv:latest /uv /uvx /bin/

# Copy latest official Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /workspace
