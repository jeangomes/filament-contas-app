FROM php:8.4-fpm

# Arguments for user/group IDs to match your host system (optional, for permissions)
# To find your current user's UID/GID, run 'id -u' and 'id -g' in your terminal
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create a non-root user (matching your host user) for better permission handling
RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

# Set working directory inside the container
WORKDIR /var/www/html

# Switch to the non-root user
USER www-data

# The application code will be mounted from the host via docker-compose
# The default command runs PHP-FPM
CMD ["php-fpm"]
