# Dockerfile for the Codespaces dev container.
# Extends Microsoft's PHP 8.2 devcontainer image with pdo_mysql and mysqli
# (compiled against the image's bundled PHP via docker-php-ext-install) and the
# default MySQL client used by setup.sh.

FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install pdo_mysql mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
